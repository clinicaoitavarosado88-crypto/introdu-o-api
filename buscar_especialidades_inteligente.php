<?php
/**
 * API: Buscar Especialidades Médicas com Mapeamento Inteligente
 *
 * Endpoint que entende como os pacientes realmente falam
 *
 * Exemplos:
 * - "medico de coração" → Cardiologista
 * - "medico de pele" → Dermatologista
 * - "medico de diabetes" → Endocrinologista
 * - "medico de vista" → Oftalmologista
 *
 * @return JSON com especialidades encontradas
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
// MAPEAMENTO DE ESPECIALIDADES
// ========================================

/**
 * Mapeia termos que os pacientes usam para especialidades médicas formais
 */
function mapearEspecialidade($termo) {
    $termo = strtolower(trim($termo));
    $termo = removerAcentos($termo);

    // Mapeamento completo conforme linguagem do paciente
    $mapeamento = [
        // Angiologista
        'angio' => 'Angiologista',
        'angiologista' => 'Angiologista',
        'medico de veia' => 'Angiologista',
        'medico de varizes' => 'Angiologista',
        'veia' => 'Angiologista',
        'varizes' => 'Angiologista',

        // Cardiologista
        'cardio' => 'Cardiologista',
        'cardiologista' => 'Cardiologista',
        'medico do coracao' => 'Cardiologista',
        'medico de coracao' => 'Cardiologista',
        'risco cirurgico' => 'Cardiologista',
        'coracao' => 'Cardiologista',

        // Dermatologista
        'dermato' => 'Dermatologista',
        'dermatologista' => 'Dermatologista',
        'medico de pele' => 'Dermatologista',
        'medico de alergia' => 'Dermatologista',
        'pele' => 'Dermatologista',
        'alergia' => 'Dermatologista',

        // Endocrinologista
        'endocrino' => 'Endocrinologista',
        'endocrinologista' => 'Endocrinologista',
        'medico de diabetes' => 'Endocrinologista',
        'medico de hormonio' => 'Endocrinologista',
        'diabetes' => 'Endocrinologista',
        'hormonio' => 'Endocrinologista',

        // Gastroenterologista
        'gastro' => 'Gastroenterologista',
        'gastroenterologista' => 'Gastroenterologista',
        'medico para gastrite' => 'Gastroenterologista',
        'gastrite' => 'Gastroenterologista',
        'estomago' => 'Gastroenterologista',

        // Geriatra
        'geriatra' => 'Geriatra',
        'medico de idoso' => 'Geriatra',
        'idoso' => 'Geriatra',

        // Ginecologista
        'gineco' => 'Ginecologista',
        'ginecologista' => 'Ginecologista',
        'medica da mulher' => 'Ginecologista',
        'medico da mulher' => 'Ginecologista',
        'mulher' => 'Ginecologista',

        // Mastologista
        'masto' => 'Mastologista',
        'mastologista' => 'Mastologista',
        'medico de mama' => 'Mastologista',
        'medico de peito' => 'Mastologista',
        'medico de seios' => 'Mastologista',
        'mama' => 'Mastologista',
        'seios' => 'Mastologista',

        // Neurologista
        'neuro' => 'Neurologista',
        'neurologista' => 'Neurologista',
        'medico de cabeca' => 'Neurologista',
        'cabeca' => 'Neurologista',

        // Oftalmologista
        'oftalmo' => 'Oftalmologista',
        'oftalmologista' => 'Oftalmologista',
        'oculista' => 'Oftalmologista',
        'medico de vista' => 'Oftalmologista',
        'medico dos olhos' => 'Oftalmologista',
        'vista' => 'Oftalmologista',
        'olhos' => 'Oftalmologista',

        // Otorrinolaringologista
        'otorrino' => 'Otorrinolaringologista',
        'otorrinolaringologista' => 'Otorrinolaringologista',
        'medico de ouvido' => 'Otorrinolaringologista',
        'medico de lavagem de ouvido' => 'Otorrinolaringologista',
        'ouvido' => 'Otorrinolaringologista',

        // Ortopedista
        'ortopedia' => 'Ortopedista',
        'ortopedista' => 'Ortopedista',
        'medico de osso' => 'Ortopedista',
        'fratura' => 'Ortopedista',
        'osso' => 'Ortopedista',

        // Pneumologista
        'pneumo' => 'Pneumologista',
        'pneumologista' => 'Pneumologista',
        'medico do pulmao' => 'Pneumologista',
        'pulmao' => 'Pneumologista',

        // Pediatra
        'pediatra' => 'Pediatra',
        'medico de crianca' => 'Pediatra',
        'crianca' => 'Pediatra',

        // Proctologista
        'procto' => 'Proctologista',
        'proctologista' => 'Proctologista',
        'medico de anus' => 'Proctologista',
        'medico de hemorroida' => 'Proctologista',
        'hemorroida' => 'Proctologista',

        // Reumatologista
        'reumato' => 'Reumatologista',
        'reumatologista' => 'Reumatologista',
        'medico de artrite' => 'Reumatologista',
        'medico de osteoporose' => 'Reumatologista',
        'artrite' => 'Reumatologista',
        'osteoporose' => 'Reumatologista',

        // Urologista
        'uro' => 'Urologista',
        'urologista' => 'Urologista',
        'medico de prostata' => 'Urologista',
        'prostata' => 'Urologista',

        // Clínico Geral
        'clinico' => 'Clínico Geral',
        'clinico geral' => 'Clínico Geral',
        'clinica geral' => 'Clínico Geral'
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

    // Se não encontrou, retornar capitalizado
    return ucwords($termo);
}

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

function corrigirCaracteres($texto) {
    if ($texto === null) return null;
    $substituicoes = [
        'º' => 'º', 'ª' => 'ª',
        'Ã§' => 'ç', 'Ã£' => 'ã', 'Ã¡' => 'á',
        'Ã©' => 'é', 'Ã­' => 'í', 'Ã³' => 'ó', 'Ãº' => 'ú',
        'Ã' => 'Ç', 'Ã‚' => 'Â', 'Ãª' => 'ê', 'Ã´' => 'ô'
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

    // Mapear termo
    $termoMapeado = mapearEspecialidade($termoBusca);

    // Buscar especialidades
    $sql = "SELECT DISTINCT
                e.ID as id,
                e.NOME as nome
            FROM ESPECIALIDADES e
            WHERE e.NOME IS NOT NULL";

    if (!empty($termoBusca)) {
        $termoOriginal = strtoupper($termoBusca);
        $termoMapeadoUpper = strtoupper($termoMapeado);

        $sql .= " AND (
                    UPPER(e.NOME) LIKE '%$termoOriginal%' OR
                    UPPER(e.NOME) LIKE '%$termoMapeadoUpper%'
                  )";
    }

    $sql .= " ORDER BY e.NOME";

    $result = ibase_query($conn, $sql);

    if (!$result) {
        throw new Exception('Erro ao buscar especialidades: ' . ibase_errmsg());
    }

    $especialidades = [];
    while ($row = ibase_fetch_assoc($result)) {
        $nome = corrigirCaracteres(trim($row['NOME']));

        $especialidades[] = [
            'id' => (string)$row['ID'],
            'text' => $nome,
            'nome' => $nome
        ];
    }

    ibase_free_result($result);
    ibase_commit($conn);
    ibase_close($conn);

    // Resposta
    $response = [
        'results' => $especialidades,
        'pagination' => ['more' => false],
        'total' => count($especialidades)
    ];

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
