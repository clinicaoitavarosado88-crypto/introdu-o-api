<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include 'includes/connection.php';
include 'includes/auth_middleware.php';

try {
    // Verificar autenticação
    $auth_result = verify_api_token();
    if (!$auth_result['valid']) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => $auth_result['message']]);
        exit;
    }

    $paciente_id = $_GET['paciente_id'] ?? '';
    $cpf = $_GET['cpf'] ?? '';
    $status = $_GET['status'] ?? '';
    $data_inicio = $_GET['data_inicio'] ?? '';
    $data_fim = $_GET['data_fim'] ?? '';
    $limite = $_GET['limite'] ?? 50;

    // Validar parâmetros
    if (empty($paciente_id) && empty($cpf)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'message' => 'paciente_id ou cpf é obrigatório'
        ]);
        exit;
    }

    // Buscar paciente se foi fornecido CPF
    if (!empty($cpf) && empty($paciente_id)) {
        $cpf_limpo = preg_replace('/[^0-9]/', '', $cpf);
        $sql_paciente = "
            SELECT ID, NOME
            FROM LAB_PACIENTES
            WHERE CPF LIKE '%$cpf_limpo%'
            ORDER BY ID DESC
            FIRST 1
        ";
        $result_pac = ibase_query($conn, $sql_paciente);
        if ($pac_row = ibase_fetch_assoc($result_pac)) {
            $paciente_id = $pac_row['ID'];
        } else {
            http_response_code(404);
            echo json_encode([
                'error' => 'Not Found',
                'message' => 'Paciente não encontrado com o CPF informado'
            ]);
            exit;
        }
    }

    // Construir filtros
    $where_clauses = ["ag.PACIENTE_ID = $paciente_id"];

    if (!empty($status)) {
        $status_upper = strtoupper($status);
        $where_clauses[] = "ag.STATUS = '$status_upper'";
    }

    if (!empty($data_inicio)) {
        $where_clauses[] = "ag.DATA_AGENDAMENTO >= '$data_inicio'";
    }

    if (!empty($data_fim)) {
        $where_clauses[] = "ag.DATA_AGENDAMENTO <= '$data_fim'";
    }

    $where_sql = implode(' AND ', $where_clauses);

    // SQL principal
    $sql = "
        SELECT FIRST $limite
            ag.ID,
            ag.NUMERO_AGENDAMENTO,
            ag.DATA_AGENDAMENTO,
            ag.HORA_AGENDAMENTO,
            ag.STATUS,
            ag.TIPO_CONSULTA,
            ag.TIPO_AGENDAMENTO,
            ag.OBSERVACOES,
            ag.CONFIRMADO,
            ag.ORDEM_CHEGADA,
            ag.HORA_CHEGADA,
            ag.DATA_CRIACAO,
            ag.CRIADO_POR,
            -- Dados da agenda
            a.ID as AGENDA_ID,
            a.SALA,
            a.TELEFONE,
            a.TIPO as AGENDA_TIPO,
            -- Dados da unidade
            u.NOME_UNIDADE,
            u.ENDERECO as UNIDADE_ENDERECO,
            -- Dados do médico (se consulta)
            m.NOME as MEDICO_NOME,
            m.CRM,
            -- Dados da especialidade (se consulta)
            e.NOME as ESPECIALIDADE_NOME,
            -- Dados do procedimento (se procedimento)
            ge.NOME as PROCEDIMENTO_NOME,
            -- Dados do convênio
            c.CONVENIO as NOME_CONVENIO,
            -- Dados do paciente
            p.PACIENTE as PACIENTE_NOME,
            p.CPF as PACIENTE_CPF,
            p.FONE1 as PACIENTE_TELEFONE
        FROM AGENDAMENTOS ag
        LEFT JOIN AGENDAS a ON a.ID = ag.AGENDA_ID
        LEFT JOIN LAB_CIDADES u ON u.ID = a.UNIDADE_ID
        LEFT JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
        LEFT JOIN LAB_MEDICOS_ESPECIALIDADES me ON me.MEDICO_ID = m.ID AND me.ESPECIALIDADE_ID = (
            SELECT FIRST 1 esp.ESPECIALIDADE_ID
            FROM LAB_MEDICOS_ESPECIALIDADES esp
            WHERE esp.MEDICO_ID = m.ID
        )
        LEFT JOIN ESPECIALIDADES e ON e.ID = me.ESPECIALIDADE_ID
        LEFT JOIN GRUPO_EXAMES ge ON ge.ID = a.PROCEDIMENTO_ID
        LEFT JOIN LAB_CONVENIOS c ON c.IDCONVENIO = ag.CONVENIO_ID
        LEFT JOIN LAB_PACIENTES p ON p.IDPACIENTE = ag.PACIENTE_ID
        WHERE $where_sql
        ORDER BY ag.DATA_AGENDAMENTO DESC, ag.HORA_AGENDAMENTO DESC
    ";

    $result = ibase_query($conn, $sql);

    if (!$result) {
        throw new Exception('Erro ao consultar agendamentos');
    }

    $agendamentos = [];

    while ($row = ibase_fetch_assoc($result)) {
        $agendamento_id = (int)$row['ID'];

        // Buscar exames do agendamento se for procedimento
        $exames = [];
        if ($row['AGENDA_TIPO'] === 'procedimento') {
            $sql_exames = "
                SELECT
                    ae.EXAME_ID,
                    e.NOME as EXAME_NOME,
                    ae.QUANTIDADE
                FROM AGENDAMENTO_EXAMES ae
                LEFT JOIN LAB_EXAMES e ON e.ID = ae.EXAME_ID
                WHERE ae.AGENDAMENTO_ID = $agendamento_id
                ORDER BY e.NOME
            ";

            $result_exames = ibase_query($conn, $sql_exames);
            while ($exame_row = ibase_fetch_assoc($result_exames)) {
                $exames[] = [
                    'id' => (int)$exame_row['EXAME_ID'],
                    'nome' => mb_convert_encoding(trim($exame_row['EXAME_NOME']), 'UTF-8', 'Windows-1252'),
                    'quantidade' => (int)$exame_row['QUANTIDADE']
                ];
            }
        }

        // Verificar se tem ordem de serviço
        $tem_os = false;
        $numero_os = null;
        $sql_os = "
            SELECT NUMERO_OS
            FROM ORDEM_SERVICOS
            WHERE AGENDAMENTO_ID = $agendamento_id
            ORDER BY ID DESC
            FIRST 1
        ";
        $result_os = ibase_query($conn, $sql_os);
        if ($os_row = ibase_fetch_assoc($result_os)) {
            $tem_os = true;
            $numero_os = $os_row['NUMERO_OS'];
        }

        // Calcular possibilidades de ação
        $pode_cancelar = in_array($row['STATUS'], ['AGENDADO', 'CONFIRMADO']) &&
                        strtotime($row['DATA_AGENDAMENTO']) > time();

        $pode_reagendar = in_array($row['STATUS'], ['AGENDADO', 'CONFIRMADO', 'CANCELADO']) &&
                         $row['STATUS'] !== 'ATENDIDO';

        $pode_confirmar = $row['STATUS'] === 'AGENDADO' &&
                         strtotime($row['DATA_AGENDAMENTO']) >= time();

        $agendamentos[] = [
            'id' => $agendamento_id,
            'numero' => (int)$row['NUMERO_AGENDAMENTO'],
            'data' => $row['DATA_AGENDAMENTO'],
            'horario' => $row['HORA_AGENDAMENTO'],
            'status' => $row['STATUS'],
            'tipo_consulta' => $row['TIPO_CONSULTA'],
            'tipo_agendamento' => $row['TIPO_AGENDAMENTO'],
            'observacoes' => mb_convert_encoding(trim($row['OBSERVACOES'] ?? ''), 'UTF-8', 'Windows-1252'),
            'confirmado' => (bool)$row['CONFIRMADO'],
            'ordem_chegada' => $row['ORDEM_CHEGADA'] ? (int)$row['ORDEM_CHEGADA'] : null,
            'hora_chegada' => $row['HORA_CHEGADA'],
            'data_criacao' => $row['DATA_CRIACAO'],
            'usuario_criacao' => $row['CRIADO_POR'],
            'agenda' => [
                'id' => (int)$row['AGENDA_ID'],
                'tipo' => $row['AGENDA_TIPO'],
                'sala' => trim($row['SALA'] ?? ''),
                'telefone' => trim($row['TELEFONE'] ?? '')
            ],
            'unidade' => [
                'nome' => mb_convert_encoding(trim($row['NOME_UNIDADE'] ?? ''), 'UTF-8', 'Windows-1252'),
                'endereco' => mb_convert_encoding(trim($row['UNIDADE_ENDERECO'] ?? ''), 'UTF-8', 'Windows-1252')
            ],
            'medico' => [
                'nome' => mb_convert_encoding(trim($row['MEDICO_NOME']), 'UTF-8', 'Windows-1252'),
                'crm' => trim($row['CRM'])
            ],
            'especialidade' => [
                'nome' => mb_convert_encoding(trim($row['ESPECIALIDADE_NOME']), 'UTF-8', 'Windows-1252')
            ],
            'procedimento' => [
                'nome' => mb_convert_encoding(trim($row['PROCEDIMENTO_NOME']), 'UTF-8', 'Windows-1252')
            ],
            'convenio' => [
                'nome' => mb_convert_encoding(trim($row['NOME_CONVENIO']), 'UTF-8', 'Windows-1252')
            ],
            'exames' => $exames,
            'ordem_servico' => [
                'tem_os' => $tem_os,
                'numero' => $numero_os
            ],
            'acoes_permitidas' => [
                'pode_cancelar' => $pode_cancelar,
                'pode_reagendar' => $pode_reagendar,
                'pode_confirmar' => $pode_confirmar
            ]
        ];
    }

    // Buscar dados do paciente
    $sql_paciente = "
        SELECT
            ID,
            NOME,
            CPF,
            DATA_NASCIMENTO,
            TELEFONE,
            EMAIL
        FROM LAB_PACIENTES
        WHERE ID = $paciente_id
    ";

    $paciente_info = null;
    $result_pac_info = ibase_query($conn, $sql_paciente);
    if ($pac_info_row = ibase_fetch_assoc($result_pac_info)) {
        $paciente_info = [
            'id' => (int)$pac_info_row['ID'],
            'nome' => mb_convert_encoding(trim($pac_info_row['NOME']), 'UTF-8', 'Windows-1252'),
            'cpf' => trim($pac_info_row['CPF']),
            'data_nascimento' => $pac_info_row['DATA_NASCIMENTO'],
            'telefone' => trim($pac_info_row['TELEFONE']),
            'email' => trim($pac_info_row['EMAIL'])
        ];
    }

    // Resposta
    $response = [
        'status' => 'sucesso',
        'paciente' => $paciente_info,
        'total_agendamentos' => count($agendamentos),
        'filtros_aplicados' => [
            'paciente_id' => (int)$paciente_id,
            'status' => $status ?: null,
            'data_inicio' => $data_inicio ?: null,
            'data_fim' => $data_fim ?: null,
            'limite' => (int)$limite
        ],
        'agendamentos' => $agendamentos
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

    // Commit da transação
    ibase_commit($conn);

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn)) {
        ibase_rollback($conn);
    }

    error_log("Erro ao consultar agendamentos do paciente: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'Erro ao consultar agendamentos do paciente'
    ]);
}
?>