<?php
// buscar_horarios.php - VERSÃO CORRIGIDA PARA ENCODING E LIMITE DE VAGAS
header('Content-Type: application/json');
include 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

$agenda_id = $_GET['agenda_id'] ?? 0;
$data = $_GET['data'] ?? '';

if (!$agenda_id || !$data) {
    echo json_encode(['debug' => 'Parâmetros inválidos', 'agenda_id' => $agenda_id, 'data' => $data]);
    exit;
}

echo json_encode(['debug' => 'INÍCIO', 'agenda_id' => $agenda_id, 'data' => $data]); echo "\n";

// Converte a data para um objeto DateTime
try {
    $data_obj = new DateTime($data);
} catch (Exception $e) {
    echo json_encode([]);
    exit;
}

// Mapeia dias da semana - já em Windows-1252 para o banco
$dias_semana_map = [
    1 => 'Segunda',
    2 => 'Terça',      // Windows-1252
    3 => 'Quarta',
    4 => 'Quinta',
    5 => 'Sexta',
    6 => 'Sábado',     // Windows-1252
    0 => 'Domingo'
];

$dia_semana_num = (int)$data_obj->format('w');
$dia_semana = $dias_semana_map[$dia_semana_num];

// Debug
error_log("buscar_horarios.php - Dia da semana (num): $dia_semana_num");
error_log("buscar_horarios.php - Dia da semana (texto): $dia_semana");

// Função para converter UTF-8 para Windows-1252
function utf8_to_windows1252($str) {
    return iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $str);
}

// Primeira tentativa - buscar direto
$query_horarios = "SELECT * FROM AGENDA_HORARIOS 
                   WHERE AGENDA_ID = ? AND TRIM(DIA_SEMANA) = ?";

$stmt = ibase_prepare($conn, $query_horarios);
$result = ibase_execute($stmt, $agenda_id, utf8_to_windows1252($dia_semana));
$horario_funcionamento = ibase_fetch_assoc($result);

// Se não encontrou, tenta variações
if (!$horario_funcionamento) {
    // Array com variações possíveis
    $variacoes = [];
    
    switch($dia_semana_num) {
        case 2: // Terça
            $variacoes = ['Terça', 'Terca', 'Terça-feira', 'Terca-feira', 'TERÇA', 'TERCA'];
            break;
        case 6: // Sábado
            $variacoes = ['Sábado', 'Sabado', 'SÁBADO', 'SABADO'];
            break;
        default:
            $variacoes = [$dia_semana, $dia_semana . '-feira', strtoupper($dia_semana)];
    }
    
    foreach ($variacoes as $variacao) {
        $stmt_var = ibase_prepare($conn, "SELECT * FROM AGENDA_HORARIOS WHERE AGENDA_ID = ? AND TRIM(DIA_SEMANA) = ?");
        $result_var = ibase_execute($stmt_var, $agenda_id, utf8_to_windows1252($variacao));
        $horario_funcionamento = ibase_fetch_assoc($result_var);
        
        if ($horario_funcionamento) {
            error_log("buscar_horarios.php - Encontrado com variação: $variacao");
            break;
        }
    }
}

// Se ainda não encontrou, tenta com LIKE
if (!$horario_funcionamento) {
    $query_like = "SELECT * FROM AGENDA_HORARIOS 
                   WHERE AGENDA_ID = ? AND UPPER(TRIM(DIA_SEMANA)) LIKE ?";
    
    $stmt_like = ibase_prepare($conn, $query_like);
    $dia_like = strtoupper(substr($dia_semana, 0, 3)) . '%';
    $result_like = ibase_execute($stmt_like, $agenda_id, $dia_like);
    $horario_funcionamento = ibase_fetch_assoc($result_like);
    
    if ($horario_funcionamento) {
        error_log("buscar_horarios.php - Encontrado com LIKE: $dia_like");
    }
}

if (!$horario_funcionamento) {
    error_log("buscar_horarios.php - Nenhum horário encontrado para agenda $agenda_id no dia $dia_semana");
    echo json_encode(['debug' => 'Sem horário de funcionamento', 'agenda_id' => $agenda_id, 'dia_semana' => $dia_semana]);
    exit;
}

echo json_encode(['debug' => 'Horário encontrado', 'horario' => $horario_funcionamento]); echo "\n";

// ============================================================================
// VERIFICAR BLOQUEIOS DA AGENDA
// ============================================================================

