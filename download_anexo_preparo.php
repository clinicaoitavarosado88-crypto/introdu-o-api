<?php
// download_anexo_preparo.php - Script para download de anexos de preparos
include 'includes/connection.php';

// Verificar se o ID foi fornecido
$anexoId = (int) ($_GET['id'] ?? 0);

if ($anexoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID do anexo é obrigatório']);
    exit;
}

try {
    // Buscar informações do anexo no banco
    $sql = "SELECT NOME_ARQUIVO, NOME_ORIGINAL, TIPO_ARQUIVO, CAMINHO_ARQUIVO, TAMANHO_ARQUIVO 
            FROM AGENDA_PREPAROS_ANEXOS 
            WHERE ID = ?";
    
    $stmt = ibase_prepare($conn, $sql);
    $result = ibase_execute($stmt, $anexoId);
    
    if (!$result || !($anexo = ibase_fetch_assoc($result))) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Anexo não encontrado']);
        exit;
    }
    
    $caminhoArquivo = $anexo['CAMINHO_ARQUIVO'];
    $nomeOriginal = $anexo['NOME_ORIGINAL'];
    $tipoArquivo = $anexo['TIPO_ARQUIVO'];
    
    // Verificar se o arquivo existe no sistema de arquivos
    if (!file_exists($caminhoArquivo)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Arquivo não encontrado no servidor']);
        exit;
    }
    
    // Definir o tipo MIME baseado na extensão
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];
    
    $mimeType = $mimeTypes[$tipoArquivo] ?? 'application/octet-stream';
    
    // Configurar headers para download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $nomeOriginal . '"');
    header('Content-Length: ' . filesize($caminhoArquivo));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    // Limpar buffer de saída
    ob_clean();
    flush();
    
    // Enviar o arquivo
    readfile($caminhoArquivo);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>