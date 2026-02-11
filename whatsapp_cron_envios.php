<?php
// whatsapp_cron_envios.php - Script para execução via CRON
// Envia confirmações automáticas 24h antes das consultas
// Uso: php whatsapp_cron_envios.php ou via crontab

include 'includes/connection.php';
include 'includes/auditoria.php';
include 'whatsapp_config.php';

// Configurações
$HORAS_ANTECEDENCIA = 24; // Enviar 24 horas antes
$MAX_TENTATIVAS = 3;      // Máximo de tentativas de envio

// Carregar configurações do WhatsApp
$whatsappConfig = getWhatsAppConfig('whatsapp');
$WHATSAPP_API_URL = $whatsappConfig['api_url'];
$WHATSAPP_INSTANCE = $whatsappConfig['instance_name'];
$WHATSAPP_API_KEY = $whatsappConfig['api_key'];

// Função de log
function logCron($mensagem, $tipo = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] [$tipo] $mensagem\n";
    file_put_contents('/var/www/html/oitava/agenda/logs/cron_whatsapp.log', $log, FILE_APPEND | LOCK_EX);
    
    // Se for CLI, também exibe no terminal
    if (php_sapi_name() === 'cli') {
        echo $log;
    }
}

// Função para formatar telefone
function formatarTelefone($telefone) {
    // Remove tudo que não é número
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    // Se não tem código do país, adiciona 55 (Brasil)
    if (strlen($telefone) === 11) {
        $telefone = '55' . $telefone;
    }
    
    return $telefone . '@s.whatsapp.net';
}

// Função para gerar mensagem de confirmação
function gerarMensagemConfirmacao($dados) {
    $nome = $dados['PACIENTE_NOME'];
    $data = date('d/m/Y', strtotime($dados['DATA_CONSULTA']));
    $hora = substr($dados['HORA_CONSULTA'], 0, 5);
    $medico = $dados['MEDICO_NOME'] ?: 'Equipe médica';
    $especialidade = $dados['ESPECIALIDADE_NOME'] ?: 'Consulta';
    $unidade = $dados['UNIDADE_NOME'];
    
    $mensagem = "🏥 *CLÍNICA OITAVA*\n\n";
    $mensagem .= "Olá *{$nome}*!\n\n";
    $mensagem .= "Você tem consulta agendada para:\n";
    $mensagem .= "📅 *{$data}* às *{$hora}*\n";
    $mensagem .= "👨‍⚕️ {$medico} - {$especialidade}\n";
    $mensagem .= "📍 {$unidade}\n\n";
    $mensagem .= "*Confirme sua presença:*\n";
    $mensagem .= "✅ *1* - CONFIRMAR\n";
    $mensagem .= "❌ *2* - CANCELAR\n";
    $mensagem .= "🔄 *3* - REAGENDAR\n\n";
    $mensagem .= "_Responda apenas o número_\n\n";
    $mensagem .= "Em caso de dúvidas, ligue:\n📞 (xx) xxxx-xxxx";
    
    return $mensagem;
}

// Função para enviar mensagem via WhatsApp API
function enviarWhatsApp($telefone, $mensagem, $agendamentoId) {
    global $WHATSAPP_API_URL, $WHATSAPP_INSTANCE, $WHATSAPP_API_KEY;
    
    $dados = [
        'number' => $telefone,
        'text' => $mensagem
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "{$WHATSAPP_API_URL}/message/sendText/{$WHATSAPP_INSTANCE}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . $WHATSAPP_API_KEY
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        logCron("Erro cURL para agendamento $agendamentoId: $error", 'ERROR');
        return false;
    }
    
    if ($httpCode !== 200) {
        logCron("HTTP $httpCode para agendamento $agendamentoId: $response", 'ERROR');
        return false;
    }
    
    $responseData = json_decode($response, true);
    
    if (isset($responseData['key']['id'])) {
        logCron("Mensagem enviada para agendamento $agendamentoId. ID: " . $responseData['key']['id']);
        return $responseData['key']['id'];
    }
    
    logCron("Resposta inesperada para agendamento $agendamentoId: $response", 'ERROR');
    return false;
}

