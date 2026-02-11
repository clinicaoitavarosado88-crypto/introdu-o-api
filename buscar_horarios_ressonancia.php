<?php
// buscar_horarios_ressonancia.php - Buscar horários para agendas de RESSONÂNCIA
// Considera: CONTRASTE (médico presente), ANESTESIA (dias/horários específicos), TEMPO DO EXAME

// Verificação de autenticação por token (exceto para CLI)
if (php_sapi_name() !== 'cli') {
    include 'includes/auth_middleware.php';
}

header('Content-Type: application/json');
include 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

$agenda_id = $_GET['agenda_id'] ?? 0;
$data = $_GET['data'] ?? '';
$exame_id_param = $_GET['exame_id'] ?? ''; // ✅ Pode ser 1 ID ou múltiplos separados por vírgula (ex: "544,545")

if (!$agenda_id || !$data) {
    echo json_encode(['erro' => true, 'mensagem' => 'Parâmetros obrigatórios: agenda_id, data']);
    exit;
}

// ✅ Processar múltiplos IDs de exames
$exames_ids = [];
if (!empty($exame_id_param)) {
    $exames_ids_raw = explode(',', $exame_id_param);
    $exames_ids = array_map('intval', array_filter($exames_ids_raw));
}

$tem_exames_selecionados = count($exames_ids) > 0;

// Converte a data para um objeto DateTime
try {
    $data_obj = new DateTime($data);
} catch (Exception $e) {
    echo json_encode(['erro' => true, 'mensagem' => 'Data inválida']);
    exit;
}

// Mapeia dias da semana - já em Windows-1252 para o banco
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

error_log("buscar_horarios_ressonancia.php - Dia: $dia_semana, Exames IDs: " . implode(',', $exames_ids));

// Função para converter UTF-8 para Windows-1252
function utf8_to_windows1252($str) {
    return iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $str);
}

// ============================================================================
// 1. BUSCAR INFORMAÇÕES DOS EXAMES (pode ser múltiplos)
// ============================================================================

$exame_precisa_contraste = false;
$exame_precisa_anestesia = false;
$tempo_exame = 30; // Padrão inicial: 30 minutos

