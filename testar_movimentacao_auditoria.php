<?php
// testar_movimentacao_auditoria.php
// Teste da auditoria de movimentação

include 'includes/connection.php';
include 'includes/auditoria.php';

// Simular cookie do usuário
$_COOKIE['log_usuario'] = 'RENISON';

echo "<h2>🧪 Testando Auditoria de Movimentação</h2>\n";

// Buscar um agendamento existente para testar
try {
    $query = "SELECT FIRST 1 ID, NUMERO_AGENDAMENTO, PACIENTE_ID, NOME_PACIENTE, 
                     DATA_AGENDAMENTO, HORA_AGENDAMENTO, AGENDA_ID, STATUS
              FROM AGENDAMENTOS 
              WHERE STATUS = 'AGENDADO' 
              AND DATA_AGENDAMENTO >= CURRENT_DATE
              ORDER BY DATA_AGENDAMENTO, HORA_AGENDAMENTO";
    
    $result = ibase_query($conn, $query);
    
    if ($result && $agendamento = ibase_fetch_assoc($result)) {
        echo "<h3>📋 Agendamento Encontrado para Teste</h3>\n";
        echo "<ul>\n";
        echo "<li><strong>ID:</strong> {$agendamento['ID']}</li>\n";
        echo "<li><strong>Número:</strong> {$agendamento['NUMERO_AGENDAMENTO']}</li>\n";
        echo "<li><strong>Paciente:</strong> " . utf8_encode($agendamento['NOME_PACIENTE']) . "</li>\n";
        echo "<li><strong>Data atual:</strong> " . date('d/m/Y', strtotime($agendamento['DATA_AGENDAMENTO'])) . "</li>\n";
        echo "<li><strong>Hora atual:</strong> " . substr($agendamento['HORA_AGENDAMENTO'], 0, 5) . "</li>\n";
        echo "<li><strong>Status:</strong> {$agendamento['STATUS']}</li>\n";
        echo "</ul>\n";
        
        // Simular movimentação via POST
        $dados_movimento = [
            'agendamento_id' => $agendamento['ID'],
            'nova_data' => date('Y-m-d', strtotime($agendamento['DATA_AGENDAMENTO'] . ' +1 day')),
            'nova_hora' => '14:30'
        ];
        
        echo "<h3>🎯 Simulando Movimentação</h3>\n";
        echo "<p><strong>Nova Data:</strong> " . date('d/m/Y', strtotime($dados_movimento['nova_data'])) . "</p>\n";
        echo "<p><strong>Nova Hora:</strong> {$dados_movimento['nova_hora']}</p>\n";
        
        // Fazer requisição simulada
        echo "<h4>📡 Fazendo Requisição...</h4>\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://localhost/oitava/agenda/mover_agendamento.php");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados_movimento));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Cookie: log_usuario=RENISON'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $resposta = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($resposta === false) {
            echo "<p style='color: red;'>❌ Erro na requisição cURL</p>\n";
        } else {
            echo "<h4>📊 Resposta da API (HTTP {$http_code}):</h4>\n";
            echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>";
            echo htmlspecialchars($resposta);
            echo "</pre>\n";
            
            $dados_resposta = json_decode($resposta, true);
            
            if ($dados_resposta && $dados_resposta['status'] === 'sucesso') {
                echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
                echo "<h4 style='color: #155724;'>✅ MOVIMENTO REALIZADO COM SUCESSO!</h4>\n";
                echo "<p style='color: #155724;'>Agendamento movido e auditoria registrada.</p>\n";
                echo "</div>\n";
                
                // Buscar o registro de auditoria mais recente
                echo "<h4>🔍 Verificando Auditoria Registrada</h4>\n";
                
                $query_auditoria = "SELECT FIRST 1 * FROM AGENDA_AUDITORIA 
                                   WHERE AGENDAMENTO_ID = ? AND ACAO = 'MOVER'
                                   ORDER BY ID DESC";
                
                $stmt_auditoria = ibase_prepare($conn, $query_auditoria);
                $result_auditoria = ibase_execute($stmt_auditoria, $agendamento['ID']);
                
                if ($result_auditoria && $audit = ibase_fetch_assoc($result_auditoria)) {
                    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
                    echo "<h5>📋 Registro de Auditoria Encontrado:</h5>\n";
                    echo "<table border='1' style='border-collapse: collapse; font-size: 12px; width: 100%;'>\n";
                    echo "<tr><th style='padding: 5px;'>Campo</th><th style='padding: 5px;'>Valor</th></tr>\n";
                    
                    foreach ($audit as $campo => $valor) {
                        if ($valor !== null && $valor !== '') {
                            if (is_string($valor) && strlen($valor) > 100) {
                                $valor = substr($valor, 0, 100) . '...';
                            }
                            echo "<tr><td style='padding: 5px;'><strong>$campo</strong></td><td style='padding: 5px;'>" . htmlspecialchars($valor) . "</td></tr>\n";
                        }
                    }
                    
                    echo "</table>\n";
                    echo "</div>\n";
                } else {
                    echo "<p style='color: orange;'>⚠️ Registro de auditoria não encontrado</p>\n";
                }
                
            } else {
                echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
                echo "<h4 style='color: #721c24;'>❌ FALHA NA MOVIMENTAÇÃO</h4>\n";
                echo "<p style='color: #721c24;'>Erro: " . ($dados_resposta['mensagem'] ?? 'Resposta inválida') . "</p>\n";
                echo "</div>\n";
            }
        }
        
    } else {
        echo "<p style='color: red;'>❌ Nenhum agendamento disponível para teste</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>\n";
}

echo "<h3>🔗 Links Úteis</h3>\n";
echo "<ul>\n";
echo "<li><a href='auditoria.php' target='_blank'>📋 Ver Interface de Auditoria</a></li>\n";
echo "<li><a href='consultar_auditoria.php?limit=5&acao=MOVER' target='_blank'>🔍 Ver Movimentações via API</a></li>\n";
echo "</ul>\n";
?>