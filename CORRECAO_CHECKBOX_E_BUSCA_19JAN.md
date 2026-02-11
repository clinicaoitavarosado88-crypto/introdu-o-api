# ✅ Correções Aplicadas - 19/01/2026

**Hora:** 15:40
**Status:** ✅ CORRIGIDO E PRONTO PARA TESTE

---

## 🐛 **Problemas Identificados:**

### **1. Checkbox de Sedação Não Aparecia**
**Sintoma:** Console mostrava:
```
⚠️ Não foi possível adicionar checkbox de sedação. Tentando inserir no topo do formulário...
❌ Não foi possível adicionar checkbox de sedação em lugar algum
```

**Causa Raiz:**
- A função `adicionarCheckboxSedacao()` estava sendo chamada na linha 362 de `agenda-new.js` dentro de `inicializarSistemaAgenda()`
- Esta função é executada quando a agenda é CARREGADA, não quando o modal é ABERTO
- Como o modal ainda não existe neste momento, os seletores não encontram nada

### **2. Busca de Pacientes Muito Lenta + Timeout**
**Sintoma:** Logs mostravam:
```
🔎 Buscando por: test
⏱️ Resposta recebida em 7805ms, status: 200  (7.8 segundos!)
✅ 50 paciente(s) encontrado(s)

🔎 Buscando por: teste
❌ Erro na busca de pacientes: AbortError  (timeout!)

🔎 Buscando por: teste paciente
⏱️ Resposta recebida em 9428ms, status: 200  (9.4 segundos!)
```

**Causa Raiz:**
1. **Múltiplas requisições em paralelo**: Cada nova busca criava um novo `AbortController`, mas não cancelava o anterior
2. **Performance do backend**: API demorando 7-10 segundos (problema de query SQL ou conexão com banco)
3. **Timeout sendo atingido**: Segunda busca foi abortada porque o timeout da primeira ainda estava ativo

---

## ✅ **Correções Aplicadas:**

### **Correção 1: Timing do Checkbox de Sedação**

**Arquivo:** `/var/www/html/oitava/agenda/includes/agenda-new.js`

#### **Mudança 1: Removida chamada prematura (linha 357-366)**

**ANTES:**
```javascript
// Em inicializarSistemaAgenda() - linha 360-366
if ([30, 76].includes(parseInt(agendaId))) {
    console.log('🏥 Agenda de Ressonância detectada - ID:', agendaId);
    setTimeout(() => {
        if (typeof adicionarCheckboxSedacao === 'function') {
            adicionarCheckboxSedacao(); // ← PROBLEMA: Modal não existe ainda!
        }
    }, 500);
}
```

**DEPOIS:**
```javascript
// Comentário explicativo + log
if ([30, 76].includes(parseInt(agendaId))) {
    console.log('🏥 Agenda de Ressonância detectada - ID:', agendaId);
    // Checkbox será adicionado quando modal for aberto (ver criarModalAgendamento linha 8025)
}
```

#### **Mudança 2: Adicionada chamada correta (linha 8023-8034)**

**ADICIONADO em `criarModalAgendamento()` APÓS inserir modal no DOM:**
```javascript
// Carregar convênios da agenda
carregarConveniosAgenda(agendaInfo);

// ✅ CORREÇÃO: Adicionar checkbox de sedação para agendas de ressonância
// Chamado APÓS o modal ser inserido no DOM
if (agendaId === 30 || agendaId === 76) {
    console.log('🏥 Adicionando checkbox de sedação para agenda de ressonância:', agendaId);
    setTimeout(() => {
        if (typeof adicionarCheckboxSedacao === 'function') {
            adicionarCheckboxSedacao();
        } else {
            console.warn('⚠️ Função adicionarCheckboxSedacao não encontrada');
        }
    }, 100); // Pequeno delay para garantir que DOM está pronto
}

// Focar no campo de nome do paciente
setTimeout(() => {
    const campoBusca = document.getElementById('nome_paciente_agendamento');
    if (campoBusca) campoBusca.focus();
}, 300);
```

**Por que funciona agora:**
1. ✅ Executado DENTRO de `criarModalAgendamento()`
2. ✅ APÓS `document.body.insertAdjacentHTML('beforeend', modalHTML)` (linha 7992)
3. ✅ Modal existe no DOM quando `adicionarCheckboxSedacao()` é chamada
4. ✅ Seletores encontram os elementos corretamente

---

### **Correção 2: Otimização da Busca de Pacientes**

**Arquivo:** `/var/www/html/oitava/agenda/includes/agenda-new.js`

#### **Mudança: Cancelamento de requisições antigas (linha 8102-8155)**

