<?php
// verificar_vagas.php
header('Content-Type: application/json');
include 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

$agenda_id = $_GET['agenda_id'] ?? 0;
$data = $_GET['data'] ?? '';
$convenio_id = $_GET['convenio_id'] ?? 0;

if (!$agenda_id || !$data) {
    echo json_encode(['erro' => 'Parâmetros inválidos']);
    exit;
}

// Buscar limite de vagas da agenda (da tabela AGENDA_HORARIOS)
// Primeiro determinar o dia da semana
try {
    $data_obj = new DateTime($data);
} catch (Exception $e) {
    echo json_encode(['erro' => 'Data inválida']);
    exit;
}

$dias_semana_map = [
    1 => 'Segunda',
    2 => 'Terça',
    3 => 'Quarta',
    4 => 'Quinta',
    5 => 'Sexta',
    6 => 'Sábado',
    0 => 'Domingo'
];

$dia_semana_num = (int)$data_obj->format('w');
$dia_semana = $dias_semana_map[$dia_semana_num];

// Função para converter UTF-8 para Windows-1252
function utf8_to_windows1252($str) {
    return iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $str);
}

// Buscar limite de vagas do dia específico
$query_horario = "SELECT VAGAS_DIA FROM AGENDA_HORARIOS WHERE AGENDA_ID = ? AND TRIM(DIA_SEMANA) = ?";
$stmt_horario = ibase_prepare($conn, $query_horario);
$result_horario = ibase_execute($stmt_horario, $agenda_id, utf8_to_windows1252($dia_semana));
$horario = ibase_fetch_assoc($result_horario);
$limite_total = $horario['VAGAS_DIA'] ?? 20;

// Verificar vagas ocupadas no dia (apenas agendamentos NORMAIS)
$query_ocupadas = "SELECT COUNT(*) as TOTAL FROM AGENDAMENTOS 
                   WHERE AGENDA_ID = ? AND DATA_AGENDAMENTO = ? 
                   AND STATUS NOT IN ('CANCELADO', 'FALTOU')
                   AND TIPO_AGENDAMENTO = 'NORMAL'";
$stmt_ocupadas = ibase_prepare($conn, $query_ocupadas);
$result_ocupadas = ibase_execute($stmt_ocupadas, $agenda_id, $data);
$ocupadas = ibase_fetch_assoc($result_ocupadas);
$total_ocupadas = $ocupadas['TOTAL'] ?? 0;

// Verificar limite específico do convênio
$limite_convenio = null;
$vagas_convenio_ocupadas = 0;

if ($convenio_id) {
    $query_convenio = "SELECT LIMITE_ATENDIMENTOS FROM AGENDA_CONVENIOS 
                       WHERE AGENDA_ID = ? AND CONVENIO_ID = ?";
    $stmt_convenio = ibase_prepare($conn, $query_convenio);
    $result_convenio = ibase_execute($stmt_convenio, $agenda_id, $convenio_id);
    $convenio = ibase_fetch_assoc($result_convenio);
    
    if ($convenio && $convenio['LIMITE_ATENDIMENTOS'] > 0) {
        $limite_convenio = $convenio['LIMITE_ATENDIMENTOS'];
        
        // Contar quantas vagas deste convênio já foram usadas (apenas agendamentos NORMAIS)
        $query_convenio_usado = "SELECT COUNT(*) as TOTAL FROM AGENDAMENTOS 
                                 WHERE AGENDA_ID = ? AND DATA_AGENDAMENTO = ? 
                                 AND CONVENIO_ID = ?
                                 AND STATUS NOT IN ('CANCELADO', 'FALTOU')
                                 AND TIPO_AGENDAMENTO = 'NORMAL'";
        $stmt_usado = ibase_prepare($conn, $query_convenio_usado);
        $result_usado = ibase_execute($stmt_usado, $agenda_id, $data, $convenio_id);
        $usado = ibase_fetch_assoc($result_usado);
        $vagas_convenio_ocupadas = $usado['TOTAL'] ?? 0;
    }
}

// Determinar se é particular ou cartão de desconto
$query_nome_convenio = "SELECT NOME FROM CONVENIOS WHERE ID = ?";
$stmt_nome = ibase_prepare($conn, $query_nome_convenio);
$result_nome = ibase_execute($stmt_nome, $convenio_id);
$nome_convenio = ibase_fetch_assoc($result_nome);
$nome = strtolower($nome_convenio['NOME'] ?? '');

$eh_particular = (strpos($nome, 'particular') !== false || strpos($nome, 'cartão') !== false || strpos($nome, 'cartao') !== false);

// Calcular vagas disponíveis
$resposta = [
    'total_vagas' => $limite_total,
    'vagas_ocupadas' => $total_ocupadas,
    'vagas_disponiveis' => $limite_total - $total_ocupadas,
    'convenio' => [
        'id' => $convenio_id,
        'nome' => utf8_encode($nome_convenio['NOME'] ?? ''),
        'eh_particular' => $eh_particular,
        'limite_especifico' => $limite_convenio,
        'vagas_usadas' => $vagas_convenio_ocupadas,
        'vagas_disponiveis_convenio' => $limite_convenio ? ($limite_convenio - $vagas_convenio_ocupadas) : null
    ]
];

// Verificar se pode agendar
$pode_agendar = true;
$mensagem = '';

if ($total_ocupadas >= $limite_total) {
    $pode_agendar = false;
    $mensagem = 'Não há mais vagas disponíveis para este dia.';
} else if (!$eh_particular && $limite_convenio && $vagas_convenio_ocupadas >= $limite_convenio) {
    $pode_agendar = false;
    $mensagem = "O limite de vagas para o convênio {$nome_convenio['NOME']} foi atingido.";
}

$resposta['pode_agendar'] = $pode_agendar;
$resposta['mensagem'] = $mensagem;

echo json_encode($resposta);
?>