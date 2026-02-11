
<?php
// buscar_agendamento_horario.php
header('Content-Type: application/json');
include 'includes/connection.php';

$agenda_id = $_GET['agenda_id'] ?? 0;
$data = $_GET['data'] ?? '';
$horario = $_GET['horario'] ?? '';

if (!$agenda_id || !$data || !$horario) {
    echo json_encode(null);
    exit;
}

// Busca agendamento existente no horário
$query = "SELECT ID, NUMERO_AGENDAMENTO 
          FROM AGENDAMENTOS 
          WHERE AGENDA_ID = ? 
          AND DATA_AGENDAMENTO = ? 
          AND HORA_AGENDAMENTO = ?
          AND STATUS NOT IN ('CANCELADO', 'FALTOU')";

$stmt = ibase_prepare($conn, $query);
$result = ibase_execute($stmt, $agenda_id, $data, $horario . ':00');
$agendamento = ibase_fetch_assoc($result);

if ($agendamento) {
    echo json_encode([
        'id' => $agendamento['ID'],
        'numero' => $agendamento['NUMERO_AGENDAMENTO']
    ]);
} else {
    echo json_encode(null);
}
?>