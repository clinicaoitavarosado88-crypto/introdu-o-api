<?php
// dashboard_api.php - API para dados do dashboard
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include 'includes/connection.php';
include 'whatsapp_config.php';

$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($action) {
        case 'logs':
            echo json_encode(getLogs());
            break;
        case 'dashboard':
        default:
            echo json_encode(getDashboardData());
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getDashboardData() {
    global $conn;
    
    $data = [
        'stats' => getStats(),
        'system_status' => getSystemStatus(),
        'chart_status' => getChartStatus(),
        'chart_envios' => getChartEnvios(),
        'ultimas_confirmacoes' => getUltimasConfirmacoes(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    return $data;
}

function getStats() {
    global $conn;
    
    $stats = [
        'total_confirmacoes' => 0,
        'enviadas_hoje' => 0,
        'confirmadas_hoje' => 0,
        'taxa_resposta' => 0
    ];
    
    try {
        // Total de confirmações
        $query = "SELECT COUNT(*) as total FROM WHATSAPP_CONFIRMACOES";
        $result = ibase_query($conn, $query);
        if ($row = ibase_fetch_assoc($result)) {
            $stats['total_confirmacoes'] = (int)$row['TOTAL'];
        }
        
        // Enviadas hoje
        $query = "SELECT COUNT(*) as total FROM WHATSAPP_CONFIRMACOES 
                  WHERE CAST(SENT_AT AS DATE) = CURRENT_DATE 
                  AND STATUS IN ('enviado', 'confirmado', 'cancelado')";
        $result = ibase_query($conn, $query);
        if ($row = ibase_fetch_assoc($result)) {
            $stats['enviadas_hoje'] = (int)$row['TOTAL'];
        }
        
        // Confirmadas hoje
        $query = "SELECT COUNT(*) as total FROM WHATSAPP_CONFIRMACOES 
                  WHERE CAST(RESPONSE_AT AS DATE) = CURRENT_DATE 
                  AND STATUS = 'confirmado'";
        $result = ibase_query($conn, $query);
        if ($row = ibase_fetch_assoc($result)) {
            $stats['confirmadas_hoje'] = (int)$row['TOTAL'];
        }
        
        // Taxa de resposta (últimos 30 dias)
        $query = "SELECT 
                    COUNT(*) as total_enviadas,
                    SUM(CASE WHEN STATUS IN ('confirmado', 'cancelado') THEN 1 ELSE 0 END) as total_respondidas
                  FROM WHATSAPP_CONFIRMACOES 
                  WHERE SENT_AT IS NOT NULL 
                  AND SENT_AT >= CURRENT_DATE - 30";
        $result = ibase_query($conn, $query);
        if ($row = ibase_fetch_assoc($result)) {
            $enviadas = (int)$row['TOTAL_ENVIADAS'];
            $respondidas = (int)$row['TOTAL_RESPONDIDAS'];
            $stats['taxa_resposta'] = $enviadas > 0 ? round(($respondidas / $enviadas) * 100, 1) : 0;
        }
        
    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    }
    
    return $stats;
}

function getSystemStatus() {
    $config = getWhatsAppConfig('whatsapp');
    
    $status = [
        'api_online' => false,
        'webhook_working' => false,
        'cron_active' => false
    ];
    
    // Verificar API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['api_url'] . '/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $config['api_key']]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $status['api_online'] = ($httpCode == 200);
    
    // Verificar webhook (últimas 24h)
    $webhookLogFile = '/var/www/html/oitava/agenda/logs/webhook_validation.log';
    if (file_exists($webhookLogFile)) {
        $webhookLogs = file($webhookLogFile);
        $recentWebhookActivity = false;
        $oneDayAgo = time() - (24 * 60 * 60);
        
        foreach (array_reverse($webhookLogs) as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $logTime = strtotime($matches[1]);
                if ($logTime > $oneDayAgo) {
                    $recentWebhookActivity = true;
                    break;
                }
            }
        }
        $status['webhook_working'] = $recentWebhookActivity;
    }
    
    // Verificar CRON (últimas 2h)
    $cronLogFile = '/var/www/html/oitava/agenda/logs/cron_output.log';
    if (file_exists($cronLogFile)) {
        $fileModTime = filemtime($cronLogFile);
        $twoHoursAgo = time() - (2 * 60 * 60);
        $status['cron_active'] = ($fileModTime > $twoHoursAgo);
    }
    
    return $status;
}

function getChartStatus() {
    global $conn;
    
    $chartData = [
        'enviadas' => 0,
        'confirmadas' => 0,
        'canceladas' => 0,
        'pendentes' => 0
    ];
    
    try {
        $query = "SELECT 
                    STATUS,
                    COUNT(*) as total
                  FROM WHATSAPP_CONFIRMACOES 
                  WHERE CREATED_AT >= CURRENT_DATE - 30
                  GROUP BY STATUS";
        $result = ibase_query($conn, $query);
        
        while ($row = ibase_fetch_assoc($result)) {
            $status = strtolower($row['STATUS']);
            switch ($status) {
                case 'enviado':
                    $chartData['enviadas'] = (int)$row['TOTAL'];
                    break;
                case 'confirmado':
                    $chartData['confirmadas'] = (int)$row['TOTAL'];
                    break;
                case 'cancelado':
                    $chartData['canceladas'] = (int)$row['TOTAL'];
                    break;
                case 'pendente':
                    $chartData['pendentes'] = (int)$row['TOTAL'];
                    break;
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar dados do gráfico de status: " . $e->getMessage());
    }
    
    return $chartData;
}

function getChartEnvios() {
    global $conn;
    
    $chartData = [
        'labels' => [],
        'values' => []
    ];
    
    try {
        // Últimos 7 dias
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $chartData['labels'][] = date('d/m', strtotime($date));
            
            $query = "SELECT COUNT(*) as total 
                      FROM WHATSAPP_CONFIRMACOES 
                      WHERE CAST(SENT_AT AS DATE) = '$date'
                      AND STATUS IN ('enviado', 'confirmado', 'cancelado')";
            $result = ibase_query($conn, $query);
            
            if ($row = ibase_fetch_assoc($result)) {
                $chartData['values'][] = (int)$row['TOTAL'];
            } else {
                $chartData['values'][] = 0;
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar dados do gráfico de envios: " . $e->getMessage());
    }
    
    return $chartData;
}

function getUltimasConfirmacoes() {
    global $conn;
    
    $confirmacoes = [];
    
    try {
        $query = "SELECT 
                    PACIENTE_NOME,
                    PACIENTE_TELEFONE,
                    DATA_CONSULTA,
                    HORA_CONSULTA,
                    STATUS
                  FROM WHATSAPP_CONFIRMACOES 
                  ORDER BY CREATED_AT DESC 
                  ROWS 10";
        $result = ibase_query($conn, $query);
        
        while ($row = ibase_fetch_assoc($result)) {
            $confirmacoes[] = [
                'PACIENTE_NOME' => $row['PACIENTE_NOME'],
                'PACIENTE_TELEFONE' => $row['PACIENTE_TELEFONE'],
                'DATA_CONSULTA' => $row['DATA_CONSULTA'],
                'HORA_CONSULTA' => $row['HORA_CONSULTA'],
                'STATUS' => $row['STATUS']
            ];
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar últimas confirmações: " . $e->getMessage());
    }
    
    return $confirmacoes;
}

function getLogs() {
    $logFile = '/var/www/html/oitava/agenda/logs/whatsapp.log';
    $logs = [];
    
    if (file_exists($logFile)) {
        $fileLines = file($logFile);
        $recentLogs = array_slice($fileLines, -50); // Últimas 50 linhas
        
        foreach ($recentLogs as $line) {
            $logs[] = htmlspecialchars(trim($line));
        }
    } else {
        $logs[] = "[SISTEMA] Arquivo de log não encontrado: $logFile";
    }
    
    return ['logs' => $logs];
}
?>