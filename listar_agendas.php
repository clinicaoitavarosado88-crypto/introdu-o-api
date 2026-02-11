<?php
// Arquivo para listagem de agendas em HTML (cards)
include 'includes/connection.php';
include 'includes/agenda_card.php';

$tipo = $_GET['tipo'] ?? '';
$nome = $_GET['nome'] ?? '';
$nome = trim($nome);
$dia = isset($_GET['dia']) ? utf8_decode(trim($_GET['dia'])) : '';
if ($dia === '' || strtolower($dia) === 'null' || strtolower($dia) === 'undefined' || strtolower($dia) === 'nan') {
    $dia = null;
}

// Filtro por cidade - padrão Mossoró (ID 1)
$cidade_id = $_GET['cidade'] ?? '1';
$whereCidade = '';
if (!empty($cidade_id) && is_numeric($cidade_id)) {
    $whereCidade = " AND a.UNIDADE_ID = $cidade_id";
}

// Função para ler BLOB
function lerBlob($conn, $id) {
    if (!is_string($id) || empty($id)) return '';
    try {
        $blob = ibase_blob_open($conn, $id);
        $conteudo = '';
        while ($segmento = ibase_blob_get($blob, 4096)) {
            $conteudo .= $segmento;
        }
        ibase_blob_close($blob);
        return trim($conteudo);
    } catch (Exception $e) {
        return '';
    }
}

// Função para corrigir caracteres corrompidos do Firebird
function corrigirCaracteres($texto) {
    if (empty($texto)) return $texto;

    // Mapa de bytes corrompidos -> caracteres corretos
    $correcoes = [
        "\x9D" => "º",  // Ordinal masculino
        "\x9C" => "ª",  // Ordinal feminino
        "\x87" => "Ç",  // C cedilha maiúsculo
        "\xE7" => "ç",  // c cedilha minúsculo
    ];

    return str_replace(array_keys($correcoes), array_values($correcoes), $texto);
}


// 🔍 Buscar ID da especialidade ou procedimento
if ($tipo === 'consulta') {
    // Busca especialidade convertendo caracteres corrompidos (similar ao procedimento)
    $sql_todas = "SELECT ID, NOME FROM ESPECIALIDADES";
    $res_todas = ibase_query($conn, $sql_todas);
    $id = null;
    
    while ($row_esp = ibase_fetch_assoc($res_todas)) {
        $nome_banco_utf8 = mb_convert_encoding($row_esp['NOME'], 'UTF-8', 'Windows-1252');
        if (strtoupper(trim($nome_banco_utf8)) === strtoupper(trim($nome))) {
            $id = $row_esp['ID'];
            break;
        }
    }

    if (!$id) {
        echo '<div class="text-red-600 p-4">Especialidade não encontrada: ' . htmlspecialchars($nome) . '</div>';
        exit;
    }
//file_put_contents('log_dia.txt', "Dia recebido: [" . $dia . "]\n", FILE_APPEND);

$whereDia = '';
if (isset($dia) && $dia !== '') {
    //file_put_contents('log_dia.txt', "Dia recebido2: [" . $dia . "]\n", FILE_APPEND);

    $whereDia = " AND EXISTS (
        SELECT 1 FROM AGENDA_HORARIOS d 
        WHERE d.AGENDA_ID = a.ID 
        AND TRIM(d.DIA_SEMANA) = '$dia'
    )";
}
$dia_exibicao = $dia ? ucfirst(strtolower($dia)) : 'Todos os Dias';
$sql = "
    SELECT a.ID, u.NOME_UNIDADE AS UNIDADE, m.NOME AS MEDICO, e.NOME AS ESPECIALIDADE,
           a.TELEFONE, a.SALA, a.TEMPO_ESTIMADO_MINUTOS, me.IDADE_MINIMA,
           a.OBSERVACOES, a.INFORMACOES_FIXAS, a.ORIENTACOES, a.POSSUI_RETORNO, a.ATENDE_COMORBIDADE,
           a.LIMITE_VAGAS_DIA, a.LIMITE_RETORNOS_DIA, a.LIMITE_ENCAIXES_DIA,
           $id AS ESPECIALIDADE_ID_FILTRADA
    FROM AGENDAS a
    JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
    JOIN LAB_MEDICOS_ESPECIALIDADES me ON me.MEDICO_ID = m.ID
    JOIN ESPECIALIDADES e ON e.ID = me.ESPECIALIDADE_ID
    JOIN LAB_CIDADES u ON u.ID = a.UNIDADE_ID
    WHERE a.TIPO = 'consulta' AND me.ESPECIALIDADE_ID = $id $whereDia $whereCidade
