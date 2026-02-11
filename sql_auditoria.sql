-- sql_auditoria.sql
-- Script para criar tabela de auditoria da agenda
-- Execute este script no banco de dados Firebird

-- Criar tabela de auditoria
CREATE TABLE AGENDA_AUDITORIA (
    ID INTEGER NOT NULL,
    AGENDAMENTO_ID INTEGER,                    -- ID do agendamento (pode ser NULL para ações gerais)
    NUMERO_AGENDAMENTO VARCHAR(50),            -- Número do agendamento para referência
    ACAO VARCHAR(50) NOT NULL,                 -- CRIAR, EDITAR, CANCELAR, BLOQUEAR, DESBLOQUEAR, ENCAIXE, etc.
    TABELA_AFETADA VARCHAR(50),                -- Nome da tabela principal afetada
    USUARIO VARCHAR(100) NOT NULL,             -- Usuário que executou a ação
    DATA_ACAO TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Data/hora da ação
    IP_USUARIO VARCHAR(45),                    -- IP do usuário (IPv4/IPv6)
    DADOS_ANTIGOS BLOB SUB_TYPE TEXT,          -- JSON com dados antes da alteração
    DADOS_NOVOS BLOB SUB_TYPE TEXT,            -- JSON com dados após a alteração
    CAMPOS_ALTERADOS VARCHAR(500),             -- Lista dos campos que foram alterados
    OBSERVACOES VARCHAR(1000),                 -- Observações sobre a ação
    AGENDA_ID INTEGER,                         -- ID da agenda para filtros
    PACIENTE_NOME VARCHAR(200),                -- Nome do paciente para buscas
    DATA_AGENDAMENTO DATE,                     -- Data do agendamento para filtros
    HORA_AGENDAMENTO TIME,                     -- Hora do agendamento para filtros
    STATUS_ANTERIOR VARCHAR(50),               -- Status anterior do agendamento
    STATUS_NOVO VARCHAR(50),                   -- Novo status do agendamento
    CONSTRAINT PK_AGENDA_AUDITORIA PRIMARY KEY (ID)
);

-- Criar sequence para auto increment
CREATE SEQUENCE SEQ_AGENDA_AUDITORIA;

-- Criar trigger para auto increment
SET TERM !! ;
CREATE TRIGGER TRG_AGENDA_AUDITORIA_BI FOR AGENDA_AUDITORIA
ACTIVE BEFORE INSERT POSITION 0
AS
BEGIN
    IF (NEW.ID IS NULL) THEN
        NEW.ID = GEN_ID(SEQ_AGENDA_AUDITORIA, 1);
END!!
SET TERM ; !!

-- Criar índices para performance
CREATE INDEX IDX_AUDITORIA_AGENDAMENTO ON AGENDA_AUDITORIA (AGENDAMENTO_ID);
CREATE INDEX IDX_AUDITORIA_USUARIO ON AGENDA_AUDITORIA (USUARIO);
CREATE INDEX IDX_AUDITORIA_DATA ON AGENDA_AUDITORIA (DATA_ACAO);
CREATE INDEX IDX_AUDITORIA_ACAO ON AGENDA_AUDITORIA (ACAO);
CREATE INDEX IDX_AUDITORIA_AGENDA ON AGENDA_AUDITORIA (AGENDA_ID);
CREATE INDEX IDX_AUDITORIA_PACIENTE ON AGENDA_AUDITORIA (PACIENTE_NOME);
CREATE INDEX IDX_AUDITORIA_DATA_AGEND ON AGENDA_AUDITORIA (DATA_AGENDAMENTO);

-- Comentários da tabela
COMMENT ON TABLE AGENDA_AUDITORIA IS 'Tabela de auditoria para todas as operações da agenda';
COMMENT ON COLUMN AGENDA_AUDITORIA.ID IS 'ID único do registro de auditoria';
COMMENT ON COLUMN AGENDA_AUDITORIA.AGENDAMENTO_ID IS 'ID do agendamento afetado';
COMMENT ON COLUMN AGENDA_AUDITORIA.ACAO IS 'Tipo de ação executada (CRIAR, EDITAR, CANCELAR, etc.)';
COMMENT ON COLUMN AGENDA_AUDITORIA.USUARIO IS 'Usuário que executou a ação';
COMMENT ON COLUMN AGENDA_AUDITORIA.DADOS_ANTIGOS IS 'Dados antes da alteração em formato JSON';
COMMENT ON COLUMN AGENDA_AUDITORIA.DADOS_NOVOS IS 'Dados após a alteração em formato JSON';
COMMENT ON COLUMN AGENDA_AUDITORIA.CAMPOS_ALTERADOS IS 'Lista de campos que foram alterados';

-- Exemplo de uso:
-- INSERT INTO AGENDA_AUDITORIA (AGENDAMENTO_ID, ACAO, TABELA_AFETADA, USUARIO, OBSERVACOES)
-- VALUES (123, 'CANCELAR', 'AGENDAMENTOS', 'RENISON', 'Cancelado pelo usuário');

COMMIT;