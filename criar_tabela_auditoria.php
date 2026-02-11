<?php
// criar_tabela_auditoria.php
// Script para executar a criação da tabela de auditoria no banco

include 'includes/connection.php';

echo "<h2>🔧 Criação da Tabela de Auditoria</h2>\n";

try {
    // Ler o arquivo SQL
    $sql_file = 'sql_auditoria.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("Arquivo SQL não encontrado: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    echo "<p>📄 Arquivo SQL carregado: $sql_file</p>\n";
    
    // Separar comandos SQL (dividir por GO, COMMIT, ou ponto e vírgula duplo)
    $comandos = preg_split('/(?:COMMIT;|;;|\sGO\s)/i', $sql_content);
    $comandos = array_filter($comandos, 'trim'); // Remover comandos vazios
    
    echo "<p>🔍 Encontrados " . count($comandos) . " comandos SQL</p>\n";
    
    $sucesso = 0;
    $erros = 0;
    
    foreach ($comandos as $index => $comando) {
        $comando = trim($comando);
        if (empty($comando) || strpos($comando, '--') === 0) {
            continue; // Pular comentários e comandos vazios
        }
        
        echo "<h4>Executando comando " . ($index + 1) . ":</h4>\n";
        echo "<pre style='background: #f5f5f5; padding: 10px; font-size: 12px;'>" . htmlspecialchars(substr($comando, 0, 200)) . "...</pre>\n";
        
        try {
            $result = ibase_query($conn, $comando);
            
            if ($result) {
                echo "<p style='color: green;'>✅ Executado com sucesso</p>\n";
                $sucesso++;
            } else {
                $error = ibase_errmsg();
                echo "<p style='color: orange;'>⚠️ Warning: $error</p>\n";
                
                // Alguns erros são esperados (tabela já existe, etc.)
                if (stripos($error, 'already exists') !== false || 
                    stripos($error, 'já existe') !== false) {
                    echo "<p style='color: blue;'>ℹ️ Objeto já existe (normal)</p>\n";
                    $sucesso++;
                } else {
                    $erros++;
                }
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>\n";
            $erros++;
        }
        
        echo "<hr>\n";
    }
    
    // Testar a tabela
    echo "<h3>🧪 Testando a tabela criada</h3>\n";
    
    try {
        $test_query = "SELECT COUNT(*) as TOTAL FROM AGENDA_AUDITORIA";
        $result = ibase_query($conn, $test_query);
        
        if ($result) {
            $row = ibase_fetch_assoc($result);
            echo "<p style='color: green;'>✅ Tabela AGENDA_AUDITORIA criada com sucesso!</p>\n";
            echo "<p>📊 Total de registros existentes: {$row['TOTAL']}</p>\n";
            
            // Testar inserção de um registro de teste
            echo "<h4>Inserindo registro de teste...</h4>\n";
            
            include 'includes/auditoria.php';
            
            $teste_params = [
                'acao' => 'TESTE_INSTALACAO',
                'usuario' => 'SISTEMA',
                'tabela_afetada' => 'SISTEMA',
                'observacoes' => 'Registro de teste da instalação da auditoria em ' . date('Y-m-d H:i:s')
            ];
            
            if (registrarAuditoria($conn, $teste_params)) {
                echo "<p style='color: green;'>✅ Registro de teste inserido com sucesso!</p>\n";
                
                // Buscar o registro de teste
                $buscar_teste = ibase_query($conn, "SELECT * FROM AGENDA_AUDITORIA WHERE ACAO = 'TESTE_INSTALACAO' ORDER BY ID DESC ROWS 1");
                if ($buscar_teste) {
                    $registro_teste = ibase_fetch_assoc($buscar_teste);
                    echo "<p>🔍 Registro encontrado:</p>\n";
                    echo "<ul>\n";
                    echo "<li>ID: {$registro_teste['ID']}</li>\n";
                    echo "<li>Ação: {$registro_teste['ACAO']}</li>\n";
                    echo "<li>Usuário: {$registro_teste['USUARIO']}</li>\n";
                    echo "<li>Data: {$registro_teste['DATA_ACAO']}</li>\n";
                    echo "</ul>\n";
                }
            } else {
                echo "<p style='color: red;'>❌ Erro ao inserir registro de teste</p>\n";
            }
            
        } else {
            throw new Exception("Não foi possível acessar a tabela: " . ibase_errmsg());
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro ao testar tabela: " . $e->getMessage() . "</p>\n";
    }
    
    // Resumo final
    echo "<h3>📊 Resumo da Instalação</h3>\n";
    echo "<p>✅ Comandos executados com sucesso: <strong>$sucesso</strong></p>\n";
    echo "<p>❌ Comandos com erro: <strong>$erros</strong></p>\n";
    
    if ($erros === 0) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>\n";
        echo "<h4>🎉 INSTALAÇÃO CONCLUÍDA COM SUCESSO!</h4>\n";
        echo "<p>A tabela de auditoria foi criada e está funcionando.</p>\n";
        echo "<p><a href='auditoria.php' style='color: #155724;'><strong>📋 Acessar Interface de Auditoria</strong></a></p>\n";
        echo "</div>\n";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>\n";
        echo "<h4>⚠️ INSTALAÇÃO COM PROBLEMAS</h4>\n";
        echo "<p>Alguns comandos falharam. Verifique os erros acima.</p>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro geral: " . $e->getMessage() . "</p>\n";
}
?>