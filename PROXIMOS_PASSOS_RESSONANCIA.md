# 🎯 Próximos Passos - Sistema de Ressonância

## 📋 Checklist de Implementação:

### ✅ **JÁ FEITO:**
- [x] Campos criados no banco de dados
- [x] API `buscar_horarios_ressonancia.php` implementada
- [x] Validações de contraste e anestesia funcionando
- [x] Quinta-feira configurada para anestesia (limite: 2)

---

### 🔄 **PRÓXIMOS PASSOS:**

## **PASSO 1: Configurar Disponibilidade de Médico** 🩺

**O que fazer:**
Definir em quais dias/horários há médico presente (para exames COM contraste).

**Perguntas para você:**
```
1. TODOS os dias têm médico? Ou só alguns?
   ☐ Todos os dias
   ☐ Apenas: ___________________ (Segunda, Quarta, Sexta...)

2. O médico fica o dia todo? Ou só em alguns turnos?
   ☐ Dia todo (manhã e tarde)
   ☐ Apenas manhã
   ☐ Apenas tarde
   ☐ Depende do dia

3. É igual nas duas agendas (30 e 76)?
   ☐ Sim, mesma configuração
   ☐ Não, cada agenda é diferente
```

**Script para executar depois de responder:**
```sql
-- EXEMPLO: Médico em TODOS os dias
UPDATE AGENDA_HORARIOS
SET TEM_MEDICO = 'S'
WHERE AGENDA_ID IN (30, 76);

-- OU

-- EXEMPLO: Médico APENAS Segunda, Quarta e Sexta
UPDATE AGENDA_HORARIOS
SET TEM_MEDICO = 'S'
WHERE AGENDA_ID IN (30, 76)
  AND TRIM(DIA_SEMANA) IN ('Segunda', 'Quarta', 'Sexta');

-- OU

-- EXEMPLO: Médico APENAS pela TARDE
UPDATE AGENDA_HORARIOS
SET TEM_MEDICO = 'S'
WHERE AGENDA_ID IN (30, 76)
  AND HORARIO_INICIO_TARDE IS NOT NULL;

-- VERIFICAR
SELECT TRIM(DIA_SEMANA) as DIA, TEM_MEDICO
FROM AGENDA_HORARIOS
WHERE AGENDA_ID = 30
ORDER BY DIA_SEMANA;
```

---

## **PASSO 2: Ajustar Configuração de Anestesia** 💉

**O que fazer:**
Confirmar se Quinta-feira está correto ou precisa mudar.

**Perguntas para você:**
```
1. Quinta-feira pela MANHÃ está correto?
   ☐ Sim, manter Quinta
   ☐ Mudar para: ___________________ (dia da semana)

2. Limite de 2 anestesias por dia está correto?
   ☐ Sim, manter 2
   ☐ Mudar para: _____ anestesias

3. Precisa de anestesia em mais de um dia?
   ☐ Não, só um dia
   ☐ Sim, também em: ___________________ (outro dia)
```

**Script para executar se precisar mudar:**
```sql
-- EXEMPLO: Mudar de Quinta para Terça
-- 1. Remover Quinta
UPDATE AGENDA_HORARIOS
SET ACEITA_ANESTESIA = 'N',
    LIMITE_ANESTESIAS = 0
WHERE AGENDA_ID IN (30, 76)
  AND TRIM(DIA_SEMANA) = 'Quinta';

-- 2. Adicionar Terça
UPDATE AGENDA_HORARIOS
SET ACEITA_ANESTESIA = 'S',
    LIMITE_ANESTESIAS = 2
WHERE AGENDA_ID IN (30, 76)
  AND TRIM(DIA_SEMANA) = 'Terça';

-- VERIFICAR
SELECT TRIM(DIA_SEMANA) as DIA, ACEITA_ANESTESIA, LIMITE_ANESTESIAS
FROM AGENDA_HORARIOS
WHERE AGENDA_ID = 30
  AND ACEITA_ANESTESIA = 'S';
```

---

## **PASSO 3: Identificar Exames que Precisam de Anestesia** 🔍

**O que fazer:**
Marcar no banco quais exames de ressonância precisam de anestesia.

