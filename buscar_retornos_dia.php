<?php
// buscar_retornos_dia.php
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

// Busca retornos do dia com informações completas
$query = "SELECT 
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
            e.NOME as ESPECIALIDADE_NOME,
            pr.NOME as PROCEDIMENTO_NOME,
            med.NOME as MEDICO_NOME
          FROM AGENDAMENTOS ag
          LEFT JOIN LAB_PACIENTES p ON ag.PACIENTE_ID = p.IDPACIENTE
          JOIN CONVENIOS c ON ag.CONVENIO_ID = c.ID
          JOIN AGENDAS a ON ag.AGENDA_ID = a.ID
          LEFT JOIN LAB_MEDICOS_PRES med ON a.MEDICO_ID = med.ID
          LEFT JOIN LAB_MEDICOS_ESPECIALIDADES me ON med.ID = me.MEDICO_ID
          LEFT JOIN ESPECIALIDADES e ON e.ID = me.ESPECIALIDADE_ID
          LEFT JOIN GRUPO_EXAMES pr ON a.PROCEDIMENTO_ID = pr.ID
          WHERE ag.AGENDA_ID = ? 
          AND ag.DATA_AGENDAMENTO = ?
          AND ag.STATUS NOT IN ('CANCELADO', 'FALTOU')
          AND ag.TIPO_AGENDAMENTO = 'RETORNO'
          ORDER BY ag.HORA_AGENDAMENTO";

$stmt = ibase_prepare($conn, $query);
$result = ibase_execute($stmt, $agenda_id, $data);

$retornos = [];

while ($row = ibase_fetch_assoc($result)) {
    $hora = substr($row['HORA_AGENDAMENTO'], 0, 5); // Remove segundos
    
    // ✅ DEBUG DETALHADO DO TIPO_AGENDAMENTO
    $tipo_agendamento_raw = $row['TIPO_AGENDAMENTO'] ?? null;
    error_log("DEBUG RETORNO - TIPO_AGENDAMENTO raw: '" . ($tipo_agendamento_raw ?? 'NULL') . "' (type: " . gettype($tipo_agendamento_raw) . ")");
    if ($tipo_agendamento_raw !== null) {
        error_log("DEBUG RETORNO - TIPO_AGENDAMENTO hex: " . bin2hex($tipo_agendamento_raw));
        error_log("DEBUG RETORNO - TIPO_AGENDAMENTO length: " . strlen($tipo_agendamento_raw));
    }
    
    // Determinar o tipo de atendimento
    $tipo_atendimento = '';
    if ($row['ESPECIALIDADE_NOME']) {
        $tipo_atendimento = utf8_encode($row['ESPECIALIDADE_NOME']);
    } else if ($row['PROCEDIMENTO_NOME']) {
        $tipo_atendimento = utf8_encode($row['PROCEDIMENTO_NOME']);
    }
    
    $retornos[$hora] = [
        'id' => $row['ID'],
        'numero' => $row['NUMERO_AGENDAMENTO'],
        'hora' => $hora,
        'paciente' => utf8_encode($row['PACIENTE_NOME']),
        'telefone' => $row['PACIENTE_TELEFONE'] ?? '',
        'cpf' => $row['PACIENTE_CPF'] ?? '',
        'convenio' => utf8_encode($row['CONVENIO_NOME']),
        'status' => $row['STATUS'],
        'tipo_consulta' => $row['TIPO_CONSULTA'] ?? 'retorno',
        'observacoes' => utf8_encode($row['OBSERVACOES'] ?? ''),
        'tipo_agendamento' => trim($row['TIPO_AGENDAMENTO'] ?? 'RETORNO'),
        'tipo_atendimento' => $tipo_atendimento,
        'medico' => utf8_encode($row['MEDICO_NOME'] ?? ''),
        'exames' => [] // Será preenchido abaixo se for procedimento
    ];
    
    // ✅ DEBUG DO VALOR FINAL
    $tipo_final = $retornos[$hora]['tipo_agendamento'];
    error_log("DEBUG RETORNO FINAL - hora: $hora, tipo_agendamento final: '$tipo_final' (length: " . strlen($tipo_final) . ")");
}

// Buscar exames para retornos de procedimento
foreach ($retornos as $hora => $retorno) {
    // Buscar exames se for um retorno com exames associados
    // Vamos buscar para todos os retornos e verificar se há exames
    if ($retorno['numero']) {
        
        $query_exames = "SELECT 
                            ae.EXAME_ID,
                            le.EXAME as NOME_EXAME
                         FROM AGENDAMENTO_EXAMES ae
                         LEFT JOIN LAB_EXAMES le ON le.IDEXAME = ae.EXAME_ID
                         WHERE ae.NUMERO_AGENDAMENTO = ?
                         ORDER BY le.EXAME";
        
        $stmt_exames = ibase_prepare($conn, $query_exames);
        $result_exames = ibase_execute($stmt_exames, $retorno['numero']);
        
        $exames = [];
        while ($row_exame = ibase_fetch_assoc($result_exames)) {
            if ($row_exame['NOME_EXAME']) { // Só adicionar se o exame existir
                $exames[] = [
                    'id' => (int)$row_exame['EXAME_ID'],
                    'nome' => utf8_encode(trim($row_exame['NOME_EXAME']))
                ];
            }
        }
        
        $retornos[$hora]['exames'] = $exames;
        ibase_free_result($result_exames);
    }
}

// Debug
error_log("buscar_retornos_dia.php - Retornos encontrados: " . count($retornos));
error_log("buscar_retornos_dia.php - Dados: " . json_encode($retornos));

// Debug específico para tipo_agendamento
foreach ($retornos as $hora => $ret) {
    error_log("Retorno $hora - tipo_agendamento: '" . ($ret['tipo_agendamento'] ?? 'NULL') . "' (length: " . strlen($ret['tipo_agendamento'] ?? '') . ")");
    error_log("Retorno $hora - exames: " . count($ret['exames'] ?? []));
}

echo json_encode($retornos);
?>