// Função para criar registro de confirmação
function criarConfirmacao($conn, $agendamento, $mensagem) {
    try {
        $query = "INSERT INTO WHATSAPP_CONFIRMACOES (
                    AGENDAMENTO_ID, NUMERO_AGENDAMENTO, PACIENTE_NOME, PACIENTE_TELEFONE,
                    DATA_CONSULTA, HORA_CONSULTA, MEDICO_NOME, ESPECIALIDADE_NOME, UNIDADE_NOME,
                    STATUS, MENSAGEM_ENVIADA, CREATED_BY
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?, 'CRON_SYSTEM')";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, 
            $agendamento['ID'],
            $agendamento['NUMERO_AGENDAMENTO'],
            $agendamento['PACIENTE_NOME'],
            $agendamento['PACIENTE_TELEFONE'],
            $agendamento['DATA_CONSULTA'],
            $agendamento['HORA_CONSULTA'],
            $agendamento['MEDICO_NOME'],
            $agendamento['ESPECIALIDADE_NOME'],
            $agendamento['UNIDADE_NOME'],
            $mensagem
        );
        
        return $result ? ibase_gen_id('GEN_WHATSAPP_CONFIRMACOES_ID', 0) : false;
        
    } catch (Exception $e) {
        logCron("Erro ao criar confirmação: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Função para atualizar status após envio
function atualizarAposEnvio($conn, $confirmacaoId, $sucesso, $messageId = null) {
    try {
        if ($sucesso) {
            $query = "UPDATE WHATSAPP_CONFIRMACOES 
                      SET STATUS = 'enviado', 
                          DATA_ENVIO = CURRENT_TIMESTAMP,
                          WHATSAPP_MESSAGE_ID = ?,
                          TENTATIVAS = TENTATIVAS + 1
                      WHERE ID = ?";
            $stmt = ibase_prepare($conn, $query);
            ibase_execute($stmt, $messageId, $confirmacaoId);
        } else {
            $query = "UPDATE WHATSAPP_CONFIRMACOES 
                      SET STATUS = 'erro', 
                          TENTATIVAS = TENTATIVAS + 1
                      WHERE ID = ?";
            $stmt = ibase_prepare($conn, $query);
            ibase_execute($stmt, $confirmacaoId);
        }
        
        return true;
        
    } catch (Exception $e) {
        logCron("Erro ao atualizar status: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// =================== EXECUÇÃO PRINCIPAL ===================

logCron("Iniciando execução do CRON de confirmações WhatsApp");

try {
    // Buscar agendamentos para confirmar (24h antes)
    $dataLimite = date('Y-m-d', strtotime("+{$HORAS_ANTECEDENCIA} hours"));
    $horaAtual = date('H:i:s');
    
    $query = "SELECT DISTINCT
                a.ID,
                a.NUMERO_AGENDAMENTO,
                a.DATA_AGENDAMENTO as DATA_CONSULTA,
                a.HORA_AGENDAMENTO as HORA_CONSULTA,
                COALESCE(p.PACIENTE, a.NOME_PACIENTE) as PACIENTE_NOME,
                COALESCE(p.FONE1, a.TELEFONE_PACIENTE) as PACIENTE_TELEFONE,
                med.NOME as MEDICO_NOME,
                esp.NOME as ESPECIALIDADE_NOME,
                u.NOME_UNIDADE as UNIDADE_NOME
              FROM AGENDAMENTOS a
              LEFT JOIN LAB_PACIENTES p ON a.PACIENTE_ID = p.IDPACIENTE
              JOIN AGENDAS ag ON a.AGENDA_ID = ag.ID
              LEFT JOIN LAB_MEDICOS_PRES med ON ag.MEDICO_ID = med.ID
              LEFT JOIN ESPECIALIDADES esp ON a.ESPECIALIDADE_ID = esp.ID
              LEFT JOIN LAB_CIDADES u ON ag.UNIDADE_ID = u.ID
              WHERE a.DATA_AGENDAMENTO = ?
              AND a.STATUS IN ('agendado', 'confirmado')
              AND COALESCE(p.FONE1, a.TELEFONE_PACIENTE) IS NOT NULL
              AND COALESCE(p.FONE1, a.TELEFONE_PACIENTE) != ''
              AND NOT EXISTS (
                  SELECT 1 FROM WHATSAPP_CONFIRMACOES wc 
                  WHERE wc.AGENDAMENTO_ID = a.ID 
                  AND wc.STATUS IN ('enviado', 'confirmado', 'cancelado')
              )";
    
    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt, $dataLimite);
    
    $totalEncontrados = 0;
    $totalEnviados = 0;
    $totalErros = 0;
    
    while ($agendamento = ibase_fetch_assoc($result)) {
        $totalEncontrados++;
        
        // Verificar se telefone é válido
        $telefone = preg_replace('/[^0-9]/', '', $agendamento['PACIENTE_TELEFONE']);
        if (strlen($telefone) < 10) {
            logCron("Telefone inválido para agendamento {$agendamento['ID']}: {$agendamento['PACIENTE_TELEFONE']}", 'WARN');
            continue;
        }
        
        // Gerar mensagem
        $mensagem = gerarMensagemConfirmacao($agendamento);
        
        // Criar registro de confirmação
        $confirmacaoId = criarConfirmacao($conn, $agendamento, $mensagem);
        
        if (!$confirmacaoId) {
            logCron("Falha ao criar confirmação para agendamento {$agendamento['ID']}", 'ERROR');
            $totalErros++;
            continue;
        }
        
        // Enviar WhatsApp
        $telefoneFormatado = formatarTelefone($telefone);
        $messageId = enviarWhatsApp($telefoneFormatado, $mensagem, $agendamento['ID']);
        
        // Atualizar status
        $sucesso = atualizarAposEnvio($conn, $confirmacaoId, (bool)$messageId, $messageId);
        
        if ($messageId && $sucesso) {
            $totalEnviados++;
            
            // Registrar auditoria
            registrarAuditoria($conn, [
                'acao' => 'WHATSAPP_ENVIO_AUTOMATICO',
                'usuario' => 'CRON_SYSTEM',
                'tabela_afetada' => 'WHATSAPP_CONFIRMACOES',
                'agendamento_id' => $agendamento['ID'],
                'observacoes' => "Confirmação enviada via WhatsApp para {$agendamento['PACIENTE_NOME']}",
                'dados_novos' => json_encode([
                    'telefone' => $telefoneFormatado,
                    'message_id' => $messageId
                ])
            ]);
        } else {
            $totalErros++;
        }
        
        // Pequena pausa entre envios para não sobrecarregar a API
        sleep(1);
    }
    
    logCron("Execução concluída. Encontrados: $totalEncontrados, Enviados: $totalEnviados, Erros: $totalErros");
    
} catch (Exception $e) {
    logCron("Erro fatal na execução: " . $e->getMessage(), 'ERROR');
}

logCron("Finalizando execução do CRON");
?>