// 1. Verificar se a agenda está bloqueada permanentemente
$query_bloqueio_permanente = "SELECT COUNT(*) as BLOQUEADO 
                              FROM AGENDA_BLOQUEIOS 
                              WHERE AGENDA_ID = ? 
                              AND TIPO_BLOQUEIO = 'AGENDA_PERMANENTE' 
                              AND ATIVO = 1";

$stmt_bloqueio_perm = ibase_prepare($conn, $query_bloqueio_permanente);
$result_bloqueio_perm = ibase_execute($stmt_bloqueio_perm, $agenda_id);
$bloqueio_perm = ibase_fetch_assoc($result_bloqueio_perm);

if ($bloqueio_perm['BLOQUEADO'] > 0) {
    error_log("buscar_horarios.php - Agenda $agenda_id bloqueada permanentemente");
    echo json_encode([
        'debug' => 'BLOQUEADO PERMANENTE',
        'bloqueado' => true,
        'tipo_bloqueio' => 'agenda_permanente',
        'mensagem' => 'Esta agenda está bloqueada permanentemente'
    ]);
    exit;
}

echo json_encode(['debug' => 'Sem bloqueio permanente']); echo "\n";

// 2. Verificar se a agenda está bloqueada temporariamente na data
$query_bloqueio_temp = "SELECT COUNT(*) as BLOQUEADO 
                        FROM AGENDA_BLOQUEIOS 
                        WHERE AGENDA_ID = ? 
                        AND TIPO_BLOQUEIO = 'AGENDA_TEMPORARIO' 
                        AND ATIVO = 1 
                        AND ? BETWEEN DATA_INICIO AND DATA_FIM";

$stmt_bloqueio_temp = ibase_prepare($conn, $query_bloqueio_temp);
$result_bloqueio_temp = ibase_execute($stmt_bloqueio_temp, $agenda_id, $data);
$bloqueio_temp = ibase_fetch_assoc($result_bloqueio_temp);

if ($bloqueio_temp['BLOQUEADO'] > 0) {
    error_log("buscar_horarios.php - Agenda $agenda_id bloqueada temporariamente na data $data");
    echo json_encode([
        'bloqueado' => true,
        'tipo_bloqueio' => 'agenda_temporario',
        'mensagem' => 'Esta agenda está bloqueada temporariamente nesta data'
    ]);
    exit;
}

// 3. Verificar se o dia específico está bloqueado
$query_bloqueio_dia = "SELECT COUNT(*) as BLOQUEADO 
                       FROM AGENDA_BLOQUEIOS 
                       WHERE AGENDA_ID = ? 
                       AND TIPO_BLOQUEIO = 'DIA' 
                       AND ATIVO = 1 
                       AND DATA_BLOQUEIO = ?";

$stmt_bloqueio_dia = ibase_prepare($conn, $query_bloqueio_dia);
$result_bloqueio_dia = ibase_execute($stmt_bloqueio_dia, $agenda_id, $data);
$bloqueio_dia = ibase_fetch_assoc($result_bloqueio_dia);

if ($bloqueio_dia['BLOQUEADO'] > 0) {
    error_log("buscar_horarios.php - Dia $data bloqueado para agenda $agenda_id");
    echo json_encode([
        'bloqueado' => true,
        'tipo_bloqueio' => 'dia',
        'mensagem' => 'Este dia está bloqueado para agendamentos'
    ]);
    exit;
}

// 4. Buscar horários específicos bloqueados na data
$query_horarios_bloqueados = "SELECT HORARIO_INICIO, HORARIO_FIM 
                              FROM AGENDA_BLOQUEIOS 
                              WHERE AGENDA_ID = ? 
                              AND TIPO_BLOQUEIO = 'HORARIO' 
                              AND ATIVO = 1 
                              AND DATA_BLOQUEIO = ?";

$stmt_horarios_bloq = ibase_prepare($conn, $query_horarios_bloqueados);
$result_horarios_bloq = ibase_execute($stmt_horarios_bloq, $agenda_id, $data);

$bloqueios_horario = [];
while ($bloqueio_hora = ibase_fetch_assoc($result_horarios_bloq)) {
    $bloqueios_horario[] = [
        'inicio' => substr($bloqueio_hora['HORARIO_INICIO'], 0, 5),
        'fim' => substr($bloqueio_hora['HORARIO_FIM'], 0, 5)
    ];
}

error_log("buscar_horarios.php - Encontrados " . count($bloqueios_horario) . " bloqueios de horário para agenda $agenda_id na data $data");

