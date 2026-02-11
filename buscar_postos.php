<?php
// Buscar postos da tabela lab_postos
header('Content-Type: application/json');
include 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
    parse_str(getenv('POST_DATA') ?: '', $_POST);
}

$busca = $_GET['busca'] ?? $_POST['busca'] ?? '';

try {
    // Query para buscar postos
    $sql = "SELECT ID, NOME FROM LAB_POSTOS WHERE AI = 1";
    
    if (!empty($busca)) {
        $sql .= " AND UPPER(NOME) LIKE UPPER(?)";
        $stmt = ibase_prepare($conn, $sql . " ORDER BY NOME");
        $result = ibase_execute($stmt, '%' . $busca . '%');
    } else {
        $sql .= " ORDER BY NOME";
        $result = ibase_query($conn, $sql);
    }
    
    $postos = [];
    
    while ($row = ibase_fetch_assoc($result)) {
        $postos[] = [
            'id' => trim($row['ID']),
            'text' => trim($row['NOME'])
        ];
    }
    
    // Formato para Select2
    echo json_encode([
        'results' => $postos,
        'pagination' => ['more' => false]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'results' => [],
        'error' => 'Erro ao buscar postos: ' . $e->getMessage()
    ]);
}
?>