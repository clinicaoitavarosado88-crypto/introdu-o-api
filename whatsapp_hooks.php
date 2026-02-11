<?php
// whatsapp_hooks.php - Hooks para integração automática com sistema de agendamento
// Este arquivo é chamado automaticamente quando agendamentos são criados/alterados

include_once 'includes/connection.php';
include_once 'includes/auditoria.php';
include 'whatsapp_config.php';

// Função para log dos hooks
function logHook($mensagem, $tipo = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] [$tipo] $mensagem\n";
    file_put_contents('/var/www/html/oitava/agenda/logs/whatsapp_hooks.log', $log, FILE_APPEND | LOCK_EX);
}

// Função para verificar se deve criar confirmação WhatsApp
function deveProcessarAgendamento($agendamento) {
    // Verificar se tem telefone
    if (empty($agendamento['telefone'])) {
        return false;
    }
    
    // Verificar se a data é futura
    $dataConsulta = strtotime($agendamento['data_agendamento']);
    $agora = time();
    
    if ($dataConsulta <= $agora) {
        return false;
    }
    
    // Verificar se é menos de 24h (muito próximo)
    $config = getWhatsAppConfig('timing');
    $horasAntecedencia = isset($config['hours_before_appointment']) ? $config['hours_before_appointment'] : 24;
    $tempoMinimo = $agora + ($horasAntecedencia * 3600);
    
    if ($dataConsulta < $tempoMinimo) {
        logHook("Agendamento muito próximo para confirmação automática: {$agendamento['numero']}", 'WARN');
        return false;
    }
    
    return true;
}

