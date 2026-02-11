<?php
// buscar_anexos_preparo.php - Buscar anexos de um preparo específico
header('Content-Type: application/json');
include 'includes/connection.php';

// Aceitar parâmetros via GET ou via linha de comando para testes
if (isset($_GET['agenda_id'])) {
    $agendaId = intval($_GET['agenda_id']);
    $preparoId = intval($_GET['preparo_id'] ?? 0);
} else {
    // Para testes via CLI
    parse_str($_SERVER['QUERY_STRING'] ?? '', $params);
    $agendaId = intval($params['agenda_id'] ?? 0);
    $preparoId = intval($params['preparo_id'] ?? 0);
}

if ($agendaId <= 0 || $preparoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID da agenda e preparo são obrigatórios']);
    exit;
}

try {
    $sql = "SELECT ID, NOME_ORIGINAL, TIPO_ARQUIVO, TAMANHO_ARQUIVO, DATA_UPLOAD 
            FROM AGENDA_PREPAROS_ANEXOS 
            WHERE AGENDA_ID = ? AND PREPARO_ID = ?
            ORDER BY DATA_UPLOAD DESC";
    
    $stmt = ibase_prepare($conn, $sql);
    $result = ibase_execute($stmt, $agendaId, $preparoId);
    
    $anexos = [];
    while ($row = ibase_fetch_assoc($result)) {
        $anexos[] = [
            'id' => $row['ID'],
            'nome' => trim($row['NOME_ORIGINAL']),
            'tipo' => trim($row['TIPO_ARQUIVO']),
            'tamanho' => $row['TAMANHO_ARQUIVO'],
            'data_upload' => $row['DATA_UPLOAD']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'anexos' => $anexos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar anexos: ' . $e->getMessage()
    ]);
}

ibase_close($conn);
?>