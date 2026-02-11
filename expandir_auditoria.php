<?php
// expandir_auditoria.php
// Adicionar campos extras à tabela de auditoria

include 'includes/connection.php';

echo "<h2>🔧 Expandindo Tabela de Auditoria</h2>\n";

$novos_campos = [
    "Navegador do usuário" => "ALTER TABLE AGENDA_AUDITORIA ADD USER_AGENT VARCHAR(500)",
    "Sistema operacional" => "ALTER TABLE AGENDA_AUDITORIA ADD SISTEMA_OPERACIONAL VARCHAR(100)", 
    "Tipo de navegador" => "ALTER TABLE AGENDA_AUDITORIA ADD NAVEGADOR VARCHAR(100)",
    "Sessão do usuário" => "ALTER TABLE AGENDA_AUDITORIA ADD SESSAO_ID VARCHAR(100)",
    "URL de origem" => "ALTER TABLE AGENDA_AUDITORIA ADD URL_ORIGEM VARCHAR(500)",
    "Método HTTP" => "ALTER TABLE AGENDA_AUDITORIA ADD METODO_HTTP VARCHAR(10)",
    "Dados POST completos" => "ALTER TABLE AGENDA_AUDITORIA ADD DADOS_POST BLOB SUB_TYPE TEXT",
    "Dados GET completos" => "ALTER TABLE AGENDA_AUDITORIA ADD DADOS_GET BLOB SUB_TYPE TEXT",
    "Dados da sessão" => "ALTER TABLE AGENDA_AUDITORIA ADD DADOS_SESSAO BLOB SUB_TYPE TEXT",
    "Tempo de execução" => "ALTER TABLE AGENDA_AUDITORIA ADD TEMPO_EXECUCAO DECIMAL(10,4)",
    "ID da transação" => "ALTER TABLE AGENDA_AUDITORIA ADD TRANSACAO_ID VARCHAR(50)",
    "Convênio anterior" => "ALTER TABLE AGENDA_AUDITORIA ADD CONVENIO_ANTERIOR VARCHAR(200)",
    "Convênio novo" => "ALTER TABLE AGENDA_AUDITORIA ADD CONVENIO_NOVO VARCHAR(200)",
    "Médico anterior" => "ALTER TABLE AGENDA_AUDITORIA ADD MEDICO_ANTERIOR VARCHAR(200)",
    "Médico novo" => "ALTER TABLE AGENDA_AUDITORIA ADD MEDICO_NOVO VARCHAR(200)",
    "Exames anteriores" => "ALTER TABLE AGENDA_AUDITORIA ADD EXAMES_ANTERIORES BLOB SUB_TYPE TEXT",
    "Exames novos" => "ALTER TABLE AGENDA_AUDITORIA ADD EXAMES_NOVOS BLOB SUB_TYPE TEXT",
    "Telefone anterior" => "ALTER TABLE AGENDA_AUDITORIA ADD TELEFONE_ANTERIOR VARCHAR(20)",
    "Telefone novo" => "ALTER TABLE AGENDA_AUDITORIA ADD TELEFONE_NOVO VARCHAR(20)",
    "CPF anterior" => "ALTER TABLE AGENDA_AUDITORIA ADD CPF_ANTERIOR VARCHAR(14)",
    "CPF novo" => "ALTER TABLE AGENDA_AUDITORIA ADD CPF_NOVO VARCHAR(14)",
    "Email anterior" => "ALTER TABLE AGENDA_AUDITORIA ADD EMAIL_ANTERIOR VARCHAR(200)",
    "Email novo" => "ALTER TABLE AGENDA_AUDITORIA ADD EMAIL_NOVO VARCHAR(200)",
    "Data nasc anterior" => "ALTER TABLE AGENDA_AUDITORIA ADD DATA_NASC_ANTERIOR DATE",
    "Data nasc nova" => "ALTER TABLE AGENDA_AUDITORIA ADD DATA_NASC_NOVA DATE",
    "Tipo consulta anterior" => "ALTER TABLE AGENDA_AUDITORIA ADD TIPO_CONSULTA_ANTERIOR VARCHAR(50)",
    "Tipo consulta novo" => "ALTER TABLE AGENDA_AUDITORIA ADD TIPO_CONSULTA_NOVO VARCHAR(50)",
    "Resultado da ação" => "ALTER TABLE AGENDA_AUDITORIA ADD RESULTADO_ACAO VARCHAR(20)", // SUCCESS, ERROR, WARNING
    "Código de erro" => "ALTER TABLE AGENDA_AUDITORIA ADD CODIGO_ERRO VARCHAR(50)",
    "Stack trace" => "ALTER TABLE AGENDA_AUDITORIA ADD STACK_TRACE BLOB SUB_TYPE TEXT",
    "Duração total" => "ALTER TABLE AGENDA_AUDITORIA ADD DURACAO_TOTAL_MS INTEGER"
];

$sucesso = 0;
$erros = 0;

foreach ($novos_campos as $descricao => $sql) {
    echo "<h4>Adicionando: $descricao</h4>\n";
    echo "<pre style='background: #f0f0f0; padding: 5px; font-size: 11px;'>" . htmlspecialchars($sql) . "</pre>\n";
    
    try {
        $result = ibase_query($conn, $sql);
        
        if ($result !== false) {
            echo "<p style='color: green;'>✅ Campo adicionado</p>\n";
            $sucesso++;
        } else {
            $error = ibase_errmsg();
            if (stripos($error, 'already exists') !== false || stripos($error, 'duplicate') !== false) {
                echo "<p style='color: blue;'>ℹ️ Campo já existe (OK)</p>\n";
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

// Commit das alterações
ibase_query($conn, "COMMIT");

echo "<h3>📊 Resumo da Expansão</h3>\n";
echo "<p>✅ Campos adicionados: <strong>$sucesso</strong></p>\n";
echo "<p>❌ Erros: <strong>$erros</strong></p>\n";

if ($erros === 0) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
    echo "<h4 style='color: #155724;'>🎉 TABELA EXPANDIDA COM SUCESSO!</h4>\n";
    echo "<p style='color: #155724;'>Agora a auditoria captura muito mais informações!</p>\n";
    echo "<p><a href='auditoria.php' style='color: #155724; font-weight: bold;'>📋 Ver Interface Atualizada</a></p>\n";
    echo "</div>\n";
}

// Mostrar estrutura atual da tabela
echo "<h3>📋 Estrutura Atual da Tabela</h3>\n";
try {
    $query = "SELECT RDB\$FIELD_NAME, RDB\$FIELD_TYPE 
              FROM RDB\$RELATION_FIELDS 
              WHERE RDB\$RELATION_NAME = 'AGENDA_AUDITORIA' 
              ORDER BY RDB\$FIELD_POSITION";
    
    $result = ibase_query($conn, $query);
    if ($result) {
        echo "<table border='1' style='border-collapse: collapse; font-size: 12px;'>\n";
        echo "<tr><th>Campo</th><th>Tipo</th></tr>\n";
        
        while ($row = ibase_fetch_assoc($result)) {
            $campo = trim($row['RDB$FIELD_NAME']);
            $tipo = $row['RDB$FIELD_TYPE'];
            echo "<tr><td>$campo</td><td>$tipo</td></tr>\n";
        }
        echo "</table>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro ao listar campos: " . $e->getMessage() . "</p>\n";
}
?>