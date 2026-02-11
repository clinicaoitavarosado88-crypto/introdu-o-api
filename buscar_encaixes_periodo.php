<?php
// buscar_encaixes_periodo.php
header('Content-Type: application/json');
include 'includes/connection.php';

$agenda_id = $_GET['agenda_id'] ?? 0;
$datas = $_GET['datas'] ?? '';

if (!$agenda_id || !$datas) {
    echo json_encode([]);
    exit;
}

$datasArray = explode(',', $datas);
$resultado = [];

foreach ($datasArray as $data) {
    $data = trim($data);
    if (!$data) continue;
    
    // Buscar encaixes para esta data específica
    $query = "SELECT * FROM AGENDA_ENCAIXES 
              WHERE AGENDA_ID = ? AND DATA_ENCAIXE = ?";
    
    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt, $agenda_id, $data);
    
    $encaixes = [];
    while ($row = ibase_fetch_assoc($result)) {
        $encaixes[] = $row;
    }
    
    $resultado[$data] = $encaixes;
}

echo json_encode($resultado);
?>