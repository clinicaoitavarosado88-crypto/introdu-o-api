<?php
// buscar_convenios_agenda.php
// Retorna os LAB_CONVENIOS disponíveis para uma agenda
// A cidade é detectada automaticamente pelo UNIDADE_ID da agenda (LAB_CIDADES)
//
// Parâmetros:
//   agenda_id (obrigatório) - ID da agenda
//
// Fluxo:
//   1. AGENDAS.UNIDADE_ID → LAB_CIDADES.ID → descobre a cidade (IDLOCAL)
//   2. AGENDA_CONVENIOS → categorias que a agenda aceita (Particular, Cartão de Desconto, etc.)
//   3. CONVENIO_LAB_CONVENIOS → LAB_CONVENIOS específicos por cidade/forma de pagamento

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

include 'includes/connection.php';

try {
    $agenda_id = (int)($_GET['agenda_id'] ?? $_POST['agenda_id'] ?? 0);

    if (!$agenda_id) {
        throw new Exception('agenda_id é obrigatório');
    }

    // 1. Buscar a agenda e sua cidade (UNIDADE_ID = LAB_CIDADES.ID = IDLOCAL)
    $q_agenda = ibase_prepare($conn, "SELECT a.UNIDADE_ID, a.TIPO, a.TIPO_AGENDA, c.NOME_UNIDADE as CIDADE,
            m.NOME as MEDICO
        FROM AGENDAS a
        LEFT JOIN LAB_CIDADES c ON a.UNIDADE_ID = c.ID
        LEFT JOIN LAB_MEDICOS_PRES m ON a.MEDICO_ID = m.ID
        WHERE a.ID = ?");
    $r_agenda = ibase_execute($q_agenda, $agenda_id);
    $info_agenda = ibase_fetch_assoc($r_agenda);

    if (!$info_agenda) {
        throw new Exception('Agenda não encontrada');
    }

    $idlocal = (int)($info_agenda['UNIDADE_ID'] ?? 0);
    $cidade = mb_convert_encoding(trim($info_agenda['CIDADE'] ?? ''), 'UTF-8', 'Windows-1252');
    $medico = mb_convert_encoding(trim($info_agenda['MEDICO'] ?? ''), 'UTF-8', 'Windows-1252');

    if (!$idlocal) {
        throw new Exception('Agenda sem cidade/unidade configurada');
    }

    // 2. Buscar categorias (CONVENIOS) vinculadas à agenda
    $q_cat = ibase_prepare($conn, "SELECT ac.CONVENIO_ID, c.NOME as CATEGORIA
        FROM AGENDA_CONVENIOS ac
        JOIN CONVENIOS c ON ac.CONVENIO_ID = c.ID
        WHERE ac.AGENDA_ID = ?
        ORDER BY c.NOME");
    $r_cat = ibase_execute($q_cat, $agenda_id);

    $categorias = [];
    while ($row = ibase_fetch_assoc($r_cat)) {
        $categorias[] = [
            'id' => (int)$row['CONVENIO_ID'],
            'nome' => mb_convert_encoding(trim($row['CATEGORIA']), 'UTF-8', 'Windows-1252')
        ];
    }

    if (empty($categorias)) {
        throw new Exception('Nenhum convênio vinculado a esta agenda');
    }

    // 3. Para cada categoria, buscar os LAB_CONVENIOS da cidade
    $resultado = [];

    foreach ($categorias as $cat) {
        $q_lab = ibase_prepare($conn, "SELECT clc.LAB_CONVENIO_ID, lc.CONVENIO, lc.PARTICULAR, lc.PIX, lc.E_CARTAO, lc.CARTAO_BENEFICIO, lc.SOCIO
            FROM CONVENIO_LAB_CONVENIOS clc
            JOIN LAB_CONVENIOS lc ON clc.LAB_CONVENIO_ID = lc.IDCONVENIO
            WHERE clc.CONVENIO_ID = ?
            AND lc.IDLOCAL = ?
            AND (lc.SUSPENSO IS NULL OR lc.SUSPENSO <> 'S')
            ORDER BY lc.CONVENIO");
        $r_lab = ibase_execute($q_lab, $cat['id'], $idlocal);

        $opcoes = [];
        while ($row = ibase_fetch_assoc($r_lab)) {
            // Determinar forma de pagamento
            $forma = 'DINHEIRO';
            if (trim($row['PIX'] ?? '') === 'S') $forma = 'PIX';
            elseif (trim($row['E_CARTAO'] ?? '') === 'S' && trim($row['CARTAO_BENEFICIO'] ?? '') === 'S') $forma = 'CARTAO_DESCONTO';
            elseif (trim($row['E_CARTAO'] ?? '') === 'S') $forma = 'CARTAO';
            elseif (trim($row['CARTAO_BENEFICIO'] ?? '') === 'S') $forma = 'CARTAO_DESCONTO';
            elseif (trim($row['SOCIO'] ?? '') === 'S' && trim($row['PARTICULAR'] ?? '') !== 'S') $forma = 'SOCIO';

            $opcoes[] = [
                'lab_convenio_id' => (int)$row['LAB_CONVENIO_ID'],
                'nome' => mb_convert_encoding(trim($row['CONVENIO']), 'UTF-8', 'Windows-1252'),
                'forma_pagamento' => $forma,
                'particular' => trim($row['PARTICULAR'] ?? '') === 'S',
                'pix' => trim($row['PIX'] ?? '') === 'S',
                'cartao' => trim($row['E_CARTAO'] ?? '') === 'S',
                'cartao_desconto' => trim($row['CARTAO_BENEFICIO'] ?? '') === 'S',
                'socio' => trim($row['SOCIO'] ?? '') === 'S'
            ];
        }

        $resultado[] = [
            'categoria_id' => $cat['id'],
            'categoria' => $cat['nome'],
            'tem_opcoes' => !empty($opcoes),
            'opcoes' => $opcoes
        ];
    }

    echo json_encode([
        'status' => 'sucesso',
        'agenda_id' => $agenda_id,
        'cidade' => $cidade,
        'idlocal' => $idlocal,
        'medico' => $medico,
        'tipo' => trim($info_agenda['TIPO'] ?? ''),
        'total_categorias' => count($resultado),
        'categorias' => $resultado
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