";
//echo $sql;

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
    
    if (!$id) {
        echo '<div class="text-red-600 p-4">Procedimento não encontrado: ' . htmlspecialchars($nome) . '</div>';
        exit;
    }

       $whereDia = '';
    if (isset($dia) && $dia !== '') {
        $whereDia = " AND EXISTS (
            SELECT 1 FROM AGENDA_HORARIOS d 
            WHERE d.AGENDA_ID = a.ID 
            AND TRIM(d.DIA_SEMANA) = '$dia'
        )";
    }

    $sql = "
        SELECT a.ID, u.NOME_UNIDADE AS UNIDADE, p.NOME AS PROCEDIMENTO, m.NOME AS MEDICO,
               a.TELEFONE, a.SALA, a.TEMPO_ESTIMADO_MINUTOS,
               a.OBSERVACOES, a.INFORMACOES_FIXAS, a.ORIENTACOES,
               a.LIMITE_VAGAS_DIA, a.LIMITE_RETORNOS_DIA, a.LIMITE_ENCAIXES_DIA, a.POSSUI_RETORNO, a.ATENDE_COMORBIDADE
        FROM AGENDAS a
        JOIN GRUPO_EXAMES p ON p.ID = a.PROCEDIMENTO_ID
        JOIN LAB_CIDADES u ON u.ID = a.UNIDADE_ID
        LEFT JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
        WHERE a.TIPO = 'procedimento' AND p.ID = $id $whereDia $whereCidade
    ";

    //file_put_contents('log_dia.txt', "grupoexames: [" . $sql . "]\n [" . $id . "]\n", FILE_APPEND);

} else {
    echo '<div class="text-red-600 p-4">Tipo inválido. Use tipo=consulta ou tipo=procedimento</div>';
    exit;
}

$res_agendas = ibase_query($conn, $sql);

// Verificar se há agendas
$count = 0;
?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
<?php
while ($a = ibase_fetch_assoc($res_agendas)) {
    $count++;
    // Renderizar o card usando a função do arquivo agenda_card.php
    echo renderAgendaCard($a, $conn, $tipo);
}
?>
</div>

<?php
// Mensagem caso não encontre agendas
if ($count === 0) {
    // Determinar a mensagem apropriada
    $mensagem = '';
    $icone = '📅';

    if (!empty($dia)) {
        // Filtrado por dia específico - mensagem mais amigável
        $dia_formatado = ucfirst(strtolower($dia));
        $tipo_formatado = ($tipo === 'consulta') ? 'consultas' : 'procedimentos';
        $mensagem = "Não há atendimento de <strong>$nome</strong> às <strong>$dia_formatado</strong>s nesta unidade.";
        $icone = '🗓️';
    } else {
        // Sem filtro de dia - mensagem genérica
        $mensagem = "Nenhuma agenda encontrada para <strong>$nome</strong> nesta unidade.";
        $icone = '📋';
    }

    echo '<div class="text-center p-8">';
    echo '<div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 dark:bg-gray-700 mb-4">';
    echo '<span class="text-4xl">' . $icone . '</span>';
    echo '</div>';
    echo '<p class="text-lg text-gray-700 dark:text-gray-300 mb-2">' . $mensagem . '</p>';
    echo '<p class="text-sm text-gray-500 dark:text-gray-400">Tente selecionar outro dia ou outra unidade.</p>';
    echo '</div>';
}
