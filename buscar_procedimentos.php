<?php
/**
 * API: Buscar Procedimentos/Exames
 *
 * Endpoint inteligente que entende sinônimos, abreviações e variações de escrita
 *
 * Exemplos de uso:
 * - /buscar_procedimentos.php?busca=RM
 * - /buscar_procedimentos.php?busca=ressonancia
 * - /buscar_procedimentos.php?busca=ultrassom
 * - /buscar_procedimentos.php?busca=raio
 *
 * @return JSON com lista de procedimentos encontrados
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'includes/connection.php';
require_once 'includes/auth_middleware.php';

// Verificar autenticação
$auth = verify_api_token();
if (!$auth['valid']) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'message' => $auth['message']
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// ========================================
// MAPEAMENTO INTELIGENTE DE TERMOS
// ========================================

/**
 * Mapeia termos populares/abreviações para nomes formais
 */
function mapearTermoProcedimento($termo) {
    $termo = strtolower(trim($termo));
    $termo = removerAcentos($termo);

    // Mapeamento de sinônimos e abreviações
    $mapeamento = [
        // Ressonância Magnética
        'rm' => 'Ressonância Magnética',
        'ressonancia' => 'Ressonância Magnética',
        'ressonancia magnetica' => 'Ressonância Magnética',
        'ressona' => 'Ressonância Magnética',

        // Ultrassonografia
        'us' => 'Ultrassonografia',
        'ultrassom' => 'Ultrassonografia',
        'ultrasom' => 'Ultrassonografia',
        'ultra' => 'Ultrassonografia',
        'ecografia' => 'Ultrassonografia',

        // Tomografia
        'tc' => 'Tomografia',
        'tomo' => 'Tomografia',
        'tomografia' => 'Tomografia',

        // Raio-X
        'rx' => 'Raio-X',
        'raio-x' => 'Raio-X',
        'raio x' => 'Raio-X',
        'radiografia' => 'Raio-X',
        'radio' => 'Raio-X',

        // Eletrocardiograma
        'ecg' => 'Eletrocardiograma',
        'eletro' => 'Eletrocardiograma',
        'eletrocardiograma' => 'Eletrocardiograma',

        // Ecocardiograma
        'eco' => 'Ecocardiograma',
        'ecocardiograma' => 'Ecocardiograma',

        // Mamografia
        'mamo' => 'Mamografia',
        'mamografia' => 'Mamografia',

        // Endoscopia
        'endo' => 'Endoscopia',
        'endoscopia' => 'Endoscopia',

        // Colonoscopia
        'colono' => 'Colonoscopia',
        'colonoscopia' => 'Colonoscopia',

        // Doppler
        'doppler' => 'Doppler',

        // Holter
        'holter' => 'Holter',

        // Densitometria
        'densito' => 'Densitometria',
        'densitometria' => 'Densitometria',

        // Espirometria
        'espiro' => 'Espirometria',
        'espirometria' => 'Espirometria',

        // Eletroencefalograma
        'eeg' => 'Eletroencefalograma',
        'eletroencefalograma' => 'Eletroencefalograma'
    ];

    // Verificar mapeamento exato
    if (isset($mapeamento[$termo])) {
        return $mapeamento[$termo];
    }

    // Buscar correspondência parcial
    foreach ($mapeamento as $chave => $nomeFormal) {
        if (strpos($termo, $chave) !== false || strpos($chave, $termo) !== false) {
            return $nomeFormal;
        }
    }

    // Se não encontrou mapeamento, retornar o termo original capitalizado
    return ucwords($termo);
}

/**
 * Remove acentos para comparação
 */
function removerAcentos($string) {
    $acentos = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
        'é' => 'e', 'ê' => 'e',
        'í' => 'i',
        'ó' => 'o', 'õ' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ü' => 'u',
        'ç' => 'c'
    ];
    return strtr($string, $acentos);
}

/**
 * Corrige caracteres corrompidos do Firebird
 */
function corrigirCaracteres($texto) {
    if ($texto === null) return null;

    $substituicoes = [
        'º' => 'º',
        'ª' => 'ª',
        'Ã§' => 'ç',
        'Ã£' => 'ã',
        'Ã¡' => 'á',
        'Ã©' => 'é',
        'Ã­' => 'í',
        'Ã³' => 'ó',
        'Ãº' => 'ú',
        'Ã' => 'Ç',
        'Ã‚' => 'Â',
        'Ãª' => 'ê',
        'Ã´' => 'ô'
    ];

    return strtr($texto, $substituicoes);
}

// ========================================
// PROCESSAR REQUISIÇÃO
// ========================================

try {
    // Conexão Firebird
    $host = 'localhost:/opt/banco/oitava_db.fdb';
    $username = 'SYSDBA';
    $password = 'masterkey';
    $conn = ibase_connect($host, $username, $password);

    if (!$conn) {
        throw new Exception('Erro ao conectar ao banco: ' . ibase_errmsg());
    }

    // Pegar termo de busca
    $termoBusca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

    // Mapear termo para nome formal
    $termoMapeado = mapearTermoProcedimento($termoBusca);

    // Buscar procedimentos na tabela GRUPO_EXAMES
    $sql = "SELECT DISTINCT
                ge.ID as id,
                ge.NOME as nome,
                COUNT(a.ID) as total_agendas
            FROM GRUPO_EXAMES ge
            LEFT JOIN AGENDAS a ON a.PROCEDIMENTO_ID = ge.ID AND a.TIPO = 'procedimento'
            WHERE ge.NOME IS NOT NULL";

    // Adicionar filtro se houver busca
    if (!empty($termoBusca)) {
        // Buscar tanto pelo termo original quanto pelo mapeado
        $termoOriginal = strtoupper($termoBusca);
        $termoMapeadoUpper = strtoupper($termoMapeado);

        $sql .= " AND (
                    UPPER(ge.NOME) LIKE '%$termoOriginal%' OR
                    UPPER(ge.NOME) LIKE '%$termoMapeadoUpper%'
                  )";
    }

    $sql .= " GROUP BY ge.ID, ge.NOME
              ORDER BY ge.NOME";

    $result = ibase_query($conn, $sql);

    if (!$result) {
        throw new Exception('Erro ao buscar procedimentos: ' . ibase_errmsg());
    }

    $procedimentos = [];
    while ($row = ibase_fetch_assoc($result)) {
        $nome = corrigirCaracteres(trim($row['NOME']));

        $procedimentos[] = [
            'id' => (string)$row['ID'],
            'text' => $nome,
            'nome' => $nome,
            'total_agendas' => (int)$row['TOTAL_AGENDAS']
        ];
    }

    ibase_free_result($result);
    ibase_commit($conn);
    ibase_close($conn);

    // Estrutura de resposta compatível com Select2
    $response = [
        'results' => $procedimentos,
        'pagination' => [
            'more' => false
        ],
        'total' => count($procedimentos)
    ];

    // Se houve mapeamento, incluir na resposta
    if (!empty($termoBusca) && $termoMapeado !== ucwords($termoBusca)) {
        $response['termo_original'] = $termoBusca;
        $response['termo_mapeado'] = $termoMapeado;
        $response['mapeamento_aplicado'] = true;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
?>
