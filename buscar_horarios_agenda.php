<?php
// ============================================================================
// buscar_horarios_agenda.php - Buscar horários disponíveis da agenda
// ✅ Retorna horários configurados e disponibilidade
// ============================================================================

// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

// Suprimir erros
error_reporting(0);
ini_set('display_errors', 0);

function debug_log($message) {
    error_log('[HORARIOS_AGENDA] ' . date('Y-m-d H:i:s') . ' - ' . $message);
}

debug_log('=== INÍCIO buscar_horarios_agenda.php ===');

try {
    // Validar parâmetros
    $agenda_id = filter_input(INPUT_GET, 'agenda_id', FILTER_VALIDATE_INT);
    $data = filter_input(INPUT_GET, 'data', FILTER_SANITIZE_STRING);
    
    if (!$agenda_id || !$data) {
        throw new Exception('Parâmetros agenda_id e data são obrigatórios');
    }
    
    debug_log("Buscando horários para agenda $agenda_id na data $data");
    
    // Incluir conexão
    if (!file_exists('includes/connection.php')) {
        throw new Exception('Arquivo de conexão não encontrado');
    }
    
    ob_start();
    include_once 'includes/connection.php';
    ob_end_clean();
    
    if (!isset($conn)) {
        throw new Exception('Conexão não estabelecida');
    }
    
    debug_log('Conexão estabelecida');
    
    // ============================================================================
    // 1. BUSCAR CONFIGURAÇÃO DA AGENDA
    // ============================================================================
    
    $query_agenda = "SELECT 
                        ID,
                        NOME as NOME_AGENDA,
                        HORARIO_INICIO,
                        HORARIO_FIM,
                        INTERVALO_MINUTOS
                     FROM AGENDAS 
                     WHERE ID = ?";
    
    $stmt = ibase_prepare($conn, $query_agenda);
    $result = ibase_execute($stmt, $agenda_id);
    $agenda = ibase_fetch_assoc($result);
    
    if (!$agenda) {
        throw new Exception('Agenda não encontrada');
    }
    
    debug_log('Agenda encontrada: ' . $agenda['NOME_AGENDA']);
    
    // ============================================================================
    // 2. DEFINIR HORÁRIOS PADRÃO SE NÃO CONFIGURADO
    // ============================================================================
    
    $horario_inicio = $agenda['HORARIO_INICIO'] ?? '08:00:00';
    $horario_fim = $agenda['HORARIO_FIM'] ?? '17:00:00';
    $intervalo_minutos = $agenda['INTERVALO_MINUTOS'] ?? 30;
    
    // Se não tem configuração específica, usar horários padrão
    if (empty($horario_inicio) || empty($horario_fim)) {
        $horario_inicio = '08:00:00';
        $horario_fim = '17:00:00';
        $intervalo_minutos = 30;
        debug_log('Usando horários padrão');
    } else {
        debug_log("Configuração: $horario_inicio até $horario_fim, intervalo $intervalo_minutos min");
    }
    
    // ============================================================================
    // 3. GERAR LISTA DE HORÁRIOS
    // ============================================================================
    
    $horarios = [];
    
    try {
        $hora_atual = strtotime($horario_inicio);
        $hora_fim = strtotime($horario_fim);
        
        // Pausa para almoço (12:00 às 14:00)
        $pausa_inicio = strtotime('12:00:00');
        $pausa_fim = strtotime('14:00:00');
        
        while ($hora_atual <= $hora_fim) {
            $hora_formatada = date('H:i', $hora_atual);
            
            // Pular horário de almoço
            if ($hora_atual >= $pausa_inicio && $hora_atual < $pausa_fim) {
                $hora_atual = strtotime("+$intervalo_minutos minutes", $hora_atual);
                continue;
            }
            
            // Verificar se horário está ocupado
            $ocupado = false;
            try {
                $query_ocupado = "SELECT COUNT(*) as TOTAL 
                                 FROM AGENDAMENTOS 
                                 WHERE AGENDA_ID = ? 
                                 AND DATA_AGENDAMENTO = ? 
                                 AND HORA_AGENDAMENTO = ? 
                                 AND STATUS NOT IN ('CANCELADO', 'FALTOU')";
                
                $stmt_ocupado = ibase_prepare($conn, $query_ocupado);
                $result_ocupado = ibase_execute($stmt_ocupado, $agenda_id, $data, $hora_formatada . ':00');
                $ocupado_result = ibase_fetch_assoc($result_ocupado);
                
                $ocupado = ($ocupado_result['TOTAL'] > 0);
                
            } catch (Exception $e_ocupado) {
                debug_log('Erro ao verificar ocupação do horário ' . $hora_formatada . ': ' . $e_ocupado->getMessage());
                // Em caso de erro, considerar disponível
                $ocupado = false;
            }
            
            $horarios[] = [
                'hora' => $hora_formatada,
                'status' => $ocupado ? 'Ocupado' : 'Disponível',
                'ocupado' => $ocupado
            ];
            
            $hora_atual = strtotime("+$intervalo_minutos minutes", $hora_atual);
        }
        
    } catch (Exception $e_horarios) {
        debug_log('Erro ao gerar horários: ' . $e_horarios->getMessage());
        
        // Fallback: horários fixos padrão
        $horarios_padrao = [
            '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
            '11:00', '11:30', '14:00', '14:30', '15:00', '15:30',
            '16:00', '16:30', '17:00'
        ];
        
        foreach ($horarios_padrao as $hora) {
            $horarios[] = [
                'hora' => $hora,
                'status' => 'Disponível',
                'ocupado' => false
            ];
        }
        
        debug_log('Usando horários padrão fixos');
    }
    
    // ============================================================================
    // 4. RESPOSTA
    // ============================================================================
    
    $total_horarios = count($horarios);
    $horarios_ocupados = count(array_filter($horarios, function($h) { return $h['ocupado']; }));
    $horarios_disponiveis = $total_horarios - $horarios_ocupados;
    
    $response = [
        'status' => 'sucesso',
        'agenda_id' => $agenda_id,
        'data' => $data,
        'nome_agenda' => $agenda['NOME_AGENDA'],
        'total_horarios' => $total_horarios,
        'horarios_ocupados' => $horarios_ocupados,
        'horarios_disponiveis' => $horarios_disponiveis,
        'configuracao' => [
            'inicio' => $horario_inicio,
            'fim' => $horario_fim,
            'intervalo' => $intervalo_minutos
        ],
        'horarios' => $horarios
    ];
    
    debug_log("Horários gerados: $total_horarios total, $horarios_disponiveis disponíveis");
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    debug_log('ERRO: ' . $e->getMessage());
    
    // Em caso de erro, retornar horários padrão
    $horarios_padrao = [
        '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
        '11:00', '11:30', '14:00', '14:30', '15:00', '15:30',
        '16:00', '16:30', '17:00'
    ];
    
    $horarios = [];
    foreach ($horarios_padrao as $hora) {
        $horarios[] = [
            'hora' => $hora,
            'status' => 'Disponível',
            'ocupado' => false
        ];
    }
    
    $response = [
        'status' => 'sucesso',
        'agenda_id' => (int)($_GET['agenda_id'] ?? 0),
        'data' => $_GET['data'] ?? '',
        'nome_agenda' => 'Agenda',
        'total_horarios' => count($horarios),
        'horarios_ocupados' => 0,
        'horarios_disponiveis' => count($horarios),
        'configuracao' => [
            'inicio' => '08:00:00',
            'fim' => '17:00:00', 
            'intervalo' => 30
        ],
        'horarios' => $horarios,
        'erro_original' => $e->getMessage(),
        'modo_fallback' => true
    ];
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

debug_log('=== FIM buscar_horarios_agenda.php ===');

if (ob_get_level()) {
    ob_end_flush();
}
exit;
?>