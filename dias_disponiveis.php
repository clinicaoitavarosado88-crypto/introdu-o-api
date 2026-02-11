<?php
include 'includes/connection.php';

$tipo = $_GET['tipo'] ?? '';
$nome = trim($_GET['nome'] ?? '');

if ($tipo === 'consulta') {
    $sql_id = "SELECT ID FROM ESPECIALIDADES WHERE NOME = '" . str_replace("'", "''", $nome) . "'";
    $res = ibase_query($conn, $sql_id);
    $row = ibase_fetch_assoc($res);
    $id = $row['ID'] ?? null;

    $sql = "
        SELECT DISTINCT h.DIA_SEMANA
        FROM AGENDAS a
        JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
        JOIN ESPECIALIDADES e ON e.ID = m.ESPECIALIDADE_ID
        JOIN AGENDA_HORARIOS h ON h.AGENDA_ID = a.ID
        WHERE a.TIPO = 'consulta' AND e.ID = $id
        ORDER BY h.DIA_SEMANA
    ";
} elseif ($tipo === 'procedimento') {
    // Busca procedimento convertendo caracteres corrompidos
    $sql_todos = "SELECT ID, NOME FROM GRUPO_EXAMES";
    $res_todos = ibase_query($conn, $sql_todos);
    $id = null;
    
    while ($row_proc = ibase_fetch_assoc($res_todos)) {
        $nome_banco_utf8 = mb_convert_encoding($row_proc['NOME'], 'UTF-8', 'Windows-1252');
        if (strtoupper(trim($nome_banco_utf8)) === strtoupper(trim($nome))) {
            $id = $row_proc['ID'];
            break;
        }
    }

    $sql = "
        SELECT DISTINCT h.DIA_SEMANA
        FROM AGENDAS a
        JOIN AGENDA_HORARIOS h ON h.AGENDA_ID = a.ID
        WHERE a.TIPO = 'procedimento' AND a.PROCEDIMENTO_ID = $id
        ORDER BY h.DIA_SEMANA
    ";
} else {
    echo "<div class='text-red-600'>Tipo inválido.</div>";
    exit;
}

$res = ibase_query($conn, $sql);
$dias = [];
while ($row = ibase_fetch_assoc($res)) {
    $dias[] = $row['DIA_SEMANA'];
}

if ($dias) {
    // Pode usar os dados como quiser no JS
} else {
//echo "<p class='text-sm text-red-600'>Nenhum dia cadastrado.</p>";
}
