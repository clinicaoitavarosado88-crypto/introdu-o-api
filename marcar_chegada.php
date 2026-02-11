<?php
// marcar_chegada.php - Marcar chegada do paciente e definir ordem
header('Content-Type: application/json');
require_once 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
    // Para POST via CLI, simular
    if (getenv('POST_DATA')) {
        parse_str(getenv('POST_DATA'), $_POST);
    }
}

try {
    $agendamento_id = (int)($_POST['agendamento_id'] ?? $_GET['agendamento_id'] ?? 0);
    
    if (!$agendamento_id) {
        throw new Exception('ID do agendamento não fornecido');
    }
    
    // Verificar se o agendamento existe
    $query_check = "SELECT ID, AGENDA_ID, DATA_AGENDAMENTO, NOME_PACIENTE, ORDEM_CHEGADA, HORA_CHEGADA 
                    FROM AGENDAMENTOS WHERE ID = ?";
    $stmt_check = ibase_prepare($conn, $query_check);
    $result_check = ibase_execute($stmt_check, $agendamento_id);
    $agendamento = ibase_fetch_assoc($result_check);
    
    if (!$agendamento) {
        throw new Exception('Agendamento não encontrado');
    }
    
    $agenda_id = $agendamento['AGENDA_ID'];
    $data_agendamento = $agendamento['DATA_AGENDAMENTO'];
    $nome_paciente = trim($agendamento['NOME_PACIENTE']);
    
    // Verificar se já tem hora de chegada
    if (!empty($agendamento['HORA_CHEGADA'])) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Paciente já teve chegada registrada',
            'hora_chegada' => $agendamento['HORA_CHEGADA'],
            'ordem_chegada' => $agendamento['ORDEM_CHEGADA']
        ]);
        exit;
    }
    
    // Calcular próxima ordem de chegada para a agenda/data
    $query_ordem = "SELECT MAX(ORDEM_CHEGADA) as MAXIMA_ORDEM 
                    FROM AGENDAMENTOS 
                    WHERE AGENDA_ID = ? AND DATA_AGENDAMENTO = ? 
                    AND ORDEM_CHEGADA IS NOT NULL";
    $stmt_ordem = ibase_prepare($conn, $query_ordem);
    $result_ordem = ibase_execute($stmt_ordem, $agenda_id, $data_agendamento);
    $ordem_result = ibase_fetch_assoc($result_ordem);
    
    $proxima_ordem = ($ordem_result['MAXIMA_ORDEM'] ?? 0) + 1;
    $hora_chegada = date('Y-m-d H:i:s');
    
    // Atualizar agendamento com ordem e hora de chegada
    $query_update = "UPDATE AGENDAMENTOS 
                     SET ORDEM_CHEGADA = ?, HORA_CHEGADA = ? 
                     WHERE ID = ?";
    $stmt_update = ibase_prepare($conn, $query_update);
    $result_update = ibase_execute($stmt_update, $proxima_ordem, $hora_chegada, $agendamento_id);
    
    if ($result_update) {
        ibase_commit($conn);
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => "Chegada registrada para $nome_paciente",
            'ordem_chegada' => $proxima_ordem,
            'hora_chegada' => $hora_chegada,
            'detalhes' => [
                'agendamento_id' => $agendamento_id,
                'agenda_id' => $agenda_id,
                'data' => $data_agendamento,
                'paciente' => utf8_encode($nome_paciente)
            ]
        ]);
    } else {
        ibase_rollback($conn);
        throw new Exception('Erro ao registrar chegada');
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        ibase_rollback($conn);
    }
    
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>