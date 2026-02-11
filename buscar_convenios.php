<?php
// Desabilitar output buffering para evitar travamentos
while (ob_get_level()) ob_end_clean();

header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Função para corrigir encoding de acentuação
function corrigirEncodingConvenio($texto) {
    if (empty($texto)) return $texto;

    // Corrigir sequências específicas de bytes UTF-8 mal interpretados
    $textoCorrigido = preg_replace('/\xC3\x87\xC3\x83O/', 'ÇÃO', $texto);
    $textoCorrigido = preg_replace('/\xC3\x87\xC3\x83/', 'ÇÃ', $textoCorrigido);
    $textoCorrigido = preg_replace('/\xC3\x87O/', 'ÇO', $textoCorrigido);
    $textoCorrigido = preg_replace('/\xC3\x87/', 'Ç', $textoCorrigido);

    // Mapeamento adicional de padrões conhecidos para maior cobertura
    $correções = [
        'TERCEIRIZA��O' => 'TERCEIRIZAÇÃO',
        'SERVI�O' => 'SERVIÇO',
        'TERCEIRIZA��ES' => 'TERCEIRIZAÇÕES',
        'SERVI�OS' => 'SERVIÇOS',
        'SOLU��O' => 'SOLUÇÃO',
        'CONSTRU��O' => 'CONSTRUÇÃO',
        'EDUCA��O' => 'EDUCAÇÃO',
        'INFORMA��O' => 'INFORMAÇÃO',
        'IMPORTA��O' => 'IMPORTAÇÃO',
        'EXPORTA��O' => 'EXPORTAÇÃO'
    ];

    return str_replace(array_keys($correções), array_values($correções), $textoCorrigido);
}

// Suporte para linha de comando e POST
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
    parse_str(getenv('POST_DATA') ?: '', $_POST);
}

// Incluir conexão
include_once 'includes/connection.php';

$busca = trim($_GET['busca'] ?? $_POST['busca'] ?? '');

try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Conexão com banco não estabelecida');
    }

    $convenios = [];

    if (empty($busca)) {
        // Se busca vazia, retornar alguns convênios comuns
        echo json_encode([
            'results' => [
                ['id' => '1', 'text' => 'PARTICULAR - id: 1'],
                ['id' => '0', 'text' => 'Digite para buscar convênios...']
            ],
            'pagination' => ['more' => false],
            'total' => 2
        ]);
        exit;
    }

    // Query usando STARTING WITH para evitar problemas de performance com LIKE
    $buscaUpper = strtoupper($busca);

    // STARTING WITH é muito mais rápido e usa índice
    $sql = "SELECT FIRST 50 idconvenio, convenio, sindicato, suspenso
            FROM lab_convenios
            WHERE UPPER(convenio) STARTING WITH '$buscaUpper'
            OR CAST(idconvenio AS VARCHAR(10)) = '$busca'
            ORDER BY convenio ASC";

    $query = ibase_query($conn, $sql);

    if (!$query) {
        throw new Exception('Erro na query: ' . ibase_errmsg());
    }

    while ($row = ibase_fetch_assoc($query)) {
        $idConvenio = $row['IDCONVENIO'];
        $convenio = $row['CONVENIO'];
        $suspenso = $row['SUSPENSO'];
        $sindicato = $row['SINDICATO'];

        // Pular convênios suspensos
        if ($suspenso === 'S') {
            continue;
        }

        // Converter para UTF-8 de forma segura, removendo caracteres problemáticos
        $convenio = @mb_convert_encoding($convenio, 'UTF-8', 'ISO-8859-1');
        if ($convenio === false) {
            $convenio = $row['CONVENIO']; // Fallback
        }

        // Corrigir encoding do nome do convênio
        $convenioCorrigido = corrigirEncodingConvenio($convenio);

        // Se ainda tem caracteres problemáticos, tentar outras abordagens
        if (strpos($convenioCorrigido, '��') !== false || strpos($convenioCorrigido, '�') !== false) {
            // Tentar converter de ISO para UTF-8
            $convenioCorrigido = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $convenio);
            if ($convenioCorrigido === false) {
                // Se falhou, usar mb_convert_encoding
                $convenioCorrigido = @mb_convert_encoding($convenio, 'UTF-8', 'ISO-8859-1');
                if ($convenioCorrigido === false) {
                    $convenioCorrigido = $convenio;
                }
            }
        }

        // Texto para exibição
        $convenioTexto = $convenioCorrigido;

        $convenios[] = [
            'id' => trim($idConvenio),
            'text' => trim($convenioTexto) . " - id: " . trim($idConvenio),
            'data-nome' => trim($convenioCorrigido),
            'data-sindicato' => trim($sindicato ?? '')
        ];

        // Limitar a 15 resultados
        if (count($convenios) >= 15) {
            break;
        }
    }

    ibase_free_result($query);

    echo json_encode([
        'results' => $convenios,
        'pagination' => ['more' => false],
        'total' => count($convenios)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'results' => [],
        'error' => 'Erro ao buscar convênios: ' . $e->getMessage()
    ]);
}
?>
