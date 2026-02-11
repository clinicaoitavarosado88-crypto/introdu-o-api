<?php
// ✅ ARQUIVO: diagnostico_sistema.php
// Ferramenta de diagnóstico para o sistema de agendas

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>🔧 Diagnóstico do Sistema de Agenda</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background-color: #f5f5f5; 
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .success { 
            color: #28a745; 
            font-weight: bold; 
        }
        .error { 
            color: #dc3545; 
            font-weight: bold; 
        }
        .warning { 
            color: #ffc107; 
            font-weight: bold; 
        }
        .info { 
            color: #17a2b8; 
        }
        pre { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 5px; 
            overflow-x: auto; 
            border-left: 4px solid #007bff;
        }
        .section { 
            margin: 25px 0; 
            padding: 20px; 
            border: 1px solid #dee2e6; 
            border-radius: 8px; 
            background: #f8f9fa;
        }
        .section h3 {
            margin-top: 0;
            color: #495057;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .test-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .test-button:hover {
            background: #0056b3;
        }
        .log-output {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>🔧 Diagnóstico do Sistema de Agenda</h1>
    <p class="info">Esta ferramenta ajuda a identificar e corrigir problemas no sistema de agendas.</p>

    <?php
    $diagnostico = [];
    $erros = 0;
    $avisos = 0;
    $sucessos = 0;

    // ============================================================================
    // 1. VERIFICAR ARQUIVOS DO SISTEMA
    // ============================================================================
    
    echo "<div class='section'>";
    echo "<h3>📁 1. Verificação de Arquivos</h3>";
    
    $arquivos_necessarios = [
        'includes/connection.php' => 'Conexão com banco',
        'verificar_horario_disponivel.php' => 'Verificação de horários',
        'buscar_info_agenda.php' => 'Informações da agenda',
        'processar_encaixe.php' => 'Processamento de encaixes',
        'verificar_encaixes.php' => 'Verificação de encaixes',
        'agenda.js' => 'JavaScript principal'
    ];
    
    foreach ($arquivos_necessarios as $arquivo => $descricao) {
        if (file_exists($arquivo)) {
            echo "<p class='success'>✅ {$arquivo} - {$descricao} (" . number_format(filesize($arquivo)) . " bytes)</p>";
            $sucessos++;
        } else {
            echo "<p class='error'>❌ {$arquivo} - {$descricao} (NÃO ENCONTRADO)</p>";
            $erros++;
            
            // Verificar caminhos alternativos
            $caminhos_alternativos = ["../{$arquivo}", "../../{$arquivo}"];
            foreach ($caminhos_alternativos as $caminho) {
                if (file_exists($caminho)) {
                    echo "<p class='warning'>⚠️ Encontrado em: {$caminho}</p>";
                }
            }
        }
    }
    
    echo "</div>";

    // ============================================================================
    // 2. TESTE DE CONEXÃO COM BANCO
    // ============================================================================
    
    echo "<div class='section'>";
    echo "<h3>🔌 2. Conexão com Banco de Dados</h3>";
    
    $connection_paths = [
        'includes/connection.php',
        '../includes/connection.php',
        '../../includes/connection.php'
    ];
    
    $conn = null;
    $connection_file = null;
    
    foreach ($connection_paths as $path) {
        if (file_exists($path)) {
            try {
                include_once $path;
                if (isset($conn) && $conn) {
                    $connection_file = $path;
                    echo "<p class='success'>✅ Conexão estabelecida usando: {$path}</p>";
                    $sucessos++;
                    break;
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ Erro ao incluir {$path}: " . $e->getMessage() . "</p>";
                $erros++;
            }
        }
    }
    
    if (!$conn) {
        echo "<p class='error'>❌ Não foi possível estabelecer conexão com banco</p>";
        $erros++;
    } else {
        // Testar query básica
        try {
            $test_query = "SELECT CURRENT_TIMESTAMP FROM RDB\$DATABASE";
            $result = ibase_query($conn, $test_query);
            if ($result) {
                $row = ibase_fetch_row($result);
                echo "<p class='success'>✅ Query teste executada: " . $row[0] . "</p>";
                $sucessos++;
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Erro na query teste: " . $e->getMessage() . "</p>";
            $erros++;
        }
    }
    
    echo "</div>";

    // ============================================================================
    // 3. VERIFICAR ESTRUTURA DE TABELAS
    // ============================================================================
    
    if ($conn) {
        echo "<div class='section'>";
        echo "<h3>🗃️ 3. Estrutura do Banco de Dados</h3>";
        
        $tabelas = ['AGENDAS', 'AGENDA_HORARIOS', 'AGENDA_CONVENIOS', 'AGENDAMENTOS'];
        
        foreach ($tabelas as $tabela) {
            try {
                $query = "SELECT COUNT(*) as TOTAL FROM {$tabela}";
                $result = ibase_query($conn, $query);
                
                if ($result) {
                    $row = ibase_fetch_assoc($result);
                    echo "<p class='success'>✅ Tabela {$tabela}: {$row['TOTAL']} registros</p>";
                    $sucessos++;
                } else {
                    echo "<p class='error'>❌ Erro ao consultar tabela {$tabela}</p>";
                    $erros++;
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ Tabela {$tabela} não encontrada: " . $e->getMessage() . "</p>";
                $erros++;
            }
        }
        
        echo "</div>";
        
        // ============================================================================
        // 4. TESTAR AGENDA ESPECÍFICA
        // ============================================================================
        
        echo "<div class='section'>";
        echo "<h3>🎯 4. Teste da Agenda ID 1</h3>";
        
        try {
            // Buscar agenda
            $query_agenda = "SELECT * FROM AGENDAS WHERE ID = 1";
            $result = ibase_query($conn, $query_agenda);
            
            if ($result) {
                $agenda = ibase_fetch_assoc($result);
                if ($agenda) {
                    echo "<p class='success'>✅ Agenda encontrada:</p>";
                    echo "<pre>" . json_encode($agenda, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                    $sucessos++;
                } else {
                    echo "<p class='warning'>⚠️ Agenda ID 1 não encontrada em AGENDAS</p>";
                    $avisos++;
                    
                    // Tentar em AGENDA_HORARIOS
                    $query_horarios = "SELECT * FROM AGENDA_HORARIOS WHERE AGENDA_ID = 1";
                    $result_horarios = ibase_query($conn, $query_horarios);
                    
                    if ($result_horarios) {
                        $horarios = ibase_fetch_assoc($result_horarios);
                        if ($horarios) {
                            echo "<p class='success'>✅ Encontrada em AGENDA_HORARIOS:</p>";
                            echo "<pre>" . json_encode($horarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                            $sucessos++;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Erro ao buscar agenda: " . $e->getMessage() . "</p>";
            $erros++;
        }
        
        echo "</div>";
    }

    // ============================================================================
    // 5. TESTES DE API
    // ============================================================================
    
    echo "<div class='section'>";
    echo "<h3>🌐 5. Testes de API</h3>";
    echo "<div class='grid'>";
    
    // Teste 1: Verificar horário
    echo "<div>";
    echo "<h4>Verificar Horário Disponível</h4>";
    $url_horario = "verificar_horario_disponivel.php?agenda_id=1&data=2025-08-11&horario=14:00";
    
    if (file_exists('verificar_horario_disponivel.php')) {
        echo "<button class='test-button' onclick='testarAPI(\"{$url_horario}\", \"resultado-horario\")'>Testar</button>";
        echo "<div id='resultado-horario' class='log-output' style='display:none; margin-top:10px;'></div>";
    } else {
        echo "<p class='error'>❌ Arquivo não encontrado</p>";
    }
    echo "</div>";
    
    // Teste 2: Buscar info agenda
    echo "<div>";
    echo "<h4>Buscar Info da Agenda</h4>";
    $url_info = "buscar_info_agenda.php?agenda_id=1";
    
    if (file_exists('buscar_info_agenda.php')) {
        echo "<button class='test-button' onclick='testarAPI(\"{$url_info}\", \"resultado-info\")'>Testar</button>";
        echo "<div id='resultado-info' class='log-output' style='display:none; margin-top:10px;'></div>";
    } else {
        echo "<p class='error'>❌ Arquivo não encontrado</p>";
    }
    echo "</div>";
    
    echo "</div>";
    echo "</div>";

    // ============================================================================
    // 6. RESUMO FINAL
    // ============================================================================
    
    echo "<div class='section'>";
    echo "<h3>📊 6. Resumo do Diagnóstico</h3>";
    
    $total = $sucessos + $avisos + $erros;
    $porcentagem_sucesso = $total > 0 ? round(($sucessos / $total) * 100) : 0;
    
    echo "<div class='grid'>";
    echo "<div>";
    echo "<p><strong>Resultados:</strong></p>";
    echo "<p class='success'>✅ Sucessos: {$sucessos}</p>";
    echo "<p class='warning'>⚠️ Avisos: {$avisos}</p>";
    echo "<p class='error'>❌ Erros: {$erros}</p>";
    echo "<p><strong>Taxa de Sucesso: {$porcentagem_sucesso}%</strong></p>";
    echo "</div>";
    
    echo "<div>";
    echo "<p><strong>Recomendações:</strong></p>";
    if ($erros == 0) {
        echo "<p class='success'>🎉 Sistema funcionando corretamente!</p>";
    } else if ($erros <= 2) {
        echo "<p class='warning'>⚠️ Alguns ajustes necessários</p>";
    } else {
        echo "<p class='error'>🚨 Correções urgentes necessárias</p>";
    }
    
    if ($porcentagem_sucesso >= 80) {
        echo "<p class='info'>💡 O sistema de encaixes deve funcionar mesmo com os problemas detectados, usando fallbacks.</p>";
    }
    echo "</div>";
    echo "</div>";
    
    echo "</div>";

    // Fechar conexão
    if ($conn) {
        ibase_close($conn);
    }
    ?>
</div>

<script>
async function testarAPI(url, resultadoId) {
    const resultadoDiv = document.getElementById(resultadoId);
    resultadoDiv.style.display = 'block';
    resultadoDiv.innerHTML = '⏳ Testando API...';
    
    try {
        const response = await fetch(url);
        const text = await response.text();
        
        resultadoDiv.innerHTML = `
            <strong>Status:</strong> ${response.status} ${response.statusText}<br>
            <strong>Resposta:</strong><br>
            <pre style="white-space: pre-wrap; margin: 10px 0;">${text}</pre>
        `;
        
        // Tentar fazer parse do JSON se possível
        try {
            const json = JSON.parse(text.split('\n')[0]);
            resultadoDiv.innerHTML += `
                <strong>JSON Parsed:</strong><br>
                <pre style="white-space: pre-wrap; color: #90cdf4;">${JSON.stringify(json, null, 2)}</pre>
            `;
        } catch (e) {
            // Não é JSON válido, tudo bem
        }
        
    } catch (error) {
        resultadoDiv.innerHTML = `
            <span style="color: #f56565;">❌ Erro: ${error.message}</span>
        `;
    }
}

console.log('🔧 Diagnóstico carregado! Use as funções testarAPI() para verificar endpoints.');
</script>

</body>
</html>