<?php
header('Content-Type: application/json; charset=UTF-8');
require_once 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

try {
    $numero_agendamento = isset($_GET['numero_agendamento']) ? trim($_GET['numero_agendamento']) : '';
    
    if (empty($numero_agendamento)) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Número do agendamento não fornecido',
            'exames' => []
        ]);
        exit;
    }
    
    // Buscar exames do agendamento na tabela de relacionamento
    $sql = "SELECT 
                ae.EXAME_ID,
                le.EXAME as NOME_EXAME
            FROM AGENDAMENTO_EXAMES ae
            LEFT JOIN LAB_EXAMES le ON le.IDEXAME = ae.EXAME_ID
            WHERE ae.NUMERO_AGENDAMENTO = ?
            ORDER BY le.EXAME";
    
    $stmt = ibase_prepare($conn, $sql);
    $result = ibase_execute($stmt, $numero_agendamento);
    
    $exames = [];
    while ($row = ibase_fetch_assoc($result)) {
        if ($row['NOME_EXAME']) { // Só adicionar se o exame existir
            $exames[] = [
                'id' => (int)$row['EXAME_ID'],
                'nome' => utf8_encode(trim($row['NOME_EXAME']))
            ];
        }
    }
    
    ibase_free_result($result);
    
    echo json_encode([
        'status' => 'sucesso',
        'numero_agendamento' => $numero_agendamento,
        'total_exames' => count($exames),
        'exames' => $exames
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
        'exames' => []
    ]);
}
?>