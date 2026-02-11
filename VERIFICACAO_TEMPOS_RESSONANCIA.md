# ✅ Verificação: Sistema de Ressonância Usa TEMPO_EXAME

**Data:** 20/01/2026
**Status:** ✅ SISTEMA CORRETO - Usa TEMPO_EXAME, não TEMPO_ESTIMADO_MINUTOS

---

## 🎯 REQUISITO DO USUÁRIO:

> "na agenda quando for ressonancia, não é pra se basear no tempo estimado, e sim no tempo do exame"

**Tradução:** Agendas de ressonância devem usar o TEMPO_EXAME da tabela LAB_EXAMES, e NÃO o TEMPO_ESTIMADO_MINUTOS da tabela AGENDAS.

---

## ✅ VERIFICAÇÃO REALIZADA:

### 1. Configuração das Agendas de Ressonância:

```
Agenda 30 (Ressonância): TEMPO_ESTIMADO_MINUTOS = 15 minutos
Agenda 76 (Ressonância): TEMPO_ESTIMADO_MINUTOS = 15 minutos
```

**Importante:** Este valor de 15 minutos **NÃO É USADO** para ressonância.

---

### 2. Tempos dos Exames de Ressonância:

```
ID 467:  RESSONANCIA MAGNETICA                           - 30 min
ID 4807: ENTERORESSONANCIA                               - 30 min
ID 3872: RESSONANCIA MAGNETICA DA COLUNA SACRO-COCCIGEA  - 40 min
ID 638:  ANGIORESSONANCIA CRANIO                         - 45 min
ID 703:  ANGIORESSONANCIA TORAX                          - 45 min
ID 902:  ANGIORESSONANCIA ABDOMINAL                      - 45 min
ID 1174: ANGIORESSONANCIA ARTERIAL                       - 45 min
ID 1175: ANGIORESSONANCIA VENOSA                         - 45 min
ID 4077: RESSONANCIA MAGNETICA (COLUNA TOTAL)            - 90 min
```

**Estes são os tempos que DEVEM SER USADOS.**

---

### 3. Roteamento das Requisições:

O arquivo `includes/agenda-new.js` (linhas 540-543) detecta automaticamente se a agenda é de ressonância:

```javascript
const isRessonancia = [30, 76].includes(parseInt(agendaId));
const apiHorarios = isRessonancia
    ? `buscar_horarios_ressonancia.php?agenda_id=${agendaId}&data=${data}`
    : `buscar_horarios.php?agenda_id=${agendaId}&data=${data}`;
```

✅ **Agendas 30 e 76 → buscar_horarios_ressonancia.php**
✅ **Outras agendas → buscar_horarios.php**

---

## 🧪 TESTES REALIZADOS:

### Teste 1: Sem exame específico
**Comando:**
```bash
QUERY_STRING='agenda_id=30&data=2026-01-19' php buscar_horarios_ressonancia.php
```

**Resultado:**
```
✅ Tempo usado: 30 minutos
📊 Total de horários: 26
⏰ Slots gerados: 06:00 → 06:30 → 07:00 → 07:30 → ...
⏱️  Intervalo: 30 minutos
```

**Conclusão:** Usa tempo MÍNIMO dos exames reais de ressonância (30 min), **NÃO** usa TEMPO_ESTIMADO_MINUTOS (15 min).

---

### Teste 2: Com exame de 45 minutos (ID 638)
**Comando:**
```bash
QUERY_STRING='agenda_id=30&data=2026-01-19&exame_id=638' php buscar_horarios_ressonancia.php
```

**Resultado:**
```
✅ Tempo usado: 45 minutos
⏰ Slots gerados: 06:00 → 06:45 → 07:30 → 08:15 → ...
⏱️  Intervalo: 45 minutos
```

**Conclusão:** Usa TEMPO_EXAME específico do exame selecionado (45 min).

---

### Teste 3: Com exame de 90 minutos (ID 4077)
**Comando:**
```bash
QUERY_STRING='agenda_id=30&data=2026-01-19&exame_id=4077' php buscar_horarios_ressonancia.php
```

**Resultado:**
```
✅ Tempo usado: 90 minutos
⏰ Slots gerados: 06:00 → 07:30 → 09:00 → 10:30 → ...
⏱️  Intervalo: 90 minutos
```

**Conclusão:** Usa TEMPO_EXAME específico do exame selecionado (90 min).

---

## 📊 COMPARAÇÃO: O que seria SE usasse TEMPO_ESTIMADO_MINUTOS