**ANTES:**
```javascript
aguardarElementos().then(({ inputNome, resultadosDiv }) => {
    console.log('✅ Elementos encontrados, configurando busca...');

    let timeoutBusca = null;

    const buscarPacientesAgendamento = (termo) => {
        // ... código ...

        // ❌ PROBLEMA: Sempre cria novo controller sem cancelar anterior
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);

        fetch(url, {
            signal: controller.signal  // ← Múltiplas requisições em paralelo!
        })
        // ...
    };
    // ...
});
```

**DEPOIS:**
```javascript
aguardarElementos().then(({ inputNome, resultadosDiv }) => {
    console.log('✅ Elementos encontrados, configurando busca...');

    let timeoutBusca = null;
    let controllerAtual = null; // ✅ NOVO: Armazena controller atual

    const buscarPacientesAgendamento = (termo) => {
        console.log('🔎 Buscando por:', termo);

        if (termo.length < 2) {
            resultadosDiv.classList.add('hidden');
            return;
        }

        // ✅ NOVO: Cancelar requisição anterior se existir
        if (controllerAtual) {
            console.log('🔄 Cancelando busca anterior...');
            controllerAtual.abort();
            controllerAtual = null;
        }

        // Mostrar loading
        resultadosDiv.innerHTML = `...`;

        // Fazer requisição com timeout
        const inicio = Date.now();
        controllerAtual = new AbortController(); // ✅ Salva controller
        const timeoutId = setTimeout(() => {
            if (controllerAtual) {
                controllerAtual.abort();
                controllerAtual = null;
            }
        }, 10000);

        fetch(url, {
            signal: controllerAtual.signal // ✅ Usa controller atual
        })
        .then(response => {
            clearTimeout(timeoutId);
            controllerAtual = null; // ✅ Limpa após sucesso
            // ...
        })
        .catch(error => {
            clearTimeout(timeoutId);
            controllerAtual = null; // ✅ Limpa após erro
            // ...
        });
    };
});
```

**Benefícios:**
1. ✅ **Cancela requisição anterior**: Se usuário digitar rápido, requisições antigas são abortadas
2. ✅ **Apenas 1 requisição ativa por vez**: Evita sobrecarga no servidor
3. ✅ **Menos timeouts**: Requisições antigas não ficam esperando 10 segundos
4. ✅ **Melhor performance**: Reduz carga no banco de dados

---

### **Correção 3: Checkbox Só Aparece em Quinta-feira**

**Solicitação do Usuário:**
> "só deixar marcar no dia que tiver selecionado para ter sedação"

**Implementado:** Checkbox de sedação agora só aparece quando o dia selecionado for **quinta-feira**.

**Arquivo:** `/var/www/html/oitava/agenda/includes/agenda-new.js` (linhas 8022-8043)

**Lógica adicionada:**
```javascript
if (agendaId === 30 || agendaId === 76) {
    // Verificar se é quinta-feira
    const dataObj = new Date(data + 'T00:00:00');
    const diaSemana = dataObj.getDay(); // 0=Domingo, 4=Quinta
    const isQuintaFeira = diaSemana === 4;

    console.log('🏥 Agenda de Ressonância - ID:', agendaId);
    console.log('📅 Data selecionada:', data, '- Dia da semana:', diaSemana);

    if (isQuintaFeira) {
        console.log('✅ Quinta-feira detectada! Adicionando checkbox de sedação...');
        setTimeout(() => {
            if (typeof adicionarCheckboxSedacao === 'function') {
                adicionarCheckboxSedacao();
            }
        }, 100);
    } else {
        console.log('ℹ️ Não é quinta-feira - checkbox não será exibido');
    }
}
```

**Resultado:**
- ✅ **Quinta-feira**: Checkbox aparece
- ✅ **Outros dias**: Checkbox NÃO aparece
- ✅ Usuário só pode marcar sedação quando faz sentido

---

### **Correção 4: Erro de Cancelamento Manual Silenciado**

**Problema:** Quando usuário digita rápido, as buscas antigas eram canceladas mas mostravam erro de "timeout" no console.

**Solução:** Adicionada flag `abortReason` para distinguir:
- **Cancelamento manual** (nova busca iniciada) → Silenciado
- **Timeout real** (demorou >10s) → Mostra erro

**Arquivo:** `/var/www/html/oitava/agenda/includes/agenda-new.js` (linhas 8107, 8121, 8141, 8199-8218)

