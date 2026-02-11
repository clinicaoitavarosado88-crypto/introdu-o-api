-- ============================================================================
-- SCRIPT PARA CRIAR TABELA DE RELACIONAMENTO AGENDAMENTO_EXAMES
-- ============================================================================
-- 
-- Este script cria a tabela de relacionamento entre agendamentos e exames
-- para suportar múltipla seleção de exames por agendamento
-- 
-- Criado em: 2025-08-12
-- Autor: Claude Code
-- ============================================================================

-- 1. CRIAR A TABELA PRINCIPAL
CREATE TABLE AGENDAMENTO_EXAMES (
    ID INTEGER NOT NULL PRIMARY KEY,
    NUMERO_AGENDAMENTO VARCHAR(20) NOT NULL,
    EXAME_ID INTEGER NOT NULL,
    DATA_INCLUSAO TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    OBSERVACOES VARCHAR(500)
);

-- 2. CRIAR GENERATOR PARA CHAVE PRIMÁRIA
CREATE GENERATOR GEN_AGENDAMENTO_EXAMES_ID;
SET GENERATOR GEN_AGENDAMENTO_EXAMES_ID TO 0;

-- 3. CRIAR TRIGGER PARA AUTO-INCREMENT
SET TERM !! ;
CREATE OR ALTER TRIGGER TRG_AGENDAMENTO_EXAMES_BI FOR AGENDAMENTO_EXAMES
ACTIVE BEFORE INSERT POSITION 0
AS
BEGIN
  IF (NEW.ID IS NULL) THEN
    NEW.ID = GEN_ID(GEN_AGENDAMENTO_EXAMES_ID,1);
END!!
SET TERM ; !!

-- 4. CRIAR ÍNDICES PARA OTIMIZAÇÃO
CREATE INDEX IDX_AGENDAMENTO_EXAMES_NUMERO ON AGENDAMENTO_EXAMES (NUMERO_AGENDAMENTO);
CREATE INDEX IDX_AGENDAMENTO_EXAMES_EXAME ON AGENDAMENTO_EXAMES (EXAME_ID);
CREATE INDEX IDX_AGENDAMENTO_EXAMES_DATA ON AGENDAMENTO_EXAMES (DATA_INCLUSAO);

-- 5. CRIAR ÍNDICE COMPOSTO ÚNICO (evitar duplicatas)
CREATE UNIQUE INDEX IDX_AGENDAMENTO_EXAMES_UNIQUE ON AGENDAMENTO_EXAMES (NUMERO_AGENDAMENTO, EXAME_ID);

-- 6. ADICIONAR COMENTÁRIOS À TABELA (OPCIONAL)
COMMENT ON TABLE AGENDAMENTO_EXAMES IS 'Relacionamento N:N entre agendamentos e exames - permite múltiplos exames por agendamento';
COMMENT ON COLUMN AGENDAMENTO_EXAMES.ID IS 'Chave primária auto-incremento';
COMMENT ON COLUMN AGENDAMENTO_EXAMES.NUMERO_AGENDAMENTO IS 'Referência ao número do agendamento (FK para AGENDAMENTOS.NUMERO_AGENDAMENTO)';
COMMENT ON COLUMN AGENDAMENTO_EXAMES.EXAME_ID IS 'Referência ao ID do exame (FK para LAB_EXAMES.IDEXAME)';
COMMENT ON COLUMN AGENDAMENTO_EXAMES.DATA_INCLUSAO IS 'Data e hora de inclusão do relacionamento';
COMMENT ON COLUMN AGENDAMENTO_EXAMES.OBSERVACOES IS 'Observações específicas do exame neste agendamento';

-- ============================================================================
-- QUERIES DE TESTE (EXECUTAR APÓS CRIAR A TABELA)
-- ============================================================================

-- Verificar se a tabela foi criada corretamente
SELECT COUNT(*) FROM RDB$RELATIONS WHERE RDB$RELATION_NAME = 'AGENDAMENTO_EXAMES';

-- Verificar estrutura da tabela
SELECT 
    RF.RDB$FIELD_NAME AS CAMPO,
    CASE F.RDB$FIELD_TYPE
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
    F.RDB$FIELD_LENGTH AS TAMANHO,
    RF.RDB$NULL_FLAG AS OBRIGATORIO
FROM RDB$RELATION_FIELDS RF
JOIN RDB$FIELDS F ON RF.RDB$FIELD_SOURCE = F.RDB$FIELD_NAME
WHERE RF.RDB$RELATION_NAME = 'AGENDAMENTO_EXAMES'
ORDER BY RF.RDB$FIELD_POSITION;

-- Verificar índices criados
SELECT RDB$INDEX_NAME, RDB$RELATION_NAME, RDB$UNIQUE_FLAG
FROM RDB$INDICES 
WHERE RDB$RELATION_NAME = 'AGENDAMENTO_EXAMES';

-- ============================================================================
-- QUERIES DE EXEMPLO PARA USO
-- ============================================================================

-- Inserir relacionamento (exemplo)
-- INSERT INTO AGENDAMENTO_EXAMES (NUMERO_AGENDAMENTO, EXAME_ID, OBSERVACOES) 
-- VALUES ('AGD-0001', 123, 'Exame prioritário');

-- Buscar exames de um agendamento
-- SELECT ae.*, le.EXAME 
-- FROM AGENDAMENTO_EXAMES ae
-- JOIN LAB_EXAMES le ON le.IDEXAME = ae.EXAME_ID
-- WHERE ae.NUMERO_AGENDAMENTO = 'AGD-0001';

-- Buscar agendamentos de um exame
-- SELECT ae.*, a.NOME_PACIENTE 
-- FROM AGENDAMENTO_EXAMES ae
-- JOIN AGENDAMENTOS a ON a.NUMERO_AGENDAMENTO = ae.NUMERO_AGENDAMENTO
-- WHERE ae.EXAME_ID = 123;

-- Contar exames por agendamento
-- SELECT NUMERO_AGENDAMENTO, COUNT(*) as TOTAL_EXAMES
-- FROM AGENDAMENTO_EXAMES 
-- GROUP BY NUMERO_AGENDAMENTO;

-- ============================================================================
-- SCRIPT DE LIMPEZA (USAR APENAS SE NECESSÁRIO REMOVER A TABELA)
-- ============================================================================

-- ATENÇÃO: Descomente apenas se precisar remover a tabela
-- DROP INDEX IDX_AGENDAMENTO_EXAMES_UNIQUE;
-- DROP INDEX IDX_AGENDAMENTO_EXAMES_DATA;
-- DROP INDEX IDX_AGENDAMENTO_EXAMES_EXAME;
-- DROP INDEX IDX_AGENDAMENTO_EXAMES_NUMERO;
-- DROP TRIGGER TRG_AGENDAMENTO_EXAMES_BI;
-- DROP GENERATOR GEN_AGENDAMENTO_EXAMES_ID;
-- DROP TABLE AGENDAMENTO_EXAMES;

-- ============================================================================
-- FIM DO SCRIPT
-- ============================================================================