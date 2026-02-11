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

    $convenio_id = $_GET['convenio_id'] ?? '';
    $exame_id = $_GET['exame_id'] ?? '';
    $exames_ids = $_GET['exames_ids'] ?? '';
    $especialidade_id = $_GET['especialidade_id'] ?? '';
    $procedimento_id = $_GET['procedimento_id'] ?? '';
    $unidade_id = $_GET['unidade_id'] ?? '';

    if (empty($convenio_id)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'message' => 'convenio_id é obrigatório'
        ]);
        exit;
    }

    $valores = [];

    // ============================================================================
    // 1. VALORES DE EXAMES ESPECÍFICOS (para procedimentos/exames)
    // ============================================================================

    if (!empty($exame_id)) {
        // Consultar valor de um exame específico
        $sql_exame = "
            SELECT
                e.IDEXAME,
                e.EXAME as NOME_EXAME,
                e.IDUNIDADE,
                e.IDPERFIL,
                u.MODALIDADE,
                c.PR_UNIT as VALOR_UNITARIO,
                c.CH_UNIT as CH_UNITARIO,
                c.AI as ATIVO_CONVENIO
            FROM LAB_EXAMES e
            LEFT JOIN LAB_UNIDADES u ON u.ID = e.IDUNIDADE
            LEFT JOIN LAB_CONVENIOSTAB_IT c ON c.IDEXAME = e.IDEXAME AND c.IDCONVENIO = $convenio_id
            WHERE e.IDEXAME = $exame_id
            AND e.AI = 1
        ";

        $result = ibase_query($conn, $sql_exame);
        while ($row = ibase_fetch_assoc($result)) {
            $valor_unitario = (float)$row['VALOR_UNITARIO'];
            $ch_unitario = (float)$row['CH_UNITARIO'];
            $coberto_convenio = (bool)$row['ATIVO_CONVENIO'];

            $valores[] = [
                'tipo' => 'exame',
                'exame_id' => (int)$row['IDEXAME'],
                'nome' => mb_convert_encoding(trim($row['NOME_EXAME']), 'UTF-8', 'Windows-1252'),
                'unidade_id' => (int)$row['IDUNIDADE'],
                'modalidade' => trim($row['MODALIDADE']),
                'valor_unitario' => $valor_unitario,
                'ch_unitario' => $ch_unitario,
                'coberto_convenio' => $coberto_convenio,
                'pode_adicionar' => $valor_unitario > 0 && $coberto_convenio
            ];
        }
    }

    // ============================================================================
    // 2. VALORES DE MÚLTIPLOS EXAMES (lista de IDs)
    // ============================================================================

    if (!empty($exames_ids)) {
        $exames_array = explode(',', $exames_ids);
        $exames_lista = implode(',', array_filter($exames_array, 'is_numeric'));

        if ($exames_lista) {
            $sql_exames = "
                SELECT
                    e.IDEXAME,
                    e.EXAME as NOME_EXAME,
                    e.IDUNIDADE,
                    e.IDPERFIL,
                    u.MODALIDADE,
                    c.PR_UNIT as VALOR_UNITARIO,
                    c.CH_UNIT as CH_UNITARIO,
                    c.AI as ATIVO_CONVENIO
                FROM LAB_EXAMES e
                LEFT JOIN LAB_UNIDADES u ON u.ID = e.IDUNIDADE
                LEFT JOIN LAB_CONVENIOSTAB_IT c ON c.IDEXAME = e.IDEXAME AND c.IDCONVENIO = $convenio_id
                WHERE e.IDEXAME IN ($exames_lista)
                AND e.AI = 1
                ORDER BY e.EXAME
            ";

            $result = ibase_query($conn, $sql_exames);
            while ($row = ibase_fetch_assoc($result)) {
                $valor_unitario = (float)$row['VALOR_UNITARIO'];
                $ch_unitario = (float)$row['CH_UNITARIO'];
                $coberto_convenio = (bool)$row['ATIVO_CONVENIO'];

                $valores[] = [
                    'tipo' => 'exame',
                    'exame_id' => (int)$row['IDEXAME'],
                    'nome' => mb_convert_encoding(trim($row['NOME_EXAME']), 'UTF-8', 'Windows-1252'),
                    'unidade_id' => (int)$row['IDUNIDADE'],
                    'modalidade' => trim($row['MODALIDADE']),
                    'valor_unitario' => $valor_unitario,
                    'ch_unitario' => $ch_unitario,
                    'coberto_convenio' => $coberto_convenio,
                    'pode_adicionar' => $valor_unitario > 0 && $coberto_convenio
                ];
            }
        }
    }

    // ============================================================================
    // 3. VALORES DE CONSULTA POR ESPECIALIDADE
    // ============================================================================

    if (!empty($especialidade_id)) {
        // Buscar na tabela de preços de consulta
        $where_unidade = !empty($unidade_id) ? " AND tc.UNIDADE_ID = $unidade_id" : "";

        $sql_consulta = "
            SELECT
                tc.ID,
                tc.ESPECIALIDADE_ID,
                e.NOME as ESPECIALIDADE_NOME,
                tc.UNIDADE_ID,
                u.NOME_UNIDADE,
                tc.VALOR_CONSULTA,
                tc.VALOR_RETORNO,
                tc.ATIVO
            FROM TABELA_CONSULTAS tc
            LEFT JOIN ESPECIALIDADES e ON e.ID = tc.ESPECIALIDADE_ID
            LEFT JOIN LAB_CIDADES u ON u.ID = tc.UNIDADE_ID
            WHERE tc.ESPECIALIDADE_ID = $especialidade_id
            AND tc.CONVENIO_ID = $convenio_id
            AND tc.ATIVO = 1
            $where_unidade
        ";

        $result = ibase_query($conn, $sql_consulta);
        while ($row = ibase_fetch_assoc($result)) {
            $valores[] = [
                'tipo' => 'consulta',
                'especialidade_id' => (int)$row['ESPECIALIDADE_ID'],
                'especialidade_nome' => mb_convert_encoding(trim($row['ESPECIALIDADE_NOME']), 'UTF-8', 'Windows-1252'),
                'unidade_id' => (int)$row['UNIDADE_ID'],
                'unidade_nome' => mb_convert_encoding(trim($row['NOME_UNIDADE']), 'UTF-8', 'Windows-1252'),
                'valor_consulta' => (float)$row['VALOR_CONSULTA'],
                'valor_retorno' => (float)$row['VALOR_RETORNO'],
                'pode_criar_os' => (float)$row['VALOR_CONSULTA'] >= 0
            ];
        }
    }

    // ============================================================================
    // 4. VALORES DE PROCEDIMENTO (GRUPO DE EXAMES)
    // ============================================================================

    if (!empty($procedimento_id)) {
        // Buscar exames do procedimento e seus valores
        $sql_procedimento = "
            SELECT
                eg.EXAME_ID,
                e.EXAME as NOME_EXAME,
                e.IDUNIDADE,
                u.MODALIDADE,
                c.PR_UNIT as VALOR_UNITARIO,
                c.CH_UNIT as CH_UNITARIO,
                c.AI as ATIVO_CONVENIO,
                ge.NOME as PROCEDIMENTO_NOME
            FROM EXAMES_GRUPO eg
            LEFT JOIN LAB_EXAMES e ON e.IDEXAME = eg.EXAME_ID
            LEFT JOIN LAB_UNIDADES u ON u.ID = e.IDUNIDADE
            LEFT JOIN LAB_CONVENIOSTAB_IT c ON c.IDEXAME = e.IDEXAME AND c.IDCONVENIO = $convenio_id
            LEFT JOIN GRUPO_EXAMES ge ON ge.ID = eg.GRUPO_ID
            WHERE eg.GRUPO_ID = $procedimento_id
            AND e.AI = 1
            ORDER BY e.EXAME
        ";

        $exames_procedimento = [];
        $valor_total_procedimento = 0;

        $result = ibase_query($conn, $sql_procedimento);
        while ($row = ibase_fetch_assoc($result)) {
            $valor_unitario = (float)$row['VALOR_UNITARIO'];
            $ch_unitario = (float)$row['CH_UNITARIO'];
            $coberto_convenio = (bool)$row['ATIVO_CONVENIO'];

            if ($valor_unitario > 0 && $coberto_convenio) {
                $valor_total_procedimento += $valor_unitario;
            }

            $exames_procedimento[] = [
                'exame_id' => (int)$row['EXAME_ID'],
                'nome' => mb_convert_encoding(trim($row['NOME_EXAME']), 'UTF-8', 'Windows-1252'),
                'unidade_id' => (int)$row['IDUNIDADE'],
                'modalidade' => trim($row['MODALIDADE']),
                'valor_unitario' => $valor_unitario,
                'ch_unitario' => $ch_unitario,
                'coberto_convenio' => $coberto_convenio
            ];
        }

        if (!empty($exames_procedimento)) {
            $valores[] = [
                'tipo' => 'procedimento',
                'procedimento_id' => (int)$procedimento_id,
                'procedimento_nome' => mb_convert_encoding(trim($row['PROCEDIMENTO_NOME'] ?? ''), 'UTF-8', 'Windows-1252'),
                'valor_total' => $valor_total_procedimento,
                'total_exames' => count($exames_procedimento),
                'exames' => $exames_procedimento,
                'pode_criar_os' => $valor_total_procedimento > 0
            ];
        }
    }

    // ============================================================================
    // 5. INFORMAÇÕES DO CONVÊNIO
    // ============================================================================

    $sql_convenio = "
        SELECT
            ID,
            NOME_CONVENIO,
            CODIGO_ANS,
            ATIVO
        FROM LAB_CONVENIOS
        WHERE ID = $convenio_id
    ";

    $convenio_info = null;
    $result_convenio = ibase_query($conn, $sql_convenio);
    if ($conv_row = ibase_fetch_assoc($result_convenio)) {
        $convenio_info = [
            'id' => (int)$conv_row['ID'],
            'nome' => mb_convert_encoding(trim($conv_row['NOME_CONVENIO']), 'UTF-8', 'Windows-1252'),
            'codigo_ans' => trim($conv_row['CODIGO_ANS']),
            'ativo' => (bool)$conv_row['ATIVO']
        ];
    }

    // ============================================================================
    // 6. RESUMO DOS VALORES
    // ============================================================================

    $resumo = [
        'total_itens' => count($valores),
        'valor_total_geral' => 0,
        'itens_com_valor' => 0,
        'itens_sem_cobertura' => 0
    ];

    foreach ($valores as $valor) {
        if ($valor['tipo'] === 'exame') {
            if ($valor['pode_adicionar']) {
                $resumo['valor_total_geral'] += $valor['valor_unitario'];
                $resumo['itens_com_valor']++;
            } else {
                $resumo['itens_sem_cobertura']++;
            }
        } elseif ($valor['tipo'] === 'consulta') {
            if ($valor['pode_criar_os']) {
                $resumo['valor_total_geral'] += $valor['valor_consulta'];
                $resumo['itens_com_valor']++;
            }
        } elseif ($valor['tipo'] === 'procedimento') {
            if ($valor['pode_criar_os']) {
                $resumo['valor_total_geral'] += $valor['valor_total'];
                $resumo['itens_com_valor']++;
            }
        }
    }

    // Resposta
    $response = [
        'status' => 'sucesso',
        'convenio' => $convenio_info,
        'parametros_consulta' => [
            'convenio_id' => (int)$convenio_id,
            'exame_id' => $exame_id ?: null,
            'exames_ids' => $exames_ids ?: null,
            'especialidade_id' => $especialidade_id ?: null,
            'procedimento_id' => $procedimento_id ?: null,
            'unidade_id' => $unidade_id ?: null
        ],
        'resumo' => $resumo,
        'valores' => $valores
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Erro ao consultar valores da OS: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'Erro ao consultar valores para criação da OS'
    ]);
}
?>