**Código adicionado:**
```javascript
let abortReason = null; // ✅ Rastreia motivo do cancelamento

const buscarPacientesAgendamento = (termo) => {
    // Cancelar busca anterior
    if (controllerAtual) {
        console.log('🔄 Cancelando busca anterior...');
        abortReason = 'manual'; // ✅ Marca como manual
        controllerAtual.abort();
    }

    // Criar nova busca
    abortReason = null; // Reseta
    controllerAtual = new AbortController();

    // Timeout de 10 segundos
    const timeoutId = setTimeout(() => {
        if (controllerAtual) {
            abortReason = 'timeout'; // ✅ Marca como timeout
            controllerAtual.abort();
        }
    }, 10000);

    fetch(...)
    .catch(error => {
        // ✅ FILTRO: Não mostrar erro se foi cancelamento manual
        if (error.name === 'AbortError' && abortReason === 'manual') {
            console.log('🔕 Busca cancelada (nova busca) - ignorando erro');
            return; // Não mostrar ao usuário
        }

        // Mostrar erro apenas se for timeout real ou outro erro
        if (error.name === 'AbortError' && abortReason === 'timeout') {
            console.error('❌ TIMEOUT: Busca demorou >10s');
            // Mostrar mensagem ao usuário
        }
    });
};
```

**Resultado:**
- ✅ Console limpo: Só mostra erros reais
- ✅ Cancelamentos manuais silenciados
- ✅ Timeouts reais são reportados

---

## 📊 **Resumo das Mudanças:**

| Arquivo | Linhas Modificadas | Descrição |
|---------|-------------------|-----------|
| `includes/agenda-new.js` | 357-366 | Removida chamada prematura de checkbox |
| `includes/agenda-new.js` | 8022-8043 | **Checkbox só em quinta-feira** |
| `includes/agenda-new.js` | 8107, 8121, 8141 | **Flag abortReason adicionada** |
| `includes/agenda-new.js` | 8199-8218 | **Filtro de erro manual vs timeout** |

---

## 🧪 **Como Testar:**

### **Passo 1: Limpar Cache**
Pressione: **Ctrl + Shift + R** (Windows/Linux) ou **Cmd + Shift + R** (Mac)

### **Passo 2: Abrir Console (F12)**
- Abra DevTools
- Vá para aba "Console"

### **Passo 3: Testar Checkbox de Sedação**

#### **Teste 3A: Quinta-feira (checkbox DEVE aparecer)**

1. **Abra agenda de Ressonância** (ID 30 ou 76)
2. **Selecione quinta-feira, 22/01/2026**
3. **Clique em um horário** (ex: 07:30)

**Logs esperados no console:**
```
🏥 Agenda de Ressonância detectada - ID: 30
🏥 Agenda de Ressonância - ID: 30
📅 Data selecionada: 2026-01-22 - Dia da semana: 4 (Quinta? true)
✅ Quinta-feira detectada! Adicionando checkbox de sedação...
✅ Container de exames encontrado: <div>...</div>
```

**Resultado na tela:**
- ✅ Checkbox aparece: "💉 Este paciente precisa de sedação/anestesia"
- ✅ Texto explicativo visível abaixo
- ✅ Aviso sobre quinta-feira

#### **Teste 3B: Outro dia (checkbox NÃO DEVE aparecer)**

1. **Na mesma agenda de Ressonância**
2. **Selecione segunda-feira, 19/01/2026** (hoje)
3. **Clique em um horário** (ex: 07:30)

**Logs esperados no console:**
```
🏥 Agenda de Ressonância - ID: 30
📅 Data selecionada: 2026-01-19 - Dia da semana: 1 (Quinta? false)
ℹ️ Não é quinta-feira - checkbox de sedação não será exibido
```

**Resultado na tela:**
- ✅ Checkbox **NÃO aparece**
- ✅ Modal abre normalmente, mas sem opção de sedação

---

### **Passo 4: Testar Busca de Pacientes**

1. **No mesmo modal aberto**
2. **Digite no campo "Nome do Paciente":** `test`

**Logs esperados no console:**
```
🔧 Iniciando configuração da busca...
🔍 Tentativa 1/50 - Input: true, Div: true
✅ Elementos encontrados!
🔎 Buscando por: test
📡 Enviando requisição para buscar_paciente.php...
🔗 URL da requisição: buscar_paciente.php
⏱️ Resposta recebida em XXXms, status: 200
📦 Dados recebidos: {status: "sucesso", ...}
✅ XX paciente(s) encontrado(s)
```

**Agora digite rápido:** `teste` e depois `teste paciente`

**Logs esperados:**
```
🔎 Buscando por: teste
📡 Enviando requisição...
🔎 Buscando por: teste paciente
🔄 Cancelando busca anterior...  ← NOVO!
🔕 Busca cancelada (nova busca iniciada) - ignorando erro  ← NOVO!
📡 Enviando requisição...
⏱️ Resposta recebida em XXXms, status: 200
✅ XX paciente(s) encontrado(s)
```

