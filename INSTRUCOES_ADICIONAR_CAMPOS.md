# 📋 INSTRUÇÕES: Como Adicionar Novos Campos Editáveis

## 🎯 Para adicionar um novo campo editável na auditoria:

### 1. **Editar `editar_agendamento.php`**

**A) Atualizar a query de UPDATE do agendamento:**
```php
$query_update_agendamento = "UPDATE AGENDAMENTOS SET 
                             STATUS = ?,
                             TIPO_CONSULTA = ?,
                             OBSERVACOES = ?,
                             NOVO_CAMPO = ?     -- ADICIONAR AQUI
                             WHERE ID = ?";
```

**B) Adicionar parâmetro na execução:**
```php
$result_agendamento = ibase_execute($stmt_agendamento,
    $status,
    $tipo_consulta,
    $observacoes_convertidas,
    $novo_campo_valor,    -- ADICIONAR AQUI
    $agendamento_id
);
```

**C) Adicionar o novo campo em dados_antes_edicao:**
```php
$dados_antes_edicao = [
    // ... campos existentes ...
    'novo_campo' => $agendamento_atual['NOVO_CAMPO']
];
```

**D) Adicionar o novo campo em dados_apos_edicao:**
```php
$dados_apos_edicao = [
    // ... campos existentes ...
    'novo_campo' => $novo_campo_valor
];
```

### 2. **Editar `includes/auditoria.php`**

**A) Adicionar na função `identificarCamposAlterados`:**
```php
$campos_editaveis = [
    'email', 'status', 'tipo_consulta', 'observacoes', 'data_nascimento',
    'novo_campo' // ADICIONAR AQUI
];
```

**B) Adicionar na função `compararDadosDetalhados`:**
```php
$campos_comparar = [
    // ... campos existentes ...
    'novo_campo' => ['novo_campo_anterior', 'novo_campo_novo']
];
```

**C) Adicionar na query SELECT de `buscarHistoricoAuditoria`:**
```sql
SELECT ... STATUS_ANTERIOR, STATUS_NOVO,
TIPO_CONSULTA_ANTERIOR, TIPO_CONSULTA_NOVO,
NOVO_CAMPO_ANTERIOR, NOVO_CAMPO_NOVO  -- ADICIONAR AQUI
FROM AGENDA_AUDITORIA
```

**D) Adicionar na query INSERT de `registrarAuditoriaExpandida`:**
```sql
INSERT INTO AGENDA_AUDITORIA (...
TIPO_CONSULTA_ANTERIOR, TIPO_CONSULTA_NOVO,
NOVO_CAMPO_ANTERIOR, NOVO_CAMPO_NOVO,  -- ADICIONAR AQUI
CONVENIO_ANTERIOR, CONVENIO_NOVO, ...
) VALUES (..., ?, ?, ...)  -- ADICIONAR ?, ?
```

**E) Adicionar nos parâmetros da execução:**
```php
$params['tipo_consulta_anterior'] ?? null,
$params['tipo_consulta_novo'] ?? null,
$params['novo_campo_anterior'] ?? null,  // ADICIONAR
$params['novo_campo_novo'] ?? null,      // ADICIONAR
$params['convenio_anterior'] ?? null,
```

**F) Adicionar no array de parâmetros:**
```php
'tipo_consulta_anterior' => $comparacao['tipo_consulta_anterior'],
'tipo_consulta_novo' => $comparacao['tipo_consulta_novo'], 
'novo_campo_anterior' => $comparacao['novo_campo_anterior'], // ADICIONAR
'novo_campo_novo' => $comparacao['novo_campo_novo'],         // ADICIONAR
'convenio_anterior' => $comparacao['convenio_anterior'],
```

### 3. **Editar `buscar_historico_agendamento.php`**

**A) Adicionar no mapeamento de campos amigáveis:**
```php
$camposAmigaveis = [
    // ... campos existentes ...
    'novo_campo' => 'Nome Amigável do Campo'
];
```

**B) Adicionar no retorno dos dados:**
```php
'tipo_consulta_anterior' => $registro['TIPO_CONSULTA_ANTERIOR'] ?? null,
'tipo_consulta_novo' => $registro['TIPO_CONSULTA_NOVO'] ?? null,
'novo_campo_anterior' => $registro['NOVO_CAMPO_ANTERIOR'] ?? null, // ADICIONAR
'novo_campo_novo' => $registro['NOVO_CAMPO_NOVO'] ?? null,         // ADICIONAR
```

### 4. **Editar `includes/agenda-new.js`**

**Adicionar exibição das mudanças:**
```javascript
${item.novo_campo_anterior && item.novo_campo_novo ? `
    <div class="text-xs bg-green-50 text-green-800 px-2 py-1 rounded mb-2">
        <i class="bi bi-arrow-right mr-1"></i>
        <strong>Nome do Campo:</strong> ${item.novo_campo_anterior} → ${item.novo_campo_novo}
    </div>
` : ''}
```

### 5. **Criar/Alterar colunas no banco (se necessário)**
```sql
-- Adicionar coluna na tabela principal (se não existir)
ALTER TABLE AGENDAMENTOS ADD NOVO_CAMPO VARCHAR(255);

-- Adicionar colunas na auditoria para valores antes/depois
ALTER TABLE AGENDA_AUDITORIA ADD NOVO_CAMPO_ANTERIOR VARCHAR(255);
ALTER TABLE AGENDA_AUDITORIA ADD NOVO_CAMPO_NOVO VARCHAR(255);
```

## 🚀 **Exemplo Completo: Campo "observações" já implementado**

✅ **Campos já funcionando:**
- `email` - E-mail do paciente
- `status` - Status do agendamento (AGENDADO, CONFIRMADO, etc.)
- `tipo_consulta` - Primeira vez ou retorno
- `observacoes` - Observações do agendamento (**RECÉM ADICIONADO!**)
- `data_nascimento` - Data de nascimento do paciente

## 🎯 **Processo Simplificado para Novos Campos:**

1. **Atualizar tabela AGENDAMENTOS** com o novo campo
2. **Seguir passos 1-4 acima** adicionando o novo campo
3. **Testar** fazendo uma edição e verificando o histórico
4. **Criar colunas de auditoria** se necessário

## ⚠️ **IMPORTANTE:**

- **Sempre teste** em ambiente de desenvolvimento primeiro
- **Backup do banco** antes de alterações estruturais  
- **Encoding:** Use `iconv('UTF-8', 'Windows-1252//IGNORE', $valor)` para campos de texto
- **Validação:** Adicione validação de dados antes de salvar

✅ **Resultado:** Campo aparece automaticamente no histórico quando alterado!