**Como identificar:**
```bash
# Ver todos os exames de ressonância
php -r "
include 'includes/connection.php';
\$sql = \"SELECT FIRST 30 IDEXAME, EXAME, USA_CONTRASTE, PRECISA_ANESTESIA
        FROM LAB_EXAMES
        WHERE UPPER(EXAME) LIKE '%RESSON%'
        ORDER BY EXAME\";
\$res = ibase_query(\$conn, \$sql);
echo str_pad('ID', 8) . str_pad('Contraste', 12) . str_pad('Anestesia', 12) . \"Nome\n\";
echo str_repeat('-', 80) . \"\n\";
while (\$row = ibase_fetch_assoc(\$res)) {
    \$nome = mb_convert_encoding(\$row['EXAME'], 'UTF-8', 'Windows-1252');
    \$contraste = \$row['USA_CONTRASTE'] ?: 'N';
    \$anestesia = \$row['PRECISA_ANESTESIA'] ?: 'N';
    echo str_pad(\$row['IDEXAME'], 8) . str_pad(\$contraste, 12) . str_pad(\$anestesia, 12) . \$nome . \"\n\";
}
"
```

**Marcar exames:**
```
Normalmente precisam de anestesia:
☐ Exames pediátricos (crianças)
☐ Exames com sedação no nome
☐ Exames específicos que você indicar
```

**Script para marcar:**
```sql
-- OPÇÃO 1: Marcar por palavra-chave no nome
UPDATE LAB_EXAMES
SET PRECISA_ANESTESIA = 'S'
WHERE UPPER(EXAME) LIKE '%SEDACAO%'
   OR UPPER(EXAME) LIKE '%ANESTESIA%'
   OR UPPER(EXAME) LIKE '%PEDIATRIC%';

-- OPÇÃO 2: Marcar por IDs específicos (você me passa a lista)
UPDATE LAB_EXAMES
SET PRECISA_ANESTESIA = 'S'
WHERE IDEXAME IN (1234, 5678, 9012); -- IDs que você indicar

-- VERIFICAR
SELECT IDEXAME, EXAME
FROM LAB_EXAMES
WHERE PRECISA_ANESTESIA = 'S'
  AND UPPER(EXAME) LIKE '%RESSON%';
```

---

## **PASSO 4: Definir Tempo dos Exames** ⏱️

**O que fazer:**
Garantir que todos os exames de ressonância têm tempo correto.

**Ver exames sem tempo:**
```bash
php -r "
include 'includes/connection.php';
\$sql = \"SELECT IDEXAME, EXAME, TEMPO_EXAME
        FROM LAB_EXAMES
        WHERE UPPER(EXAME) LIKE '%RESSON%'
          AND (TEMPO_EXAME IS NULL OR TEMPO_EXAME = 0)
        ORDER BY EXAME\";
\$res = ibase_query(\$conn, \$sql);
\$count = 0;
echo \"Exames SEM tempo definido:\n\n\";
while (\$row = ibase_fetch_assoc(\$res)) {
    \$nome = mb_convert_encoding(\$row['EXAME'], 'UTF-8', 'Windows-1252');
    echo \"  ID: {\$row['IDEXAME']} - \$nome\n\";
    \$count++;
}
echo \"\n\Total: \$count exames sem tempo\n\";
"
```

**Definir tempos padrão:**
```sql
-- Tempo padrão: 30 minutos (para ressonâncias simples)
UPDATE LAB_EXAMES
SET TEMPO_EXAME = 30
WHERE UPPER(EXAME) LIKE '%RESSON%'
  AND (TEMPO_EXAME IS NULL OR TEMPO_EXAME = 0)
  AND UPPER(EXAME) NOT LIKE '%ANGIO%'; -- Angioressonância é mais complexa

-- Angioressonância: 45 minutos (mais complexo)
UPDATE LAB_EXAMES
SET TEMPO_EXAME = 45
WHERE UPPER(EXAME) LIKE '%ANGIORESSON%'
  AND (TEMPO_EXAME IS NULL OR TEMPO_EXAME = 0);

-- Ressonância com contraste: adicionar 15 min extra
-- (será tratado no agendamento)

-- VERIFICAR
SELECT IDEXAME, EXAME, TEMPO_EXAME
FROM LAB_EXAMES
WHERE UPPER(EXAME) LIKE '%RESSON%'
ORDER BY TEMPO_EXAME, EXAME;
```

---

## **PASSO 5: Integrar no Frontend** 💻

