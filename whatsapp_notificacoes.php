<?php
// whatsapp_notificacoes.php - Sistema de notificações para equipe
// Envia notificações para equipe sobre eventos importantes do WhatsApp

include 'includes/connection.php';
include 'includes/auditoria.php';
include 'whatsapp_config.php';

// Função para log de notificações
function logNotificacao($mensagem, $tipo = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] [$tipo] $mensagem\n";
    file_put_contents('/var/www/html/oitava/agenda/logs/notificacoes_whatsapp.log', $log, FILE_APPEND | LOCK_EX);
    
    if (php_sapi_name() === 'cli') {
        echo $log;
    }
}

// Configurações de notificação
$NOTIFICACAO_CONFIG = [
    'enabled' => true,
    'metodos' => ['email', 'whatsapp'], // email, whatsapp, sms
    'equipe_responsavel' => [
        [
            'nome' => 'Recepção',
            'email' => 'recepcao@clinicaoitava.com.br',
            'whatsapp' => '84987654321',
            'eventos' => ['cancelamento', 'reagendamento', 'falha_sistema']
        ],
        [
            'nome' => 'Coordenação',
            'email' => 'coordenacao@clinicaoitava.com.br', 
            'whatsapp' => '84987654322',
            'eventos' => ['relatorio_diario', 'falha_sistema', 'taxa_baixa']
        ],
        [
            'nome' => 'Administração',
            'email' => 'admin@clinicaoitava.com.br',
            'whatsapp' => '84987654323',
            'eventos' => ['falha_sistema', 'webhook_error', 'relatorio_diario']
        ]
    ],
    'horarios_notificacao' => [
        'inicio' => '08:00',
        'fim' => '18:00'
    ],
    'intervalos_relatorio' => [
        'resumo_manha' => '09:00',   // Resumo da manhã
        'resumo_tarde' => '14:00',   // Resumo do meio-dia
        'resumo_final' => '17:00'    // Resumo final do dia
    ]
];

// Função para verificar se está em horário de notificação
function isHorarioNotificacao() {
    global $NOTIFICACAO_CONFIG;
    $horaAtual = date('H:i');
    $inicio = $NOTIFICACAO_CONFIG['horarios_notificacao']['inicio'];
    $fim = $NOTIFICACAO_CONFIG['horarios_notificacao']['fim'];
    
    return $horaAtual >= $inicio && $horaAtual <= $fim;
}

