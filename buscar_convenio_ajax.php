<?php
// Versão limpa para AJAX - apenas conexão
include("includes/connection.php");

// Definir cabeçalho JSON
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

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
    parse_str(getenv('POST_DATA') ?: '', $_POST);
}

if (isset($_POST['busca'])) {
    $busca = $_POST['busca'];

    if (!isset($conn) || !$conn) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Conexão com banco não estabelecida',
            'results' => []
        ]);
        exit();
    }

    $sql = "SELECT idconvenio, convenio, sindicato, suspenso, ai
            FROM lab_convenios
            WHERE UPPER(convenio) LIKE UPPER('%$busca%') OR CAST(idconvenio AS VARCHAR(10)) LIKE '%$busca%'
            ORDER BY convenio ASC";
    $query = ibase_query($conn, $sql);

    if (!$query) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro na consulta: ' . ibase_errmsg(),
            'results' => []
        ]);
        exit();
    }

    $convenios = [];
    while ($row = ibase_fetch_assoc($query)) {
        $idConvenio = $row['IDCONVENIO'];
        $convenio = $row['CONVENIO'];
        $suspenso = $row['SUSPENSO'];
        $sindicato = $row['SINDICATO'];

        // Corrigir encoding para exibição
        $convenioExibicao = corrigirEncodingConvenio($convenio);

        // Se ainda tem caracteres problemáticos, tentar abordagem mais agressiva
        if (strpos($convenioExibicao, '��') !== false || strpos($convenioExibicao, '�') !== false) {
            $convenioExibicao = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $convenio);
            if ($convenioExibicao === false) {
                $convenioExibicao = @mb_convert_encoding($convenio, 'UTF-8', 'ISO-8859-1');
                if ($convenioExibicao === false) {
                    $convenioExibicao = $convenio;
                }
            }
        }

        $convenios[] = [
            'id' => trim($idConvenio),
            'nome' => trim($convenioExibicao),
            'nome_original' => trim($convenio),
            'suspenso' => ($suspenso === 'S'),
            'sindicato' => trim($sindicato ?? ''),
            'text' => trim($convenioExibicao) . ($suspenso === 'S' ? ' (Suspenso)' : '') . " - id: " . trim($idConvenio)
        ];
    }

    echo json_encode([
        'status' => 'sucesso',
        'total' => count($convenios),
        'results' => $convenios
    ], JSON_UNESCAPED_UNICODE);
    exit();
} else {
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Digite para buscar convênios',
        'results' => []
    ]);
}
?>