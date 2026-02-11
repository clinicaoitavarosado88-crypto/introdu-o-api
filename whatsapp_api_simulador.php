<?php
// whatsapp_api_simulador.php - Simulador da Evolution API para demonstração
// Este arquivo simula uma API WhatsApp funcionando para testes

header('Content-Type: application/json');

// Log das chamadas
function logSimulador($dados) {
    $log = date('Y-m-d H:i:s') . ' - SIMULADOR: ' . json_encode($dados) . "\n";
    file_put_contents('/var/www/html/oitava/agenda/logs/whatsapp.log', $log, FILE_APPEND | LOCK_EX);
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$input = file_get_contents('php://input');
$data = json_decode($input, true);

logSimulador([
    'method' => $method,
    'uri' => $uri,
    'data' => $data
]);

// Simular diferentes endpoints
if (strpos($uri, '/instance/fetchInstances') !== false) {
    // Lista de instâncias
    echo json_encode([
        [
            'instance' => [
                'instanceName' => 'CLINICA_OITAVA',
                'status' => 'open'
            ]
        ]
    ]);
} elseif (strpos($uri, '/instance/create') !== false) {
    // Criar instância
    echo json_encode([
        'instance' => [
            'instanceName' => 'CLINICA_OITAVA',
            'status' => 'created'
        ],
        'hash' => [
            'apikey' => 'CLINICA_OITAVA_2025_API_KEY'
        ]
    ]);
} elseif (strpos($uri, '/instance/connect') !== false) {
    // Conectar instância - retornar QR Code simulado
    echo json_encode([
        'base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        'code' => 'QR_CODE_SIMULADO_CLINICA_OITAVA'
    ]);
} elseif (strpos($uri, '/instance/connectionState') !== false) {
    // Status da conexão
    echo json_encode([
        'instance' => [
            'instanceName' => 'CLINICA_OITAVA',
            'state' => 'open'
        ]
    ]);
} elseif (strpos($uri, '/message/sendText') !== false) {
    // Enviar mensagem
    $numero = $data['number'] ?? '';
    $texto = $data['text'] ?? '';
    
    echo json_encode([
        'key' => [
            'remoteJid' => $numero,
            'fromMe' => true,
            'id' => 'MSG_' . time() . '_' . rand(1000, 9999)
        ],
        'message' => [
            'conversation' => $texto
        ],
        'messageTimestamp' => time(),
        'status' => 'PENDING'
    ]);
} elseif (strpos($uri, '/health') !== false) {
    // Health check
    echo json_encode([
        'status' => 'ok',
        'timestamp' => time()
    ]);
} else {
    // Endpoint não encontrado
    http_response_code(404);
    echo json_encode([
        'error' => 'Endpoint não encontrado',
        'uri' => $uri
    ]);
}
?>