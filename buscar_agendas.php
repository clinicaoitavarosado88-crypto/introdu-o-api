<?php
header('Content-Type: application/json; charset=UTF-8');

include 'includes/connection.php';

$q = strtoupper(trim($_GET['q'] ?? ''));

// Consulta: agenda + dias agrupados da tabela agenda_horarios
$sql = "
  SELECT
    a.id,
    a.agenda,
    a.tipo,
    LIST(DISTINCT h.dia_semana, ', ') AS dias
  FROM agendas a
  LEFT JOIN agenda_horarios h ON h.agenda_id = a.id
";

if ($q !== '') {
  $q = str_replace("'", "''", $q);
  $sql .= " WHERE UPPER(a.agenda) LIKE '%$q%' OR UPPER(a.tipo) LIKE '%$q%'";
}

$sql .= " GROUP BY a.id, a.agenda, a.tipo ORDER BY a.id DESC";

$res = ibase_query($conn, $sql);

$agendas = [];
while ($row = ibase_fetch_assoc($res)) {
  $agendas[] = [
    'id' => (int)$row['ID'],
    'agenda' => mb_convert_encoding(trim($row['AGENDA']), 'UTF-8', 'Windows-1252'),
    'tipo' => trim($row['TIPO']),
    'dias' => mb_convert_encoding(trim($row['DIAS'] ?? ''), 'UTF-8', 'Windows-1252')
  ];
}

echo json_encode([
  'status' => 'sucesso',
  'total' => count($agendas),
  'agendas' => $agendas
], JSON_UNESCAPED_UNICODE);
?>