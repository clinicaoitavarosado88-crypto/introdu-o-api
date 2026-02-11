<?php
// Consultar Auditoria - Versão Simples
include 'includes/connection.php';

// Parâmetros via GET
$acao = $_GET['acao'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$numero = $_GET['numero'] ?? '';
$limite = (int)($_GET['limit'] ?? 10);

header('Content-Type: application/json; charset=utf-8');

try {
    // Construir query
    $where_conditions = [];
    $params = [];

    if (!empty($acao)) {
        $where_conditions[] = "ACAO = ?";
        $params[] = $acao;
    }

    if (!empty($usuario)) {
        $where_conditions[] = "USUARIO CONTAINING ?";
        $params[] = strtoupper($usuario);
    }

    if (!empty($numero)) {
        $where_conditions[] = "NUMERO_AGENDAMENTO = ?";
        $params[] = strtoupper($numero);
    }

    $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

    $query = "SELECT FIRST {$limite}
                ID, ACAO, USUARIO, DATA_ACAO,
                NUMERO_AGENDAMENTO, PACIENTE_NOME,
                STATUS_ANTERIOR, STATUS_NOVO,
                TIPO_CONSULTA_ANTERIOR, TIPO_CONSULTA_NOVO,
                OBSERVACOES, IP_USUARIO,
                AGENDA_ID, DATA_AGENDAMENTO, HORA_AGENDAMENTO
              FROM AGENDA_AUDITORIA
              {$where_clause}
              ORDER BY ID DESC";

    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt, ...$params);

    $registros = [];
    while ($row = ibase_fetch_assoc($result)) {
        // Converter encoding
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = utf8_encode($value);
            }
        }
        $registros[] = $row;
    }

    echo json_encode([
        'status' => 'sucesso',
        'total' => count($registros),
        'registros' => $registros
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>