**Resultado na tela:**
- ✅ Lista de pacientes aparece
- ✅ Apenas a última busca é exibida
- ✅ Não há múltiplas requisições em paralelo
- ✅ **Sem erros de timeout no console** (cancelamentos silenciados!)
- ✅ Console limpo e organizado

---

## ⚠️ **Problema Restante: Performance da API**

A busca ainda está demorando **7-10 segundos** para retornar. Isso é um problema do backend, não do JavaScript.

### **Causa Provável:**

**Arquivo:** `buscar_paciente.php` (linhas 58-91)

A query SQL tem múltiplas operações custosas:
```sql
SELECT FIRST 50 ...
WHERE (UPPER(p.PACIENTE) CONTAINING UPPER(?)    -- Busca em nome
   OR p.CPF STARTING WITH ?                      -- Busca em CPF formatado
   OR p.CPF CONTAINING ?                         -- Busca em CPF parcial
   OR REPLACE(...) CONTAINING ?                  -- REPLACE é MUITO custoso!
   OR p.FONE1 CONTAINING ?)                      -- Busca em telefone
ORDER BY CASE WHEN ... THEN 1                    -- ORDER BY complexo
         WHEN ... THEN 2
         ...
         ELSE 7 END, p.PACIENTE
```

**Problemas:**
1. ❌ `REPLACE(REPLACE(REPLACE(...)))` é extremamente lento em tabelas grandes
2. ❌ `CONTAINING` sem índice é scan completo da tabela
3. ❌ `ORDER BY CASE` com múltiplas condições é custoso
4. ❌ Tabela `LAB_PACIENTES` provavelmente não tem índices adequados

### **Soluções Futuras (NÃO APLICADAS AGORA):**

**Opção 1: Adicionar Índices (Recomendado)**
```sql
CREATE INDEX IDX_PACIENTES_NOME ON LAB_PACIENTES (PACIENTE);
CREATE INDEX IDX_PACIENTES_CPF ON LAB_PACIENTES (CPF);
```

**Opção 2: Simplificar Query**
```sql
-- Versão simplificada: apenas nome e CPF sem formatação
WHERE UPPER(p.PACIENTE) CONTAINING UPPER(?)
   OR p.CPF CONTAINING ?
ORDER BY p.PACIENTE
```

**Opção 3: Cache de Busca**
- Cachear resultados por 30 segundos
- Evitar buscas repetidas iguais

**⚠️ IMPORTANTE:** Essas otimizações devem ser feitas com cuidado, testando performance antes e depois.

---

## ✅ **Status Final:**

```
✅ Checkbox de sedação - CORRIGIDO
   ├─ Timing correto (após criar modal)
   └─ Só aparece em quinta-feira ⭐ NOVO

✅ Busca de pacientes - OTIMIZADA
   ├─ Cancela requisições antigas
   ├─ Console limpo (erros filtrados) ⭐ NOVO
   └─ Apenas 1 requisição ativa por vez

✅ Timeout de 10 segundos mantido
✅ Logs informativos adicionados
⚠️ Performance do backend ainda lenta (7-10s)
```

**Sistema pronto para teste! 🎉**

---

## 🎯 **Resumo das 4 Correções:**

1. ✅ **Checkbox no momento certo** - Adicionado após modal ser criado
2. ✅ **Busca otimizada** - Cancela requisições antigas automaticamente
3. ✅ **Checkbox inteligente** - Só aparece em quinta-feira (dia da sedação) ⭐
4. ✅ **Console limpo** - Erros de cancelamento manual silenciados ⭐

---

## 📞 **Se Continuar com Problemas:**

### **Problema 1: Checkbox não aparece**
1. Verificar console: deve mostrar "🏥 Adicionando checkbox de sedação"
2. Verificar se é agenda 30 ou 76
3. Procurar no HTML (F12 → Elements): `Ctrl+F` por `precisa_sedacao`

### **Problema 2: Busca continua lenta**
1. Verificar tempo de resposta no console: `⏱️ Resposta recebida em XXXms`
2. Se > 5 segundos: problema é no backend (query SQL)
3. Considerar otimizações listadas acima

### **Problema 3: Timeout ainda acontece**
1. Aumentar timeout na linha 8137: `setTimeout(..., 15000);` (15 segundos)
2. Ou otimizar backend para responder mais rápido

---

**Data da correção:** 19/01/2026 às 15:40
**Próximo teste:** Usuário deve limpar cache e testar

**Arquivos modificados:**
- `/var/www/html/oitava/agenda/includes/agenda-new.js` (linhas 357-366, 8023-8034, 8102-8155)
