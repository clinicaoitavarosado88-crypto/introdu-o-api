-- ✅ Adicionar campo PRECISA_SEDACAO na tabela AGENDA
-- Data: 20/01/2026
-- Descrição: Campo para marcar se o agendamento precisa de sedação/anestesia

-- Verificar se o campo já existe antes de adicionar
-- Execute este script no banco de dados Firebird

ALTER TABLE AGENDA ADD PRECISA_SEDACAO VARCHAR(1) DEFAULT 'N';

-- Comentário do campo
COMMENT ON COLUMN AGENDA.PRECISA_SEDACAO IS 'Indica se o paciente precisa de sedacao/anestesia (S/N)';

-- Criar índice para consultas rápidas
CREATE INDEX IDX_AGENDA_SEDACAO ON AGENDA(PRECISA_SEDACAO);

COMMIT;
