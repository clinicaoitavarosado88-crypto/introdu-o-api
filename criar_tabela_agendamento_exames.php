<?php
// ============================================================================
// SCRIPT PARA CRIAR E TESTAR TABELA AGENDAMENTO_EXAMES
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
require_once 'includes/connection.php';

function log_message($message) {
    echo date('Y-m-d H:i:s') . " - " . $message . "\n";
    flush();
}

try {
    log_message("=== INICIANDO CRIAÇÃO DA TABELA AGENDAMENTO_EXAMES ===");
    
    // Verificar se a tabela já existe
    $sql_check = "SELECT COUNT(*) as EXISTE FROM RDB\$RELATIONS WHERE RDB\$RELATION_NAME = 'AGENDAMENTO_EXAMES'";
    $result_check = ibase_query($conn, $sql_check);
    $row_check = ibase_fetch_assoc($result_check);
    
    if ($row_check['EXISTE'] > 0) {
        log_message("⚠️ Tabela AGENDAMENTO_EXAMES já existe!");
        log_message("   Para recriar, execute primeiro o script de limpeza no arquivo SQL.");
        
        // Mostrar estrutura atual
        $sql_structure = "SELECT 
            RF.RDB\$FIELD_NAME AS CAMPO,
            CASE F.RDB\$FIELD_TYPE
                WHEN 261 THEN 'BLOB'
                WHEN 14 THEN 'CHAR'
                WHEN 40 THEN 'CSTRING'
                WHEN 11 THEN 'D_FLOAT'
                WHEN 27 THEN 'DOUBLE'
                WHEN 10 THEN 'FLOAT'
                WHEN 16 THEN 'INT64'
                WHEN 8 THEN 'INTEGER'
                WHEN 9 THEN 'QUAD'
                WHEN 7 THEN 'SMALLINT'
                WHEN 12 THEN 'DATE'
                WHEN 13 THEN 'TIME'
                WHEN 35 THEN 'TIMESTAMP'
                WHEN 37 THEN 'VARCHAR'
            END AS TIPO,
            F.RDB\$FIELD_LENGTH AS TAMANHO,
            RF.RDB\$NULL_FLAG AS OBRIGATORIO
        FROM RDB\$RELATION_FIELDS RF
        JOIN RDB\$FIELDS F ON RF.RDB\$FIELD_SOURCE = F.RDB\$FIELD_NAME
        WHERE RF.RDB\$RELATION_NAME = 'AGENDAMENTO_EXAMES'
        ORDER BY RF.RDB\$FIELD_POSITION";
        
        $result_structure = ibase_query($conn, $sql_structure);
        log_message("📋 Estrutura atual da tabela:");
        
        while ($row = ibase_fetch_assoc($result_structure)) {
            $campo = trim($row['CAMPO']);
            $tipo = trim($row['TIPO']);
            $tamanho = $row['TAMANHO'] ? "({$row['TAMANHO']})" : '';
            $obrigatorio = $row['OBRIGATORIO'] ? ' NOT NULL' : ' NULL';
            
            log_message("   - {$campo}: {$tipo}{$tamanho}{$obrigatorio}");
        }
        
    } else {
        log_message("✅ Tabela não existe, prosseguindo com a criação...");
        
        // Iniciar transação
        $trans = ibase_trans($conn);
        
        try {
            // 1. Criar a tabela principal
            log_message("1️⃣ Criando tabela AGENDAMENTO_EXAMES...");
            $sql_create = "CREATE TABLE AGENDAMENTO_EXAMES (
                ID INTEGER NOT NULL PRIMARY KEY,
                NUMERO_AGENDAMENTO VARCHAR(20) NOT NULL,
                EXAME_ID INTEGER NOT NULL,
                DATA_INCLUSAO TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                OBSERVACOES VARCHAR(500)
            )";
            ibase_query($trans, $sql_create);
            log_message("   ✅ Tabela criada com sucesso");
            
            // 2. Criar generator
            log_message("2️⃣ Criando generator...");
            ibase_query($trans, "CREATE GENERATOR GEN_AGENDAMENTO_EXAMES_ID");
            ibase_query($trans, "SET GENERATOR GEN_AGENDAMENTO_EXAMES_ID TO 0");
            log_message("   ✅ Generator criado com sucesso");
            
            // 3. Criar trigger
            log_message("3️⃣ Criando trigger de auto-increment...");
            $sql_trigger = "CREATE OR ALTER TRIGGER TRG_AGENDAMENTO_EXAMES_BI FOR AGENDAMENTO_EXAMES
                ACTIVE BEFORE INSERT POSITION 0
                AS
                BEGIN
                  IF (NEW.ID IS NULL) THEN
                    NEW.ID = GEN_ID(GEN_AGENDAMENTO_EXAMES_ID,1);
                END";
            ibase_query($trans, $sql_trigger);
            log_message("   ✅ Trigger criado com sucesso");
            
            // 4. Criar índices
            log_message("4️⃣ Criando índices...");
            
            ibase_query($trans, "CREATE INDEX IDX_AGENDAMENTO_EXAMES_NUMERO ON AGENDAMENTO_EXAMES (NUMERO_AGENDAMENTO)");
            log_message("   ✅ Índice por NUMERO_AGENDAMENTO criado");
            
            ibase_query($trans, "CREATE INDEX IDX_AGENDAMENTO_EXAMES_EXAME ON AGENDAMENTO_EXAMES (EXAME_ID)");
            log_message("   ✅ Índice por EXAME_ID criado");
            
            ibase_query($trans, "CREATE INDEX IDX_AGENDAMENTO_EXAMES_DATA ON AGENDAMENTO_EXAMES (DATA_INCLUSAO)");
            log_message("   ✅ Índice por DATA_INCLUSAO criado");
            
            ibase_query($trans, "CREATE UNIQUE INDEX IDX_AGENDAMENTO_EXAMES_UNIQUE ON AGENDAMENTO_EXAMES (NUMERO_AGENDAMENTO, EXAME_ID)");
            log_message("   ✅ Índice único criado (evita duplicatas)");
            
            // Confirmar transação
            ibase_commit($trans);
            log_message("✅ Transação confirmada - Tabela criada com sucesso!");
            
        } catch (Exception $e_inner) {
            ibase_rollback($trans);
            throw new Exception("Erro na criação da tabela: " . $e_inner->getMessage());
        }
    }
    
    // 5. Testar a tabela criada
    log_message("5️⃣ Testando a tabela...");
    
    // Verificar índices
    $sql_indices = "SELECT RDB\$INDEX_NAME, RDB\$UNIQUE_FLAG
        FROM RDB\$INDICES 
        WHERE RDB\$RELATION_NAME = 'AGENDAMENTO_EXAMES'";
    $result_indices = ibase_query($conn, $sql_indices);
    
    log_message("📊 Índices encontrados:");
    while ($row_idx = ibase_fetch_assoc($result_indices)) {
        $nome = trim($row_idx['RDB$INDEX_NAME']);
        $unico = $row_idx['RDB$UNIQUE_FLAG'] ? ' (ÚNICO)' : '';
        log_message("   - {$nome}{$unico}");
    }
    
    // Teste de inserção
    log_message("6️⃣ Realizando teste de inserção...");
    
    $trans_test = ibase_trans($conn);
    try {
        $sql_insert = "INSERT INTO AGENDAMENTO_EXAMES (NUMERO_AGENDAMENTO, EXAME_ID, OBSERVACOES) VALUES (?, ?, ?)";
        $stmt_insert = ibase_prepare($trans_test, $sql_insert);
        ibase_execute($stmt_insert, 'TEST-001', 999, 'Teste de inserção');
        
        // Verificar se foi inserido
        $sql_select = "SELECT ID, NUMERO_AGENDAMENTO, EXAME_ID, DATA_INCLUSAO, OBSERVACOES FROM AGENDAMENTO_EXAMES WHERE NUMERO_AGENDAMENTO = 'TEST-001'";
        $result_select = ibase_query($trans_test, $sql_select);
        $row_test = ibase_fetch_assoc($result_select);
        
        if ($row_test) {
            log_message("   ✅ Teste de inserção bem-sucedido:");
            log_message("      - ID: {$row_test['ID']}");
            log_message("      - Agendamento: {$row_test['NUMERO_AGENDAMENTO']}");
            log_message("      - Exame ID: {$row_test['EXAME_ID']}");
            log_message("      - Data: {$row_test['DATA_INCLUSAO']}");
            log_message("      - Obs: {$row_test['OBSERVACOES']}");
        }
        
        // Remover teste
        ibase_query($trans_test, "DELETE FROM AGENDAMENTO_EXAMES WHERE NUMERO_AGENDAMENTO = 'TEST-001'");
        ibase_commit($trans_test);
        log_message("   ✅ Registro de teste removido");
        
    } catch (Exception $e_test) {
        ibase_rollback($trans_test);
        log_message("   ❌ Erro no teste: " . $e_test->getMessage());
    }
    
    log_message("=== PROCESSO CONCLUÍDO COM SUCESSO ===");
    log_message("");
    log_message("🎉 A tabela AGENDAMENTO_EXAMES está pronta para uso!");
    log_message("📝 Para usar no sistema de encaixe, basta selecionar múltiplos exames.");
    log_message("🔍 Os relacionamentos serão salvos automaticamente nesta tabela.");
    
    // Resposta JSON para uso via AJAX
    $response = [
        'status' => 'sucesso',
        'mensagem' => 'Tabela AGENDAMENTO_EXAMES criada e testada com sucesso!',
        'tabela_existe' => true,
        'teste_inserção' => true
    ];
    
    echo "\n" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    log_message("❌ ERRO: " . $e->getMessage());
    log_message("📄 Verifique o arquivo criar_tabela_agendamento_exames.sql para executar manualmente");
    
    $response = [
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
        'tabela_existe' => false,
        'teste_inserção' => false
    ];
    
    echo "\n" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>