// Busca informações da agenda (tempo estimado)
$query_agenda = "SELECT TEMPO_ESTIMADO_MINUTOS FROM AGENDAS WHERE ID = ?";
$stmt_agenda = ibase_prepare($conn, $query_agenda);
$result_agenda = ibase_execute($stmt_agenda, $agenda_id);
$agenda = ibase_fetch_assoc($result_agenda);

$tempo_estimado = $agenda['TEMPO_ESTIMADO_MINUTOS'] ?? 30;
// ✅ USAR VAGAS_DIA da tabela AGENDA_HORARIOS em vez de LIMITE_VAGAS_DIA da tabela AGENDAS
$limite_vagas_dia = $horario_funcionamento['VAGAS_DIA'] ?? 20;

// ✅ SEPARAR: Busca agendamentos NORMAIS (excluindo encaixes) para contagem de vagas
$query_agendados_normais = "SELECT HORA_AGENDAMENTO FROM AGENDAMENTOS 
                           WHERE AGENDA_ID = ? AND DATA_AGENDAMENTO = ? 
                           AND STATUS NOT IN ('CANCELADO', 'FALTOU')
                           AND (TIPO_AGENDAMENTO IS NULL OR TIPO_AGENDAMENTO <> 'ENCAIXE')";

$stmt_agendados_normais = ibase_prepare($conn, $query_agendados_normais);
$result_agendados_normais = ibase_execute($stmt_agendados_normais, $agenda_id, $data);

// ✅ BUSCAR TODOS os agendamentos (incluindo encaixes) para verificar horários ocupados
$query_todos_agendamentos = "SELECT HORA_AGENDAMENTO FROM AGENDAMENTOS 
                            WHERE AGENDA_ID = ? AND DATA_AGENDAMENTO = ? 
                            AND STATUS NOT IN ('CANCELADO', 'FALTOU')";

$stmt_todos_agendamentos = ibase_prepare($conn, $query_todos_agendamentos);
$result_todos_agendamentos = ibase_execute($stmt_todos_agendamentos, $agenda_id, $data);

$horarios_ocupados = [];
$vagas_normais_ocupadas = 0;

// Contar apenas agendamentos normais para vagas
while ($agendado = ibase_fetch_assoc($result_agendados_normais)) {
    $vagas_normais_ocupadas++;
}

// Listar TODOS os horários ocupados (normais + encaixes)
while ($agendado = ibase_fetch_assoc($result_todos_agendamentos)) {
    $horarios_ocupados[] = substr($agendado['HORA_AGENDAMENTO'], 0, 5);
}

// ✅ CALCULAR: Vagas normais restantes (sem contar encaixes)
$vagas_restantes = $limite_vagas_dia - $vagas_normais_ocupadas;

error_log("buscar_horarios.php - Limite de vagas NORMAIS: $limite_vagas_dia, Ocupadas NORMAIS: $vagas_normais_ocupadas, Restantes: $vagas_restantes");

// Gera lista de horários possíveis respeitando o limite de vagas
$horarios_disponiveis = [];
$total_horarios_gerados = 0;

// Horários da manhã
if ($horario_funcionamento['HORARIO_INICIO_MANHA'] && $horario_funcionamento['HORARIO_FIM_MANHA']) {
    $inicio_manha = new DateTime($data . ' ' . $horario_funcionamento['HORARIO_INICIO_MANHA']);
    $fim_manha = new DateTime($data . ' ' . $horario_funcionamento['HORARIO_FIM_MANHA']);
    
    $atual = clone $inicio_manha;
    while ($atual < $fim_manha && $total_horarios_gerados < $vagas_restantes) {
        $hora_formatada = $atual->format('H:i');
        // Só adiciona se ainda não estiver ocupado
        if (!in_array($hora_formatada, $horarios_ocupados)) {
            $horarios_disponiveis[] = $hora_formatada;
            $total_horarios_gerados++;
        }
        $atual->add(new DateInterval("PT{$tempo_estimado}M"));
    }
}

