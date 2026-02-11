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

    $exame_id = $_GET['exame_id'] ?? '';
    $procedimento_id = $_GET['procedimento_id'] ?? '';
    $busca = $_GET['busca'] ?? '';

    $preparos = [];

    // Buscar preparos específicos por exame
    if (!empty($exame_id)) {
        $sql = "
            SELECT
                p.ID,
                p.EXAME_ID,
                e.NOME as EXAME_NOME,
                p.TITULO_PREPARO,
                p.INSTRUCOES,
                p.TEMPO_JEJUM_HORAS,
                p.MEDICAMENTOS_SUSPENSOS,
                p.ORIENTACOES_ESPECIAIS,
                p.ANEXOS_IDS,
                p.ATIVO,
                p.DATA_ATUALIZACAO
            FROM EXAME_PREPAROS p
            LEFT JOIN LAB_EXAMES e ON e.ID = p.EXAME_ID
            WHERE p.EXAME_ID = $exame_id
            AND p.ATIVO = 1
            ORDER BY p.TITULO_PREPARO
        ";
    }
    // Buscar preparos por procedimento (grupo de exames)
    elseif (!empty($procedimento_id)) {
        $sql = "
            SELECT
                p.ID,
                p.EXAME_ID,
                e.NOME as EXAME_NOME,
                p.TITULO_PREPARO,
                p.INSTRUCOES,
                p.TEMPO_JEJUM_HORAS,
                p.MEDICAMENTOS_SUSPENSOS,
                p.ORIENTACOES_ESPECIAIS,
                p.ANEXOS_IDS,
                p.ATIVO,
                p.DATA_ATUALIZACAO
            FROM EXAME_PREPAROS p
            LEFT JOIN LAB_EXAMES e ON e.ID = p.EXAME_ID
            LEFT JOIN EXAMES_GRUPO eg ON eg.EXAME_ID = e.ID
            WHERE eg.GRUPO_ID = $procedimento_id
            AND p.ATIVO = 1
            ORDER BY p.TITULO_PREPARO
        ";
    }
    // Buscar preparos por termo de busca
    elseif (!empty($busca)) {
        $busca_upper = strtoupper($busca);
        $sql = "
            SELECT
                p.ID,
                p.EXAME_ID,
                e.NOME as EXAME_NOME,
                p.TITULO_PREPARO,
                p.INSTRUCOES,
                p.TEMPO_JEJUM_HORAS,
                p.MEDICAMENTOS_SUSPENSOS,
                p.ORIENTACOES_ESPECIAIS,
                p.ANEXOS_IDS,
                p.ATIVO,
                p.DATA_ATUALIZACAO
            FROM EXAME_PREPAROS p
            LEFT JOIN LAB_EXAMES e ON e.ID = p.EXAME_ID
            WHERE (
                UPPER(p.TITULO_PREPARO) LIKE '%$busca_upper%'
                OR UPPER(e.NOME) LIKE '%$busca_upper%'
                OR UPPER(p.INSTRUCOES) LIKE '%$busca_upper%'
            )
            AND p.ATIVO = 1
            ORDER BY p.TITULO_PREPARO
        ";
    }
    // Listar todos os preparos
    else {
        $sql = "
            SELECT
                p.ID,
                p.EXAME_ID,
                e.NOME as EXAME_NOME,
                p.TITULO_PREPARO,
                p.INSTRUCOES,
                p.TEMPO_JEJUM_HORAS,
                p.MEDICAMENTOS_SUSPENSOS,
                p.ORIENTACOES_ESPECIAIS,
                p.ANEXOS_IDS,
                p.ATIVO,
                p.DATA_ATUALIZACAO
            FROM EXAME_PREPAROS p
            LEFT JOIN LAB_EXAMES e ON e.ID = p.EXAME_ID
            WHERE p.ATIVO = 1
            ORDER BY e.NOME, p.TITULO_PREPARO
        ";
    }

    $result = ibase_query($conn, $sql);

    while ($row = ibase_fetch_assoc($result)) {
        $preparo_id = (int)$row['ID'];

        // Buscar anexos se existirem
        $anexos = [];
        $anexos_ids = trim($row['ANEXOS_IDS']);
        if (!empty($anexos_ids)) {
            $anexos_ids_array = explode(',', $anexos_ids);
            foreach ($anexos_ids_array as $anexo_id) {
                $anexo_id = trim($anexo_id);
                if (is_numeric($anexo_id)) {
                    $sql_anexo = "
                        SELECT
                            ID,
                            NOME_ARQUIVO,
                            TIPO_ARQUIVO,
                            TAMANHO_BYTES,
                            URL_DOWNLOAD
                        FROM ANEXOS_PREPAROS
                        WHERE ID = $anexo_id
                    ";
                    $result_anexo = ibase_query($conn, $sql_anexo);
                    if ($anexo_row = ibase_fetch_assoc($result_anexo)) {
                        $anexos[] = [
                            'id' => (int)$anexo_row['ID'],
                            'nome' => mb_convert_encoding(trim($anexo_row['NOME_ARQUIVO']), 'UTF-8', 'Windows-1252'),
                            'tipo' => trim($anexo_row['TIPO_ARQUIVO']),
                            'tamanho_bytes' => (int)$anexo_row['TAMANHO_BYTES'],
                            'url_download' => trim($anexo_row['URL_DOWNLOAD'])
                        ];
                    }
                }
            }
        }

        // Processar instruções em array se for JSON
        $instrucoes = trim($row['INSTRUCOES']);
        $instrucoes_array = [];
        if (!empty($instrucoes)) {
            $instrucoes_decoded = json_decode($instrucoes, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($instrucoes_decoded)) {
                $instrucoes_array = $instrucoes_decoded;
            } else {
                // Se não for JSON, tratar como texto simples
                $instrucoes_array = [mb_convert_encoding($instrucoes, 'UTF-8', 'Windows-1252')];
            }
        }

        // Processar medicamentos suspensos
        $medicamentos = [];
        $medicamentos_texto = trim($row['MEDICAMENTOS_SUSPENSOS']);
        if (!empty($medicamentos_texto)) {
            $medicamentos_decoded = json_decode($medicamentos_texto, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($medicamentos_decoded)) {
                $medicamentos = $medicamentos_decoded;
            } else {
                $medicamentos = [mb_convert_encoding($medicamentos_texto, 'UTF-8', 'Windows-1252')];
            }
        }

        $preparos[] = [
            'id' => $preparo_id,
            'exame_id' => (int)$row['EXAME_ID'],
            'exame_nome' => mb_convert_encoding(trim($row['EXAME_NOME']), 'UTF-8', 'Windows-1252'),
            'titulo' => mb_convert_encoding(trim($row['TITULO_PREPARO']), 'UTF-8', 'Windows-1252'),
            'instrucoes' => $instrucoes_array,
            'tempo_jejum_horas' => $row['TEMPO_JEJUM_HORAS'] ? (int)$row['TEMPO_JEJUM_HORAS'] : null,
            'medicamentos_suspensos' => $medicamentos,
            'orientacoes_especiais' => mb_convert_encoding(trim($row['ORIENTACOES_ESPECIAIS']), 'UTF-8', 'Windows-1252'),
            'anexos' => $anexos,
            'total_anexos' => count($anexos),
            'data_atualizacao' => $row['DATA_ATUALIZACAO']
        ];
    }

    // Resposta
    $response = [
        'status' => 'sucesso',
        'total_preparos' => count($preparos),
        'filtros_aplicados' => [
            'exame_id' => $exame_id ?: null,
            'procedimento_id' => $procedimento_id ?: null,
            'busca' => $busca ?: null
        ],
        'preparos' => $preparos
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Erro ao consultar preparos: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'Erro ao consultar preparos de exames'
    ]);
}
?>