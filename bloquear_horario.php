<?php
// bloquear_horario.php

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
error_log("=== BLOQUEAR HORÁRIO ===");
error_log("POST data: " . print_r($_POST, true));

// Verificar permissão antes de prosseguir
if (!verificarPermissaoAdminAgenda($conn, 'bloquear/desbloquear horário')) {
    exit; // A função já enviou a resposta JSON
}

try {
    // Validar campos obrigatórios
    $agenda_id = $_POST['agenda_id'] ?? '';
    $data_agendamento = $_POST['data_agendamento'] ?? '';
    $horario_agendamento = $_POST['horario_agendamento'] ?? '';
    $acao = $_POST['acao'] ?? 'bloquear'; // 'bloquear' ou 'desbloquear'
    
    if (empty($agenda_id) || empty($data_agendamento) || empty($horario_agendamento)) {
        throw new Exception('Agenda, data e horário são obrigatórios');
    }
    
    error_log("📝 Ação: $acao");
    error_log("  - Agenda ID: $agenda_id");
    error_log("  - Data: $data_agendamento");
    error_log("  - Horário: $horario_agendamento");
    
    // Iniciar transação
    $trans = ibase_trans($conn);
    
    try {
        if ($acao === 'bloquear') {
            // Verificar se já existe um agendamento ATIVO neste horário (ignorar CANCELADOS)
            $query_verificar = "SELECT ID, STATUS FROM AGENDAMENTOS
                               WHERE AGENDA_ID = ?
                               AND DATA_AGENDAMENTO = ?
                               AND HORA_AGENDAMENTO = ?
                               AND STATUS NOT IN ('CANCELADO', 'FALTOU', 'NO_SHOW')";

            $stmt_verificar = ibase_prepare($trans, $query_verificar);
            $result_verificar = ibase_execute($stmt_verificar, $agenda_id, $data_agendamento, $horario_agendamento);

            if (ibase_fetch_assoc($result_verificar)) {
                throw new Exception('Já existe um agendamento ou bloqueio neste horário');
            }
            
            // Gerar número único para o bloqueio (formato mais curto)
            $numero_bloqueio = 'BL' . date('His') . $agenda_id;
            
            // Criar registro de bloqueio
            $query_bloquear = "INSERT INTO AGENDAMENTOS (
                                NUMERO_AGENDAMENTO,
                                AGENDA_ID,
                                NOME_PACIENTE,
                                CONVENIO_ID,
                                DATA_AGENDAMENTO,
                                HORA_AGENDAMENTO,
                                STATUS,
                                TIPO_AGENDAMENTO
                              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_bloquear = ibase_prepare($trans, $query_bloquear);
            $result_bloquear = ibase_execute($stmt_bloquear,
                $numero_bloqueio,
                $agenda_id,
                'BLOQUEADO',
                1, // Convênio padrão
                $data_agendamento,
                $horario_agendamento,
                'BLOQUEADO',
                'NORMAL'
            );
            
            if (!$result_bloquear) {
                $error_info = ibase_errmsg();
                throw new Exception('Erro ao bloquear horário: ' . $error_info);
            }
            
            $mensagem = 'Horário bloqueado com sucesso';
            error_log("✅ $mensagem");
            
            // Registrar auditoria do bloqueio
            $usuario_atual = getUsuarioAtual();
            auditarBloqueio(
                $conn, 
                'BLOQUEAR', 
                $usuario_atual, 
                $agenda_id, 
                $data_agendamento, 
                $horario_agendamento,
                "Horário bloqueado pelo usuário {$usuario_atual}"
            );
            
        } else if ($acao === 'desbloquear') {
            // Remover bloqueio
            $query_desbloquear = "DELETE FROM AGENDAMENTOS 
                                 WHERE AGENDA_ID = ? 
                                 AND DATA_AGENDAMENTO = ? 
                                 AND HORA_AGENDAMENTO = ? 
                                 AND STATUS = 'BLOQUEADO'
                                 AND NOME_PACIENTE = 'BLOQUEADO'";
            
            $stmt_desbloquear = ibase_prepare($trans, $query_desbloquear);
            $result_desbloquear = ibase_execute($stmt_desbloquear, $agenda_id, $data_agendamento, $horario_agendamento);
            
            if (!$result_desbloquear) {
                $error_info = ibase_errmsg();
                throw new Exception('Erro ao desbloquear horário: ' . $error_info);
            }
            
            $mensagem = 'Horário desbloqueado com sucesso';
            error_log("✅ $mensagem");
            
            // Registrar auditoria do desbloqueio
            $usuario_atual = getUsuarioAtual();
            auditarBloqueio(
                $conn, 
                'DESBLOQUEAR', 
                $usuario_atual, 
                $agenda_id, 
                $data_agendamento, 
                $horario_agendamento,
                "Horário desbloqueado pelo usuário {$usuario_atual}"
            );
        } else {
            throw new Exception('Ação inválida: ' . $acao);
        }
        
        // Confirmar transação
        ibase_commit($trans);
        
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => $mensagem,
            'acao' => $acao
        ]);
        
    } catch (Exception $e) {
        ibase_rollback($trans);
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("❌ Erro ao $acao horário: " . $e->getMessage());
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>