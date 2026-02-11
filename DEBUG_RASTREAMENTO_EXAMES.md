# 🔍 Sistema de Rastreamento de Exames - Debug Completo

**Data:** 20/01/2026
**Status:** ✅ IMPLEMENTADO
**Objetivo:** Identificar de onde vêm os IDs extras de exames não selecionados

---

## 🎯 PROBLEMA A SER INVESTIGADO

- **Sintoma:** Usuário seleciona 2 exames (IDs 544, 545)
- **JavaScript envia correto:** `exames_ids: "544,545"` ✅
- **Banco salva 3+ exames:** 544 ✅, 545 ✅, 2369 ❌ (extra!)
- **IDs extras variam:** Ora 443 (USG), ora 2369 (USG)

---

## ✅ O QUE FOI IMPLEMENTADO

### 1. Array de Debug Global: `$debug_trace_exames`

Criado no arquivo `processar_agendamento.php` (linha ~90) que rastreia TODO o processamento dos exames, do início ao fim.

### 2. Rastreamento Incluído:

#### **A) Dados POST Recebidos:**
```json
{
  "todos_campos_post": {
    "exames_ids": "544,545",
    "exame_id": "...",  // Se existir
    ...
  }
}
```

#### **B) Processamento Passo a Passo:**
```json
{
  "exames_ids_raw": "544,545",
  "passo_1_explode": ["544", "545"],
  "passo_2_array_filter": ["544", "545"],
  "passo_3_array_map": [544, 545],
  "passo_4_array_unique": [544, 545],
  "exames_ids_final": [544, 545],
  "quantidade_final": 2
}
```

#### **C) Inserções no Banco:**
```json
{
  "insercoes_bd": [
    {
      "exame_id": 544,
      "status": "SUCESSO"
    },
    {
      "exame_id": 545,
      "status": "SUCESSO"
    }
  ]
}
```

#### **D) Verificação BD Pós-Commit:**
```json
{
  "exames_salvos_bd": [
    {
      "exame_id": 544,
      "exame_nome": "RM COLUNA CERVICAL"
    },
    {
      "exame_id": 545,
      "exame_nome": "RM COLUNA DORSAL"
    },
    {
      "exame_id": 2369,  // ❌ DE ONDE VEIO ISSO???
      "exame_nome": "2º VIA USG DE PARTES MOLES"
    }
  ],
  "total_salvo_bd": 3  // ❌ Esperávamos 2!
}
```

---

## 🧪 COMO TESTAR

### Passo 1: Criar Novo Agendamento

1. Abrir modal de agendamento
2. Selecionar **EXATAMENTE 2 exames** (por exemplo: RM COLUNA CERVICAL + RM COLUNA DORSAL)
3. **NÃO selecionar nenhum outro exame**
4. Salvar o agendamento

### Passo 2: Verificar Resposta JSON no Console

Abrir DevTools (F12) → Console e procurar pela resposta JSON que contém:

```javascript
{
  "status": "sucesso",
  "numero_agendamento": "AGD-XXXX",
  "debug_exames_processamento": {
    // ⬇️ DADOS CRÍTICOS AQUI ⬇️
    ...
  }
}
```

### Passo 3: Analisar o Debug

Copiar o objeto `debug_exames_processamento` completo e verificar:

#### ✅ **Cenário Normal (Correto):**
```json
{
  "exames_ids_final": [544, 545],
  "quantidade_final": 2,
  "insercoes_bd": [
    {"exame_id": 544, "status": "SUCESSO"},
    {"exame_id": 545, "status": "SUCESSO"}
  ],
  "exames_salvos_bd": [
    {"exame_id": 544, "exame_nome": "RM COLUNA CERVICAL"},
    {"exame_id": 545, "exame_nome": "RM COLUNA DORSAL"}
  ],
  "total_salvo_bd": 2  // ✅ Bate com quantidade_final!
}
```

#### ❌ **Cenário com Bug (Exames Extras):**
```json
{
  "exames_ids_final": [544, 545],  // ✅ Correto até aqui
  "quantidade_final": 2,  // ✅ Correto
  "insercoes_bd": [
    {"exame_id": 544, "status": "SUCESSO"},
    {"exame_id": 545, "status": "SUCESSO"}
  ],  // ✅ Apenas 2 inserções - correto!
  "exames_salvos_bd": [
    {"exame_id": 443, "exame_nome": "USG..."},   // ❌ DE ONDE VEIO???
    {"exame_id": 544, "exame_nome": "RM COLUNA CERVICAL"},
    {"exame_id": 545, "exame_nome": "RM COLUNA DORSAL"}
  ],
  "total_salvo_bd": 3  // ❌ Erro! Deveria ser 2!
}
```

