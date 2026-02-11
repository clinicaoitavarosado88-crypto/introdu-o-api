<?php
// ============================================================================
// CONSULTAS ÚTEIS PARA TABELA AGENDAMENTO_EXAMES
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
require_once 'includes/connection.php';

// Suporte para linha de comando e web
if (php_sapi_name() === 'cli') {
    parse_str(getenv('QUERY_STRING') ?: '', $_GET);
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar_exames_agendamento':
            // Listar exames de um agendamento específico
            $numero_agendamento = $_GET['numero_agendamento'] ?? '';
            
            if (empty($numero_agendamento)) {
                throw new Exception('Número do agendamento não fornecido');
            }
            
            $sql = "SELECT 
                        ae.ID,
                        ae.NUMERO_AGENDAMENTO,
                        ae.EXAME_ID,
                        ae.DATA_INCLUSAO,
                        ae.OBSERVACOES,
                        le.EXAME as NOME_EXAME
                    FROM AGENDAMENTO_EXAMES ae
                    LEFT JOIN LAB_EXAMES le ON le.IDEXAME = ae.EXAME_ID
                    WHERE ae.NUMERO_AGENDAMENTO = ?
                    ORDER BY ae.ID";
            
            $stmt = ibase_prepare($conn, $sql);
            $result = ibase_execute($stmt, $numero_agendamento);
            
            $exames = [];
            while ($row = ibase_fetch_assoc($result)) {
                $exames[] = [
                    'id' => (int)$row['ID'],
                    'numero_agendamento' => trim($row['NUMERO_AGENDAMENTO']),
                    'exame_id' => (int)$row['EXAME_ID'],
                    'nome_exame' => utf8_encode(trim($row['NOME_EXAME'] ?? 'Exame não encontrado')),
                    'data_inclusao' => $row['DATA_INCLUSAO'],
                    'observacoes' => utf8_encode(trim($row['OBSERVACOES'] ?? ''))
                ];
            }
            
            echo json_encode([
                'status' => 'sucesso',
                'numero_agendamento' => $numero_agendamento,
                'total_exames' => count($exames),
                'exames' => $exames
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'estatisticas':
            // Estatísticas gerais da tabela
            $queries = [
                'total_relacionamentos' => "SELECT COUNT(*) as TOTAL FROM AGENDAMENTO_EXAMES",
                'agendamentos_com_exames' => "SELECT COUNT(DISTINCT NUMERO_AGENDAMENTO) as TOTAL FROM AGENDAMENTO_EXAMES",
                'exames_mais_usados' => "SELECT ae.EXAME_ID, le.EXAME, COUNT(*) as QUANTIDADE 
                                        FROM AGENDAMENTO_EXAMES ae
                                        LEFT JOIN LAB_EXAMES le ON le.IDEXAME = ae.EXAME_ID
                                        GROUP BY ae.EXAME_ID, le.EXAME 
                                        ORDER BY COUNT(*) DESC 
                                        ROWS 10",
                'agendamentos_recentes' => "SELECT ae.NUMERO_AGENDAMENTO, COUNT(*) as TOTAL_EXAMES, MAX(ae.DATA_INCLUSAO) as ULTIMA_INCLUSAO
                                           FROM AGENDAMENTO_EXAMES ae
                                           GROUP BY ae.NUMERO_AGENDAMENTO
                                           ORDER BY MAX(ae.DATA_INCLUSAO) DESC
                                           ROWS 10"
            ];
            
            $stats = [];
            
            foreach ($queries as $key => $sql) {
                $result = ibase_query($conn, $sql);
                
                if ($key === 'total_relacionamentos' || $key === 'agendamentos_com_exames') {
                    $row = ibase_fetch_assoc($result);
                    $stats[$key] = (int)$row['TOTAL'];
                } else {
                    $items = [];
                    while ($row = ibase_fetch_assoc($result)) {
                        if ($key === 'exames_mais_usados') {
                            $items[] = [
                                'exame_id' => (int)$row['EXAME_ID'],
                                'nome_exame' => utf8_encode(trim($row['EXAME'] ?? 'Sem nome')),
                                'quantidade' => (int)$row['QUANTIDADE']
                            ];
                        } else {
                            $items[] = [
                                'numero_agendamento' => trim($row['NUMERO_AGENDAMENTO']),
                                'total_exames' => (int)$row['TOTAL_EXAMES'],
                                'ultima_inclusao' => $row['ULTIMA_INCLUSAO']
                            ];
                        }
                    }
                    $stats[$key] = $items;
                }
            }
            
            echo json_encode([
                'status' => 'sucesso',
                'estatisticas' => $stats
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'verificar_duplicatas':
            // Verificar se existem duplicatas (não deveria haver devido ao índice único)
            $sql = "SELECT NUMERO_AGENDAMENTO, EXAME_ID, COUNT(*) as DUPLICATAS
                    FROM AGENDAMENTO_EXAMES 
                    GROUP BY NUMERO_AGENDAMENTO, EXAME_ID
                    HAVING COUNT(*) > 1";
            
            $result = ibase_query($conn, $sql);
            $duplicatas = [];
            
            while ($row = ibase_fetch_assoc($result)) {
                $duplicatas[] = [
                    'numero_agendamento' => trim($row['NUMERO_AGENDAMENTO']),
                    'exame_id' => (int)$row['EXAME_ID'],
                    'quantidade_duplicatas' => (int)$row['DUPLICATAS']
                ];
            }
            
            echo json_encode([
                'status' => 'sucesso',
                'total_duplicatas' => count($duplicatas),
                'duplicatas' => $duplicatas,
                'mensagem' => count($duplicatas) > 0 ? 'ATENÇÃO: Duplicatas encontradas!' : 'Nenhuma duplicata encontrada'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'limpar_orfaos':
            // Limpar relacionamentos órfãos (agendamentos que não existem mais)
            $sql_orfaos = "DELETE FROM AGENDAMENTO_EXAMES 
                          WHERE NUMERO_AGENDAMENTO NOT IN (
                              SELECT NUMERO_AGENDAMENTO FROM AGENDAMENTOS
                          )";
            
            $result = ibase_query($conn, $sql_orfaos);
            $affected = ibase_affected_rows($conn);
            
            echo json_encode([
                'status' => 'sucesso',
                'relacionamentos_removidos' => $affected,
                'mensagem' => "Limpeza concluída: $affected relacionamentos órfãos removidos"
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            // Menu de opções disponíveis
            echo json_encode([
                'status' => 'info',
                'mensagem' => 'Consultas disponíveis para AGENDAMENTO_EXAMES',
                'opcoes' => [
                    'listar_exames_agendamento' => 'Listar exames de um agendamento específico (requer: numero_agendamento)',
                    'estatisticas' => 'Estatísticas gerais da tabela',
                    'verificar_duplicatas' => 'Verificar se existem relacionamentos duplicados',
                    'limpar_orfaos' => 'Remover relacionamentos de agendamentos que não existem mais'
                ],
                'exemplos' => [
                    '?action=listar_exames_agendamento&numero_agendamento=AGD-0001',
                    '?action=estatisticas',
                    '?action=verificar_duplicatas',
                    '?action=limpar_orfaos'
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
        'action' => $action
    ], JSON_UNESCAPED_UNICODE);
}
?>