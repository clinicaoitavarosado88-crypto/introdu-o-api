<?php
// buscar_bloqueios.php - Busca bloqueios de uma agenda específica
header('Content-Type: application/json; charset=UTF-8');

include 'includes/connection.php';
include 'includes/verificar_permissao.php';

// Verificar permissão
if (!verificarPermissaoAdminAgenda($conn, 'buscar bloqueios')) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Permissão negada'
    ]);
    exit;
}

$agenda_id = (int) ($_GET['agenda_id'] ?? 0);
$tipo = $_GET['tipo'] ?? '';

if (!$agenda_id) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'ID da agenda não fornecido'
    ]);
    exit;
}

// Construir condição de tipo
$condicao_tipo = '';
switch ($tipo) {
    case 'DIA':
        $condicao_tipo = "AND TIPO_BLOQUEIO = 'DIA'";
        break;
    case 'HORARIO':
        $condicao_tipo = "AND TIPO_BLOQUEIO = 'HORARIO'";
        break;
    case 'AGENDA':
        $condicao_tipo = "AND (TIPO_BLOQUEIO = 'AGENDA_PERMANENTE' OR TIPO_BLOQUEIO = 'AGENDA_TEMPORARIO')";
        break;
}

$sql = "
    SELECT 
        ID,
        TIPO_BLOQUEIO,
        DATA_BLOQUEIO,
        DATA_INICIO,
        DATA_FIM,
        HORARIO_INICIO,
        HORARIO_FIM,
        MOTIVO,
        USUARIO_BLOQUEIO,
        DATA_CRIACAO
    FROM AGENDA_BLOQUEIOS
    WHERE AGENDA_ID = ? 
    AND ATIVO = 1
    $condicao_tipo
    ORDER BY DATA_CRIACAO DESC
";

$stmt = ibase_prepare($conn, $sql);
$result = ibase_execute($stmt, $agenda_id);

$bloqueios = [];
while ($row = ibase_fetch_assoc($result)) {
    // Formatar os dados para JSON
    $bloqueio_formatado = [
        'id' => (int)$row['ID'],
        'tipo_bloqueio' => trim($row['TIPO_BLOQUEIO']),
        'data_bloqueio' => $row['DATA_BLOQUEIO'] ? trim($row['DATA_BLOQUEIO']) : null,
        'data_inicio' => $row['DATA_INICIO'] ? trim($row['DATA_INICIO']) : null,
        'data_fim' => $row['DATA_FIM'] ? trim($row['DATA_FIM']) : null,
        'horario_inicio' => $row['HORARIO_INICIO'] ? substr(trim($row['HORARIO_INICIO']), 0, 5) : null,
        'horario_fim' => $row['HORARIO_FIM'] ? substr(trim($row['HORARIO_FIM']), 0, 5) : null,
        'motivo' => mb_convert_encoding(trim($row['MOTIVO'] ?? ''), 'UTF-8', 'Windows-1252'),
        'usuario_bloqueio' => mb_convert_encoding(trim($row['USUARIO_BLOQUEIO'] ?? ''), 'UTF-8', 'Windows-1252'),
        'data_criacao' => trim($row['DATA_CRIACAO'])
    ];

    $bloqueios[] = $bloqueio_formatado;
}

echo json_encode([
    'status' => 'sucesso',
    'agenda_id' => $agenda_id,
    'tipo_filtro' => $tipo,
    'total' => count($bloqueios),
    'bloqueios' => $bloqueios
], JSON_UNESCAPED_UNICODE);
?>