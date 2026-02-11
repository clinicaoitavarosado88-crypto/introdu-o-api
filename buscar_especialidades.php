<?php
// Verificação de autenticação por token (exceto para CLI)
if (php_sapi_name() !== 'cli') {
    include 'includes/auth_middleware.php';
}

// Buscar especialidades da tabela especialidades
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
}

// Disable output buffering for CLI
if (php_sapi_name() === 'cli') {
    ob_implicit_flush(true);
}

include 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
    parse_str(getenv('POST_DATA') ?: '', $_POST);
}

$busca = $_GET['busca'] ?? $_POST['busca'] ?? '';

try {
    // Query para buscar especialidades/unidades
    $sql = "SELECT ID, NOME AS UNIDADE FROM ESPECIALIDADES";
    
    if (!empty($busca)) {
        $sql .= " WHERE UPPER(NOME) LIKE UPPER(?)";
        $stmt = ibase_prepare($conn, $sql . " ORDER BY NOME");
        $result = ibase_execute($stmt, '%' . $busca . '%');
    } else {
        $sql .= " ORDER BY NOME";
        $result = ibase_query($conn, $sql);
    }
    
    $especialidades = [];
    
    while ($row = ibase_fetch_assoc($result)) {
        $especialidades[] = [
            'id' => trim($row['ID']),
            'text' => trim($row['UNIDADE'])
        ];
    }
    
    // Formato para Select2
    $output = json_encode([
        'results' => $especialidades,
        'pagination' => ['more' => false]
    ]);
    
    echo $output;
    
    // Force output for CLI
    if (php_sapi_name() === 'cli') {
        flush();
    }
    
} catch (Exception $e) {
    echo json_encode([
        'results' => [],
        'error' => 'Erro ao buscar especialidades: ' . $e->getMessage()
    ]);
}
?>