if ($tem_exames_selecionados) {
    // ✅ Exames específicos foram informados - buscar dados de CADA um e SOMAR os tempos
    $tempo_exame = 0; // Resetar para somar
    $exames_info_detalhada = [];

    foreach ($exames_ids as $exame_id) {
        $query_exame = "SELECT EXAME, USA_CONTRASTE, PRECISA_ANESTESIA, TEMPO_EXAME
                        FROM LAB_EXAMES
                        WHERE IDEXAME = ?";

        $stmt_exame = ibase_prepare($conn, $query_exame);
        $result_exame = ibase_execute($stmt_exame, $exame_id);
        $exame_info = ibase_fetch_assoc($result_exame);

        if ($exame_info) {
            $tempo_deste_exame = (int)($exame_info['TEMPO_EXAME'] ?? 30);

            if ($tempo_deste_exame <= 0) {
                $tempo_deste_exame = 30; // Fallback
            }

            // ✅ SOMAR o tempo deste exame ao total
            $tempo_exame += $tempo_deste_exame;

            // ✅ Se QUALQUER exame precisa contraste ou anestesia, marcar como necessário
            if (trim($exame_info['USA_CONTRASTE']) === 'S') {
                $exame_precisa_contraste = true;
            }
            if (trim($exame_info['PRECISA_ANESTESIA']) === 'S') {
                $exame_precisa_anestesia = true;
            }

            $exames_info_detalhada[] = [
                'id' => $exame_id,
                'nome' => trim($exame_info['EXAME']),
                'tempo' => $tempo_deste_exame,
                'contraste' => (trim($exame_info['USA_CONTRASTE']) === 'S'),
                'anestesia' => (trim($exame_info['PRECISA_ANESTESIA']) === 'S')
            ];

            error_log("buscar_horarios_ressonancia.php - Exame ID $exame_id: Tempo={$tempo_deste_exame}min");
        }
    }

    // Se nenhum exame foi encontrado, usar padrão
    if ($tempo_exame <= 0) {
        $tempo_exame = 30;
    }

    error_log("buscar_horarios_ressonancia.php - " . count($exames_ids) . " exame(s) selecionado(s), TEMPO TOTAL SOMADO: {$tempo_exame}min, Contraste=" . ($exame_precisa_contraste ? 'S' : 'N') . ", Anestesia=" . ($exame_precisa_anestesia ? 'S' : 'N'));

} else {
    // ✅ Quando não informar exames, usar tempo MÍNIMO dos exames de RESSONÂNCIA REAIS
    // Excluir taxas (TAXA, CONTRASTE, ANTECIPAÇÃO) que têm 30min mas não são exames reais
    $query_tempo_medio = "SELECT MIN(TEMPO_EXAME) as TEMPO_MINIMO
                         FROM LAB_EXAMES
                         WHERE UPPER(EXAME) LIKE '%RESSON%'
                         AND TEMPO_EXAME > 0
                         AND UPPER(EXAME) NOT LIKE '%TAXA%'
                         AND UPPER(EXAME) NOT LIKE '%CONTRASTE%'
                         AND UPPER(EXAME) NOT LIKE '%ANTECIPA%'";

    $result_tempo = ibase_query($conn, $query_tempo_medio);
    $tempo_info = ibase_fetch_assoc($result_tempo);

    if ($tempo_info && $tempo_info['TEMPO_MINIMO'] > 0) {
        // Usar o tempo MÍNIMO dos exames REAIS (não taxas)
        $tempo_exame = (int)$tempo_info['TEMPO_MINIMO'];
        error_log("buscar_horarios_ressonancia.php - Sem exames específicos, usando tempo MÍNIMO dos exames de ressonância (excluindo taxas): {$tempo_exame}min");
    } else {
        // Se não encontrar, usar 45min como padrão (tempo típico de ressonância)
        $tempo_exame = 45;
        error_log("buscar_horarios_ressonancia.php - Sem exames específicos, usando padrão para ressonância: 45min");
    }
}

// ============================================================================
// 2. BUSCAR CONFIGURAÇÃO DO HORÁRIO
// ============================================================================

$query_horarios = "SELECT * FROM AGENDA_HORARIOS
                   WHERE AGENDA_ID = ? AND TRIM(DIA_SEMANA) = ?";

$stmt = ibase_prepare($conn, $query_horarios);
$result = ibase_execute($stmt, $agenda_id, utf8_to_windows1252($dia_semana));
$horario_funcionamento = ibase_fetch_assoc($result);

