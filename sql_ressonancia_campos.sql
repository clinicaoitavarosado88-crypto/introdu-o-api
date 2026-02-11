/*
====================================================================
SCRIPT: Adicionar campos para Agendamento de Ressonância
====================================================================
Objetivo: Permitir configurar horários específicos para:
- Exames COM contraste (precisa de médico)
- Exames COM anestesia (dias/horários específicos + limite)

Data: 2026-01-19
====================================================================
*/

-- ============================================================
-- 1. ADICIONAR CAMPOS NA TABELA AGENDA_HORARIOS
-- ============================================================

-- Campo: TEM_MEDICO (S/N)
-- Indica se neste horário há médico presente
-- Se SIM: pode fazer exames COM contraste
-- Se NÃO: só pode fazer exames SEM contraste
ALTER TABLE AGENDA_HORARIOS
ADD TEM_MEDICO CHAR(1) DEFAULT 'N' CHECK (TEM_MEDICO IN ('S', 'N'));

COMMENT ON COLUMN AGENDA_HORARIOS.TEM_MEDICO IS
'Indica se há médico presente neste horário (S/N). Necessário para exames COM contraste.';

-- Campo: ACEITA_ANESTESIA (S/N)
-- Indica se este horário aceita exames COM anestesia
-- Normalmente Quinta pela manhã, mas configurável
ALTER TABLE AGENDA_HORARIOS
ADD ACEITA_ANESTESIA CHAR(1) DEFAULT 'N' CHECK (ACEITA_ANESTESIA IN ('S', 'N'));

COMMENT ON COLUMN AGENDA_HORARIOS.ACEITA_ANESTESIA IS
'Indica se este horário aceita agendamentos COM anestesia (S/N). Configurável por dia da semana.';

-- Campo: LIMITE_ANESTESIAS (INTEGER)
-- Limite de exames com anestesia permitidos neste dia
-- Exemplo: 2 anestesias por dia
ALTER TABLE AGENDA_HORARIOS
ADD LIMITE_ANESTESIAS INTEGER DEFAULT 0 CHECK (LIMITE_ANESTESIAS >= 0);

COMMENT ON COLUMN AGENDA_HORARIOS.LIMITE_ANESTESIAS IS
'Número máximo de exames COM anestesia permitidos neste dia. 0 = sem limite.';

-- ============================================================
-- 2. VERIFICAR SE CAMPO PRECISA_ANESTESIA JÁ EXISTE
-- ============================================================

-- Tentativa de adicionar campo na LAB_EXAMES
-- Se já existir, o comando falhará (ignorar o erro)
ALTER TABLE LAB_EXAMES
ADD PRECISA_ANESTESIA CHAR(1) DEFAULT 'N' CHECK (PRECISA_ANESTESIA IN ('S', 'N'));

COMMENT ON COLUMN LAB_EXAMES.PRECISA_ANESTESIA IS
'Indica se este exame requer anestesia/sedação (S/N).';

-- ============================================================
-- 3. CONFIGURAÇÃO INICIAL (EXEMPLO)
-- ============================================================

-- Exemplo: Configurar QUINTA PELA MANHÃ para aceitar anestesia
-- Agenda 30 e 76 (Ressonância Magnética)

-- Agenda 30 - Quinta-feira
UPDATE AGENDA_HORARIOS
SET ACEITA_ANESTESIA = 'S',
    LIMITE_ANESTESIAS = 2  -- Máximo 2 anestesias por dia
WHERE AGENDA_ID = 30
  AND TRIM(DIA_SEMANA) = 'Quinta';

-- Agenda 76 - Quinta-feira
UPDATE AGENDA_HORARIOS
SET ACEITA_ANESTESIA = 'S',
    LIMITE_ANESTESIAS = 2
WHERE AGENDA_ID = 76
  AND TRIM(DIA_SEMANA) = 'Quinta';

-- ============================================================
-- 4. EXEMPLO: Marcar horários COM médico (todos os dias)
-- ============================================================

-- Se TODOS os horários têm médico presente:
-- UPDATE AGENDA_HORARIOS
-- SET TEM_MEDICO = 'S'
-- WHERE AGENDA_ID IN (30, 76);

-- Se APENAS ALGUNS turnos têm médico (descomentar conforme necessário):
-- Exemplo: Médico só pela TARDE
-- UPDATE AGENDA_HORARIOS
-- SET TEM_MEDICO = 'S'
-- WHERE AGENDA_ID IN (30, 76)
--   AND HORARIO_INICIO_TARDE IS NOT NULL;

-- ============================================================
-- 5. CONSULTAR CONFIGURAÇÃO ATUAL
-- ============================================================

-- Ver horários configurados com médico/anestesia
SELECT
    a.ID as AGENDA_ID,
    TRIM(h.DIA_SEMANA) as DIA,
    h.TEM_MEDICO,
    h.ACEITA_ANESTESIA,
    h.LIMITE_ANESTESIAS,
    h.VAGAS_DIA
FROM AGENDA_HORARIOS h
JOIN AGENDAS a ON a.ID = h.AGENDA_ID
WHERE a.ID IN (30, 76)
ORDER BY a.ID, h.DIA_SEMANA;

COMMIT;