// Função para criar confirmação automática
function criarConfirmacaoAutomatica($conn, $agendamentoData) {
    try {
        // Verificar se já existe confirmação para este agendamento
        $queryCheck = "SELECT ID FROM WHATSAPP_CONFIRMACOES WHERE AGENDAMENTO_ID = ?";
        $stmtCheck = ibase_prepare($conn, $queryCheck);
        $resultCheck = ibase_execute($stmtCheck, $agendamentoData['id']);
        $existente = ibase_fetch_assoc($resultCheck);
        
        if ($existente) {
            logHook("Confirmação já existe para agendamento {$agendamentoData['numero']}", 'INFO');
            return $existente['ID'];
        }
        
        // Buscar dados complementares do agendamento
        $queryCompleto = "SELECT 
                            a.*,
                            COALESCE(p.PACIENTE, a.NOME_PACIENTE) as PACIENTE_NOME,
                            COALESCE(p.FONE1, a.TELEFONE_PACIENTE) as PACIENTE_TELEFONE,
                            med.NOME as MEDICO_NOME,
                            esp.NOME as ESPECIALIDADE_NOME,
                            u.NOME_UNIDADE as UNIDADE_NOME
                          FROM AGENDAMENTOS a
                          LEFT JOIN LAB_PACIENTES p ON a.PACIENTE_ID = p.IDPACIENTE
                          LEFT JOIN AGENDAS ag ON a.AGENDA_ID = ag.ID
                          LEFT JOIN LAB_MEDICOS_PRES med ON ag.MEDICO_ID = med.ID
                          LEFT JOIN ESPECIALIDADES esp ON a.ESPECIALIDADE_ID = esp.ID
                          LEFT JOIN LAB_CIDADES u ON ag.UNIDADE_ID = u.ID
                          WHERE a.ID = ?";
        
        $stmtCompleto = ibase_prepare($conn, $queryCompleto);
        $resultCompleto = ibase_execute($stmtCompleto, $agendamentoData['id']);
        $dadosCompletos = ibase_fetch_assoc($resultCompleto);
        
        if (!$dadosCompletos) {
            logHook("Dados do agendamento não encontrados: {$agendamentoData['id']}", 'ERROR');
            return false;
        }
        
        // Criar confirmação
        $queryInsert = "INSERT INTO WHATSAPP_CONFIRMACOES (
                          AGENDAMENTO_ID, NUMERO_AGENDAMENTO, PACIENTE_NOME, PACIENTE_TELEFONE,
                          DATA_CONSULTA, HORA_CONSULTA, MEDICO_NOME, ESPECIALIDADE_NOME, UNIDADE_NOME,
                          STATUS, CREATED_BY
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', 'HOOK_SYSTEM')";
        
        $stmtInsert = ibase_prepare($conn, $queryInsert);
        $resultInsert = ibase_execute($stmtInsert,
            $dadosCompletos['ID'],
            $dadosCompletos['NUMERO_AGENDAMENTO'],
            $dadosCompletos['PACIENTE_NOME'],
            $dadosCompletos['PACIENTE_TELEFONE'],
            $dadosCompletos['DATA_AGENDAMENTO'],
            $dadosCompletos['HORA_AGENDAMENTO'],
            $dadosCompletos['MEDICO_NOME'],
            $dadosCompletos['ESPECIALIDADE_NOME'],
            $dadosCompletos['UNIDADE_NOME']
        );
        
        if ($resultInsert) {
            $confirmacaoId = ibase_gen_id('GEN_WHATSAPP_CONFIRMACOES_ID', 0);
            
            logHook("Confirmação criada automaticamente: {$dadosCompletos['NUMERO_AGENDAMENTO']} (ID: $confirmacaoId)", 'SUCCESS');
            
            // Registrar auditoria
            registrarAuditoria($conn, [
                'acao' => 'WHATSAPP_CONFIRMACAO_CRIADA',
                'usuario' => 'HOOK_SYSTEM',
                'tabela_afetada' => 'WHATSAPP_CONFIRMACOES',
                'agendamento_id' => $dadosCompletos['ID'],
                'observacoes' => "Confirmação WhatsApp criada automaticamente para {$dadosCompletos['PACIENTE_NOME']}",
                'dados_novos' => json_encode([
                    'telefone' => $dadosCompletos['PACIENTE_TELEFONE'],
                    'data_consulta' => $dadosCompletos['DATA_AGENDAMENTO'],
                    'hora_consulta' => $dadosCompletos['HORA_AGENDAMENTO']
                ])
            ]);
            
            return $confirmacaoId;
        }
        
        return false;
        
    } catch (Exception $e) {
        logHook("Erro ao criar confirmação automática: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Função para processar cancelamento de agendamento
function processarCancelamentoAgendamento($conn, $agendamentoId) {
    try {
        // Atualizar status das confirmações relacionadas
        $query = "UPDATE WHATSAPP_CONFIRMACOES 
                  SET STATUS = 'cancelado_agenda', 
                      UPDATED_AT = CURRENT_TIMESTAMP
                  WHERE AGENDAMENTO_ID = ? 
                  AND STATUS IN ('pendente', 'enviado')";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, $agendamentoId);
        
        if ($result) {
            logHook("Confirmações canceladas para agendamento $agendamentoId", 'INFO');
            
            // Registrar auditoria
            registrarAuditoria($conn, [
                'acao' => 'WHATSAPP_CANCELAMENTO_AGENDA',
                'usuario' => 'HOOK_SYSTEM',
                'tabela_afetada' => 'WHATSAPP_CONFIRMACOES',
                'agendamento_id' => $agendamentoId,
                'observacoes' => "Confirmações WhatsApp canceladas devido ao cancelamento do agendamento"
            ]);
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        logHook("Erro ao processar cancelamento: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Função para processar alteração de agendamento
function processarAlteracaoAgendamento($conn, $agendamentoId, $alteracoes) {
    try {
        // Se alterou data/hora, atualizar confirmações
        if (isset($alteracoes['data_agendamento']) || isset($alteracoes['hora_agendamento'])) {
            
            // Buscar dados atualizados
            $queryDados = "SELECT 
                            DATA_AGENDAMENTO, HORA_AGENDAMENTO, NUMERO_AGENDAMENTO
                          FROM AGENDAMENTOS 
                          WHERE ID = ?";
            
            $stmtDados = ibase_prepare($conn, $queryDados);
            $resultDados = ibase_execute($stmtDados, $agendamentoId);
            $dados = ibase_fetch_assoc($resultDados);
            
            if ($dados) {
                // Atualizar confirmações pendentes/enviadas
                $queryUpdate = "UPDATE WHATSAPP_CONFIRMACOES 
                               SET DATA_CONSULTA = ?, 
                                   HORA_CONSULTA = ?,
                                   STATUS = CASE 
                                       WHEN STATUS = 'enviado' THEN 'alterado_reenviado'
                                       ELSE 'alterado'
                                   END,
                                   UPDATED_AT = CURRENT_TIMESTAMP
                               WHERE AGENDAMENTO_ID = ? 
                               AND STATUS IN ('pendente', 'enviado')";
                
                $stmtUpdate = ibase_prepare($conn, $queryUpdate);
                $resultUpdate = ibase_execute($stmtUpdate, 
                    $dados['DATA_AGENDAMENTO'], 
                    $dados['HORA_AGENDAMENTO'], 
                    $agendamentoId
                );
                
                if ($resultUpdate) {
                    logHook("Confirmações atualizadas para agendamento {$dados['NUMERO_AGENDAMENTO']}", 'INFO');
                    
                    // Registrar auditoria
                    registrarAuditoria($conn, [
                        'acao' => 'WHATSAPP_ALTERACAO_AGENDA',
                        'usuario' => 'HOOK_SYSTEM',
                        'tabela_afetada' => 'WHATSAPP_CONFIRMACOES',
                        'agendamento_id' => $agendamentoId,
                        'observacoes' => "Confirmações WhatsApp atualizadas devido à alteração do agendamento",
                        'dados_novos' => json_encode([
                            'nova_data' => $dados['DATA_AGENDAMENTO'],
                            'nova_hora' => $dados['HORA_AGENDAMENTO']
                        ])
                    ]);
                    
                    return true;
                }
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        logHook("Erro ao processar alteração: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Hook principal - processamento de agendamentos
function processarHookAgendamento($acao, $agendamentoData) {
    global $conn;
    
    $identificador = isset($agendamentoData['numero']) ? $agendamentoData['numero'] : $agendamentoData['id'];
    logHook("Processando hook: $acao para agendamento $identificador", 'INFO');
    
    switch ($acao) {
        case 'criar':
        case 'agendar':
            if (deveProcessarAgendamento($agendamentoData)) {
                $confirmacaoId = criarConfirmacaoAutomatica($conn, $agendamentoData);
                return $confirmacaoId ? 'confirmacao_criada' : 'erro_criar_confirmacao';
            } else {
                return 'agendamento_ignorado';
            }
            break;
            
        case 'cancelar':
            $sucesso = processarCancelamentoAgendamento($conn, $agendamentoData['id']);
            return $sucesso ? 'confirmacoes_canceladas' : 'erro_cancelar_confirmacoes';
            break;
            
        case 'alterar':
        case 'editar':
            $alteracoes = isset($agendamentoData['alteracoes']) ? $agendamentoData['alteracoes'] : [];
            $sucesso = processarAlteracaoAgendamento($conn, $agendamentoData['id'], $alteracoes);
            return $sucesso ? 'confirmacoes_atualizadas' : 'confirmacoes_nao_afetadas';
            break;
            
        default:
            logHook("Ação não reconhecida: $acao", 'WARN');
            return 'acao_ignorada';
    }
}

// API para chamadas externas
// ✅ Só executar se o arquivo for acessado diretamente, não via include
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_SERVER['SCRIPT_FILENAME']) &&
    realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    header('Content-Type: application/json');
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados inválidos']);
        exit;
    }
    
    $acao = isset($data['acao']) ? $data['acao'] : '';
    $agendamentoData = isset($data['agendamento']) ? $data['agendamento'] : [];
    
    if (!$acao || !$agendamentoData) {
        http_response_code(400);
        echo json_encode(['error' => 'Ação e dados do agendamento são obrigatórios']);
        exit;
    }
    
    try {
        $resultado = processarHookAgendamento($acao, $agendamentoData);
        
        echo json_encode([
            'success' => true,
            'acao' => $acao,
            'resultado' => $resultado,
            'timestamp' => date('c')
        ]);
        
    } catch (Exception $e) {
        logHook("Erro no hook API: " . $e->getMessage(), 'ERROR');
        
        http_response_code(500);
        echo json_encode([
            'error' => 'Erro interno',
            'message' => $e->getMessage()
        ]);
    }
    
    exit;
}

// Exemplo de uso direto (para integração com outros arquivos PHP)
/*
// Exemplo 1: Novo agendamento criado
$agendamentoData = [
    'id' => 123,
    'numero' => 'AG2025001',
    'data_agendamento' => '2025-08-18',
    'hora_agendamento' => '14:30:00',
    'telefone' => '11999887766'
];
$resultado = processarHookAgendamento('criar', $agendamentoData);

// Exemplo 2: Agendamento cancelado
$agendamentoData = ['id' => 123];
$resultado = processarHookAgendamento('cancelar', $agendamentoData);

// Exemplo 3: Agendamento alterado
$agendamentoData = [
    'id' => 123,
    'alteracoes' => [
        'data_agendamento' => '2025-08-19',
        'hora_agendamento' => '15:00:00'
    ]
];
$resultado = processarHookAgendamento('alterar', $agendamentoData);
*/
?>