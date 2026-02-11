<?php
// criar_tabela_bloqueios.php - Cria a tabela AGENDA_BLOQUEIOS
include 'includes/connection.php';
include 'includes/verificar_permissao.php';

// Verificar permissão para administrar agendas (comentado para criação da tabela)
// $usuario_atual = getUsuarioAtual();
// if (!$usuario_atual || !podeAdministrarAgendas($conn, $usuario_atual)) {
//     die('Sem permissão para criar tabelas');
// }

try {
    // Verificar se a tabela já existe
    $sql_check = "SELECT COUNT(*) as EXISTE FROM RDB\$RELATIONS WHERE RDB\$RELATION_NAME = 'AGENDA_BLOQUEIOS'";
    $result_check = ibase_query($conn, $sql_check);
    $row_check = ibase_fetch_assoc($result_check);
    
    if ($row_check['EXISTE'] > 0) {
        echo "✅ Tabela AGENDA_BLOQUEIOS já existe\n";
    } else {
        echo "🔧 Criando tabela AGENDA_BLOQUEIOS...\n";
        
        // Criar a tabela
        $sql_create = "CREATE TABLE AGENDA_BLOQUEIOS (
            ID INTEGER NOT NULL,
            AGENDA_ID INTEGER NOT NULL,
            TIPO_BLOQUEIO VARCHAR(20) NOT NULL,
            DATA_BLOQUEIO DATE,
            DATA_INICIO DATE,
            DATA_FIM DATE,
            HORARIO_INICIO TIME,
            HORARIO_FIM TIME,
            MOTIVO VARCHAR(500),
            USUARIO_BLOQUEIO VARCHAR(50) NOT NULL,
            DATA_CRIACAO TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ATIVO SMALLINT DEFAULT 1,
            CONSTRAINT PK_AGENDA_BLOQUEIOS PRIMARY KEY (ID),
            CONSTRAINT FK_AGENDA_BLOQUEIOS_AGENDA FOREIGN KEY (AGENDA_ID) REFERENCES AGENDAS(ID)
        )";
        
        $result_create = ibase_query($conn, $sql_create);
        
        if ($result_create) {
            echo "✅ Tabela AGENDA_BLOQUEIOS criada com sucesso\n";
            
            // Criar gerador para ID
            $sql_gen = "CREATE GENERATOR GEN_AGENDA_BLOQUEIOS_ID";
            ibase_query($conn, $sql_gen);
            
            // Criar trigger para auto-incremento
            $sql_trigger = "CREATE TRIGGER TR_AGENDA_BLOQUEIOS_ID FOR AGENDA_BLOQUEIOS
                ACTIVE BEFORE INSERT POSITION 0
                AS BEGIN
                    IF (NEW.ID IS NULL) THEN
                        NEW.ID = GEN_ID(GEN_AGENDA_BLOQUEIOS_ID, 1);
                END";
            ibase_query($conn, $sql_trigger);
            
            echo "✅ Gerador e trigger criados\n";
            
            // Criar índices para performance
            $indices = [
                "CREATE INDEX IDX_AGENDA_BLOQUEIOS_AGENDA ON AGENDA_BLOQUEIOS(AGENDA_ID)",
                "CREATE INDEX IDX_AGENDA_BLOQUEIOS_DATA ON AGENDA_BLOQUEIOS(DATA_BLOQUEIO)",
                "CREATE INDEX IDX_AGENDA_BLOQUEIOS_TIPO ON AGENDA_BLOQUEIOS(TIPO_BLOQUEIO)",
                "CREATE INDEX IDX_AGENDA_BLOQUEIOS_ATIVO ON AGENDA_BLOQUEIOS(ATIVO)"
            ];
            
            foreach ($indices as $idx_sql) {
                ibase_query($conn, $idx_sql);
            }
            
            echo "✅ Índices criados\n";
            
        } else {
            throw new Exception('Erro ao criar tabela');
        }
    }
    
    echo "\n🎉 Estrutura da tabela AGENDA_BLOQUEIOS pronta para uso!\n";
    echo "\nCampos disponíveis:\n";
    echo "- ID: Chave primária (auto-incremento)\n";
    echo "- AGENDA_ID: ID da agenda (FK)\n";
    echo "- TIPO_BLOQUEIO: DIA, AGENDA_PERMANENTE, AGENDA_TEMPORARIO, HORARIO\n";
    echo "- DATA_BLOQUEIO: Data específica (para bloqueio de dia/horário)\n";
    echo "- DATA_INICIO/DATA_FIM: Período (para bloqueio de agenda)\n";
    echo "- HORARIO_INICIO/HORARIO_FIM: Horários (para bloqueio de horário)\n";
    echo "- MOTIVO: Motivo do bloqueio\n";
    echo "- USUARIO_BLOQUEIO: Usuário que fez o bloqueio\n";
    echo "- DATA_CRIACAO: Timestamp de criação\n";
    echo "- ATIVO: 1=ativo, 0=removido\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>