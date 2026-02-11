<?php
// Verificar configuração de horários na tabela AGENDA_HORARIOS
header('Content-Type: application/json');
include 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

$agenda_id = $_GET['agenda_id'] ?? 0;

if (!$agenda_id) {
    echo json_encode(['erro' => 'agenda_id é obrigatório']);
    exit;
}

// Buscar TODOS os horários dessa agenda
$query = "SELECT * FROM AGENDA_HORARIOS WHERE AGENDA_ID = ?";
$stmt = ibase_prepare($conn, $query);
$result = ibase_execute($stmt, $agenda_id);

$horarios = [];
while ($row = ibase_fetch_assoc($result)) {
    $horarios[] = [
        'dia_semana' => trim($row['DIA_SEMANA']),
        'horario_inicio_manha' => $row['HORARIO_INICIO_MANHA'],
        'horario_fim_manha' => $row['HORARIO_FIM_MANHA'],
        'horario_inicio_tarde' => $row['HORARIO_INICIO_TARDE'],
        'horario_fim_tarde' => $row['HORARIO_FIM_TARDE'],
        'vagas_dia' => $row['VAGAS_DIA']
    ];
}

echo json_encode([
    'agenda_id' => $agenda_id,
    'total_registros' => count($horarios),
    'horarios_configurados' => $horarios
]);
?>