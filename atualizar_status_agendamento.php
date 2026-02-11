<?php
// atualizar_status_agendamento.php - Atualiza status do agendamento
header('Content-Type: application/json');
require_once 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
    if (getenv('POST_DATA')) {
        parse_str(getenv('POST_DATA'), $_POST);
    }
}

try {
    $agendamento_id = (int)($_POST['agendamento_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $usuario = trim($_POST['usuario'] ?? 'SISTEMA');
    
    if (!$agendamento_id) {
        throw new Exception('ID do agendamento não fornecido');
    }
    
    if (!$status) {
        throw new Exception('Status não fornecido');
    }
    
    // Validar status permitidos
    $status_permitidos = ['AGENDADO', 'CONFIRMADO', 'AGUARDANDO', 'EM_ATENDIMENTO', 'ATENDIDO', 'CANCELADO', 'FALTOU'];
    if (!in_array($status, $status_permitidos)) {
        throw new Exception('Status inválido');
    }
    
    // Verificar se o agendamento existe
    $query_check = "SELECT ID, NOME_PACIENTE, STATUS FROM AGENDAMENTOS WHERE ID = ?";
    $stmt_check = ibase_prepare($conn, $query_check);
    $result_check = ibase_execute($stmt_check, $agendamento_id);
    $agendamento = ibase_fetch_assoc($result_check);
    
    if (!$agendamento) {
        throw new Exception('Agendamento não encontrado');
    }
    
    $status_anterior = $agendamento['STATUS'];
    
    // Se o status já é o mesmo, não precisa alterar
    if ($status_anterior === $status) {
        echo json_encode([
            'success' => true,
            'mensagem' => 'Status já estava definido como: ' . $status,
            'status_atual' => $status
        ]);
        exit;
    }
    
    // Atualizar o status
    $query_update = "UPDATE AGENDAMENTOS SET STATUS = ?, DATA_ALTERACAO = CURRENT_TIMESTAMP WHERE ID = ?";
    $stmt_update = ibase_prepare($conn, $query_update);
    $result_update = ibase_execute($stmt_update, $status, $agendamento_id);
    
    if (!$result_update) {
        throw new Exception('Erro ao atualizar status no banco de dados');
    }
    
    // Commit da transação
    ibase_commit($conn);
    
    // Log da alteração (opcional)
    error_log("Status do agendamento {$agendamento_id} alterado de '{$status_anterior}' para '{$status}' por {$usuario}");
    
    echo json_encode([
        'success' => true,
        'mensagem' => 'Status atualizado com sucesso',
        'status_anterior' => $status_anterior,
        'status_atual' => $status,
        'agendamento_id' => $agendamento_id
    ]);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn)) {
        ibase_rollback($conn);
    }
    
    error_log("Erro ao atualizar status: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'mensagem' => $e->getMessage()
    ]);
}
?>