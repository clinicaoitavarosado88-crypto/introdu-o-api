<?php
// Verificação de autenticação por token
include 'includes/auth_middleware.php';

header('Content-Type: application/json');
include 'includes/connection.php';

$busca = $_GET['busca'] ?? $_POST['busca'] ?? '';

try {
    // Limit to prevent timeout with large dataset
    $sql = "SELECT FIRST 100 ID, NOME FROM LAB_MEDICOS_PRES";
    
    if (!empty($busca)) {
        $sql = "SELECT FIRST 50 ID, NOME FROM LAB_MEDICOS_PRES WHERE UPPER(NOME) LIKE UPPER(?)";
        $stmt = ibase_prepare($conn, $sql . " ORDER BY NOME");
        $result = ibase_execute($stmt, '%' . $busca . '%');
    } else {
        $sql .= " ORDER BY NOME";
        $result = ibase_query($conn, $sql);
    }
    
    $medicos = [];
    
    while ($row = ibase_fetch_assoc($result)) {
        $medicos[] = [
            'id' => trim($row['ID']),
            'text' => trim($row['NOME'])
        ];
    }
    
    echo json_encode([
        'results' => $medicos,
        'pagination' => ['more' => false]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'results' => [],
        'error' => 'Erro ao buscar médicos: ' . $e->getMessage()
    ]);
}
?>