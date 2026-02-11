<?php
// download_anexo.php - Script para download de anexos
include 'includes/connection.php';

// Verificar parâmetros (aceitar via GET ou CLI)
if (isset($_GET['id'])) {
    $anexoId = intval($_GET['id']);
} else {
    // Para testes via CLI
    parse_str($_SERVER['QUERY_STRING'] ?? '', $params);
    $anexoId = intval($params['id'] ?? 0);
}

if ($anexoId <= 0) {
    http_response_code(400);
    die('ID do anexo inválido');
}

try {
    // Buscar informações do anexo
    $sql = "SELECT NOME_ARQUIVO, NOME_ORIGINAL, TIPO_ARQUIVO, CAMINHO_ARQUIVO, TAMANHO_ARQUIVO 
            FROM AGENDA_PREPAROS_ANEXOS 
            WHERE ID = ?";
    
    $stmt = ibase_prepare($conn, $sql);
    $result = ibase_execute($stmt, $anexoId);
    $anexo = ibase_fetch_assoc($result);
    
    if (!$anexo) {
        http_response_code(404);
        die('Anexo não encontrado');
    }
    
    $caminhoArquivo = $anexo['CAMINHO_ARQUIVO'];
    
    // Verificar se arquivo existe
    if (!file_exists($caminhoArquivo)) {
        http_response_code(404);
        die('Arquivo não encontrado no servidor');
    }
    
    // Definir headers para download
    $nomeOriginal = $anexo['NOME_ORIGINAL'];
    $tipoArquivo = $anexo['TIPO_ARQUIVO'];
    $tamanho = $anexo['TAMANHO_ARQUIVO'];
    
    // Determinar content-type
    $contentTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];
    
    $contentType = $contentTypes[$tipoArquivo] ?? 'application/octet-stream';
    
    // Headers para download
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $nomeOriginal . '"');
    header('Content-Length: ' . $tamanho);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Limpar output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Enviar arquivo
    readfile($caminhoArquivo);
    exit;
    
} catch (Exception $e) {
    error_log("Erro no download de anexo: " . $e->getMessage());
    http_response_code(500);
    die('Erro interno do servidor');
}
?>