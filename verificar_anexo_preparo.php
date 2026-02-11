<?php
// verificar_anexo_preparo.php - Script para verificar se um anexo existe
header('Content-Type: application/json');
include 'includes/connection.php';

$anexoId = (int) ($_GET['id'] ?? 0);

if ($anexoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID do anexo é obrigatório']);
    exit;
}

try {
    $sql = "SELECT ID, NOME_ARQUIVO, NOME_ORIGINAL, TIPO_ARQUIVO, TAMANHO_ARQUIVO, CAMINHO_ARQUIVO 
            FROM AGENDA_PREPAROS_ANEXOS 
            WHERE ID = ?";
    
    $stmt = ibase_prepare($conn, $sql);
    $result = ibase_execute($stmt, $anexoId);
    
    if (!$result || !($anexo = ibase_fetch_assoc($result))) {
        echo json_encode(['success' => false, 'error' => 'Anexo não encontrado']);
        exit;
    }
    
    // Verificar se arquivo existe fisicamente
    $arquivoExiste = file_exists($anexo['CAMINHO_ARQUIVO']);
    
    echo json_encode([
        'success' => true, 
        'anexo' => [
            'id' => $anexo['ID'],
            'nome_original' => $anexo['NOME_ORIGINAL'],
            'nome_arquivo' => $anexo['NOME_ARQUIVO'],
            'tipo' => $anexo['TIPO_ARQUIVO'],
            'tamanho' => $anexo['TAMANHO_ARQUIVO'],
            'caminho' => $anexo['CAMINHO_ARQUIVO'],
            'arquivo_existe' => $arquivoExiste
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>