**Interpretação:**
- Se `insercoes_bd` mostra apenas 2 inserções (correto)
- Mas `exames_salvos_bd` mostra 3 exames (errado)
- **Significa:** Há alguma lógica EXTERNA (trigger, procedure, código PHP adicional) inserindo exames automaticamente

---

## 🎯 POSSÍVEIS FONTES DO BUG

### Hipótese 1: Trigger AFTER INSERT ❓
- O trigger `TRG_AGENDAMENTO_EXAMES_BI` é BEFORE INSERT e apenas gera ID
- Pode haver outro trigger AFTER INSERT não descoberto

### Hipótese 2: Stored Procedure ❓
- Alguma procedure que insere "exames relacionados" automaticamente

### Hipótese 3: Código PHP Adicional ❓
- Outra parte do código PHP que insere exames
- Talvez em `includes/auditoria.php` ou outro arquivo

### Hipótese 4: JavaScript Enviando Campos Extras ❓
- Campo hidden adicional com IDs extras
- Verificar em `todos_campos_post` se há mais campos além de `exames_ids`

---

## 📊 COMPARAÇÃO DOS DADOS

| Campo | Valor Esperado | Se Aparecer Extra | Significa |
|-------|---------------|-------------------|-----------|
| `todos_campos_post` | Apenas `exames_ids: "544,545"` | Outros campos com IDs | ❌ JavaScript enviando extra |
| `exames_ids_final` | `[544, 545]` | `[544, 545, 443]` | ❌ PHP processando errado |
| `insercoes_bd` | 2 inserções | 3+ inserções | ❌ PHP inserindo extras |
| `exames_salvos_bd` | 2 exames | 3+ exames | ❌ Trigger/Procedure/Código externo |

---

## 🔧 PRÓXIMOS PASSOS APÓS TESTE

### Se `todos_campos_post` mostrar campos extras:
→ Problema está no **JavaScript** enviando dados incorretos
→ Revisar `includes/agenda-new.js`

### Se `exames_ids_final` tiver IDs extras:
→ Problema está no **processamento PHP** (explode/filter/map)
→ Revisar lógica em `processar_agendamento.php` linhas 98-131

### Se `insercoes_bd` mostrar inserções extras:
→ Problema está no **loop foreach** do PHP
→ Revisar linhas 982-1010 de `processar_agendamento.php`

### Se `exames_salvos_bd` tiver mais que `insercoes_bd`:
→ Problema está em **código externo ao PHP**:
- Trigger AFTER INSERT não descoberto
- Stored Procedure automática
- Outro arquivo PHP sendo executado

---

## 📝 COMANDOS ÚTEIS PARA INVESTIGAÇÃO

### Buscar TODOS os triggers da tabela:
```sql
SELECT RDB$TRIGGER_NAME, RDB$TRIGGER_TYPE, RDB$TRIGGER_INACTIVE
FROM RDB$TRIGGERS
WHERE RDB$RELATION_NAME = 'AGENDAMENTO_EXAMES'
ORDER BY RDB$TRIGGER_TYPE;
```

**Tipos de trigger:**
- `1` = BEFORE INSERT
- `2` = AFTER INSERT
- `3` = BEFORE UPDATE
- `4` = AFTER UPDATE
- `5` = BEFORE DELETE
- `6` = AFTER DELETE

### Buscar Stored Procedures que mencionam AGENDAMENTO_EXAMES:
```sql
SELECT RDB$PROCEDURE_NAME
FROM RDB$PROCEDURES
WHERE RDB$PROCEDURE_SOURCE CONTAINING 'AGENDAMENTO_EXAMES';
```

---

## ✅ CONCLUSÃO

Com este sistema de rastreamento, vamos **identificar EXATAMENTE** onde os IDs extras estão sendo adicionados:

1. ✅ No JavaScript (campo POST)?
2. ✅ No processamento PHP?
3. ✅ No loop de inserção?
4. ✅ Em código externo (trigger/procedure)?

**O debug vai revelar a verdade!** 🕵️

---

**Implementado em:** 20/01/2026
**Por:** Claude Code Assistant
**Arquivos modificados:**
- `processar_agendamento.php` (linhas 89-93, 100-127, 980-1049, 1068)

**Status:** ⏳ AGUARDANDO TESTE DO USUÁRIO
