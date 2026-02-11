<?php
// upload_anexo_preparo.php - Script para upload de anexos de preparos
header('Content-Type: application/json');
include 'includes/connection.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

// Verificar se há arquivos enviados
if (!isset($_FILES['anexos']) || empty($_FILES['anexos']['name'][0])) {
    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado']);
    exit;
}

// Configurações
$uploadDir = 'uploads/preparos/';
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png'];

// Criar diretório se não existir
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Erro ao criar diretório de upload']);
        exit;
    }
}

// Processar arquivos
$arquivosUpload = [];
$arquivos = $_FILES['anexos'];
$agendaIdRaw = $_POST['agenda_id'] ?? '';
$preparoIdRaw = $_POST['preparo_id'] ?? '';

// ✅ CORREÇÃO: Lidar com IDs temporários para criação de agenda
$isTemporary = strpos($agendaIdRaw, 'temp_') === 0;

if ($isTemporary) {
    // Agenda em criação - não permitir upload ainda
    echo json_encode([
        'success' => false, 
        'error' => 'Para anexar documentos, primeiro salve a agenda e depois edite-a para adicionar os preparos com anexos.'
    ]);
    exit;
} else {
    // Agenda existente - usar IDs normais
    $agendaId = intval($agendaIdRaw);
    $preparoId = intval($preparoIdRaw);
    
    if ($agendaId <= 0 || $preparoId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID da agenda e preparo são obrigatórios']);
        exit;
    }
}

try {
    // Iniciar transação
    ibase_trans($conn);
    
    for ($i = 0; $i < count($arquivos['name']); $i++) {
        // Verificar se não houve erro no upload
        if ($arquivos['error'][$i] !== UPLOAD_ERR_OK) {
            throw new Exception("Erro no upload do arquivo: " . $arquivos['name'][$i]);
        }
        
        // Verificar tamanho
        if ($arquivos['size'][$i] > $maxFileSize) {
            throw new Exception("Arquivo muito grande: " . $arquivos['name'][$i] . " (máx. 5MB)");
        }
        
        // Verificar tipo
        $extensao = strtolower(pathinfo($arquivos['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($extensao, $allowedTypes)) {
            throw new Exception("Tipo de arquivo não permitido: " . $arquivos['name'][$i]);
        }
        
        // Criar estrutura de diretórios por preparo/agenda
        $subDir = "agenda_{$agendaId}/preparo_{$preparoId}/";
        $diretorioFinal = $uploadDir . $subDir;
        
        // Criar subdiretório se não existir
        if (!is_dir($diretorioFinal)) {
            if (!mkdir($diretorioFinal, 0755, true)) {
                throw new Exception("Erro ao criar diretório: " . $diretorioFinal);
            }
        }
        
        // Manter nome original, mas tratar conflitos se existir
        $nomeOriginal = $arquivos['name'][$i];
        $nomeArquivo = $nomeOriginal;
        $contador = 1;
        
        // Se arquivo já existe, adicionar número sequencial
        while (file_exists($diretorioFinal . $nomeArquivo)) {
            $nomeBase = pathinfo($nomeOriginal, PATHINFO_FILENAME);
            $extensaoArquivo = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
            $nomeArquivo = $nomeBase . "_({$contador})." . $extensaoArquivo;
            $contador++;
        }
        
        $caminhoCompleto = $diretorioFinal . $nomeArquivo;
        
        // Mover arquivo
        if (!move_uploaded_file($arquivos['tmp_name'][$i], $caminhoCompleto)) {
            throw new Exception("Erro ao salvar arquivo: " . $arquivos['name'][$i]);
        }
        
        // Inserir no banco de dados sempre
        $sql = "INSERT INTO AGENDA_PREPAROS_ANEXOS 
                (PREPARO_ID, AGENDA_ID, NOME_ARQUIVO, NOME_ORIGINAL, TIPO_ARQUIVO, 
                 TAMANHO_ARQUIVO, CAMINHO_ARQUIVO, USUARIO_UPLOAD) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = ibase_prepare($conn, $sql);
        $result = ibase_execute($stmt, 
            $preparoId,
            $agendaId, 
            $nomeArquivo, // Nome do arquivo no sistema (pode ter contador se houver conflito)
            $nomeOriginal, // Nome original enviado pelo usuário
            $extensao,
            $arquivos['size'][$i],
            $caminhoCompleto,
            'SISTEMA' // Pode ser substituído por user logado
        );
        
        if (!$result) {
            throw new Exception("Erro ao salvar informações no banco: " . $arquivos['name'][$i] . " - " . ibase_errmsg());
        }
        
        // Buscar o ID gerado pelo último insert
        $sqlId = "SELECT GEN_ID(SEQ_ANEXOS_PREPAROS, 0) as ULTIMO_ID FROM RDB\$DATABASE";
        $resultId = ibase_query($conn, $sqlId);
        $row = ibase_fetch_assoc($resultId);
        $anexoId = $row ? $row['ULTIMO_ID'] : null;
        
        $arquivosUpload[] = [
            'id' => $anexoId,
            'nome_original' => $nomeOriginal,
            'nome_arquivo' => $nomeArquivo,
            'tamanho' => $arquivos['size'][$i],
            'tipo' => $extensao,
            'caminho' => $caminhoCompleto
        ];
    }
    
    // Confirmar transação
    ibase_commit($conn);
    
    echo json_encode([
        'success' => true, 
        'arquivos' => $arquivosUpload,
        'message' => 'Arquivos enviados com sucesso'
    ]);
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    ibase_rollback($conn);
    
    // Remover arquivos que foram movidos
    foreach ($arquivosUpload as $arquivo) {
        $caminhoArquivo = $uploadDir . $arquivo['nome_arquivo'];
        if (file_exists($caminhoArquivo)) {
            unlink($caminhoArquivo);
        }
    }
    
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>