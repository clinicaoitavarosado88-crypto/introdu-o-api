<?php
// criar_auditoria_manual.php
// Criação manual da tabela de auditoria passo a passo

include 'includes/connection.php';

echo "<h2>🔧 Criação Manual da Tabela de Auditoria</h2>\n";

$comandos = [
    "Criar tabela" => "CREATE TABLE AGENDA_AUDITORIA (
        ID INTEGER NOT NULL,
        AGENDAMENTO_ID INTEGER,
        NUMERO_AGENDAMENTO VARCHAR(50),
        ACAO VARCHAR(50) NOT NULL,
        TABELA_AFETADA VARCHAR(50),
        USUARIO VARCHAR(100) NOT NULL,
        DATA_ACAO TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        IP_USUARIO VARCHAR(45),
        DADOS_ANTIGOS BLOB SUB_TYPE TEXT,
        DADOS_NOVOS BLOB SUB_TYPE TEXT,
        CAMPOS_ALTERADOS VARCHAR(500),
        OBSERVACOES VARCHAR(1000),
        AGENDA_ID INTEGER,
        PACIENTE_NOME VARCHAR(200),
        DATA_AGENDAMENTO DATE,
        HORA_AGENDAMENTO TIME,
        STATUS_ANTERIOR VARCHAR(50),
        STATUS_NOVO VARCHAR(50),
        CONSTRAINT PK_AGENDA_AUDITORIA PRIMARY KEY (ID)
    )",
    
    "Criar sequence" => "CREATE SEQUENCE SEQ_AGENDA_AUDITORIA",
    
    "Criar trigger" => "CREATE TRIGGER TRG_AGENDA_AUDITORIA_BI FOR AGENDA_AUDITORIA
    ACTIVE BEFORE INSERT POSITION 0
    AS
    BEGIN
        IF (NEW.ID IS NULL) THEN
            NEW.ID = GEN_ID(SEQ_AGENDA_AUDITORIA, 1);
    END",
    
    "Índice agendamento" => "CREATE INDEX IDX_AUDITORIA_AGENDAMENTO ON AGENDA_AUDITORIA (AGENDAMENTO_ID)",
    "Índice usuário" => "CREATE INDEX IDX_AUDITORIA_USUARIO ON AGENDA_AUDITORIA (USUARIO)",
    "Índice data" => "CREATE INDEX IDX_AUDITORIA_DATA ON AGENDA_AUDITORIA (DATA_ACAO)",
    "Índice ação" => "CREATE INDEX IDX_AUDITORIA_ACAO ON AGENDA_AUDITORIA (ACAO)"
];

$sucesso = 0;
$erros = 0;

foreach ($comandos as $descricao => $sql) {
    echo "<h4>$descricao</h4>\n";
    echo "<pre style='background: #f0f0f0; padding: 10px; font-size: 11px;'>" . htmlspecialchars($sql) . "</pre>\n";
    
    try {
        $result = ibase_query($conn, $sql);
        
        if ($result !== false) {
            echo "<p style='color: green;'>✅ Sucesso</p>\n";
            $sucesso++;
        } else {
            $error = ibase_errmsg();
            if (stripos($error, 'already exists') !== false || stripos($error, 'já existe') !== false) {
                echo "<p style='color: blue;'>ℹ️ Já existe (OK)</p>\n";
                $sucesso++;
            } else {
                echo "<p style='color: red;'>❌ Erro: $error</p>\n";
                $erros++;
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Exceção: " . $e->getMessage() . "</p>\n";
        $erros++;
    }
    
    echo "<hr>\n";
}

// Testar tabela
echo "<h3>🧪 Testando tabela</h3>\n";

try {
    $test = ibase_query($conn, "SELECT COUNT(*) as TOTAL FROM AGENDA_AUDITORIA");
    if ($test) {
        $row = ibase_fetch_assoc($test);
        echo "<p style='color: green;'>✅ Tabela funcionando! Registros: {$row['TOTAL']}</p>\n";
        
        // Inserir registro de teste
        include 'includes/auditoria.php';
        
        $resultado_teste = registrarAuditoria($conn, [
            'acao' => 'TESTE_INSTALACAO',
            'usuario' => 'SISTEMA_INSTALACAO',
            'observacoes' => 'Teste da instalação manual em ' . date('Y-m-d H:i:s')
        ]);
        
        if ($resultado_teste) {
            echo "<p style='color: green;'>✅ Registro de teste inserido!</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Falha ao inserir registro de teste</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Erro ao testar: " . ibase_errmsg() . "</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro no teste: " . $e->getMessage() . "</p>\n";
}

echo "<h3>📊 Resumo</h3>\n";
echo "<p>✅ Sucessos: $sucesso</p>\n";
echo "<p>❌ Erros: $erros</p>\n";

if ($erros === 0) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
    echo "<h4 style='color: #155724;'>🎉 TABELA CRIADA COM SUCESSO!</h4>\n";
    echo "<p style='color: #155724;'>O sistema de auditoria está pronto para uso.</p>\n";
    echo "<p><a href='auditoria.php' style='color: #155724; font-weight: bold;'>📋 Ver Interface de Auditoria</a></p>\n";
    echo "<p><a href='exemplo_integracao.php' style='color: #155724;'>🔗 Voltar aos Testes</a></p>\n";
    echo "</div>\n";
}
?>