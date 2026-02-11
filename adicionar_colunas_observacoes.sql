-- Script para adicionar colunas de observações na auditoria
-- Execute este script no banco de dados para suportar mudanças de observações

-- Adicionar colunas para observações anteriores e novas
ALTER TABLE AGENDA_AUDITORIA ADD OBSERVACOES_ANTERIORES VARCHAR(1000);
ALTER TABLE AGENDA_AUDITORIA ADD OBSERVACOES_NOVAS VARCHAR(1000);

-- Verificar se as colunas foram criadas
SELECT COUNT(*) as TOTAL_COLUNAS 
FROM RDB$RELATION_FIELDS 
WHERE RDB$RELATION_NAME = 'AGENDA_AUDITORIA' 
AND RDB$FIELD_NAME IN ('OBSERVACOES_ANTERIORES', 'OBSERVACOES_NOVAS');