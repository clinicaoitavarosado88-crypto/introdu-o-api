-- =====================================================
-- MIGRAÇÃO: Adicionar ESPECIALIDADE_ID na tabela AGENDAS
-- Corrige o problema de filtro por especialidade
-- =====================================================

-- 1. Adicionar coluna ESPECIALIDADE_ID na tabela AGENDAS
ALTER TABLE AGENDAS ADD ESPECIALIDADE_ID INTEGER;

-- 2. Criar índice para performance
CREATE INDEX IDX_AGENDAS_ESPECIALIDADE ON AGENDAS (ESPECIALIDADE_ID);

-- 3. Preencher ESPECIALIDADE_ID para agendas existentes do tipo consulta
-- Usa a primeira especialidade encontrada do médico vinculado
UPDATE AGENDAS a SET a.ESPECIALIDADE_ID = (
    SELECT FIRST 1 me.ESPECIALIDADE_ID
    FROM LAB_MEDICOS_ESPECIALIDADES me
    WHERE me.MEDICO_ID = a.MEDICO_ID
)
WHERE a.TIPO = 'consulta' AND a.ESPECIALIDADE_ID IS NULL AND a.MEDICO_ID IS NOT NULL;

-- 4. Verificar resultado
-- SELECT a.ID, a.TIPO, a.MEDICO_ID, a.ESPECIALIDADE_ID, e.NOME AS ESPECIALIDADE
-- FROM AGENDAS a
-- LEFT JOIN ESPECIALIDADES e ON e.ID = a.ESPECIALIDADE_ID
-- WHERE a.TIPO = 'consulta';
