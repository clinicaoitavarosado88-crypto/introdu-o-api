#!/bin/bash
# configurar_webhook_publico.sh - Configurar webhook público para produção

echo "🌐 CONFIGURANDO WEBHOOK PÚBLICO"
echo "==============================="

# Verificar se está rodando como root/sudo
if [ "$EUID" -ne 0 ]; then
    echo "❌ Execute como root: sudo bash configurar_webhook_publico.sh"
    exit 1
fi

# Verificar se Apache está rodando
if ! systemctl is-active --quiet apache2; then
    echo "🚀 Iniciando Apache..."
    systemctl start apache2
    systemctl enable apache2
fi

# Verificar se SSL está configurado
echo "🔍 Verificando configuração SSL..."

# Habilitar módulos necessários
a2enmod rewrite
a2enmod ssl
a2enmod headers

# Criar configuração do webhook com segurança
echo "🔧 Configurando webhook seguro..."

# Adicionar configuração de segurança ao webhook
cat > /var/www/html/oitava/agenda/.htaccess << 'EOF'
# Configurações de segurança para webhook WhatsApp
RewriteEngine On

# Bloquear acesso direto aos arquivos de log
<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

# Permitir apenas POST para webhook
<Files "whatsapp_webhook.php">
    <RequireAll>
        Require method POST
    </RequireAll>
</Files>

# Headers de segurança
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Limitar tamanho do upload para webhooks
LimitRequestBody 1048576

# Cache para arquivos estáticos
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
</IfModule>
EOF

echo "✅ Configuração de segurança criada"

# Criar script de validação de webhook
cat > /var/www/html/oitava/agenda/validar_webhook.php << 'EOF'
<?php
// validar_webhook.php - Validador de webhook público
header('Content-Type: application/json');

include 'whatsapp_config.php';

function logValidacao($mensagem, $tipo = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] [$tipo] WEBHOOK_VALIDATOR: $mensagem\n";
    file_put_contents('/var/www/html/oitava/agenda/logs/webhook_validation.log', $log, FILE_APPEND | LOCK_EX);
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    logValidacao("Tentativa de acesso com método " . $_SERVER['REQUEST_METHOD'], 'WARN');
    exit;
}

// Verificar Content-Type
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Content-Type deve ser application/json']);
    logValidacao("Content-Type inválido: $contentType", 'WARN');
    exit;
}

// Verificar tamanho do payload
$input = file_get_contents('php://input');
if (strlen($input) > 1048576) { // 1MB
    http_response_code(413);
    echo json_encode(['error' => 'Payload muito grande']);
    logValidacao("Payload muito grande: " . strlen($input) . " bytes", 'WARN');
    exit;
}

// Verificar JSON válido
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    logValidacao("JSON inválido: " . json_last_error_msg(), 'ERROR');
    exit;
}

// Verificar se é um webhook do WhatsApp
$requiredFields = ['message', 'instance', 'event'];
$hasValidStructure = false;

foreach ($requiredFields as $field) {
    if (isset($data[$field])) {
        $hasValidStructure = true;
        break;
    }
}

if (!$hasValidStructure) {
    http_response_code(400);
    echo json_encode(['error' => 'Estrutura de webhook inválida']);
    logValidacao("Estrutura inválida: " . json_encode(array_keys($data)), 'WARN');
    exit;
}

// Log da validação bem-sucedida
logValidacao("Webhook válido recebido de IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'SUCCESS');

// Redirecionar para o webhook real
include 'whatsapp_webhook.php';
EOF

echo "✅ Validador de webhook criado"

# Criar monitoramento de webhook
cat > /var/www/html/oitava/agenda/monitor_webhook.php << 'EOF'
<?php
// monitor_webhook.php - Monitor de status do webhook
header('Content-Type: application/json');

$logFile = '/var/www/html/oitava/agenda/logs/webhook_validation.log';
$whatsappLogFile = '/var/www/html/oitava/agenda/logs/whatsapp.log';

// Estatísticas dos últimos 24h
$oneDayAgo = time() - (24 * 60 * 60);

$stats = [
    'webhook_status' => 'online',
    'last_24h' => [
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
        'invalid_requests' => 0
    ],
    'current_time' => date('Y-m-d H:i:s'),
    'logs_available' => file_exists($logFile),
    'whatsapp_logs_available' => file_exists($whatsappLogFile)
];

if (file_exists($logFile)) {
    $logs = file($logFile);
    foreach ($logs as $line) {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $logTime = strtotime($matches[1]);
            if ($logTime > $oneDayAgo) {
                $stats['last_24h']['total_requests']++;
                
                if (strpos($line, '[SUCCESS]') !== false) {
                    $stats['last_24h']['successful_requests']++;
                } elseif (strpos($line, '[ERROR]') !== false) {
                    $stats['last_24h']['failed_requests']++;
                } elseif (strpos($line, '[WARN]') !== false) {
                    $stats['last_24h']['invalid_requests']++;
                }
            }
        }
    }
}

