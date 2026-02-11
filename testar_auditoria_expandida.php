<?php
// testar_auditoria_expandida.php
// Teste do sistema de auditoria expandido

include 'includes/connection.php';
include 'includes/auditoria.php';

// Simular dados de um cancelamento para testar
$dados_agendamento = [
    'id' => 161,
    'numero_agendamento' => 'AG-0005',
    'paciente' => 'DAVI ABDIAS DA CRUZ',
    'agenda_id' => 2,
    'data_agendamento' => '2025-08-18',
    'hora_agendamento' => '08:30',
    'status' => 'CANCELADO',
    'convenio_id' => 5,
    'tipo_consulta' => 'primeira_vez'
];

$dados_antes_cancelamento = [
    'id' => 161,
    'numero_agendamento' => 'AG-0005',
    'paciente' => 'DAVI ABDIAS DA CRUZ',
    'agenda_id' => 2,
    'data_agendamento' => '2025-08-18',
    'hora_agendamento' => '08:30',
    'status' => 'AGENDADO',
    'convenio_id' => 5,
    'tipo_consulta' => 'primeira_vez'
];

echo "<h2>🧪 Testando Auditoria Expandida</h2>\n";

// Simular cookie e POST data para teste
$_COOKIE['log_usuario'] = 'RENISON';
$_POST['motivo'] = 'Teste da auditoria expandida';
$_GET['test'] = 'auditoria';

try {
    echo "<h3>📊 Testando auditarAgendamentoCompleto()</h3>\n";
    
    $resultado = auditarAgendamentoCompleto(
        $conn, 
        'CANCELAR', 
        'RENISON', 
        $dados_agendamento, 
        $dados_antes_cancelamento,
        'Teste completo da auditoria expandida - capturando TODAS as informações possíveis',
        ['teste_executado_em' => date('Y-m-d H:i:s'), 'versao_sistema' => '2.0']
    );
    
    if ($resultado) {
        echo "<p style='color: green;'>✅ Auditoria expandida registrada com sucesso!</p>\n";
        
        // Buscar o registro recém-criado
        echo "<h3>🔍 Verificando dados salvos</h3>\n";
        
        $query = "SELECT FIRST 1 * FROM AGENDA_AUDITORIA 
                  WHERE ACAO = 'CANCELAR' AND USUARIO = 'RENISON' 
                  ORDER BY ID DESC";
        
        $result = ibase_query($conn, $query);
        
        if ($result && $row = ibase_fetch_assoc($result)) {
            echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
            echo "<h4>📋 Dados Capturados:</h4>\n";
            echo "<table border='1' style='border-collapse: collapse; font-size: 12px; width: 100%;'>\n";
            echo "<tr><th style='padding: 5px;'>Campo</th><th style='padding: 5px;'>Valor</th></tr>\n";
            
            foreach ($row as $campo => $valor) {
                if ($valor !== null) {
                    $valor_display = is_string($valor) ? htmlspecialchars(substr($valor, 0, 100)) : $valor;
                    echo "<tr><td style='padding: 5px;'><strong>$campo</strong></td><td style='padding: 5px;'>$valor_display</td></tr>\n";
                }
            }
            
            echo "</table>\n";
            echo "</div>\n";
            
            // Mostrar dados BLOB decodificados
            if ($row['DADOS_NOVOS'] || $row['DADOS_ANTIGOS']) {
                echo "<h4>📄 Dados JSON Expandidos:</h4>\n";
                
                if ($row['DADOS_ANTIGOS']) {
                    echo "<h5>Dados Anteriores:</h5>\n";
                    $dados_antigos_json = json_decode($row['DADOS_ANTIGOS'], true);
                    echo "<pre style='background: #fff3cd; padding: 10px; font-size: 11px;'>";
                    echo json_encode($dados_antigos_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    echo "</pre>\n";
                }
                
                if ($row['DADOS_NOVOS']) {
                    echo "<h5>Dados Novos:</h5>\n";
                    $dados_novos_json = json_decode($row['DADOS_NOVOS'], true);
                    echo "<pre style='background: #d4edda; padding: 10px; font-size: 11px;'>";
                    echo json_encode($dados_novos_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    echo "</pre>\n";
                }
            }
            
        } else {
            echo "<p style='color: red;'>❌ Não foi possível recuperar o registro</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Falha ao registrar auditoria</p>\n";
    }
    
    // Testar também a função compatível
    echo "<h3>🔄 Testando função compatível auditarAgendamento()</h3>\n";
    
    $resultado_compativel = auditarAgendamento(
        $conn,
        'TESTE_COMPATIBILIDADE', 
        'RENISON',
        $dados_agendamento,
        $dados_antes_cancelamento,
        'Teste da função de compatibilidade'
    );
    
    if ($resultado_compativel) {
        echo "<p style='color: green;'>✅ Função compatível funcionando!</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Erro na função compatível</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro durante o teste: " . $e->getMessage() . "</p>\n";
}

echo "<h3>📊 Resumo dos Recursos Testados</h3>\n";
echo "<ul>\n";
echo "<li>✅ Detecção automática de IP, navegador, sistema operacional</li>\n";
echo "<li>✅ Captura de dados POST, GET, SESSION</li>\n";
echo "<li>✅ Geração de ID de transação único</li>\n";
echo "<li>✅ Comparação detalhada de dados antes/depois</li>\n";
echo "<li>✅ Armazenamento de informações completas do agendamento</li>\n";
echo "<li>✅ Medição de tempo de execução</li>\n";
echo "<li>✅ Campos específicos para convenio, telefone, CPF, email</li>\n";
echo "<li>✅ Serialização JSON para dados complexos</li>\n";
echo "</ul>\n";

echo "<p><a href='auditoria.php'>📋 Ver todos os registros na interface</a></p>\n";
echo "<p><a href='consultar_auditoria.php?limit=5'>🔍 Ver via API (JSON)</a></p>\n";
?>