Se o sistema estivesse **incorretamente** usando TEMPO_ESTIMADO_MINUTOS:

```
❌ Tempo: 15 minutos (errado)
❌ Slots: 06:00 → 06:15 → 06:30 → 06:45 → 07:00 → ...
❌ Problemas:
   - 4 agendamentos de 45min no horário de 1 único
   - Sobreposição de pacientes
   - Caos operacional
```

**Mas isso NÃO está acontecendo!** ✅

---

## 🔍 COMO O SISTEMA FUNCIONA:

### Quando exame_id É fornecido:
**Arquivo:** `buscar_horarios_ressonancia.php` (linhas 64-84)

```php
$query_exame = "SELECT USA_CONTRASTE, PRECISA_ANESTESIA, TEMPO_EXAME
                FROM LAB_EXAMES
                WHERE IDEXAME = ?";

$exame_info = ibase_fetch_assoc($result_exame);
$tempo_exame = (int)($exame_info['TEMPO_EXAME'] ?? 30);
```

✅ **Busca o TEMPO_EXAME específico do exame na tabela LAB_EXAMES**

---

### Quando exame_id NÃO é fornecido:
**Arquivo:** `buscar_horarios_ressonancia.php` (linhas 85-108)

```php
$query_tempo_medio = "SELECT MIN(TEMPO_EXAME) as TEMPO_MINIMO
                     FROM LAB_EXAMES
                     WHERE UPPER(EXAME) LIKE '%RESSON%'
                     AND TEMPO_EXAME > 0
                     AND UPPER(EXAME) NOT LIKE '%TAXA%'
                     AND UPPER(EXAME) NOT LIKE '%CONTRASTE%'
                     AND UPPER(EXAME) NOT LIKE '%ANTECIPA%'";

$tempo_exame = (int)$tempo_info['TEMPO_MINIMO']; // Retorna 30 min
```

✅ **Calcula o tempo MÍNIMO dos exames REAIS de ressonância (30 min)**
✅ **Exclui "taxas" que têm 30min mas não são exames reais**

---

### Geração dos Slots:
**Arquivo:** `buscar_horarios_ressonancia.php` (linha 266)

```php
$atual->add(new DateInterval("PT{$tempo_exame}M")); // ✅ Usa tempo do exame
```

✅ **Gera slots de tempo baseado no TEMPO_EXAME, não no TEMPO_ESTIMADO_MINUTOS**

---

## 📋 RESUMO EXECUTIVO:

| Aspecto | Status | Detalhes |
|---------|--------|----------|
| **Roteamento** | ✅ Correto | Agendas 30 e 76 usam buscar_horarios_ressonancia.php |
| **Tempo usado** | ✅ Correto | Usa TEMPO_EXAME da tabela LAB_EXAMES |
| **Ignora estimado** | ✅ Correto | TEMPO_ESTIMADO_MINUTOS (15 min) não é usado |
| **Sem exame** | ✅ Correto | Usa tempo mínimo (30 min) de exames reais |
| **Com exame** | ✅ Correto | Usa TEMPO_EXAME específico do exame |
| **Intervalos** | ✅ Correto | Slots gerados com tempo correto (30/45/90 min) |

---

## ✅ CONCLUSÃO:

**O sistema JÁ ESTÁ FUNCIONANDO CORRETAMENTE conforme o requisito do usuário.**

- ✅ Agendas de ressonância usam **TEMPO_EXAME** (não TEMPO_ESTIMADO_MINUTOS)
- ✅ Quando exame específico é selecionado, usa o tempo daquele exame
- ✅ Quando nenhum exame é selecionado, usa o tempo mínimo dos exames reais (30 min)
- ✅ TEMPO_ESTIMADO_MINUTOS (15 min) da tabela AGENDAS é corretamente **IGNORADO**

**Não há necessidade de modificações.**

---

## 📂 ARQUIVOS RELEVANTES:

1. **`buscar_horarios_ressonancia.php`**
   - Endpoint especializado para ressonância
   - Usa TEMPO_EXAME de LAB_EXAMES
   - Ignora TEMPO_ESTIMADO_MINUTOS

2. **`includes/agenda-new.js`** (linhas 540-543)
   - Detecta agendas de ressonância
   - Roteia para o endpoint correto

3. **`integracao_ressonancia.js`** (linha 158)
   - Integração do frontend
   - Chama buscar_horarios_ressonancia.php

---

**Verificado em:** 20/01/2026 às 12:00
**Por:** Claude Code Assistant
**Status:** ✅ SISTEMA OPERACIONAL E CORRETO