**Opção A: Usar SEMPRE a API de Ressonância para agendas 30 e 76**
```javascript
// Em includes/agenda-new.js ou onde busca horários

function buscarHorariosPorAgenda(agendaId, data, exameId = null) {
    // Se for agenda de ressonância (30 ou 76), usar API especial
    if ([30, 76].includes(parseInt(agendaId))) {
        const url = exameId
            ? `/agenda/buscar_horarios_ressonancia.php?agenda_id=${agendaId}&data=${data}&exame_id=${exameId}`
            : `/agenda/buscar_horarios_ressonancia.php?agenda_id=${agendaId}&data=${data}`;

        return fetch(url).then(res => res.json());
    }

    // Outras agendas: usar API normal
    return fetch(`/agenda/buscar_horarios.php?agenda_id=${agendaId}&data=${data}`)
        .then(res => res.json());
}
```

**Opção B: Modificar buscar_horarios.php para detectar automaticamente**
```php
// No início de buscar_horarios.php, adicionar:

// Verificar se é agenda de ressonância
$query_tipo = "SELECT TIPO, PROCEDIMENTO_ID FROM AGENDAS WHERE ID = ?";
$stmt_tipo = ibase_prepare($conn, $query_tipo);
$result_tipo = ibase_execute($stmt_tipo, $agenda_id);
$agenda_info = ibase_fetch_assoc($result_tipo);

// Se for ressonância, redirecionar para API especializada
if ($agenda_info && in_array($agenda_id, [30, 76])) {
    // Usar lógica de buscar_horarios_ressonancia.php
    include 'buscar_horarios_ressonancia_logic.php';
    exit;
}

// Caso contrário, continuar com lógica normal
```

---

## **PASSO 6: Interface Administrativa** ⚙️

**Criar tela para configurar:**

```html
<!-- painel_ressonancia.php -->
<div class="card">
    <div class="card-header">
        <h3>⚙️ Configuração de Ressonância</h3>
    </div>
    <div class="card-body">
        <!-- Configurar Médico -->
        <h4>🩺 Disponibilidade de Médico (Contraste)</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Dia da Semana</th>
                    <th>Tem Médico?</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach(['Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo'] as $dia): ?>
                <tr>
                    <td><?= $dia ?></td>
                    <td>
                        <input type="checkbox"
                               id="medico_<?= $dia ?>"
                               onchange="atualizarMedico('<?= $dia ?>', this.checked)">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Configurar Anestesia -->
        <h4>💉 Dias com Anestesia</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Dia da Semana</th>
                    <th>Aceita Anestesia?</th>
                    <th>Limite por Dia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach(['Segunda','Terça','Quarta','Quinta','Sexta','Sábado'] as $dia): ?>
                <tr>
                    <td><?= $dia ?></td>
                    <td>
                        <input type="checkbox"
                               id="anestesia_<?= $dia ?>"
                               onchange="atualizarAnestesia('<?= $dia ?>', this.checked)">
                    </td>
                    <td>
                        <input type="number"
                               id="limite_<?= $dia ?>"
                               min="0"
                               value="0"
                               class="form-control">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
```

---

## **PASSO 7: Testes Finais** ✅

**Checklist de testes:**
```
1. ☐ Configurar médico em um dia e testar exame COM contraste
2. ☐ Testar exame COM contraste em dia SEM médico (deve bloquear)
3. ☐ Marcar exame que precisa anestesia
4. ☐ Testar anestesia na Quinta (deve funcionar)
5. ☐ Testar anestesia em outro dia (deve bloquear)
6. ☐ Agendar 2 anestesias e tentar a 3ª (deve bloquear por limite)
7. ☐ Verificar se tempo do exame está sendo usado corretamente
```

---

## 📊 **RESUMO DE DECISÕES NECESSÁRIAS:**

Me responda estas perguntas para eu executar a configuração:

```
1. MÉDICO (Contraste):
   [ ] Todos os dias
   [ ] Apenas: ___________________ (dias da semana)
   [ ] Apenas turno: ___________________ (manhã/tarde)

2. ANESTESIA:
   [ ] Manter Quinta
   [ ] Mudar para: ___________________ (dia)
   [ ] Limite: _____ anestesias por dia

3. EXAMES COM ANESTESIA:
   [ ] Marcar automaticamente (nomes com SEDAÇÃO/ANESTESIA)
   [ ] Me passar lista de IDs específicos
   [ ] Vocês marcam manualmente depois

4. TEMPO DOS EXAMES:
   [ ] Usar padrão (30 min simples, 45 min angio)
   [ ] Definir tempos específicos por exame

5. INTEGRAÇÃO:
   [ ] Opção A: JavaScript detecta agenda 30/76
   [ ] Opção B: PHP redireciona automaticamente
```

**Me responda essas perguntas e eu executo tudo para você! 🚀**
