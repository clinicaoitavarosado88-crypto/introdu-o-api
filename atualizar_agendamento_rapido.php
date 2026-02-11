<?php
// atualizar_agendamento_rapido.php - Atualização rápida de campos específicos
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
    
    if (!$agendamento_id) {
        throw new Exception('ID do agendamento não fornecido');
    }
    
    // Verificar se o agendamento existe
    $query_check = "SELECT ID, NOME_PACIENTE FROM AGENDAMENTOS WHERE ID = ?";
    $stmt_check = ibase_prepare($conn, $query_check);
    $result_check = ibase_execute($stmt_check, $agendamento_id);
    $agendamento = ibase_fetch_assoc($result_check);
    
    if (!$agendamento) {
        throw new Exception('Agendamento não encontrado');
    }
    
    $campos_update = [];
    $valores_update = [];
    $alteracoes = [];
    
    // Verificar quais campos foram enviados para atualização
    if (isset($_POST['confirmado'])) {
        $confirmado = (int)$_POST['confirmado'];
        $campos_update[] = 'CONFIRMADO = ?';
        $valores_update[] = $confirmado;
        $alteracoes[] = 'Confirmação: ' . ($confirmado ? 'Confirmado' : 'Não confirmado');
    }
    
    if (isset($_POST['tipo_atendimento'])) {
        $tipo_atendimento = trim($_POST['tipo_atendimento']);
        if (!in_array($tipo_atendimento, ['NORMAL', 'PRIORIDADE'])) {
            throw new Exception('Tipo de atendimento inválido');
        }
        $campos_update[] = 'TIPO_ATENDIMENTO = ?';
        $valores_update[] = $tipo_atendimento;
        $alteracoes[] = 'Tipo de atendimento: ' . $tipo_atendimento;
    }
    
    if (empty($campos_update)) {
        throw new Exception('Nenhum campo foi enviado para atualização');
    }
    
    // Executar atualização
    $query_update = "UPDATE AGENDAMENTOS SET " . implode(', ', $campos_update) . " WHERE ID = ?";
    $valores_update[] = $agendamento_id; // ID no final
    
    $stmt_update = ibase_prepare($conn, $query_update);
    $result_update = ibase_execute($stmt_update, ...$valores_update);
    
    if ($result_update) {
        ibase_commit($conn);
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Agendamento atualizado com sucesso',
            'agendamento_id' => $agendamento_id,
            'paciente' => utf8_encode(trim($agendamento['NOME_PACIENTE'])),
            'alteracoes' => $alteracoes
        ]);
    } else {
        ibase_rollback($conn);
        throw new Exception('Erro ao atualizar agendamento');
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