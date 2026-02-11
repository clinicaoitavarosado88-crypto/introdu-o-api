<?php
header('Content-Type: application/json; charset=UTF-8');

include 'includes/connection.php';
$agendaId = (int) ($_GET['agenda_id'] ?? 0);

if (!$agendaId) {
  echo json_encode([
    'status' => 'erro',
    'mensagem' => 'agenda_id é obrigatório'
  ]);
  exit;
}

$sql = "SELECT a.ID, a.DATA_AGENDAMENTO, a.HORA_AGENDAMENTO, p.PACIENTE, c.NOME AS CONVENIO, a.STATUS
        FROM AGENDAMENTOS a
        LEFT JOIN LAB_PACIENTES p ON a.PACIENTE_ID = p.IDPACIENTE
        LEFT JOIN CONVENIOS c ON a.CONVENIO_ID = c.ID
        WHERE a.AGENDA_ID = $agendaId
        ORDER BY a.DATA_AGENDAMENTO, a.HORA_AGENDAMENTO";

$result = ibase_query($conn, $sql);

$agendamentos = [];
while ($row = ibase_fetch_assoc($result)) {
  $agendamentos[] = [
    'id' => (int)$row['ID'],
    'data_agendamento' => trim($row['DATA_AGENDAMENTO']),
    'hora_agendamento' => substr(trim($row['HORA_AGENDAMENTO']), 0, 5),
    'paciente' => mb_convert_encoding(trim($row['PACIENTE'] ?? ''), 'UTF-8', 'Windows-1252'),
    'convenio' => mb_convert_encoding(trim($row['CONVENIO'] ?? ''), 'UTF-8', 'Windows-1252'),
    'status' => trim($row['STATUS'])
  ];
}

echo json_encode([
  'status' => 'sucesso',
  'agenda_id' => $agendaId,
  'total' => count($agendamentos),
  'agendamentos' => $agendamentos
], JSON_UNESCAPED_UNICODE);
?>
