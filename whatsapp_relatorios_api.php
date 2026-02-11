<?php
// whatsapp_relatorios_api.php - API para dados dos relatórios avançados
header('Content-Type: application/json');
include 'includes/connection.php';
include 'includes/verificar_permissao.php';

// Verificar permissão
if (!verificarPermissaoAdminAgenda($conn, 'acessar relatórios WhatsApp')) {
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'detalhes':
        try {
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
            $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
            
            $query = "SELECT 
                        DATA_CONSULTA as data,
                        COUNT(*) as enviados,
                        SUM(CASE WHEN STATUS = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
                        SUM(CASE WHEN STATUS = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                        SUM(CASE WHEN STATUS = 'reagendar' THEN 1 ELSE 0 END) as reagendar,
                        SUM(CASE WHEN STATUS = 'erro' THEN 1 ELSE 0 END) as erros
                      FROM WHATSAPP_CONFIRMACOES 
                      WHERE DATA_CONSULTA BETWEEN ? AND ?
                      AND STATUS != 'teste'
                      GROUP BY DATA_CONSULTA
                      ORDER BY DATA_CONSULTA DESC";
            
            $stmt = ibase_prepare($conn, $query);
            $result = ibase_execute($stmt, $dataInicio, $dataFim);
            
            $detalhes = [];
            while ($row = ibase_fetch_assoc($result)) {
                $detalhes[] = [
                    'data' => $row['DATA'],
                    'enviados' => (int)$row['ENVIADOS'],
                    'confirmados' => (int)$row['CONFIRMADOS'],
                    'cancelados' => (int)$row['CANCELADOS'],
                    'reagendar' => (int)$row['REAGENDAR'],
                    'erros' => (int)$row['ERROS']
                ];
            }
            
            echo json_encode($detalhes);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'especialidades':
        try {
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
            $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
            
            $query = "SELECT 
                        COALESCE(wc.ESPECIALIDADE_NOME, 'Não informado') as especialidade,
                        COUNT(*) as enviados,
                        SUM(CASE WHEN wc.STATUS = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
                        SUM(CASE WHEN wc.STATUS = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                        ROUND(
                            CASE WHEN COUNT(*) > 0 
                            THEN (CAST(SUM(CASE WHEN wc.STATUS = 'confirmado' THEN 1 ELSE 0 END) AS FLOAT) / COUNT(*)) * 100 
                            ELSE 0 END, 1
                        ) as taxa_confirmacao
                      FROM WHATSAPP_CONFIRMACOES wc
                      WHERE wc.DATA_CONSULTA BETWEEN ? AND ?
                      AND wc.STATUS != 'teste'
                      GROUP BY wc.ESPECIALIDADE_NOME
                      HAVING COUNT(*) > 0
                      ORDER BY confirmados DESC";
            
            $stmt = ibase_prepare($conn, $query);
            $result = ibase_execute($stmt, $dataInicio, $dataFim);
            
            $especialidades = [];
            while ($row = ibase_fetch_assoc($result)) {
                $especialidades[] = [
                    'especialidade' => utf8_encode(trim($row['ESPECIALIDADE'])),
                    'enviados' => (int)$row['ENVIADOS'],
                    'confirmados' => (int)$row['CONFIRMADOS'],
                    'cancelados' => (int)$row['CANCELADOS'],
                    'taxa_confirmacao' => (float)$row['TAXA_CONFIRMACAO']
                ];
            }
            
            echo json_encode($especialidades);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'medicos':
        try {
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
            $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
            
            $query = "SELECT 
                        COALESCE(wc.MEDICO_NOME, 'Não informado') as medico,
                        COUNT(*) as enviados,
                        SUM(CASE WHEN wc.STATUS = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
                        SUM(CASE WHEN wc.STATUS = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                        ROUND(
                            CASE WHEN COUNT(*) > 0 
                            THEN (CAST(SUM(CASE WHEN wc.STATUS = 'confirmado' THEN 1 ELSE 0 END) AS FLOAT) / COUNT(*)) * 100 
                            ELSE 0 END, 1
                        ) as taxa_confirmacao
                      FROM WHATSAPP_CONFIRMACOES wc
                      WHERE wc.DATA_CONSULTA BETWEEN ? AND ?
                      AND wc.STATUS != 'teste'
                      GROUP BY wc.MEDICO_NOME
                      HAVING COUNT(*) > 0
                      ORDER BY confirmados DESC";
            
            $stmt = ibase_prepare($conn, $query);
            $result = ibase_execute($stmt, $dataInicio, $dataFim);
            
            $medicos = [];
            while ($row = ibase_fetch_assoc($result)) {
                $medicos[] = [
                    'medico' => utf8_encode(trim($row['MEDICO'])),
                    'enviados' => (int)$row['ENVIADOS'],
                    'confirmados' => (int)$row['CONFIRMADOS'],
                    'cancelados' => (int)$row['CANCELADOS'],
                    'taxa_confirmacao' => (float)$row['TAXA_CONFIRMACAO']
                ];
            }
            
            echo json_encode($medicos);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'horarios':
        try {
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
            $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
            
            $query = "SELECT 
                        EXTRACT(HOUR FROM wc.HORA_CONSULTA) as hora,
                        COUNT(*) as enviados,
                        SUM(CASE WHEN wc.STATUS = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
                        SUM(CASE WHEN wc.STATUS = 'cancelado' THEN 1 ELSE 0 END) as cancelados
                      FROM WHATSAPP_CONFIRMACOES wc
                      WHERE wc.DATA_CONSULTA BETWEEN ? AND ?
                      AND wc.STATUS != 'teste'
                      GROUP BY EXTRACT(HOUR FROM wc.HORA_CONSULTA)
                      ORDER BY hora";
            
            $stmt = ibase_prepare($conn, $query);
            $result = ibase_execute($stmt, $dataInicio, $dataFim);
            
            $horarios = [];
            while ($row = ibase_fetch_assoc($result)) {
                $horarios[] = [
                    'hora' => (int)$row['HORA'] . ':00',
                    'enviados' => (int)$row['ENVIADOS'],
                    'confirmados' => (int)$row['CONFIRMADOS'],
                    'cancelados' => (int)$row['CANCELADOS']
                ];
            }
            
            echo json_encode($horarios);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'exportar':
        try {
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
            $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
            $format = $_GET['format'] ?? 'csv';
            
            $query = "SELECT 
                        wc.DATA_CONSULTA,
                        wc.HORA_CONSULTA,
                        wc.PACIENTE_NOME,
                        wc.PACIENTE_TELEFONE,
                        wc.MEDICO_NOME,
                        wc.ESPECIALIDADE_NOME,
                        wc.UNIDADE_NOME,
                        wc.STATUS,
                        wc.DATA_ENVIO,
                        wc.DATA_RESPOSTA,
                        wc.RESPOSTA_PACIENTE,
                        wc.TENTATIVAS
                      FROM WHATSAPP_CONFIRMACOES wc
                      WHERE wc.DATA_CONSULTA BETWEEN ? AND ?
                      AND wc.STATUS != 'teste'
                      ORDER BY wc.DATA_CONSULTA DESC, wc.HORA_CONSULTA DESC";
            
            $stmt = ibase_prepare($conn, $query);
            $result = ibase_execute($stmt, $dataInicio, $dataFim);
            
            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="relatorio_whatsapp_' . $dataInicio . '_' . $dataFim . '.csv"');
                
                // Cabeçalho CSV
                echo "Data Consulta,Hora,Paciente,Telefone,Médico,Especialidade,Unidade,Status,Data Envio,Data Resposta,Resposta,Tentativas\n";
                
                // Dados
                while ($row = ibase_fetch_assoc($result)) {
                    echo sprintf('"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%d"' . "\n",
                        $row['DATA_CONSULTA'],
                        substr($row['HORA_CONSULTA'], 0, 5),
                        utf8_encode(trim($row['PACIENTE_NOME'] ?? '')),
                        $row['PACIENTE_TELEFONE'],
                        utf8_encode(trim($row['MEDICO_NOME'] ?? '')),
                        utf8_encode(trim($row['ESPECIALIDADE_NOME'] ?? '')),
                        utf8_encode(trim($row['UNIDADE_NOME'] ?? '')),
                        $row['STATUS'],
                        $row['DATA_ENVIO'] ?? '',
                        $row['DATA_RESPOSTA'] ?? '',
                        $row['RESPOSTA_PACIENTE'] ?? '',
                        (int)$row['TENTATIVAS']
                    );
                }
            } else {
                // JSON
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="relatorio_whatsapp_' . $dataInicio . '_' . $dataFim . '.json"');
                
                $dados = [];
                while ($row = ibase_fetch_assoc($result)) {
                    $dados[] = [
                        'data_consulta' => $row['DATA_CONSULTA'],
                        'hora_consulta' => substr($row['HORA_CONSULTA'], 0, 5),
                        'paciente_nome' => utf8_encode(trim($row['PACIENTE_NOME'] ?? '')),
                        'paciente_telefone' => $row['PACIENTE_TELEFONE'],
                        'medico_nome' => utf8_encode(trim($row['MEDICO_NOME'] ?? '')),
                        'especialidade_nome' => utf8_encode(trim($row['ESPECIALIDADE_NOME'] ?? '')),
                        'unidade_nome' => utf8_encode(trim($row['UNIDADE_NOME'] ?? '')),
                        'status' => $row['STATUS'],
                        'data_envio' => $row['DATA_ENVIO'],
                        'data_resposta' => $row['DATA_RESPOSTA'],
                        'resposta_paciente' => $row['RESPOSTA_PACIENTE'],
                        'tentativas' => (int)$row['TENTATIVAS']
                    ];
                }
                
                echo json_encode($dados, JSON_PRETTY_PRINT);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'dashboard':
        try {
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
            $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
            
            // Estatísticas gerais
            $queryStats = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN STATUS = 'enviado' THEN 1 ELSE 0 END) as enviados,
                            SUM(CASE WHEN STATUS = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
                            SUM(CASE WHEN STATUS = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                            SUM(CASE WHEN STATUS = 'reagendar' THEN 1 ELSE 0 END) as reagendar,
                            SUM(CASE WHEN STATUS = 'erro' THEN 1 ELSE 0 END) as erros
                          FROM WHATSAPP_CONFIRMACOES 
                          WHERE DATA_CONSULTA BETWEEN ? AND ?
                          AND STATUS != 'teste'";
            
            $stmtStats = ibase_prepare($conn, $queryStats);
            $resultStats = ibase_execute($stmtStats, $dataInicio, $dataFim);
            $stats = ibase_fetch_assoc($resultStats);
            
            // Top 5 especialidades
            $queryTop = "SELECT 
                          COALESCE(ESPECIALIDADE_NOME, 'Não informado') as especialidade,
                          COUNT(*) as total,
                          SUM(CASE WHEN STATUS = 'confirmado' THEN 1 ELSE 0 END) as confirmados
                        FROM WHATSAPP_CONFIRMACOES 
                        WHERE DATA_CONSULTA BETWEEN ? AND ?
                        AND STATUS != 'teste'
                        GROUP BY ESPECIALIDADE_NOME
                        ORDER BY total DESC
                        ROWS 5";
            
            $stmtTop = ibase_prepare($conn, $queryTop);
            $resultTop = ibase_execute($stmtTop, $dataInicio, $dataFim);
            
            $topEspecialidades = [];
            while ($row = ibase_fetch_assoc($resultTop)) {
                $topEspecialidades[] = [
                    'especialidade' => utf8_encode(trim($row['ESPECIALIDADE'])),
                    'total' => (int)$row['TOTAL'],
                    'confirmados' => (int)$row['CONFIRMADOS']
                ];
            }
            
            $dashboard = [
                'stats' => [
                    'total' => (int)$stats['TOTAL'],
                    'enviados' => (int)$stats['ENVIADOS'],
                    'confirmados' => (int)$stats['CONFIRMADOS'],
                    'cancelados' => (int)$stats['CANCELADOS'],
                    'reagendar' => (int)$stats['REAGENDAR'],
                    'erros' => (int)$stats['ERROS']
                ],
                'top_especialidades' => $topEspecialidades,
                'periodo' => [
                    'inicio' => $dataInicio,
                    'fim' => $dataFim
                ]
            ];
            
            echo json_encode($dashboard);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ação não reconhecida']);
        break;
}
?>