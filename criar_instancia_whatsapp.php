<?php
// criar_instancia_whatsapp.php - Script para criar instância WhatsApp automaticamente
header('Content-Type: application/json');
include 'whatsapp_config.php';

function logInstancia($mensagem, $tipo = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] [$tipo] $mensagem\n";
    file_put_contents('/var/www/html/oitava/agenda/logs/whatsapp.log', $log, FILE_APPEND | LOCK_EX);
    
    if (php_sapi_name() === 'cli') {
        echo $log;
    }
}

function criarInstancia() {
    $config = getWhatsAppConfig('whatsapp');
    
    logInstancia("Iniciando criação da instância WhatsApp...");
    
    // Dados da instância
    $dadosInstancia = [
        'instanceName' => $config['instance_name'],
        'token' => $config['api_key'],
        'qrcode' => true,
        'number' => '',
        'webhook' => $config['webhook_url'],
        'webhookByEvents' => true,
        'webhookBase64' => false,
        'events' => [
            'APPLICATION_STARTUP',
            'QRCODE_UPDATED',
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'MESSAGES_DELETE',
            'SEND_MESSAGE',
            'CONTACTS_SET',
            'CONTACTS_UPSERT',
            'CONTACTS_UPDATE',
            'PRESENCE_UPDATE',
            'CHATS_SET',
            'CHATS_UPSERT',
            'CHATS_UPDATE',
            'CHATS_DELETE',
            'GROUPS_UPSERT',
            'GROUP_UPDATE',
            'GROUP_PARTICIPANTS_UPDATE',
            'CONNECTION_UPDATE'
        ]
    ];
    
    // Criar instância via API
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $config['api_url'] . '/instance/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dadosInstancia),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . $config['api_key']
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        logInstancia("Erro cURL: $error", 'ERROR');
        return ['error' => $error];
    }
    
    if ($httpCode !== 201 && $httpCode !== 200) {
        logInstancia("HTTP $httpCode: $response", 'ERROR');
        return ['error' => "HTTP $httpCode: $response"];
    }
    
    $responseData = json_decode($response, true);
    
    if (isset($responseData['instance'])) {
        logInstancia("Instância criada com sucesso: " . $responseData['instance']['instanceName']);
        return $responseData;
    }
    
    logInstancia("Resposta inesperada: $response", 'WARN');
    return $responseData;
}

function obterQRCode() {
    $config = getWhatsAppConfig('whatsapp');
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $config['api_url'] . '/instance/connect/' . $config['instance_name'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $config['api_key']
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['base64'])) {
            return $data['base64'];
        }
    }
    
    return null;
}

function verificarStatusInstancia() {
    $config = getWhatsAppConfig('whatsapp');
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $config['api_url'] . '/instance/connectionState/' . $config['instance_name'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $config['api_key']
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

// Processar ação solicitada
$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

switch ($acao) {
    case 'criar':
        $resultado = criarInstancia();
        echo json_encode($resultado);
        break;
        
    case 'qrcode':
        $qrcode = obterQRCode();
        if ($qrcode) {
            echo json_encode(['success' => true, 'qrcode' => $qrcode]);
        } else {
            echo json_encode(['success' => false, 'error' => 'QR Code não disponível']);
        }
        break;
        
    case 'status':
        $status = verificarStatusInstancia();
        if ($status) {
            echo json_encode(['success' => true, 'status' => $status]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Status não disponível']);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Ação não especificada. Use: ?acao=criar, ?acao=qrcode ou ?acao=status']);
        break;
}
?>