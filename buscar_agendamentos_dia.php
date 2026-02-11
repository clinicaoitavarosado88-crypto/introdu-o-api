<?php
// buscar_agendamentos_dia.php
header('Content-Type: application/json');
include 'includes/connection.php';

// Suporte para linha de comando
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

$agenda_id = $_GET['agenda_id'] ?? 0;
$data = $_GET['data'] ?? '';

if (!$agenda_id || !$data) {
    echo json_encode([]);
    exit;
}

// Busca agendamentos do dia com informações completas (incluindo encaixes sem cadastro e informações de OS)
$query = "SELECT DISTINCT
            ag.ID,
            ag.NUMERO_AGENDAMENTO,
            ag.HORA_AGENDAMENTO,
            COALESCE(p.PACIENTE, ag.NOME_PACIENTE) as PACIENTE_NOME,
            COALESCE(p.FONE1, ag.TELEFONE_PACIENTE) as PACIENTE_TELEFONE,
            p.CPF as PACIENTE_CPF,
            c.NOME as CONVENIO_NOME,
            ag.STATUS,
            ag.TIPO_CONSULTA,
            ag.OBSERVACOES,
            ag.TIPO_AGENDAMENTO,
            ag.CONFIRMADO,
            ag.TIPO_ATENDIMENTO,
            ag.PRECISA_SEDACAO,
            ag.ORDEM_CHEGADA,
            ag.HORA_CHEGADA,
            COALESCE(esp_salva.NOME, 
                     CASE WHEN a.TIPO = 'consulta' THEN 
                          (SELECT FIRST 1 e2.NOME FROM LAB_MEDICOS_ESPECIALIDADES me2 
                           JOIN ESPECIALIDADES e2 ON e2.ID = me2.ESPECIALIDADE_ID 
                           WHERE me2.MEDICO_ID = med.ID)
                     ELSE NULL END) as ESPECIALIDADE_NOME,
            pr.NOME as PROCEDIMENTO_NOME,
            med.NOME as MEDICO_NOME,
            lr.IDRESULTADO as OS_NUMERO,
            lr.ENVIADO_LAB as OS_STATUS
          FROM AGENDAMENTOS ag
          LEFT JOIN LAB_PACIENTES p ON ag.PACIENTE_ID = p.IDPACIENTE
          JOIN CONVENIOS c ON ag.CONVENIO_ID = c.ID
          JOIN AGENDAS a ON ag.AGENDA_ID = a.ID
          LEFT JOIN LAB_MEDICOS_PRES med ON a.MEDICO_ID = med.ID
          LEFT JOIN ESPECIALIDADES esp_salva ON ag.ESPECIALIDADE_ID = esp_salva.ID
          LEFT JOIN GRUPO_EXAMES pr ON a.PROCEDIMENTO_ID = pr.ID
          LEFT JOIN LAB_RESULTADOS lr ON lr.COD_ID = ag.ID
          WHERE ag.AGENDA_ID = ? 
          AND ag.DATA_AGENDAMENTO = ?
          AND ag.STATUS NOT IN ('CANCELADO', 'FALTOU')
          ORDER BY ag.HORA_AGENDAMENTO";

$stmt = ibase_prepare($conn, $query);
$result = ibase_execute($stmt, $agenda_id, $data);

$agendamentos = [];

