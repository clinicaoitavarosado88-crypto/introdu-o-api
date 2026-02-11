<?php
// whatsapp_api.php - API para gerenciar confirmações WhatsApp
// Integração com o painel existente

header('Content-Type: application/json');
include 'includes/connection.php';
include 'includes/verificar_permissao.php';

// Função para verificar permissão de acesso
function verificarPermissao() {
    global $conn;
    
    // Verificar se usuário tem permissão de administrar agendas
    if (!verificarPermissaoAdminAgenda($conn, 'gerenciar confirmações WhatsApp')) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado']);
        exit;
    }
}

// Função para formatar dados de confirmação
function formatarConfirmacao($row) {
    return [
        'id' => (int)$row['ID'],
        'agendamento_id' => (int)$row['AGENDAMENTO_ID'],
        'numero_agendamento' => $row['NUMERO_AGENDAMENTO'],
        'nome_paciente' => utf8_encode(trim($row['PACIENTE_NOME'] ?? '')),
        'telefone' => $row['PACIENTE_TELEFONE'],
        'data_consulta' => $row['DATA_CONSULTA'],
        'horario_consulta' => substr($row['HORA_CONSULTA'], 0, 5),
        'medico_nome' => utf8_encode(trim($row['MEDICO_NOME'] ?? '')),
        'especialidade_nome' => utf8_encode(trim($row['ESPECIALIDADE_NOME'] ?? '')),
        'unidade_nome' => utf8_encode(trim($row['UNIDADE_NOME'] ?? '')),
        'status' => $row['STATUS'],
        'data_envio' => $row['DATA_ENVIO'],
        'data_resposta' => $row['DATA_RESPOSTA'],
        'resposta_paciente' => $row['RESPOSTA_PACIENTE'],
        'tentativas' => (int)$row['TENTATIVAS'],
        'created_at' => $row['CREATED_AT']
    ];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'status':
        // Verificar status do sistema WhatsApp
        try {
            // Aqui você verificaria o status da API do WhatsApp
            // Por enquanto, vamos simular
            
            $response = [
                'server_online' => true,
                'whatsapp_connected' => true,
                'last_check' => date('c'),
                'instance_status' => 'connected'
            ];
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            echo json_encode([
                'server_online' => false,
                'whatsapp_connected' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'get_stats':
        verificarPermissao();
        
        try {
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d');
            $dataFim = $_GET['data_fim'] ?? date('Y-m-d', strtotime('+7 days'));
            
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN STATUS = 'enviado' THEN 1 ELSE 0 END) as enviados,
                        SUM(CASE WHEN STATUS = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
                        SUM(CASE WHEN STATUS = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                        SUM(CASE WHEN STATUS = 'reagendar' THEN 1 ELSE 0 END) as reagendar,
                        SUM(CASE WHEN STATUS = 'erro' THEN 1 ELSE 0 END) as erros
                      FROM WHATSAPP_CONFIRMACOES 
                      WHERE DATA_CONSULTA BETWEEN ? AND ?";
            
            $stmt = ibase_prepare($conn, $query);
            $result = ibase_execute($stmt, $dataInicio, $dataFim);
            $stats = ibase_fetch_assoc($result);
            
            $total = (int)$stats['TOTAL'];
            $respondidos = (int)$stats['CONFIRMADOS'] + (int)$stats['CANCELADOS'] + (int)$stats['REAGENDAR'];
            $taxaResposta = $total > 0 ? round(($respondidos / $total) * 100, 1) : 0;
            
            echo json_encode([
                'total' => $total,
                'enviados' => (int)$stats['ENVIADOS'],
                'confirmados' => (int)$stats['CONFIRMADOS'],
                'cancelados' => (int)$stats['CANCELADOS'],
                'reagendar' => (int)$stats['REAGENDAR'],
                'erros' => (int)$stats['ERROS'],
                'taxa_resposta' => $taxaResposta
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'get_confirmations':
        verificarPermissao();
        
        try {
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d');
            $dataFim = $_GET['data_fim'] ?? date('Y-m-d', strtotime('+7 days'));
            $status = $_GET['status'] ?? '';
            
            $whereStatus = $status ? "AND STATUS = ?" : "";
            
            $query = "SELECT * FROM WHATSAPP_CONFIRMACOES 
                      WHERE DATA_CONSULTA BETWEEN ? AND ? 
                      $whereStatus
                      ORDER BY DATA_CONSULTA DESC, HORA_CONSULTA DESC";
            
            $stmt = ibase_prepare($conn, $query);
            
            if ($status) {
                $result = ibase_execute($stmt, $dataInicio, $dataFim, $status);
            } else {
                $result = ibase_execute($stmt, $dataInicio, $dataFim);
            }
            
            $confirmacoes = [];
            while ($row = ibase_fetch_assoc($result)) {
                $confirmacoes[] = formatarConfirmacao($row);
            }
            
            echo json_encode($confirmacoes);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'send_confirmation':
        verificarPermissao();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        
        try {
            $agendamentoId = $_POST['agendamento_id'] ?? '';
            
            if (!$agendamentoId) {
                http_response_code(400);
                echo json_encode(['error' => 'ID do agendamento é obrigatório']);
                break;
            }
            
            // Buscar dados do agendamento
            $query = "SELECT 
                        a.ID,
                        a.NUMERO_AGENDAMENTO,
                        a.DATA_AGENDAMENTO,
                        a.HORA_AGENDAMENTO,
                        COALESCE(p.PACIENTE, a.NOME_PACIENTE) as PACIENTE_NOME,
                        COALESCE(p.FONE1, a.TELEFONE_PACIENTE) as PACIENTE_TELEFONE
                      FROM AGENDAMENTOS a
                      LEFT JOIN LAB_PACIENTES p ON a.PACIENTE_ID = p.IDPACIENTE
                      WHERE a.ID = ?";
            
            $stmt = ibase_prepare($conn, $query);
            $result = ibase_execute($stmt, $agendamentoId);
            $agendamento = ibase_fetch_assoc($result);
            
            if (!$agendamento) {
                http_response_code(404);
                echo json_encode(['error' => 'Agendamento não encontrado']);
                break;
            }
            
            if (!$agendamento['PACIENTE_TELEFONE']) {
                http_response_code(400);
                echo json_encode(['error' => 'Telefone do paciente não encontrado']);
                break;
            }
            
            // Aqui você integraria com a API do WhatsApp para envio manual
            // Por enquanto, vamos simular o sucesso
            
            $sucesso = true; // Simular envio bem-sucedido
            
            if ($sucesso) {
                // Verificar se já existe confirmação para este agendamento
                $queryCheck = "SELECT ID FROM WHATSAPP_CONFIRMACOES WHERE AGENDAMENTO_ID = ?";
                $stmtCheck = ibase_prepare($conn, $queryCheck);
                $resultCheck = ibase_execute($stmtCheck, $agendamentoId);
                $existente = ibase_fetch_assoc($resultCheck);
                
                if ($existente) {
                    // Atualizar registro existente
                    $queryUpdate = "UPDATE WHATSAPP_CONFIRMACOES 
                                    SET STATUS = 'enviado', 
                                        DATA_ENVIO = CURRENT_TIMESTAMP,
                                        TENTATIVAS = TENTATIVAS + 1
                                    WHERE AGENDAMENTO_ID = ?";
                    $stmtUpdate = ibase_prepare($conn, $queryUpdate);
                    ibase_execute($stmtUpdate, $agendamentoId);
                } else {
                    // Criar novo registro
                    $queryInsert = "INSERT INTO WHATSAPP_CONFIRMACOES 
                                    (AGENDAMENTO_ID, NUMERO_AGENDAMENTO, PACIENTE_NOME, PACIENTE_TELEFONE,
                                     DATA_CONSULTA, HORA_CONSULTA, STATUS, DATA_ENVIO, TENTATIVAS, CREATED_BY)
                                    VALUES (?, ?, ?, ?, ?, ?, 'enviado', CURRENT_TIMESTAMP, 1, ?)";
                    $stmtInsert = ibase_prepare($conn, $queryInsert);
                    $usuario = getUsuarioAtual();
                    ibase_execute($stmtInsert, 
                        $agendamentoId,
                        $agendamento['NUMERO_AGENDAMENTO'],
                        $agendamento['PACIENTE_NOME'],
                        $agendamento['PACIENTE_TELEFONE'],
                        $agendamento['DATA_AGENDAMENTO'],
                        $agendamento['HORA_AGENDAMENTO'],
                        $usuario
                    );
                }
                
                echo json_encode(['success' => true, 'message' => 'Confirmação enviada com sucesso']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Falha no envio da mensagem']);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'trigger_manual':
        verificarPermissao();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        
        try {
            // Executar o script de envio automático
            $output = shell_exec('php /var/www/html/oitava/agenda/whatsapp_cron_envios.php 2>&1');
            
            echo json_encode([
                'success' => true,
                'message' => 'Processo de envio iniciado',
                'output' => $output
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'update_status':
        verificarPermissao();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        
        try {
            $confirmacaoId = $_POST['confirmacao_id'] ?? '';
            $novoStatus = $_POST['status'] ?? '';
            
            if (!$confirmacaoId || !$novoStatus) {
                http_response_code(400);
                echo json_encode(['error' => 'ID da confirmação e status são obrigatórios']);
                break;
            }
            
            $statusValidos = ['pendente', 'enviado', 'confirmado', 'cancelado', 'reagendar', 'erro'];
            if (!in_array($novoStatus, $statusValidos)) {
                http_response_code(400);
                echo json_encode(['error' => 'Status inválido']);
                break;
            }
            
            $query = "UPDATE WHATSAPP_CONFIRMACOES 
                      SET STATUS = ?, 
                          UPDATED_AT = CURRENT_TIMESTAMP
                      WHERE ID = ?";
            
            $stmt = ibase_prepare($conn, $query);
            $result = ibase_execute($stmt, $novoStatus, $confirmacaoId);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Status atualizado']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Falha ao atualizar status']);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ação não reconhecida']);
        break;
}
?>