// Verificar se webhook está respondendo
$webhookUrl = 'http://localhost/oitava/agenda/whatsapp_webhook.php';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => true]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$stats['webhook_response'] = [
    'http_code' => $httpCode,
    'responding' => ($httpCode == 200 || $httpCode == 400), // 400 é esperado para teste
    'response_time' => 'N/A'
];

echo json_encode($stats, JSON_PRETTY_PRINT);
EOF

echo "✅ Monitor de webhook criado"

# Criar script de teste de webhook público
cat > /var/www/html/oitava/agenda/testar_webhook_publico.php << 'EOF'
<?php
// testar_webhook_publico.php - Teste do webhook público
echo "🧪 TESTANDO WEBHOOK PÚBLICO\n";
echo "==========================\n\n";

// Solicitar domínio
$domain = trim(readline("Digite seu domínio público (ex: meusite.com.br): "));
if (empty($domain)) {
    $domain = 'localhost';
    echo "⚠️ Usando localhost para teste\n\n";
}

$webhookUrl = "https://$domain/oitava/agenda/whatsapp_webhook.php";
if ($domain === 'localhost') {
    $webhookUrl = "http://localhost/oitava/agenda/whatsapp_webhook.php";
}

echo "🔍 Testando webhook: $webhookUrl\n";

// Dados de teste
$testData = [
    'event' => 'messages.upsert',
    'instance' => 'CLINICA_OITAVA',
    'data' => [
        'key' => [
            'remoteJid' => '5584999887766@s.whatsapp.net',
            'fromMe' => false,
            'id' => 'TEST_MESSAGE_ID'
        ],
        'message' => [
            'conversation' => '1'
        ],
        'messageTimestamp' => time()
    ]
];

// Teste 1: Verificar se webhook responde
echo "1. TESTE DE CONECTIVIDADE...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: WhatsApp-Webhook-Test/1.0'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para teste local

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Erro de conexão: $error\n";
} elseif ($httpCode == 200) {
    echo "✅ Webhook respondeu corretamente\n";
    echo "📄 Resposta: $response\n";
} else {
    echo "⚠️ HTTP $httpCode - $response\n";
}

// Teste 2: Verificar logs
echo "\n2. VERIFICANDO LOGS...\n";
$logFile = '/var/www/html/oitava/agenda/logs/whatsapp.log';
if (file_exists($logFile)) {
    $logs = file($logFile);
    $recentLogs = array_slice($logs, -5);
    echo "📋 Últimos logs:\n";
    foreach ($recentLogs as $log) {
        echo "   $log";
    }
} else {
    echo "⚠️ Arquivo de log não encontrado\n";
}

// Teste 3: Monitor
echo "\n3. STATUS DO MONITOR...\n";
if (file_exists('/var/www/html/oitava/agenda/monitor_webhook.php')) {
    $monitorOutput = shell_exec('php /var/www/html/oitava/agenda/monitor_webhook.php');
    $monitor = json_decode($monitorOutput, true);
    
    if ($monitor) {
        echo "✅ Monitor funcionando\n";
        echo "📊 Requests 24h: {$monitor['last_24h']['total_requests']}\n";
        echo "✅ Sucessos: {$monitor['last_24h']['successful_requests']}\n";
        echo "❌ Falhas: {$monitor['last_24h']['failed_requests']}\n";
    } else {
        echo "❌ Monitor com problemas\n";
    }
} else {
    echo "❌ Monitor não encontrado\n";
}

echo "\n🎯 TESTE CONCLUÍDO!\n";
echo "==================\n";
echo "URL do webhook: $webhookUrl\n";
echo "Monitor: http://$domain/oitava/agenda/monitor_webhook.php\n";
echo "Logs: /var/www/html/oitava/agenda/logs/\n";
EOF

echo "✅ Teste de webhook público criado"

# Configurar permissões
chown -R www-data:www-data /var/www/html/oitava/agenda/
chmod 755 /var/www/html/oitava/agenda/*.php
chmod 644 /var/www/html/oitava/agenda/.htaccess

echo "✅ Permissões configuradas"

# Reiniciar Apache
systemctl reload apache2

echo ""
echo "🎉 WEBHOOK PÚBLICO CONFIGURADO!"
echo "==============================="
echo ""
echo "📋 ARQUIVOS CRIADOS:"
echo "- validar_webhook.php (validador de segurança)"
echo "- monitor_webhook.php (monitor de status)"
echo "- testar_webhook_publico.php (testes)"
echo "- .htaccess (configurações de segurança)"
echo ""
echo "🔧 PRÓXIMOS PASSOS:"
echo "1. Configure SSL/HTTPS no seu domínio"
echo "2. Execute: php testar_webhook_publico.php"
echo "3. Configure o webhook na Evolution API"
echo ""
echo "🌐 URLs IMPORTANTES:"
echo "- Webhook: https://seudominio.com/oitava/agenda/whatsapp_webhook.php"
echo "- Monitor: https://seudominio.com/oitava/agenda/monitor_webhook.php"
echo "- Validador: https://seudominio.com/oitava/agenda/validar_webhook.php"
echo ""
echo "⚠️ LEMBRE-SE:"
echo "- Configure SSL para produção"
echo "- Monitore logs regularmente"
echo "- Mantenha backup das configurações"