<?php
include 'includes/connection.php';

$tipo = $_GET['tipo'] ?? '';
$nome = trim($_GET['nome'] ?? '');
$dia = trim($_GET['dia'] ?? '');

// Buscar ID
if ($tipo === 'consulta') {
    $sql_id = "SELECT ID FROM ESPECIALIDADES WHERE NOME = '" . str_replace("'", "''", $nome) . "'";
    $res = ibase_query($conn, $sql_id);
    $row = ibase_fetch_assoc($res);
    $id = $row['ID'] ?? null;

    $sql = "
        SELECT a.ID, m.NOME AS MEDICO, e.NOME AS ESPECIALIDADE, h.DIA_SEMANA, h.HORARIO_INICIO, h.HORARIO_FIM
        FROM AGENDAS a
        JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
        JOIN ESPECIALIDADES e ON e.ID = a.ESPECIALIDADE_ID
        JOIN AGENDA_HORARIOS h ON h.AGENDA_ID = a.ID
        WHERE a.TIPO = 'consulta' AND a.ESPECIALIDADE_ID = $id"
        . ($dia ? " AND h.DIA_SEMANA = '" . str_replace("'", "''", $dia) . "'" : "") . "
        ORDER BY h.DIA_SEMANA, h.HORARIO_INICIO
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
        SELECT a.ID, p.NOME AS PROCEDIMENTO, h.DIA_SEMANA, h.HORARIO_INICIO, h.HORARIO_FIM
        FROM AGENDAS a
        JOIN GRUPO_EXAMES p ON p.ID = a.PROCEDIMENTO_ID
        JOIN AGENDA_HORARIOS h ON h.AGENDA_ID = a.ID
        WHERE a.TIPO = 'procedimento' AND a.PROCEDIMENTO_ID = $id"
        . ($dia ? " AND h.DIA_SEMANA = '" . str_replace("'", "''", $dia) . "'" : "") . "
        ORDER BY h.DIA_SEMANA, h.HORARIO_INICIO
    ";
} else {
    echo "<div class='text-red-600'>Tipo inválido.</div>";
    exit;
}

// Exibir resultados
$res = ibase_query($conn, $sql);
$tem = false;

while ($row = ibase_fetch_assoc($res)) {
    $tem = true;
    echo "<div class='mb-3 p-3 border bg-white dark:bg-gray-700 rounded shadow'>";
    echo "<strong>" . ($tipo === 'consulta' ? "Dr. {$row['MEDICO']} – {$row['ESPECIALIDADE']}" : "{$row['PROCEDIMENTO']}") . "</strong><br>";
    echo "{$row['DIA_SEMANA']}: {$row['HORARIO_INICIO']} – {$row['HORARIO_FIM']}";
    echo "</div>";
}

if (!$tem) {
    echo "<p class='text-sm text-gray-500'>Nenhuma agenda encontrada para esse dia.</p>";
}