// Horários da tarde (continua contando do total)
if ($horario_funcionamento['HORARIO_INICIO_TARDE'] && $horario_funcionamento['HORARIO_FIM_TARDE'] && $total_horarios_gerados < $vagas_restantes) {
    $inicio_tarde = new DateTime($data . ' ' . $horario_funcionamento['HORARIO_INICIO_TARDE']);
    $fim_tarde = new DateTime($data . ' ' . $horario_funcionamento['HORARIO_FIM_TARDE']);
    
    $atual = clone $inicio_tarde;
    while ($atual < $fim_tarde && $total_horarios_gerados < $vagas_restantes) {
        $hora_formatada = $atual->format('H:i');
        // Só adiciona se ainda não estiver ocupado
        if (!in_array($hora_formatada, $horarios_ocupados)) {
            $horarios_disponiveis[] = $hora_formatada;
            $total_horarios_gerados++;
        }
        $atual->add(new DateInterval("PT{$tempo_estimado}M"));
    }
}

// Adiciona os horários já ocupados à lista para exibição
foreach ($horarios_ocupados as $hora_ocupada) {
    if (!in_array($hora_ocupada, $horarios_disponiveis)) {
        $horarios_disponiveis[] = $hora_ocupada;
    }
}

// Ordena os horários
sort($horarios_disponiveis);

// Verifica disponibilidade e monta resposta
$horarios_resposta = [];
$agora = new DateTime();
$data_hoje = $agora->format('Y-m-d');
$hora_atual = $agora->format('H:i');

foreach ($horarios_disponiveis as $horario) {
    $disponivel = true;
    
    // Verifica se já está ocupado
    if (in_array($horario, $horarios_ocupados)) {
        $disponivel = false;
    }
    
    // Verifica se não é um horário no passado (apenas para hoje)
    if ($data === $data_hoje && $horario <= $hora_atual) {
        $disponivel = false;
    }
    
    // ============================================================================
    // VERIFICAR SE O HORÁRIO ESTÁ BLOQUEADO
    // ============================================================================
    if ($disponivel) {
        foreach ($bloqueios_horario as $bloqueio) {
            $horario_atual = $horario . ':00';
            $inicio_bloqueio = $bloqueio['inicio'] . ':00';
            $fim_bloqueio = $bloqueio['fim'] . ':00';
            
            // Verificar se o horário está dentro do período bloqueado
            if ($horario_atual >= $inicio_bloqueio && $horario_atual < $fim_bloqueio) {
                $disponivel = false;
                error_log("buscar_horarios.php - Horário $horario bloqueado (período: {$bloqueio['inicio']} às {$bloqueio['fim']})");
                break;
            }
        }
    }
    
    $horarios_resposta[] = [
        'hora' => $horario,
        'disponivel' => $disponivel
    ];
}

// ✅ CALCULAR INFO DE ENCAIXES SEPARADAMENTE
$query_encaixes = "SELECT COUNT(*) as TOTAL_ENCAIXES FROM AGENDAMENTOS 
                   WHERE AGENDA_ID = ? AND DATA_AGENDAMENTO = ? 
                   AND STATUS NOT IN ('CANCELADO', 'FALTOU')
                   AND TIPO_AGENDAMENTO = 'ENCAIXE'";

$stmt_encaixes = ibase_prepare($conn, $query_encaixes);
$result_encaixes = ibase_execute($stmt_encaixes, $agenda_id, $data);
$row_encaixes = ibase_fetch_assoc($result_encaixes);
$total_encaixes = (int)($row_encaixes['TOTAL_ENCAIXES'] ?? 0);

// Buscar limite de encaixes da agenda
$query_limite_encaixes = "SELECT LIMITE_ENCAIXES_DIA FROM AGENDAS WHERE ID = ?";
$stmt_limite_encaixes = ibase_prepare($conn, $query_limite_encaixes);
$result_limite_encaixes = ibase_execute($stmt_limite_encaixes, $agenda_id);
$row_limite_encaixes = ibase_fetch_assoc($result_limite_encaixes);
$limite_encaixes_dia = (int)($row_limite_encaixes['LIMITE_ENCAIXES_DIA'] ?? 5);

// ✅ RESPOSTA COM INFORMAÇÕES SEPARADAS
echo json_encode(['debug' => 'Montando resposta final', 'total_horarios' => count($horarios_resposta)]); echo "\n";

$resposta = [
    'debug' => 'RESPOSTA FINAL',
    'horarios' => $horarios_resposta,
    'info_vagas' => [
        'limite_total' => $limite_vagas_dia,
        'ocupadas' => $vagas_normais_ocupadas,
        'disponiveis' => $vagas_restantes
    ],
    'info_encaixes' => [
        'limite_total' => $limite_encaixes_dia,
        'ocupados' => $total_encaixes,
        'disponiveis' => $limite_encaixes_dia - $total_encaixes
    ]
];

echo json_encode($resposta);
?>