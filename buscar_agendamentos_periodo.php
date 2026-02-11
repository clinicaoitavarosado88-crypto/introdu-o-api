<?php
// buscar_agendamentos_periodo.php - VERSÃO COM DEBUG MELHORADO
header('Content-Type: application/json');
include 'includes/connection.php';

// Debug: Registrar parâmetros recebidos
error_log("buscar_agendamentos_periodo.php - Parâmetros: " . json_encode($_GET));

$agenda_id = $_GET['agenda_id'] ?? 0;
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$tipo = $_GET['tipo'] ?? 'semana';

// Validação de parâmetros
if (!$agenda_id || !$data_inicio || !$data_fim) {
    error_log("buscar_agendamentos_periodo.php - ERRO: Parâmetros inválidos");
    echo json_encode([
        'erro' => 'Parâmetros inválidos',
        'agendamentos' => [], 
        'estatisticas' => []
    ]);
    exit;
}

try {
    // Debug: Log da query
    error_log("buscar_agendamentos_periodo.php - Buscando agendamentos para agenda_id=$agenda_id, período $data_inicio a $data_fim, tipo=$tipo");

    // ✅ CORREÇÃO: Buscar TODOS os agendamentos, usando estrutura igual ao buscar_agendamentos_dia.php
    $query = "SELECT 
                ag.ID,
                ag.NUMERO_AGENDAMENTO,
                ag.DATA_AGENDAMENTO,
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
              LEFT JOIN CONVENIOS c ON ag.CONVENIO_ID = c.ID
              JOIN AGENDAS a ON ag.AGENDA_ID = a.ID
              LEFT JOIN LAB_MEDICOS_PRES med ON a.MEDICO_ID = med.ID
              LEFT JOIN LAB_MEDICOS_ESPECIALIDADES me ON med.ID = me.MEDICO_ID
              LEFT JOIN ESPECIALIDADES e ON e.ID = me.ESPECIALIDADE_ID
              LEFT JOIN GRUPO_EXAMES pr ON a.PROCEDIMENTO_ID = pr.ID
              WHERE ag.AGENDA_ID = ? 
              AND ag.DATA_AGENDAMENTO BETWEEN ? AND ?
              AND ag.STATUS NOT IN ('CANCELADO', 'FALTOU')
              ORDER BY ag.DATA_AGENDAMENTO, ag.HORA_AGENDAMENTO";

    $stmt = ibase_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . ibase_errmsg());
    }
    
    $result = ibase_execute($stmt, $agenda_id, $data_inicio, $data_fim);
    if (!$result) {
        throw new Exception('Erro ao executar query: ' . ibase_errmsg());
    }

    $agendamentos = [];
    $estatisticas = [
        'total_agendamentos' => 0,
        'primeira_vez' => 0,
        'retornos' => 0,
        'confirmados' => 0
    ];

    $count = 0;
    while ($row = ibase_fetch_assoc($result)) {
        $count++;
        $data = $row['DATA_AGENDAMENTO'];
        $hora = substr($row['HORA_AGENDAMENTO'], 0, 5);
        
        if (!isset($agendamentos[$data])) {
            $agendamentos[$data] = [];
        }
        
        // Determinar o tipo de atendimento
        $tipo_atendimento = '';
        if ($row['ESPECIALIDADE_NOME']) {
            $tipo_atendimento = utf8_encode($row['ESPECIALIDADE_NOME']);
        } else if ($row['PROCEDIMENTO_NOME']) {
            $tipo_atendimento = utf8_encode($row['PROCEDIMENTO_NOME']);
        }
        
        $agendamentos[$data][] = [
            'id' => $row['ID'],
            'numero' => $row['NUMERO_AGENDAMENTO'],
            'hora' => $hora,
            'paciente' => utf8_encode($row['PACIENTE_NOME'] ?? 'Paciente não informado'),
            'telefone' => $row['PACIENTE_TELEFONE'] ?? '',
            'cpf' => $row['PACIENTE_CPF'] ?? '',
            'convenio' => utf8_encode($row['CONVENIO_NOME'] ?? 'Convênio não informado'),
            'status' => $row['STATUS'],
            'tipo_consulta' => $row['TIPO_CONSULTA'] ?? 'primeira_vez',
            'observacoes' => utf8_encode($row['OBSERVACOES'] ?? ''),
            'tipo_atendimento' => $tipo_atendimento,
            'medico' => utf8_encode($row['MEDICO_NOME'] ?? ''),
            'tipo_agendamento' => $row['TIPO_AGENDAMENTO'] ?? ''
        ];
        
        // Estatísticas
        $estatisticas['total_agendamentos']++;
        
        if ($row['TIPO_CONSULTA'] === 'primeira_vez') {
            $estatisticas['primeira_vez']++;
        } elseif ($row['TIPO_CONSULTA'] === 'retorno') {
            $estatisticas['retornos']++;
        }
        
        if ($row['STATUS'] === 'CONFIRMADO') {
            $estatisticas['confirmados']++;
        }
    }

    // Debug: Log dos resultados
    error_log("buscar_agendamentos_periodo.php - Encontrados $count agendamentos");
    error_log("buscar_agendamentos_periodo.php - Dias com agendamentos: " . count($agendamentos));

    // Para visualização mensal, busca também os dias com funcionamento
    if ($tipo === 'mes') {
        try {
            $query_funcionamento = "SELECT DISTINCT ah.DIA_SEMANA
                                    FROM AGENDA_HORARIOS ah
                                    WHERE ah.AGENDA_ID = ?";
            
            $stmt_func = ibase_prepare($conn, $query_funcionamento);
            if ($stmt_func) {
                $result_func = ibase_execute($stmt_func, $agenda_id);
                if ($result_func) {
                    $dias_funcionamento = [];
                    while ($row = ibase_fetch_assoc($result_func)) {
                        $dias_funcionamento[] = trim(utf8_encode($row['DIA_SEMANA']));
                    }
                    $estatisticas['dias_funcionamento'] = $dias_funcionamento;
                    error_log("buscar_agendamentos_periodo.php - Dias de funcionamento: " . implode(', ', $dias_funcionamento));
                }
            }
        } catch (Exception $e) {
            error_log("buscar_agendamentos_periodo.php - Erro ao buscar dias funcionamento: " . $e->getMessage());
            // Não falha se não conseguir buscar dias de funcionamento
        }
    }

    // Resposta final
    $resposta = [
        'agendamentos' => $agendamentos,
        'estatisticas' => $estatisticas,
        'debug' => [
            'agenda_id' => $agenda_id,
            'periodo' => "$data_inicio a $data_fim",
            'tipo' => $tipo,
            'total_encontrados' => $count,
            'dias_com_agendamentos' => count($agendamentos)
        ]
    ];

    error_log("buscar_agendamentos_periodo.php - Retornando dados: " . json_encode($resposta['debug']));
    echo json_encode($resposta);

} catch (Exception $e) {
    error_log("buscar_agendamentos_periodo.php - ERRO: " . $e->getMessage());
    echo json_encode([
        'erro' => $e->getMessage(),
        'agendamentos' => [], 
        'estatisticas' => [],
        'debug' => [
            'agenda_id' => $agenda_id,
            'periodo' => "$data_inicio a $data_fim",
            'tipo' => $tipo,
            'erro_detalhado' => $e->getTraceAsString()
        ]
    ]);
}
?>