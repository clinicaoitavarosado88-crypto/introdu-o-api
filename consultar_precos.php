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

    $convenio_id = (int)($_GET['convenio_id'] ?? 0);
    $exame_id = (int)($_GET['exame_id'] ?? 0);
    $procedimento_id = (int)($_GET['procedimento_id'] ?? 0);
    $busca = trim($_GET['busca'] ?? '');

    if (empty($convenio_id)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Bad Request',
            'message' => 'convenio_id é obrigatório'
        ]);
        exit;
    }

    $precos = [];

    // ============================================================================
    // CONSULTAR PREÇOS DE EXAMES POR CONVÊNIO
    // ============================================================================

    $where_clauses = ["cit.IDCONVENIO = $convenio_id", "e.AI = 1"];

    // Filtrar APENAS convênios particulares (que aceitam pagamento particular)
    $where_clauses[] = "(conv.SOCIO = 'S' OR conv.PIX = 'S' OR conv.E_CARTAO = 'S' OR conv.CARTAO_BENEFICIO = 'S')";

    if (!empty($exame_id)) {
        $where_clauses[] = "e.IDEXAME = $exame_id";
    }

    if (!empty($procedimento_id)) {
        $where_clauses[] = "e.IDUNIDADE = $procedimento_id";
    }

    if (!empty($busca)) {
        $busca_upper = strtoupper($busca);
        $busca_escaped = str_replace("'", "''", $busca_upper);
        $where_clauses[] = "UPPER(e.EXAME) LIKE '%$busca_escaped%'";
    }

    $where_sql = implode(' AND ', $where_clauses);

    $sql = "
        SELECT
            e.IDEXAME,
            e.EXAME as NOME_EXAME,
            e.IDUNIDADE,
            ge.NOME as NOME_GRUPO,
            esp.NOME as NOME_ESPECIALIDADE,
            cit.PR_UNIT as VALOR_UNITARIO,
            cit.AI as ATIVO_CONVENIO,
            conv.CONVENIO as NOME_CONVENIO,
            conv.IDCONVENIO,
            conv.IDCIDADE as CIDADE_ID,
            conv.SOCIO,
            conv.PIX,
            conv.E_CARTAO,
            conv.CARTAO_BENEFICIO,
            cid.NOME_UNIDADE as NOME_CIDADE,
            CASE
                WHEN e.IDPERFIL > 0 THEN 'PERFIL'
                ELSE 'EXAME'
            END as TIPO_ITEM
        FROM LAB_EXAMES e
        JOIN LAB_CONVENIOSTAB_IT cit ON cit.IDEXAME = e.IDEXAME
        LEFT JOIN LAB_CONVENIOS conv ON conv.IDCONVENIO = cit.IDCONVENIO
        LEFT JOIN LAB_CIDADES cid ON cid.ID = conv.IDCIDADE
        LEFT JOIN LAB_UNIDADES u ON u.ID = e.IDUNIDADE
        LEFT JOIN GRUPO_EXAMES ge ON ge.ID_OUTRA_TABELA = e.IDUNIDADE
        LEFT JOIN ESPECIALIDADES esp ON esp.ID_OUTRA_TABELA = e.IDUNIDADE
        WHERE $where_sql
        ORDER BY e.EXAME
    ";

    $result = ibase_query($conn, $sql);

    if (!$result) {
        throw new Exception('Erro ao consultar banco de dados: ' . ibase_errmsg());
    }

    while ($row = ibase_fetch_assoc($result)) {
        $valor_unitario = (float)($row['VALOR_UNITARIO'] ?? 0);
        $ativo_convenio = (bool)($row['ATIVO_CONVENIO'] ?? false);

        $precos[] = [
            'id' => (int)$row['IDEXAME'],
            'exame_id' => (int)$row['IDEXAME'],
            'exame_nome' => mb_convert_encoding(trim($row['NOME_EXAME']), 'UTF-8', 'Windows-1252'),
            'grupo_exame_id' => (int)($row['IDUNIDADE'] ?? 0),
            'grupo_exame_nome' => mb_convert_encoding(trim($row['NOME_GRUPO'] ?? ''), 'UTF-8', 'Windows-1252'),
            'especialidade_id' => (int)($row['IDESPECIALIDADE'] ?? 0),
            'especialidade_nome' => mb_convert_encoding(trim($row['NOME_ESPECIALIDADE'] ?? ''), 'UTF-8', 'Windows-1252'),
            'convenio_id' => (int)$row['IDCONVENIO'],
            'convenio_nome' => mb_convert_encoding(trim($row['NOME_CONVENIO']), 'UTF-8', 'Windows-1252'),
            'convenio_cidade_id' => (int)($row['CIDADE_ID'] ?? 0),
            'convenio_cidade_nome' => mb_convert_encoding(trim($row['NOME_CIDADE'] ?? ''), 'UTF-8', 'Windows-1252'),
            'valor_unitario' => $valor_unitario,
            'ativo_convenio' => $ativo_convenio,
            'pode_agendar' => $valor_unitario > 0 && $ativo_convenio,
        ];
    }

    ibase_free_result($result);

    // Resposta
    $response = [
        'status' => 'sucesso',
        'total_precos' => count($precos),
        'filtros_aplicados' => [
            'convenio_id' => $convenio_id ?: null,
            'exame_id' => $exame_id ?: null,
            'grupo_exames' => $procedimento_id ?: null,
            'busca' => $busca ?: null
        ],
        'precos' => $precos
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Erro ao consultar preços: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'Erro ao consultar preços: ' . $e->getMessage()
    ]);
}
?>