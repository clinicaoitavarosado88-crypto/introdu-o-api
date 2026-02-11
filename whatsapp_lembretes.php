<?php
// whatsapp_lembretes.php - Sistema de lembretes 2h antes das consultas
// Envia lembrete para consultas confirmadas 2 horas antes do horário

include 'includes/connection.php';
include 'includes/auditoria.php';
include 'whatsapp_config.php';

// Função de log para lembretes
function logLembrete($mensagem, $tipo = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] [$tipo] $mensagem\n";
    file_put_contents('/var/www/html/oitava/agenda/logs/lembretes_whatsapp.log', $log, FILE_APPEND | LOCK_EX);
    
    if (php_sapi_name() === 'cli') {
        echo $log;
    }
}

// Função para formatar telefone
function formatarTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($telefone) === 11) {
        $telefone = '55' . $telefone;
    }
    return $telefone . '@s.whatsapp.net';
}

// Função para gerar mensagem de lembrete
function gerarMensagemLembrete($dados) {
    $config = getWhatsAppConfig('whatsapp');
    $template = getWhatsAppConfig('templates')['reminder'];
    
    $nome = $dados['PACIENTE_NOME'];
    $hora = substr($dados['HORA_CONSULTA'], 0, 5);
    $medico = $dados['MEDICO_NOME'] ?: 'Equipe médica';
    $especialidade = $dados['ESPECIALIDADE_NOME'] ?: 'Consulta';
    $unidade = $dados['UNIDADE_NOME'];
    $clinicaNome = $config['clinic_info']['name'];
    $clinicaTelefone = $config['clinic_info']['phone'];
    
    $mensagem = str_replace([
        '{{clinic_name}}',
        '{{patient_name}}',
        '{{time}}',
        '{{doctor}}',
        '{{specialty}}',
        '{{unit}}',
        '{{clinic_phone}}'
    ], [
        $clinicaNome,
        $nome,
        $hora,
        $medico,
        $especialidade,
        $unidade,
        $clinicaTelefone
    ], $template);
    
    return $mensagem;
}

// Função para enviar lembrete via WhatsApp
function enviarLembrete($telefone, $mensagem, $confirmacaoId) {
    $config = getWhatsAppConfig('whatsapp');
    
    $dados = [
        'number' => $telefone,
        'text' => $mensagem
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $config['api_url'] . "/message/sendText/" . $config['instance_name'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados),
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
        logLembrete("Erro cURL para confirmação $confirmacaoId: $error", 'ERROR');
        return false;
    }
    
    if ($httpCode !== 200) {
        logLembrete("HTTP $httpCode para confirmação $confirmacaoId: $response", 'ERROR');
        return false;
    }
    
    $responseData = json_decode($response, true);
    
    if (isset($responseData['key']['id'])) {
        logLembrete("Lembrete enviado para confirmação $confirmacaoId. ID: " . $responseData['key']['id']);
        return $responseData['key']['id'];
    }
    
    logLembrete("Resposta inesperada para confirmação $confirmacaoId: $response", 'ERROR');
    return false;
}

