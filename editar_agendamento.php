<?php
// editar_agendamento.php

// ✅ Headers CORS para evitar erro 400 no preflight
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json');

// ✅ Tratar requisição OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'includes/connection.php';
include 'includes/verificar_permissao.php';
include 'includes/auditoria.php';

// Log de debug
error_log("=== EDITAR AGENDAMENTO ===");
error_log("POST data: " . print_r($_POST, true));

try {
    // Verificar se é uma edição
    $agendamento_id = $_POST['agendamento_id'] ?? null;
    if (!$agendamento_id) {
        throw new Exception('ID do agendamento não fornecido');
    }

    // Validar campos obrigatórios
    $nome_paciente = $_POST['nome_paciente'] ?? '';
    $cpf_paciente = $_POST['cpf_paciente'] ?? '';
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $telefone_paciente = $_POST['telefone_paciente'] ?? '';
    $convenio_id = $_POST['convenio_id'] ?? '';
    $agenda_id = $_POST['agenda_id'] ?? '';
    
    // Log dos valores recebidos para debug
    error_log("📝 Valores recebidos:");
    error_log("  - nome_paciente: '$nome_paciente'");
    error_log("  - cpf_paciente: '$cpf_paciente'");
    error_log("  - data_nascimento: '$data_nascimento'");
    error_log("  - telefone_paciente: '$telefone_paciente'");
    error_log("  - convenio_id: '$convenio_id'");
    error_log("  - agenda_id: '$agenda_id'");
    
    // Tratar valores "null" como string
    if ($cpf_paciente === 'null') $cpf_paciente = '';
    if ($telefone_paciente === 'null') $telefone_paciente = '';
    if ($nome_paciente === 'null') $nome_paciente = '';
    
    if (empty($nome_paciente) || empty($data_nascimento) || empty($convenio_id) || empty($agenda_id)) {
        throw new Exception('Campos obrigatórios (nome, data nascimento, convênio, agenda) devem ser preenchidos');
    }

    // Converter encoding para Windows-1252
    $nome_convertido = iconv('UTF-8', 'Windows-1252//IGNORE', $nome_paciente);
    $email_paciente = $_POST['email_paciente'] ?? '';
    $email_convertido = iconv('UTF-8', 'Windows-1252//IGNORE', $email_paciente);
    
    // Limitar campos para evitar truncamento
    $telefone_limitado = substr($telefone_paciente, 0, 15);
    
    // Dados do agendamento
    $data_agendamento = $_POST['data_agendamento'] ?? '';
    $horario_agendamento = $_POST['horario_agendamento'] ?? '';
    $status = $_POST['status'] ?? 'AGENDADO';
    $tipo_consulta = $_POST['tipo_consulta'] ?? 'primeira_vez';
    $observacoes = $_POST['observacoes'] ?? '';
    $observacoes_convertidas = iconv('UTF-8', 'Windows-1252//IGNORE', $observacoes);

    // Iniciar transação
    $trans = ibase_trans($conn);
    
    try {
        // 1. Buscar dados atuais do agendamento para auditoria
        $query_atual = "SELECT 
                          a.ID, a.PACIENTE_ID, a.NUMERO_AGENDAMENTO, a.STATUS,
                          a.TIPO_CONSULTA, a.OBSERVACOES, a.AGENDA_ID,
                          a.DATA_AGENDAMENTO, a.HORA_AGENDAMENTO,
                          COALESCE(p.PACIENTE, a.NOME_PACIENTE) as PACIENTE_NOME,
                          p.CPF as PACIENTE_CPF,
                          p.ANIVERSARIO as DATA_NASCIMENTO,
                          COALESCE(p.FONE1, a.TELEFONE_PACIENTE) as TELEFONE_PACIENTE,
                          p.EMAIL
                        FROM AGENDAMENTOS a
                        LEFT JOIN LAB_PACIENTES p ON a.PACIENTE_ID = p.IDPACIENTE
                        WHERE a.ID = ?";
        $stmt_atual = ibase_prepare($trans, $query_atual);
        $result_atual = ibase_execute($stmt_atual, $agendamento_id);
        $agendamento_atual = ibase_fetch_assoc($result_atual);
        
        if (!$agendamento_atual) {
            throw new Exception('Agendamento não encontrado');
        }
        
        // Preparar dados antes da edição para auditoria (APENAS campos editáveis)
        $dados_antes_edicao = [
            'id' => $agendamento_atual['ID'],
            'numero_agendamento' => $agendamento_atual['NUMERO_AGENDAMENTO'],
            'email' => $agendamento_atual['EMAIL'],
            'status' => $agendamento_atual['STATUS'],
            'tipo_consulta' => $agendamento_atual['TIPO_CONSULTA'],
            'observacoes' => utf8_encode($agendamento_atual['OBSERVACOES']),
            'data_nascimento' => $agendamento_atual['DATA_NASCIMENTO']
        ];
        
        $paciente_id = $agendamento_atual['PACIENTE_ID'];
        $numero_agendamento = $agendamento_atual['NUMERO_AGENDAMENTO'];
        
        // 2. Atualizar apenas campos editáveis do paciente (EMAIL e DATA_NASCIMENTO)
        $query_update_paciente = "UPDATE LAB_PACIENTES SET 
                                  ANIVERSARIO = ?,
                                  EMAIL = ?
                                  WHERE IDPACIENTE = ?";
        
        error_log("📝 Atualizando paciente ID: $paciente_id (apenas campos editáveis)");
        error_log("  - Data nasc: '$data_nascimento'");
        error_log("  - Email: '$email_convertido'");
        
        $stmt_paciente = ibase_prepare($trans, $query_update_paciente);
        $result_paciente = ibase_execute($stmt_paciente, 
            $data_nascimento, 
            $email_convertido,
            $paciente_id
        );
        
        if (!$result_paciente) {
            $error_info = ibase_errmsg();
            error_log("❌ Erro ao atualizar paciente: $error_info");
            throw new Exception('Erro ao atualizar dados do paciente: ' . $error_info);
        }
        
        error_log("✅ Paciente atualizado com sucesso");
        
        // 3. Atualizar dados do agendamento
        $query_update_agendamento = "UPDATE AGENDAMENTOS SET 
                                     STATUS = ?,
                                     TIPO_CONSULTA = ?,
                                     OBSERVACOES = ?
                                     WHERE ID = ?";
        
        error_log("📝 Atualizando agendamento ID: $agendamento_id");
        error_log("  - Status: '$status'");
        error_log("  - Tipo consulta: '$tipo_consulta'");
        error_log("  - Observações: '$observacoes_convertidas'");
        
        $stmt_agendamento = ibase_prepare($trans, $query_update_agendamento);
        $result_agendamento = ibase_execute($stmt_agendamento,
            $status,
            $tipo_consulta,
            $observacoes_convertidas,
            $agendamento_id
        );
        
        if (!$result_agendamento) {
            $error_info = ibase_errmsg();
            $error_code = ibase_errcode();
            error_log("❌ Erro ao atualizar agendamento:");
            error_log("  - Código: $error_code");
            error_log("  - Mensagem: $error_info");
            error_log("  - Query: " . $query_update_agendamento);
            throw new Exception('Erro ao atualizar agendamento - Código: ' . $error_code . ' - ' . $error_info);
        }
        
        error_log("✅ Agendamento atualizado com sucesso");
        
        // 4. Atualizar exames do agendamento (se fornecidos)
        if (isset($_POST['exames'])) {
            $exames = $_POST['exames'] ?? [];
            
            // Primeiro, remover todos os exames existentes
            $query_delete_exames = "DELETE FROM AGENDAMENTO_EXAMES WHERE NUMERO_AGENDAMENTO = ?";
            $stmt_delete = ibase_prepare($trans, $query_delete_exames);
            $result_delete = ibase_execute($stmt_delete, $numero_agendamento);
            
            if (!$result_delete) {
                $error_info = ibase_errmsg();
                error_log("❌ Erro ao remover exames: $error_info");
                throw new Exception('Erro ao remover exames existentes: ' . $error_info);
            }
            
            error_log("✅ Removidos exames do agendamento: $numero_agendamento");
            
            // Depois, inserir os novos exames se houver
            if (!empty($exames)) {
                $query_insert_exame = "INSERT INTO AGENDAMENTO_EXAMES (NUMERO_AGENDAMENTO, EXAME_ID) VALUES (?, ?)";
                $stmt_insert_exame = ibase_prepare($trans, $query_insert_exame);
                
                foreach ($exames as $exame_id) {
                    if (!empty($exame_id)) {
                        error_log("📝 Inserindo exame $exame_id para agendamento $numero_agendamento");
                        $result_exame = ibase_execute($stmt_insert_exame, $numero_agendamento, $exame_id);
                        if (!$result_exame) {
                            $error_info = ibase_errmsg();
                            error_log("❌ Erro ao inserir exame $exame_id: $error_info");
                            throw new Exception('Erro ao inserir exame ' . $exame_id . ': ' . $error_info);
                        }
                    }
                }
                
                error_log("✅ Exames atualizados: " . implode(', ', $exames));
            } else {
                error_log("✅ Exames removidos (agenda de consulta)");
            }
        } else {
            error_log("✅ Sem alteração nos exames (campo não enviado)");
        }
        
        // Confirmar transação
        ibase_commit($trans);
        
        // Preparar dados após edição para auditoria (APENAS campos editáveis)
        $dados_apos_edicao = [
            'id' => $agendamento_id,
            'numero_agendamento' => $numero_agendamento,
            'email' => $email_paciente,
            'status' => $status,
            'tipo_consulta' => $tipo_consulta,
            'observacoes' => $observacoes,
            'data_nascimento' => $data_nascimento
        ];
        
        // Registrar auditoria da edição
        $usuario_atual = getUsuarioAtual();
        auditarAgendamento(
            $conn, 
            'EDITAR', 
            $usuario_atual, 
            $dados_apos_edicao, 
            $dados_antes_edicao,
            "Agendamento editado pelo usuario " . $usuario_atual
        );
        
        error_log("✅ Agendamento $agendamento_id atualizado com sucesso");
        
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Agendamento atualizado com sucesso',
            'agendamento_id' => $agendamento_id
        ]);
        
    } catch (Exception $e) {
        ibase_rollback($trans);
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("❌ Erro ao editar agendamento: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>