if (!$horario_funcionamento) {
    error_log("buscar_horarios_ressonancia.php - Nenhum horário encontrado para agenda $agenda_id no dia $dia_semana");

    http_response_code(404);
    echo json_encode([
        'erro' => true,
        'tipo' => 'horario_nao_configurado',
        'mensagem' => 'Esta agenda não possui horários configurados para ' . $dia_semana,
        'dia_semana' => $dia_semana,
        'horarios' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================================
// 3. VALIDAR REQUISITOS DO EXAME
// ============================================================================

$tem_medico = (trim($horario_funcionamento['TEM_MEDICO'] ?? 'N') === 'S');
$aceita_anestesia = (trim($horario_funcionamento['ACEITA_ANESTESIA'] ?? 'N') === 'S');
$limite_anestesias = (int)($horario_funcionamento['LIMITE_ANESTESIAS'] ?? 0);

// ✅ Se exame precisa de CONTRASTE, mas horário não tem médico → BLOQUEAR
if ($exame_precisa_contraste && !$tem_medico) {
    error_log("buscar_horarios_ressonancia.php - Exame precisa de contraste mas horário não tem médico");

    echo json_encode([
        'erro' => true,
        'tipo' => 'contraste_indisponivel',
        'mensagem' => 'Este exame requer contraste, mas não há médico disponível neste dia.',
        'sugestao' => 'Selecione outro dia da semana ou entre em contato com a clínica.',
        'dia_semana' => $dia_semana,
        'horarios' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ Se exame precisa de ANESTESIA, mas horário não aceita → BLOQUEAR
if ($exame_precisa_anestesia && !$aceita_anestesia) {
    error_log("buscar_horarios_ressonancia.php - Exame precisa de anestesia mas horário não aceita");

    echo json_encode([
        'erro' => true,
        'tipo' => 'anestesia_indisponivel',
        'mensagem' => 'Este exame requer anestesia, mas este dia não está configurado para receber anestesia.',
        'sugestao' => 'Agendamentos com anestesia geralmente são realizados às Quintas-feiras pela manhã.',
        'dia_semana' => $dia_semana,
        'horarios' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================================
// 4. VERIFICAR LIMITE DE ANESTESIAS JÁ AGENDADAS
// ============================================================================

$anestesias_agendadas = 0;

if ($exame_precisa_anestesia && $limite_anestesias > 0) {
    // Contar quantas anestesias já foram agendadas neste dia
    $query_anestesias = "SELECT COUNT(*) as TOTAL
                         FROM AGENDAMENTOS ag
                         JOIN LAB_EXAMES ex ON ex.IDEXAME = ag.EXAME_ID
                         WHERE ag.AGENDA_ID = ?
                           AND ag.DATA_AGENDAMENTO = ?
                           AND ag.STATUS NOT IN ('CANCELADO', 'FALTOU')
                           AND ex.PRECISA_ANESTESIA = 'S'";

    $stmt_anest = ibase_prepare($conn, $query_anestesias);
    $result_anest = ibase_execute($stmt_anest, $agenda_id, $data);
    $row_anest = ibase_fetch_assoc($result_anest);
    $anestesias_agendadas = (int)($row_anest['TOTAL'] ?? 0);

    error_log("buscar_horarios_ressonancia.php - Anestesias agendadas: $anestesias_agendadas / Limite: $limite_anestesias");

    // ✅ Se atingiu o limite → BLOQUEAR
    if ($anestesias_agendadas >= $limite_anestesias) {
        echo json_encode([
            'erro' => true,
            'tipo' => 'limite_anestesia_atingido',
            'mensagem' => "Limite de anestesias atingido para este dia ($limite_anestesias/$limite_anestesias).",
            'sugestao' => 'Selecione outro dia disponível para anestesia.',
            'dia_semana' => $dia_semana,
            'limite_anestesias' => $limite_anestesias,
            'anestesias_agendadas' => $anestesias_agendadas,
            'horarios' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ============================================================================
// 5. BUSCAR VAGAS E AGENDAMENTOS EXISTENTES
// ============================================================================

$limite_vagas_dia = $horario_funcionamento['VAGAS_DIA'] ?? 20;

// ✅ CORREÇÃO: Buscar agendamentos e SOMAR tempos de TODOS os exames
$query_agendados = "SELECT ag.HORA_AGENDAMENTO, ag.NUMERO_AGENDAMENTO
                    FROM AGENDAMENTOS ag
                    WHERE ag.AGENDA_ID = ? AND ag.DATA_AGENDAMENTO = ?
                    AND ag.STATUS NOT IN ('CANCELADO', 'FALTOU')
                    AND (ag.TIPO_AGENDAMENTO IS NULL OR ag.TIPO_AGENDAMENTO <> 'ENCAIXE')
                    ORDER BY ag.HORA_AGENDAMENTO";

$stmt_agendados = ibase_prepare($conn, $query_agendados);
$result_agendados = ibase_execute($stmt_agendados, $agenda_id, $data);

$agendamentos_existentes = [];
$vagas_ocupadas = 0;

while ($agendado = ibase_fetch_assoc($result_agendados)) {
    $hora_agd = substr($agendado['HORA_AGENDAMENTO'], 0, 5);
    $numero_agd = trim($agendado['NUMERO_AGENDAMENTO']);

    // ✅ Buscar TODOS os exames deste agendamento e SOMAR os tempos
    $query_exames_agd = "SELECT ex.TEMPO_EXAME
                         FROM AGENDAMENTO_EXAMES ae
                         LEFT JOIN LAB_EXAMES ex ON ex.IDEXAME = ae.EXAME_ID
                         WHERE ae.NUMERO_AGENDAMENTO = ?";

    $stmt_exames_agd = ibase_prepare($conn, $query_exames_agd);
    $result_exames_agd = ibase_execute($stmt_exames_agd, $numero_agd);

    $tempo_total_agd = 0;
    $count_exames = 0;
    while ($exame_agd = ibase_fetch_assoc($result_exames_agd)) {
        $tempo_exame_agd = (int)($exame_agd['TEMPO_EXAME'] ?? 0);
        if ($tempo_exame_agd <= 0) {
            $tempo_exame_agd = 30; // Fallback
        }
        $tempo_total_agd += $tempo_exame_agd;
        $count_exames++;
    }

    // Se não encontrou exames, usar tempo padrão
    if ($tempo_total_agd <= 0) {
        $tempo_total_agd = 30;
    }

    $agendamentos_existentes[] = [
        'hora' => $hora_agd,
        'tempo' => $tempo_total_agd,
        'numero' => $numero_agd
    ];

    error_log("buscar_horarios_ressonancia.php - Agendamento $numero_agd às $hora_agd: $count_exames exame(s), tempo total: {$tempo_total_agd}min");

    $vagas_ocupadas++;
    ibase_free_result($result_exames_agd);
}

$vagas_restantes = $limite_vagas_dia - $vagas_ocupadas;

error_log("buscar_horarios_ressonancia.php - Vagas: $vagas_ocupadas/$limite_vagas_dia ocupadas, $vagas_restantes disponíveis");

// ============================================================================
// 6. GERAR HORÁRIOS DISPONÍVEIS (CÁLCULO SEQUENCIAL)
// ============================================================================

$horarios_disponiveis = [];

$funcionamento_continuo = !empty($horario_funcionamento['HORARIO_INICIO_MANHA']) &&
                         empty($horario_funcionamento['HORARIO_FIM_MANHA']) &&
                         empty($horario_funcionamento['HORARIO_INICIO_TARDE']) &&
                         !empty($horario_funcionamento['HORARIO_FIM_TARDE']);

// ✅ NOVO: Gerar horários SEQUENCIALMENTE baseado nos agendamentos existentes
if ($funcionamento_continuo) {
    $inicio = new DateTime($data . ' ' . $horario_funcionamento['HORARIO_INICIO_MANHA']);
    $fim = new DateTime($data . ' ' . $horario_funcionamento['HORARIO_FIM_TARDE']);
} else {
    // Para horário não contínuo, usar apenas o período da manhã por enquanto
    $inicio = new DateTime($data . ' ' . $horario_funcionamento['HORARIO_INICIO_MANHA']);
    $fim = new DateTime($data . ' ' . ($horario_funcionamento['HORARIO_FIM_MANHA'] ?: $horario_funcionamento['HORARIO_FIM_TARDE']));
}

// Horário atual começa no início do expediente
$horario_atual = clone $inicio;

// Função auxiliar: verificar se um horário conflita com agendamento existente
function verificarConflito($hora_teste, $tempo_teste, $agendamentos, $data) {
    $teste_inicio = new DateTime($data . ' ' . $hora_teste);
    $teste_fim = clone $teste_inicio;
    $teste_fim->add(new DateInterval("PT{$tempo_teste}M"));

    foreach ($agendamentos as $agd) {
        $agd_inicio = new DateTime($data . ' ' . $agd['hora']);
        $agd_fim = clone $agd_inicio;
        $agd_fim->add(new DateInterval("PT{$agd['tempo']}M"));

        // Verifica se há sobreposição
        if ($teste_inicio < $agd_fim && $teste_fim > $agd_inicio) {
            return $agd_fim; // Retorna quando o agendamento conflitante termina
        }
    }

    return false;
}

// Gerar horários até atingir o limite de vagas ou fim do expediente
$vagas_geradas = 0;
while ($horario_atual < $fim && $vagas_geradas < $limite_vagas_dia) {
    $hora_formatada = $horario_atual->format('H:i');

    // Verificar se esse horário conflita com algum agendamento existente
    $conflito = verificarConflito($hora_formatada, $tempo_exame, $agendamentos_existentes, $data);

    if ($conflito) {
        // Se conflita, pular para o fim do agendamento conflitante
        $horario_atual = $conflito;
        continue;
    }

    // Horário está livre
    $horarios_disponiveis[] = $hora_formatada;
    $vagas_geradas++;

    // ✅ Próximo horário = atual + tempo do exame
    $horario_atual->add(new DateInterval("PT{$tempo_exame}M"));
}

error_log("buscar_horarios_ressonancia.php - Horários gerados: " . count($horarios_disponiveis) . " (limite: $limite_vagas_dia)");

// ============================================================================
// 7. MONTAR RESPOSTA FINAL
// ============================================================================

$horarios_resposta = [];
$agora = new DateTime();
$data_hoje = $agora->format('Y-m-d');
$hora_atual = $agora->format('H:i');

// Adicionar horários DISPONÍVEIS
foreach ($horarios_disponiveis as $horario) {
    $disponivel = true;

    // Verifica se não é um horário no passado
    if ($data === $data_hoje && $horario <= $hora_atual) {
        $disponivel = false;
    }

    $horarios_resposta[] = [
        'hora' => $horario,
        'disponivel' => $disponivel
    ];
}

// Adicionar horários OCUPADOS (agendamentos existentes)
foreach ($agendamentos_existentes as $agd) {
    // Verificar se já não foi adicionado
    $ja_existe = false;
    foreach ($horarios_resposta as $hr) {
        if ($hr['hora'] === $agd['hora']) {
            $ja_existe = true;
            break;
        }
    }

    if (!$ja_existe) {
        $horarios_resposta[] = [
            'hora' => $agd['hora'],
            'disponivel' => false
        ];
    }
}

// Ordenar por horário
usort($horarios_resposta, function($a, $b) {
    return strcmp($a['hora'], $b['hora']);
});

// Resposta final
$resposta = [
    'horarios' => $horarios_resposta,
    'info_vagas' => [
        'limite_total' => $limite_vagas_dia,
        'ocupadas' => $vagas_ocupadas,
        'disponiveis' => $vagas_restantes
    ],
    'info_horario' => [
        'tem_medico' => $tem_medico,
        'aceita_anestesia' => $aceita_anestesia,
        'limite_anestesias' => $limite_anestesias,
        'anestesias_agendadas' => $anestesias_agendadas,
        'anestesias_disponiveis' => $limite_anestesias - $anestesias_agendadas
    ],
    'exame_requisitos' => [
        'precisa_contraste' => $exame_precisa_contraste,
        'precisa_anestesia' => $exame_precisa_anestesia,
        'tempo_minutos' => $tempo_exame
    ],
    'debug' => [
        'agenda_id' => $agenda_id,
        'data' => $data,
        'dia_semana' => $dia_semana,
        'exame_id' => $exame_id,
        'total_horarios_gerados' => count($horarios_resposta)
    ]
];

echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
?>