// Função para registrar envio de lembrete
function registrarEnvioLembrete($conn, $confirmacaoId, $sucesso, $messageId = null) {
    try {
        $status = $sucesso ? 'lembrete_enviado' : 'lembrete_erro';
        
        $query = "UPDATE WHATSAPP_CONFIRMACOES 
                  SET STATUS = ?,
                      UPDATED_AT = CURRENT_TIMESTAMP
                  WHERE ID = ?";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, $status, $confirmacaoId);
        
        if ($result && $sucesso) {
            // Criar registro de histórico de lembrete
            $queryHistorico = "INSERT INTO WHATSAPP_CONFIRMACOES (
                                AGENDAMENTO_ID, NUMERO_AGENDAMENTO, PACIENTE_NOME, PACIENTE_TELEFONE,
                                DATA_CONSULTA, HORA_CONSULTA, STATUS, WHATSAPP_MESSAGE_ID, CREATED_BY
                              ) SELECT 
                                AGENDAMENTO_ID, NUMERO_AGENDAMENTO, PACIENTE_NOME, PACIENTE_TELEFONE,
                                DATA_CONSULTA, HORA_CONSULTA, 'lembrete_enviado', ?, 'LEMBRETE_SYSTEM'
                              FROM WHATSAPP_CONFIRMACOES WHERE ID = ?";
            
            $stmtHistorico = ibase_prepare($conn, $queryHistorico);
            ibase_execute($stmtHistorico, $messageId, $confirmacaoId);
        }
        
        return $result;
        
    } catch (Exception $e) {
        logLembrete("Erro ao registrar lembrete: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Função para verificar se deve enviar lembrete
function deveEnviarLembrete($dataConsulta, $horaConsulta) {
    $config = getWhatsAppConfig('timing');
    $horasLembrete = $config['reminder_hours'] ?? 2;
    
    $agora = new DateTime();
    $consulta = new DateTime("$dataConsulta $horaConsulta");
    $tempoLembrete = clone $consulta;
    $tempoLembrete->sub(new DateInterval("PT{$horasLembrete}H"));
    
    // Verificar se está no momento certo (± 30 minutos)
    $diff = $agora->getTimestamp() - $tempoLembrete->getTimestamp();
    
    return abs($diff) <= 1800; // 30 minutos de tolerância
}

// Função para verificar horário de funcionamento
function isHorarioFuncionamento() {
    $config = getWhatsAppConfig('whatsapp');
    $horaAtual = date('H:i');
    $inicio = $config['working_hours']['start'];
    $fim = $config['working_hours']['end'];
    
    return $horaAtual >= $inicio && $horaAtual <= $fim;
}

// =================== EXECUÇÃO PRINCIPAL ===================

logLembrete("Iniciando execução de lembretes WhatsApp");

try {
    // Verificar se está em horário de funcionamento
    if (!isHorarioFuncionamento()) {
        logLembrete("Fora do horário de funcionamento. Encerrando.", 'INFO');
        exit;
    }
    
    $dataHoje = date('Y-m-d');
    
    // Buscar confirmações que precisam de lembrete
    $query = "SELECT 
                wc.*,
                a.DATA_AGENDAMENTO,
                a.HORA_AGENDAMENTO,
                med.NOME as MEDICO_NOME,
                esp.NOME as ESPECIALIDADE_NOME,
                u.NOME_UNIDADE as UNIDADE_NOME
              FROM WHATSAPP_CONFIRMACOES wc
              JOIN AGENDAMENTOS a ON wc.AGENDAMENTO_ID = a.ID
              LEFT JOIN AGENDAS ag ON a.AGENDA_ID = ag.ID
              LEFT JOIN LAB_MEDICOS_PRES med ON ag.MEDICO_ID = med.ID
              LEFT JOIN ESPECIALIDADES esp ON a.ESPECIALIDADE_ID = esp.ID
              LEFT JOIN LAB_CIDADES u ON ag.UNIDADE_ID = u.ID
              WHERE wc.STATUS = 'confirmado'
              AND wc.DATA_CONSULTA = ?
              AND a.STATUS = 'confirmado'
              AND NOT EXISTS (
                  SELECT 1 FROM WHATSAPP_CONFIRMACOES wc2 
                  WHERE wc2.AGENDAMENTO_ID = wc.AGENDAMENTO_ID 
                  AND wc2.STATUS LIKE 'lembrete%'
              )";
    
    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt, $dataHoje);
    
    $totalEncontrados = 0;
    $totalEnviados = 0;
    $totalPulados = 0;
    $totalErros = 0;
    
    while ($confirmacao = ibase_fetch_assoc($result)) {
        $totalEncontrados++;
        
        // Verificar se deve enviar lembrete neste momento
        if (!deveEnviarLembrete($confirmacao['DATA_CONSULTA'], $confirmacao['HORA_CONSULTA'])) {
            $totalPulados++;
            continue;
        }
        
        // Verificar telefone
        $telefone = preg_replace('/[^0-9]/', '', $confirmacao['PACIENTE_TELEFONE']);
        if (strlen($telefone) < 10) {
            logLembrete("Telefone inválido para confirmação {$confirmacao['ID']}: {$confirmacao['PACIENTE_TELEFONE']}", 'WARN');
            $totalErros++;
            continue;
        }
        
        // Gerar mensagem de lembrete
        $mensagem = gerarMensagemLembrete($confirmacao);
        
        // Enviar lembrete
        $telefoneFormatado = formatarTelefone($telefone);
        $messageId = enviarLembrete($telefoneFormatado, $mensagem, $confirmacao['ID']);
        
        // Registrar resultado
        $sucesso = registrarEnvioLembrete($conn, $confirmacao['ID'], (bool)$messageId, $messageId);
        
        if ($messageId && $sucesso) {
            $totalEnviados++;
            
            // Registrar auditoria
            registrarAuditoria($conn, [
                'acao' => 'WHATSAPP_LEMBRETE',
                'usuario' => 'LEMBRETE_SYSTEM',
                'tabela_afetada' => 'WHATSAPP_CONFIRMACOES',
                'agendamento_id' => $confirmacao['AGENDAMENTO_ID'],
                'observacoes' => "Lembrete enviado para {$confirmacao['PACIENTE_NOME']} - 2h antes da consulta",
                'dados_novos' => json_encode([
                    'telefone' => $telefoneFormatado,
                    'message_id' => $messageId,
                    'hora_consulta' => $confirmacao['HORA_CONSULTA']
                ])
            ]);
            
            logLembrete("Lembrete enviado: {$confirmacao['PACIENTE_NOME']} - {$confirmacao['HORA_CONSULTA']}");
        } else {
            $totalErros++;
        }
        
        // Pausa entre envios
        sleep(2);
    }
    
    logLembrete("Execução concluída. Encontrados: $totalEncontrados, Enviados: $totalEnviados, Pulados: $totalPulados, Erros: $totalErros");
    
} catch (Exception $e) {
    logLembrete("Erro fatal na execução: " . $e->getMessage(), 'ERROR');
}

logLembrete("Finalizando execução de lembretes");
?>