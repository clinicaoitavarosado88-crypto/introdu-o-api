<?php
// whatsapp_webhook.php - Receptor de webhooks do WhatsApp
// Este arquivo processa as respostas dos pacientes via WhatsApp

header('Content-Type: application/json');
include 'includes/connection.php';
include 'includes/auditoria.php';

// Log das requisições para debug
function logWebhook($data, $type = 'INFO') {
    $log = date('Y-m-d H:i:s') . " [$type] " . json_encode($data) . "\n";
    file_put_contents('/var/www/html/oitava/agenda/logs/webhook.log', $log, FILE_APPEND | LOCK_EX);
}

// Função para atualizar status da confirmação
function atualizarConfirmacao($conn, $telefone, $novoStatus, $resposta = null) {
    try {
        $query = "UPDATE WHATSAPP_CONFIRMACOES 
                  SET STATUS = ?, 
                      DATA_RESPOSTA = CURRENT_TIMESTAMP,
                      RESPOSTA_PACIENTE = ?
                  WHERE PACIENTE_TELEFONE = ? 
                  AND STATUS = 'enviado'
                  AND DATA_CONSULTA >= CURRENT_DATE";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, $novoStatus, $resposta, $telefone);
        
        if ($result) {
            logWebhook([
                'acao' => 'confirmacao_atualizada',
                'telefone' => $telefone,
                'status' => $novoStatus,
                'resposta' => $resposta
            ]);
            return true;
        }
        return false;
        
    } catch (Exception $e) {
        logWebhook(['erro' => $e->getMessage()], 'ERROR');
        return false;
    }
}

// Função para processar respostas automáticas
function processarResposta($telefone, $mensagem) {
    $mensagem = trim(strtolower($mensagem));
    
    // Respostas de confirmação
    if (in_array($mensagem, ['1', 'sim', 'confirmo', 'confirmado', 'ok'])) {
        return 'confirmado';
    }
    
    // Respostas de cancelamento
    if (in_array($mensagem, ['2', 'não', 'nao', 'cancelar', 'cancelado'])) {
        return 'cancelado';
    }
    
    // Respostas de reagendamento
    if (in_array($mensagem, ['3', 'reagendar', 'remarcar'])) {
        return 'reagendar';
    }
    
    return null; // Resposta não reconhecida
}

// Função para enviar resposta automática
function enviarRespostaAutomatica($telefone, $status) {
    $respostas = [
        'confirmado' => "✅ *Consulta confirmada!*\n\nObrigado por confirmar sua presença. Estaremos esperando você no horário agendado.\n\n_Em caso de imprevisto, entre em contato conosco._",
        'cancelado' => "❌ *Consulta cancelada*\n\nSua consulta foi cancelada conforme solicitado. Para reagendar, entre em contato conosco.\n\n📞 Telefone: (xx) xxxx-xxxx",
        'reagendar' => "🔄 *Reagendamento solicitado*\n\nRecebemos sua solicitação de reagendamento. Nossa equipe entrará em contato para agendar uma nova data.\n\n📞 Em caso de urgência: (xx) xxxx-xxxx"
    ];
    
    $mensagem = $respostas[$status] ?? '';
    
    if (!$mensagem) return false;
    
    // Aqui você integraria com a API do WhatsApp para enviar a resposta
    // Exemplo para Evolution API:
    /*
    $dados_envio = [
        'number' => $telefone,
        'text' => $mensagem
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.evolutionapi.com/message/sendText/INSTANCIA',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados_envio),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: SUA_API_KEY'
        ]
    ]);
    
    $response = curl_exec($curl);
    curl_close($curl);
    */
    
    logWebhook([
        'acao' => 'resposta_automatica',
        'telefone' => $telefone,
        'status' => $status,
        'mensagem' => $mensagem
    ]);
    
    return true;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Ler dados do webhook
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log da requisição recebida
logWebhook(['webhook_recebido' => $data]);

// Verificar se os dados são válidos
if (!$data || !isset($data['data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$webhookData = $data['data'];

// Processar diferentes tipos de evento
$event = $data['event'] ?? '';

switch ($event) {
    case 'messages.upsert':
        // Nova mensagem recebida
        if (isset($webhookData['messages']) && is_array($webhookData['messages'])) {
            foreach ($webhookData['messages'] as $message) {
                // Verificar se é uma mensagem de texto do paciente (não nossa)
                if ($message['messageType'] === 'textMessage' && !$message['fromMe']) {
                    $telefone = preg_replace('/[^0-9]/', '', $message['key']['remoteJid']);
                    $telefone = substr($telefone, -11); // Pegar últimos 11 dígitos
                    
                    $mensagemTexto = $message['message']['conversation'] ?? $message['message']['extendedTextMessage']['text'] ?? '';
                    
                    // Processar resposta
                    $novoStatus = processarResposta($telefone, $mensagemTexto);
                    
                    if ($novoStatus) {
                        // Atualizar no banco
                        $sucesso = atualizarConfirmacao($conn, $telefone, $novoStatus, $mensagemTexto);
                        
                        if ($sucesso) {
                            // Enviar resposta automática
                            enviarRespostaAutomatica($telefone, $novoStatus);
                            
                            // Registrar auditoria
                            registrarAuditoria($conn, [
                                'acao' => 'WHATSAPP_CONFIRMACAO',
                                'usuario' => 'SISTEMA_WHATSAPP',
                                'tabela_afetada' => 'WHATSAPP_CONFIRMACOES',
                                'observacoes' => "Confirmação via WhatsApp: $novoStatus",
                                'dados_novos' => json_encode([
                                    'telefone' => $telefone,
                                    'status' => $novoStatus,
                                    'resposta' => $mensagemTexto
                                ])
                            ]);
                        }
                    }
                }
            }
        }
        break;
        
    case 'messages.update':
        // Status de mensagem atualizado (entregue, lida, etc.)
        if (isset($webhookData['messages']) && is_array($webhookData['messages'])) {
            foreach ($webhookData['messages'] as $message) {
                $messageId = $message['key']['id'] ?? '';
                $status = $message['update']['status'] ?? '';
                
                // Atualizar status de entrega se necessário
                if ($messageId && $status) {
                    logWebhook([
                        'acao' => 'status_mensagem',
                        'message_id' => $messageId,
                        'status' => $status
                    ]);
                }
            }
        }
        break;
        
    default:
        logWebhook(['evento_nao_processado' => $event]);
        break;
}

// Resposta de sucesso para o webhook
http_response_code(200);
echo json_encode(['success' => true, 'processed' => true]);
?>