while ($row = ibase_fetch_assoc($result)) {
    $hora = substr($row['HORA_AGENDAMENTO'], 0, 5); // Remove segundos
    
    // ✅ DEBUG DETALHADO DO TIPO_AGENDAMENTO
    $tipo_agendamento_raw = $row['TIPO_AGENDAMENTO'] ?? null;
    error_log("DEBUG ROW - TIPO_AGENDAMENTO raw: '" . ($tipo_agendamento_raw ?? 'NULL') . "' (type: " . gettype($tipo_agendamento_raw) . ")");
    if ($tipo_agendamento_raw !== null) {
        error_log("DEBUG ROW - TIPO_AGENDAMENTO hex: " . bin2hex($tipo_agendamento_raw));
        error_log("DEBUG ROW - TIPO_AGENDAMENTO length: " . strlen($tipo_agendamento_raw));
    }
    
    // Determinar o tipo de atendimento
    $tipo_atendimento = '';
    if ($row['ESPECIALIDADE_NOME']) {
        $tipo_atendimento = utf8_encode($row['ESPECIALIDADE_NOME']);
    } else if ($row['PROCEDIMENTO_NOME']) {
        $tipo_atendimento = utf8_encode($row['PROCEDIMENTO_NOME']);
    }
    
    $agendamentos[$hora] = [
        'id' => $row['ID'],
        'numero' => $row['NUMERO_AGENDAMENTO'],
        'hora' => $hora,
        'paciente' => utf8_encode($row['PACIENTE_NOME']),
        'telefone' => $row['PACIENTE_TELEFONE'] ?? '',
        'cpf' => $row['PACIENTE_CPF'] ?? '',
        'convenio' => utf8_encode($row['CONVENIO_NOME']),
        'status' => $row['STATUS'],
        'tipo_consulta' => $row['TIPO_CONSULTA'] ?? 'primeira_vez',
        'observacoes' => utf8_encode($row['OBSERVACOES'] ?? ''),
        'tipo_agendamento' => trim($row['TIPO_AGENDAMENTO'] ?? 'NORMAL'),
        'tipo_atendimento' => $tipo_atendimento,
        'medico' => utf8_encode($row['MEDICO_NOME'] ?? ''),
        'exames' => [], // Será preenchido abaixo se for procedimento
        
        // Novos campos de atendimento
        'confirmado' => (bool)($row['CONFIRMADO'] ?? 0),
        'tipo_atendimento_prioridade' => trim($row['TIPO_ATENDIMENTO'] ?? 'NORMAL'),
        'precisa_sedacao' => trim($row['PRECISA_SEDACAO'] ?? 'N') === 'S',
        'ordem_chegada' => $row['ORDEM_CHEGADA'],
        'hora_chegada' => $row['HORA_CHEGADA'],

        // Informações da Ordem de Serviço
        'os_numero' => $row['OS_NUMERO'] ? (int)$row['OS_NUMERO'] : null,
        'os_status' => $row['OS_STATUS'] ? 
            ($row['OS_STATUS'] == 1 ? 'COMPLETA' : 'CRIADA') : null,
        'tem_os' => !empty($row['OS_NUMERO'])
    ];
    
    // ✅ DEBUG DO VALOR FINAL
    $tipo_final = $agendamentos[$hora]['tipo_agendamento'];
    error_log("DEBUG FINAL - hora: $hora, tipo_agendamento final: '$tipo_final' (length: " . strlen($tipo_final) . ")");
}

// Buscar exames para agendamentos de procedimento
foreach ($agendamentos as $hora => $agendamento) {
    // Buscar exames se for um agendamento com exames associados
    // Vamos buscar para todos os agendamentos e verificar se há exames
    if ($agendamento['numero']) {

        // ✅ CORREÇÃO: Buscar exames com tempo para somar duração total
        $query_exames = "SELECT
                            ae.EXAME_ID,
                            le.EXAME as NOME_EXAME,
                            le.TEMPO_EXAME
                         FROM AGENDAMENTO_EXAMES ae
                         LEFT JOIN LAB_EXAMES le ON le.IDEXAME = ae.EXAME_ID
                         WHERE ae.NUMERO_AGENDAMENTO = ?
                         ORDER BY le.EXAME";

        $stmt_exames = ibase_prepare($conn, $query_exames);
        $result_exames = ibase_execute($stmt_exames, $agendamento['numero']);

        $exames = [];
        $tempo_total = 0; // ✅ Somar tempo de todos os exames
        while ($row_exame = ibase_fetch_assoc($result_exames)) {
            if ($row_exame['NOME_EXAME']) { // Só adicionar se o exame existir
                $tempo_exame = (int)($row_exame['TEMPO_EXAME'] ?? 0);
                if ($tempo_exame <= 0) {
                    $tempo_exame = 30; // Fallback padrão
                }

                $exames[] = [
                    'id' => (int)$row_exame['EXAME_ID'],
                    'nome' => utf8_encode(trim($row_exame['NOME_EXAME'])),
                    'tempo' => $tempo_exame
                ];

                // ✅ Somar ao tempo total
                $tempo_total += $tempo_exame;
            }
        }

        $agendamentos[$hora]['exames'] = $exames;
        $agendamentos[$hora]['tempo_total_minutos'] = $tempo_total; // ✅ Novo campo

        error_log("buscar_agendamentos_dia.php - {$agendamento['numero']} às $hora: " . count($exames) . " exame(s), tempo total: {$tempo_total}min");

        ibase_free_result($result_exames);
    }
}

// Debug
error_log("buscar_agendamentos_dia.php - Agendamentos encontrados: " . count($agendamentos));
error_log("buscar_agendamentos_dia.php - Dados: " . json_encode($agendamentos));

// Debug específico para tipo_agendamento
foreach ($agendamentos as $hora => $ag) {
    error_log("Agendamento $hora - tipo_agendamento: '" . ($ag['tipo_agendamento'] ?? 'NULL') . "' (length: " . strlen($ag['tipo_agendamento'] ?? '') . ")");
    error_log("Agendamento $hora - exames: " . count($ag['exames'] ?? []));
}

echo json_encode($agendamentos);
?>