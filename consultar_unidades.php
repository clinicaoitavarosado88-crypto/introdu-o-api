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

    $unidade_id = $_GET['unidade_id'] ?? '';
    $ativa_apenas = $_GET['ativa_apenas'] ?? 'true';

    $unidades = [];

    // Buscar informações das unidades
    $where_clause = '';
    if (!empty($unidade_id)) {
        $where_clause = " WHERE u.ID = $unidade_id";
    } elseif ($ativa_apenas === 'true') {
        $where_clause = " WHERE u.AGENDA_ATI = 1";
    }

    $sql = "
        SELECT
            u.ID,
            u.NOME_UNIDADE,
            u.ENDERECO,
            u.CNPJ,
            u.AGENDA_ATI
        FROM LAB_CIDADES u
        $where_clause
        ORDER BY u.NOME_UNIDADE
    ";

    $result = ibase_query($conn, $sql);

    if (!$result) {
        throw new Exception('Erro ao consultar unidades');
    }

    while ($row = ibase_fetch_assoc($result)) {
        $unidade_id_atual = (int)$row['ID'];

        // Buscar especialidades disponíveis na unidade
        $sql_especialidades = "
            SELECT DISTINCT
                e.ID,
                e.NOME
            FROM AGENDAS a
            JOIN LAB_MEDICOS_ESPECIALIDADES me ON me.MEDICO_ID = a.MEDICO_ID
            JOIN ESPECIALIDADES e ON e.ID = me.ESPECIALIDADE_ID
            WHERE a.UNIDADE_ID = $unidade_id_atual
            AND a.TIPO = 'consulta'
            ORDER BY e.NOME
        ";

        $especialidades = [];
        $result_esp = ibase_query($conn, $sql_especialidades);
        while ($esp_row = ibase_fetch_assoc($result_esp)) {
            $especialidades[] = [
                'id' => (int)$esp_row['ID'],
                'nome' => mb_convert_encoding(trim($esp_row['NOME']), 'UTF-8', 'Windows-1252')
            ];
        }

        // Buscar procedimentos disponíveis na unidade
        $sql_procedimentos = "
            SELECT DISTINCT
                ge.ID,
                ge.NOME
            FROM AGENDAS a
            JOIN GRUPO_EXAMES ge ON ge.ID = a.PROCEDIMENTO_ID
            WHERE a.UNIDADE_ID = $unidade_id_atual
            AND a.TIPO = 'procedimento'
            ORDER BY ge.NOME
        ";

        $procedimentos = [];
        $result_proc = ibase_query($conn, $sql_procedimentos);
        while ($proc_row = ibase_fetch_assoc($result_proc)) {
            $procedimentos[] = [
                'id' => (int)$proc_row['ID'],
                'nome' => mb_convert_encoding(trim($proc_row['NOME']), 'UTF-8', 'Windows-1252')
            ];
        }

        // Buscar médicos na unidade
        $sql_medicos = "
            SELECT DISTINCT
                m.ID,
                m.NOME,
                m.CRM
            FROM AGENDAS a
            JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
            WHERE a.UNIDADE_ID = $unidade_id_atual
            ORDER BY m.NOME
        ";

        $medicos = [];
        $result_med = ibase_query($conn, $sql_medicos);
        while ($med_row = ibase_fetch_assoc($result_med)) {
            $medicos[] = [
                'id' => (int)$med_row['ID'],
                'nome' => mb_convert_encoding(trim($med_row['NOME']), 'UTF-8', 'Windows-1252'),
                'crm' => trim($med_row['CRM'])
            ];
        }

        // Buscar horários de funcionamento por dia da semana
        $sql_horarios = "
            SELECT DISTINCT
                ah.DIA_SEMANA,
                ah.HORA_INICIO,
                ah.HORA_FIM
            FROM AGENDA_HORARIOS ah
            JOIN AGENDAS a ON a.ID = ah.AGENDA_ID
            WHERE a.UNIDADE_ID = $unidade_id_atual
            ORDER BY
                CASE ah.DIA_SEMANA
                    WHEN 'SEGUNDA' THEN 1
                    WHEN 'TERCA' THEN 2
                    WHEN 'QUARTA' THEN 3
                    WHEN 'QUINTA' THEN 4
                    WHEN 'SEXTA' THEN 5
                    WHEN 'SABADO' THEN 6
                    WHEN 'DOMINGO' THEN 7
                END,
                ah.HORA_INICIO
        ";

        $horarios_funcionamento = [];
        $result_horarios = ibase_query($conn, $sql_horarios);
        while ($horario_row = ibase_fetch_assoc($result_horarios)) {
            $dia = trim($horario_row['DIA_SEMANA']);
            if (!isset($horarios_funcionamento[$dia])) {
                $horarios_funcionamento[$dia] = [];
            }
            $horarios_funcionamento[$dia][] = [
                'inicio' => trim($horario_row['HORA_INICIO']),
                'fim' => trim($horario_row['HORA_FIM'])
            ];
        }

        $unidades[] = [
            'id' => $unidade_id_atual,
            'nome' => mb_convert_encoding(trim($row['NOME_UNIDADE']), 'UTF-8', 'Windows-1252'),
            'endereco' => mb_convert_encoding(trim($row['ENDERECO'] ?? ''), 'UTF-8', 'Windows-1252'),
            'cnpj' => trim($row['CNPJ'] ?? ''),
            'ativo' => (bool)($row['AGENDA_ATI'] ?? 0),
            'horario_funcionamento' => [
                'por_dia' => $horarios_funcionamento
            ],
            'servicos' => [
                'especialidades' => $especialidades,
                'procedimentos' => $procedimentos,
                'total_especialidades' => count($especialidades),
                'total_procedimentos' => count($procedimentos)
            ],
            'medicos' => [
                'lista' => $medicos,
                'total' => count($medicos)
            ]
        ];
    }

    // Resposta
    if (!empty($unidade_id)) {
        if (count($unidades) === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Not Found', 'message' => 'Unidade não encontrada']);
            exit;
        }
        $response = [
            'status' => 'sucesso',
            'unidade' => $unidades[0]
        ];
    } else {
        $response = [
            'status' => 'sucesso',
            'total_unidades' => count($unidades),
            'filtros' => [
                'ativa_apenas' => $ativa_apenas === 'true'
            ],
            'unidades' => $unidades
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

    // Commit da transação
    ibase_commit($conn);

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn)) {
        ibase_rollback($conn);
    }

    error_log("Erro ao consultar unidades: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'Erro ao consultar informações das unidades'
    ]);
}
?>