// Função para enviar email
function enviarEmail($destinatario, $assunto, $mensagem) {
    // Configuração básica de email
    $headers = [
        'From: sistema@clinicaoitava.com.br',
        'Reply-To: noreply@clinicaoitava.com.br',
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    $mensagemCompleta = "
    <html>
    <head><title>$assunto</title></head>
    <body>
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #2563eb;'>🏥 Clínica Oitava - Sistema WhatsApp</h2>
            <div style='background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                $mensagem
            </div>
            <p style='color: #6b7280; font-size: 12px;'>
                Esta é uma notificação automática do sistema de confirmações WhatsApp.<br>
                Data/Hora: " . date('d/m/Y H:i:s') . "
            </p>
        </div>
    </body>
    </html>";
    
    return mail($destinatario, $assunto, $mensagemCompleta, implode("\r\n", $headers));
}

// Função para enviar notificação WhatsApp para equipe
function enviarWhatsAppEquipe($telefone, $mensagem) {
    $config = getWhatsAppConfig('whatsapp');
    
    $dados = [
        'number' => preg_replace('/[^0-9]/', '', $telefone) . '@s.whatsapp.net',
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
    curl_close($curl);
    
    return $httpCode === 200;
}

// Função para obter membros da equipe por evento
function obterEquipePorEvento($evento) {
    global $NOTIFICACAO_CONFIG;
    
    $equipe = [];
    foreach ($NOTIFICACAO_CONFIG['equipe_responsavel'] as $membro) {
        if (in_array($evento, $membro['eventos'])) {
            $equipe[] = $membro;
        }
    }
    
    return $equipe;
}

// Função para notificar cancelamento
function notificarCancelamento($conn, $agendamentoId) {
    if (!isHorarioNotificacao()) return;
    
    try {
        // Buscar dados do agendamento cancelado
        $query = "SELECT 
                    wc.*,
                    a.DATA_AGENDAMENTO,
                    a.HORA_AGENDAMENTO
                  FROM WHATSAPP_CONFIRMACOES wc
                  JOIN AGENDAMENTOS a ON wc.AGENDAMENTO_ID = a.ID
                  WHERE wc.AGENDAMENTO_ID = ?
                  AND wc.STATUS = 'cancelado'
                  ORDER BY wc.ID DESC
                  ROWS 1";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, $agendamentoId);
        $dados = ibase_fetch_assoc($result);
        
        if (!$dados) return;
        
        $paciente = utf8_encode($dados['PACIENTE_NOME']);
        $data = date('d/m/Y', strtotime($dados['DATA_AGENDAMENTO']));
        $hora = substr($dados['HORA_AGENDAMENTO'], 0, 5);
        $medico = utf8_encode($dados['MEDICO_NOME'] ?? 'N/A');
        
        $assunto = "🚨 Cancelamento via WhatsApp - $paciente";
        
        $mensagemEmail = "
            <h3>Cancelamento de Consulta via WhatsApp</h3>
            <p><strong>Paciente:</strong> $paciente</p>
            <p><strong>Data/Hora:</strong> $data às $hora</p>
            <p><strong>Médico:</strong> $medico</p>
            <p><strong>Cancelado em:</strong> " . date('d/m/Y H:i') . "</p>
            <p style='color: #dc2626;'><strong>Ação necessária:</strong> Verificar necessidade de reagendamento</p>
        ";
        
        $mensagemWhatsApp = "🚨 *CANCELAMENTO VIA WHATSAPP*\n\n" .
                           "👤 *Paciente:* $paciente\n" .
                           "📅 *Data:* $data às $hora\n" .
                           "👨‍⚕️ *Médico:* $medico\n\n" .
                           "⚠️ *Verificar necessidade de reagendamento*";
        
        $equipe = obterEquipePorEvento('cancelamento');
        foreach ($equipe as $membro) {
            if (in_array('email', $NOTIFICACAO_CONFIG['metodos']) && !empty($membro['email'])) {
                enviarEmail($membro['email'], $assunto, $mensagemEmail);
            }
            
            if (in_array('whatsapp', $NOTIFICACAO_CONFIG['metodos']) && !empty($membro['whatsapp'])) {
                enviarWhatsAppEquipe($membro['whatsapp'], $mensagemWhatsApp);
            }
        }
        
        logNotificacao("Notificação de cancelamento enviada para a equipe - Paciente: $paciente");
        
    } catch (Exception $e) {
        logNotificacao("Erro ao notificar cancelamento: " . $e->getMessage(), 'ERROR');
    }
}

// Função para notificar reagendamento
function notificarReagendamento($conn, $agendamentoId) {
    if (!isHorarioNotificacao()) return;
    
    try {
        $query = "SELECT 
                    wc.*,
                    a.DATA_AGENDAMENTO,
                    a.HORA_AGENDAMENTO
                  FROM WHATSAPP_CONFIRMACOES wc
                  JOIN AGENDAMENTOS a ON wc.AGENDAMENTO_ID = a.ID
                  WHERE wc.AGENDAMENTO_ID = ?
                  AND wc.STATUS = 'reagendar'
                  ORDER BY wc.ID DESC
                  ROWS 1";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, $agendamentoId);
        $dados = ibase_fetch_assoc($result);
        
        if (!$dados) return;
        
        $paciente = utf8_encode($dados['PACIENTE_NOME']);
        $data = date('d/m/Y', strtotime($dados['DATA_AGENDAMENTO']));
        $hora = substr($dados['HORA_AGENDAMENTO'], 0, 5);
        $telefone = $dados['PACIENTE_TELEFONE'];
        
        $assunto = "🔄 Solicitação de Reagendamento - $paciente";
        
        $mensagemEmail = "
            <h3>Solicitação de Reagendamento via WhatsApp</h3>
            <p><strong>Paciente:</strong> $paciente</p>
            <p><strong>Consulta atual:</strong> $data às $hora</p>
            <p><strong>Telefone:</strong> $telefone</p>
            <p><strong>Solicitado em:</strong> " . date('d/m/Y H:i') . "</p>
            <p style='color: #f59e0b;'><strong>Ação necessária:</strong> Entrar em contato para reagendar</p>
        ";
        
        $mensagemWhatsApp = "🔄 *REAGENDAMENTO SOLICITADO*\n\n" .
                           "👤 *Paciente:* $paciente\n" .
                           "📅 *Consulta atual:* $data às $hora\n" .
                           "📱 *Telefone:* $telefone\n\n" .
                           "📞 *Entrar em contato para reagendar*";
        
        $equipe = obterEquipePorEvento('reagendamento');
        foreach ($equipe as $membro) {
            if (in_array('email', $NOTIFICACAO_CONFIG['metodos']) && !empty($membro['email'])) {
                enviarEmail($membro['email'], $assunto, $mensagemEmail);
            }
            
            if (in_array('whatsapp', $NOTIFICACAO_CONFIG['metodos']) && !empty($membro['whatsapp'])) {
                enviarWhatsAppEquipe($membro['whatsapp'], $mensagemWhatsApp);
            }
        }
        
        logNotificacao("Notificação de reagendamento enviada para a equipe - Paciente: $paciente");
        
    } catch (Exception $e) {
        logNotificacao("Erro ao notificar reagendamento: " . $e->getMessage(), 'ERROR');
    }
}

// Função para enviar relatório diário
function enviarRelatorioDiario($conn) {
    try {
        $hoje = date('Y-m-d');
        
        // Buscar estatísticas do dia
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN STATUS = 'enviado' THEN 1 ELSE 0 END) as enviados,
                    SUM(CASE WHEN STATUS = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
                    SUM(CASE WHEN STATUS = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                    SUM(CASE WHEN STATUS = 'reagendar' THEN 1 ELSE 0 END) as reagendar,
                    SUM(CASE WHEN STATUS = 'erro' THEN 1 ELSE 0 END) as erros
                  FROM WHATSAPP_CONFIRMACOES 
                  WHERE DATA_CONSULTA = ?";
        
        $stmt = ibase_prepare($conn, $query);
        $result = ibase_execute($stmt, $hoje);
        $stats = ibase_fetch_assoc($result);
        
        $total = (int)$stats['TOTAL'];
        $confirmados = (int)$stats['CONFIRMADOS'];
        $cancelados = (int)$stats['CANCELADOS'];
        $reagendar = (int)$stats['REAGENDAR'];
        $enviados = (int)$stats['ENVIADOS'];
        $erros = (int)$stats['ERROS'];
        
        $taxaConfirmacao = $total > 0 ? round(($confirmados / $total) * 100, 1) : 0;
        $taxaResposta = $total > 0 ? round((($confirmados + $cancelados + $reagendar) / $total) * 100, 1) : 0;
        
        $assunto = "📊 Relatório Diário WhatsApp - " . date('d/m/Y');
        
        $mensagemEmail = "
            <h3>Relatório de Confirmações WhatsApp</h3>
            <h4>Resumo do dia " . date('d/m/Y') . "</h4>
            
            <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
                <tr style='background: #f3f4f6;'>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'><strong>Total de Mensagens</strong></td>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'>$total</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'>Enviadas</td>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'>$enviados</td>
                </tr>
                <tr style='background: #f0fdf4;'>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'>✅ Confirmadas</td>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'>$confirmados</td>
                </tr>
                <tr style='background: #fef2f2;'>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'>❌ Canceladas</td>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'>$cancelados</td>
                </tr>
                <tr style='background: #fffbeb;'>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'>🔄 Reagendar</td>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'>$reagendar</td>
                </tr>
                <tr style='background: #f3f4f6;'>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'><strong>Taxa de Confirmação</strong></td>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'><strong>{$taxaConfirmacao}%</strong></td>
                </tr>
                <tr style='background: #f3f4f6;'>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'><strong>Taxa de Resposta</strong></td>
                    <td style='border: 1px solid #d1d5db; padding: 8px;'><strong>{$taxaResposta}%</strong></td>
                </tr>
            </table>
        ";
        
        if ($erros > 0) {
            $mensagemEmail .= "<p style='color: #dc2626;'><strong>⚠️ Atenção:</strong> $erros erro(s) no envio de mensagens</p>";
        }
        
        $mensagemWhatsApp = "📊 *RELATÓRIO DIÁRIO WHATSAPP*\n" .
                           "_" . date('d/m/Y') . "_\n\n" .
                           "📤 *Total enviadas:* $enviados\n" .
                           "✅ *Confirmadas:* $confirmados\n" .
                           "❌ *Canceladas:* $cancelados\n" .
                           "🔄 *Reagendar:* $reagendar\n\n" .
                           "📈 *Taxa confirmação:* {$taxaConfirmacao}%\n" .
                           "📊 *Taxa resposta:* {$taxaResposta}%";
        
        if ($erros > 0) {
            $mensagemWhatsApp .= "\n\n⚠️ *$erros erro(s) no envio*";
        }
        
        $equipe = obterEquipePorEvento('relatorio_diario');
        foreach ($equipe as $membro) {
            if (in_array('email', $NOTIFICACAO_CONFIG['metodos']) && !empty($membro['email'])) {
                enviarEmail($membro['email'], $assunto, $mensagemEmail);
            }
            
            if (in_array('whatsapp', $NOTIFICACAO_CONFIG['metodos']) && !empty($membro['whatsapp'])) {
                enviarWhatsAppEquipe($membro['whatsapp'], $mensagemWhatsApp);
            }
        }
        
        logNotificacao("Relatório diário enviado para a equipe - Total: $total, Confirmados: $confirmados");
        
    } catch (Exception $e) {
        logNotificacao("Erro ao enviar relatório diário: " . $e->getMessage(), 'ERROR');
    }
}

// Função para verificar e enviar relatórios programados
function verificarRelatoriosProgramados($conn) {
    global $NOTIFICACAO_CONFIG;
    
    $horaAtual = date('H:i');
    
    foreach ($NOTIFICACAO_CONFIG['intervalos_relatorio'] as $tipo => $hora) {
        // Verificar se está na hora (± 5 minutos)
        $horaRelatorio = strtotime($hora);
        $horaAgora = strtotime($horaAtual);
        $diferenca = abs($horaAgora - $horaRelatorio);
        
        if ($diferenca <= 300) { // 5 minutos de tolerância
            enviarRelatorioDiario($conn);
            break; // Enviar apenas um relatório por execução
        }
    }
}

// API para notificações manuais
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $acao = $data['acao'] ?? '';
    
    try {
        switch ($acao) {
            case 'cancelamento':
                $agendamentoId = $data['agendamento_id'] ?? 0;
                notificarCancelamento($conn, $agendamentoId);
                echo json_encode(['success' => true, 'message' => 'Notificação de cancelamento enviada']);
                break;
                
            case 'reagendamento':
                $agendamentoId = $data['agendamento_id'] ?? 0;
                notificarReagendamento($conn, $agendamentoId);
                echo json_encode(['success' => true, 'message' => 'Notificação de reagendamento enviada']);
                break;
                
            case 'relatorio_diario':
                enviarRelatorioDiario($conn);
                echo json_encode(['success' => true, 'message' => 'Relatório diário enviado']);
                break;
                
            case 'relatorios_programados':
                verificarRelatoriosProgramados($conn);
                echo json_encode(['success' => true, 'message' => 'Verificação de relatórios programados executada']);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Ação não reconhecida']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}

// Execução via CRON
if (php_sapi_name() === 'cli') {
    logNotificacao("Iniciando verificação de relatórios programados");
    verificarRelatoriosProgramados($conn);
    logNotificacao("Finalizando verificação de relatórios programados");
}
?>