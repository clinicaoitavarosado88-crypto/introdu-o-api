<?php
// testar_numeros_reais.php - Teste com números de telefone reais
include 'whatsapp_config.php';

echo "📱 TESTE COM NÚMEROS REAIS\n";
echo "=========================\n\n";

// Solicitar número de teste
$numeroTeste = trim(readline("Digite um número WhatsApp para teste (ex: 5584999887766): "));

if (empty($numeroTeste)) {
    echo "❌ Número é obrigatório\n";
    exit;
}

// Validar formato do número
if (!preg_match('/^55\d{10,11}$/', $numeroTeste)) {
    echo "⚠️ Formato recomendado: 55 + DDD + número (ex: 5584999887766)\n";
    $continuar = trim(readline("Continuar mesmo assim? (s/n): "));
    if (strtolower($continuar) !== 's') {
        exit;
    }
}

echo "🧪 Testando com número: $numeroTeste\n\n";

// 1. Verificar se Evolution API está funcionando
echo "1. VERIFICANDO EVOLUTION API...\n";
$config = getWhatsAppConfig('whatsapp');

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $config['api_url'] . '/instance/connectionState/' . $config['instance_name'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['apikey: ' . $config['api_key']],
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($httpCode == 200) {
    $status = json_decode($response, true);
    echo "✅ Evolution API conectada\n";
    echo "📱 Status da instância: " . ($status['instance']['state'] ?? 'N/A') . "\n\n";
} else {
    echo "❌ Evolution API não está conectada (HTTP: $httpCode)\n";
    echo "Execute primeiro: sudo bash install_evolution_api.sh\n";
    exit;
}

// 2. Criar confirmação de teste real
echo "2. CRIANDO CONFIRMAÇÃO DE TESTE...\n";

include 'includes/connection.php';

// Dados do teste
$dadosTeste = [
    'numero_agendamento' => 'TESTE_REAL_' . date('YmdHis'),
    'paciente_nome' => 'TESTE WHATSAPP REAL',
    'paciente_telefone' => $numeroTeste,
    'data_consulta' => date('Y-m-d', strtotime('+1 day')),
    'hora_consulta' => '14:30:00',
    'medico_nome' => 'DR. TESTE REAL',
    'especialidade_nome' => 'TESTE',
    'unidade_nome' => 'Unidade Teste'
];

try {
    $query = "INSERT INTO WHATSAPP_CONFIRMACOES (
                AGENDAMENTO_ID, NUMERO_AGENDAMENTO, PACIENTE_NOME, PACIENTE_TELEFONE,
                DATA_CONSULTA, HORA_CONSULTA, MEDICO_NOME, ESPECIALIDADE_NOME, UNIDADE_NOME,
                STATUS, CREATED_BY
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', 'TESTE_REAL')";
    
    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt,
        999999,
        $dadosTeste['numero_agendamento'],
        $dadosTeste['paciente_nome'],
        $dadosTeste['paciente_telefone'],
        $dadosTeste['data_consulta'],
        $dadosTeste['hora_consulta'],
        $dadosTeste['medico_nome'],
        $dadosTeste['especialidade_nome'],
        $dadosTeste['unidade_nome']
    );
    
    if ($result) {
        $confirmacaoId = ibase_gen_id('GEN_WHATSAPP_CONFIRMACOES_ID', 0);
        echo "✅ Confirmação criada (ID: $confirmacaoId)\n";
        echo "📅 Data: {$dadosTeste['data_consulta']} às {$dadosTeste['hora_consulta']}\n\n";
    } else {
        echo "❌ Erro ao criar confirmação\n";
        exit;
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit;
}

// 3. Enviar mensagem real
echo "3. ENVIANDO MENSAGEM REAL...\n";

$templates = getWhatsAppConfig('templates');
$clinicInfo = $config['clinic_info'];

$mensagem = str_replace([
    '{{clinic_name}}', '{{patient_name}}', '{{date}}', '{{time}}',
    '{{doctor}}', '{{specialty}}', '{{unit}}', '{{clinic_phone}}'
], [
    $clinicInfo['name'],
    $dadosTeste['paciente_nome'],
    date('d/m/Y', strtotime($dadosTeste['data_consulta'])),
    $dadosTeste['hora_consulta'],
    $dadosTeste['medico_nome'],
    $dadosTeste['especialidade_nome'],
    $dadosTeste['unidade_nome'],
    $clinicInfo['phone']
], $templates['confirmation']);

$dadosEnvio = [
    'number' => $numeroTeste,
    'text' => $mensagem
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $config['api_url'] . '/message/sendText',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($dadosEnvio),
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
    echo "❌ Erro de conexão: $error\n";
} elseif ($httpCode == 200) {
    echo "✅ MENSAGEM ENVIADA COM SUCESSO!\n";
    echo "📱 Número: $numeroTeste\n";
    echo "📄 Response: $response\n\n";
    
    // Atualizar status
    $queryUpdate = "UPDATE WHATSAPP_CONFIRMACOES SET STATUS = 'enviado', SENT_AT = CURRENT_TIMESTAMP WHERE ID = ?";
    $stmtUpdate = ibase_prepare($conn, $queryUpdate);
    ibase_execute($stmtUpdate, $confirmacaoId);
    
    echo "📊 Status atualizado para 'enviado'\n\n";
} else {
    echo "❌ Falha no envio\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
}

// 4. Aguardar resposta
echo "4. AGUARDANDO RESPOSTA...\n";
echo "💬 Verifique seu WhatsApp e responda com:\n";
echo "   1 = CONFIRMAR\n";
echo "   2 = CANCELAR\n";
echo "   3 = REAGENDAR\n\n";

echo "Pressione ENTER quando tiver respondido (ou aguarde 60 segundos)...\n";

// Aguardar input ou timeout
$read = [STDIN];
$write = null;
$except = null;
$timeout = 60;

if (stream_select($read, $write, $except, $timeout)) {
    fgets(STDIN);
}

// 5. Verificar se houve resposta
echo "\n5. VERIFICANDO RESPOSTA...\n";

$queryResposta = "SELECT * FROM WHATSAPP_CONFIRMACOES WHERE ID = ?";
$stmtResposta = ibase_prepare($conn, $queryResposta);
$resultResposta = ibase_execute($stmtResposta, $confirmacaoId);
$confirmacao = ibase_fetch_assoc($resultResposta);

if ($confirmacao) {
    echo "📋 STATUS ATUAL: {$confirmacao['STATUS']}\n";
    
    if ($confirmacao['RESPONSE_AT']) {
        echo "✅ Resposta recebida em: {$confirmacao['RESPONSE_AT']}\n";
        echo "💬 Resposta do paciente: {$confirmacao['PATIENT_RESPONSE']}\n";
    } else {
        echo "⏳ Ainda aguardando resposta...\n";
        echo "💡 Dica: Verifique se o webhook está configurado corretamente\n";
    }
}

// 6. Mostrar logs recentes
echo "\n6. LOGS RECENTES...\n";
$logFile = '/var/www/html/oitava/agenda/logs/whatsapp.log';
if (file_exists($logFile)) {
    $logs = file($logFile);
    $recentLogs = array_slice($logs, -10);
    foreach ($recentLogs as $log) {
        if (strpos($log, $numeroTeste) !== false || strpos($log, 'TESTE_REAL') !== false) {
            echo "📄 $log";
        }
    }
} else {
    echo "⚠️ Arquivo de log não encontrado\n";
}

// 7. Limpeza (opcional)
echo "\n7. LIMPEZA DOS DADOS DE TESTE...\n";
$limpar = trim(readline("Deseja remover os dados de teste? (s/n): "));

if (strtolower($limpar) === 's') {
    $queryLimpar = "DELETE FROM WHATSAPP_CONFIRMACOES WHERE ID = ?";
    $stmtLimpar = ibase_prepare($conn, $queryLimpar);
    ibase_execute($stmtLimpar, $confirmacaoId);
    echo "✅ Dados de teste removidos\n";
} else {
    echo "📌 Dados mantidos para análise\n";
}

echo "\n🎯 TESTE COM NÚMEROS REAIS CONCLUÍDO!\n";
echo "=====================================\n\n";

echo "📊 RESULTADOS:\n";
echo "- API WhatsApp: " . ($httpCode == 200 ? 'FUNCIONANDO' : 'ERRO') . "\n";
echo "- Envio de mensagem: " . ($httpCode == 200 ? 'SUCESSO' : 'FALHA') . "\n";
echo "- Webhook: " . ($confirmacao['RESPONSE_AT'] ? 'FUNCIONANDO' : 'VERIFICAR') . "\n\n";

echo "🔧 PRÓXIMAS AÇÕES:\n";
echo "1. Se webhook não funcionou, verifique configuração pública\n";
echo "2. Teste com outros números\n";
echo "3. Configure monitoramento avançado\n";
echo "4. Ative sistema em produção\n\n";

echo "📋 COMANDOS ÚTEIS:\n";
echo "- Ver logs: tail -f /var/www/html/oitava/agenda/logs/whatsapp.log\n";
echo "- Monitor webhook: php monitor_webhook.php\n";
echo "- Relatórios: abrir whatsapp_relatorios.php no navegador\n";
?>