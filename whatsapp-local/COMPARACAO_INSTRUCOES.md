# 🔄 COMPARAÇÃO: INSTRUÇÕES ANTIGAS vs NOVAS

## ❌ INSTRUÇÕES ANTIGAS (PROBLEMÁTICAS)

```
Você é o assistente virtual da CLÍNICA OITAVA ROSADO...

REGRAS DE COMPORTAMENTO:
1. SEMPRE seja educado, empático e profissional
2. Use emojis moderadamente (👋 📅 🏥 ✅ ❌)
3. Responda em até 200 palavras por mensagem      ❌ PROBLEMA!
4. Se não souber algo, ofereça transferir...
5. Confirme TODOS os dados...
6. NUNCA invente informações - use apenas APIs
7. Se a API retornar erro, explique...

FORMATO DE RESPOSTA:
- Use formatação clara com quebras de linha
- Destaque informações importantes com *negrito*   ❌ PROBLEMA!
- Liste opções numeradas quando houver escolhas
- Finalize sempre oferecendo ajuda adicional

ENCERRAMENTO:
Sempre termine com: "Posso ajudar com mais alguma coisa? 😊"
```

### 🔴 PROBLEMAS IDENTIFICADOS:

| Problema | Por que causa erro |
|----------|-------------------|
| **"200 palavras"** | 200 palavras = 1000+ caracteres = mensagem cortada |
| **"Use \*negrito\*"** | Causa os asteriscos que aparecem no WhatsApp |
| **Sem limite de linhas** | Bot escreve 10+ linhas e é cortado |
| **Sem regra de resumir** | Lista todas as 11 unidades ao invés de resumir |
| **Sem diferenciar unidades/especialidades** | Confunde locais com áreas médicas |
| **"Sempre termine com..."** | Adiciona linha extra desnecessária |

---

## ✅ INSTRUÇÕES NOVAS (CORRETAS)

```
Você é o assistente virtual da CLÍNICA OITAVA ROSADO.

══════════════════════════════════════════════════════════
⚠️ REGRA MAIS IMPORTANTE - LEIA PRIMEIRO:
══════════════════════════════════════════════════════════

MÁXIMO 5 LINHAS POR RESPOSTA.
Se escrever mais, a mensagem será CORTADA NO MEIO.

══════════════════════════════════════════════════════════
📏 LIMITES OBRIGATÓRIOS
══════════════════════════════════════════════════════════

• Máximo: 5 linhas
• Máximo: 400 caracteres
• Se tiver mais de 4 itens: liste 3 + "(+ X outros)"

══════════════════════════════════════════════════════════
🚫 FORMATAÇÃO - PROIBIDO
══════════════════════════════════════════════════════════

NUNCA use:
❌ Asteriscos (*) para negrito
❌ Underlines (_)
❌ Markdown
❌ Numeração longa

SEMPRE use:
✅ Texto simples
✅ Bullet points (•) para listas CURTAS
✅ Máximo 3-4 itens listados

══════════════════════════════════════════════════════════
📍 DIFERENÇA CRÍTICA
══════════════════════════════════════════════════════════

UNIDADES = Locais físicos (cidades)
ESPECIALIDADES = Áreas médicas

Se perguntarem "Quais unidades?":
✅ Responda com CIDADES
❌ NÃO responda com especialidades
```

### 🟢 MELHORIAS IMPLEMENTADAS:

| Mudança | Benefício |
|---------|-----------|
| **Limite de 5 LINHAS** | Garante mensagem completa, sem cortes |
| **PROIBIDO asteriscos** | Remove formatação problemática |
| **Limite de caracteres** | Segurança extra contra cortes |
| **Regra de resumir** | Listas longas viram "(+ X outros)" |
| **Diferenciação clara** | Nunca confunde unidades com especialidades |
| **Sem frases extras** | Respostas mais curtas e diretas |

---

## 📊 RESULTADO PRÁTICO

### **Pergunta: "Quais unidades vocês tem?"**

#### ❌ COM INSTRUÇÕES ANTIGAS:
```
A unidade da Clínica Oitava Rosado em Mossoró oferece serviços
de diversas especialidades. Aqui estão as unidades disponíveis
naquela cidade, listadas pela API:

* *Clínica Geral*: atendimento geral e consultas com médicos
* *Cardiologia*: consultas e tratamentos relacionados ao coração
* *Dermatologia*: consultas e tratamentos relacionados à pele
* *Endocrinologia*: consultas e tratamentos relacionados às glândulas
* *Ginecologia*: consultas e tratamentos relacionados à saúde
* *Neurologia*: consultas e tratamentos relacionados ao sistema
* *Nutricionista*: consultas e planejamentos de alimentação
* *Oftalmologia*: consultas e tratamentos relacionados à saúde
* *Ortopedia*: consultas e tratamentos relacionados às articulações
* *Pediatra*: consultas e tratamentos relacion [CORTADO]

Posso ajudar com mais alguma coisa? 😊
```

**Problemas:**
- 12 linhas (esperado: 5)
- Muitos asteriscos
- Listou especialidades (não unidades!)
- Inventou descrições
- Cortado no meio
- 800+ caracteres

---

#### ✅ COM INSTRUÇÕES NOVAS:
```
Principais unidades:

• Mossoró
• Parnamirim
• Assú
(+ 8 outras unidades)

Qual cidade prefere?
```

**Melhorias:**
- 5 linhas (perfeito!)
- Sem asteriscos
- Listou unidades corretas (cidades!)
- Não inventou nada
- Completo (não cortado)
- ~100 caracteres

---

## 📋 PASSO A PASSO PARA SUBSTITUIR

### **1. Abrir o Playground**
- Acesse o painel do Digital Ocean Agent
- Localize o campo "Agent Instructions"

### **2. Apagar instruções antigas**
- Selecione TODO o texto atual
- Delete tudo

### **3. Colar instruções novas**
- Abra o arquivo: `AGENT_INSTRUCTIONS_CORRETAS_FINAL.txt`
- Copie TODO o conteúdo
- Cole no campo "Agent Instructions"

### **4. Salvar**
- Clique em "Save" ou "Update Agent"
- Aguarde confirmação

### **5. Testar**
- Envie no WhatsApp: "Quais unidades vocês tem?"
- Verifique se resposta tem:
  - ✅ Máximo 5 linhas
  - ✅ Sem asteriscos
  - ✅ Lista cidades (não especialidades)
  - ✅ Resumido "(+ X outras)"

---

## 🎯 CHECKLIST DE VALIDAÇÃO

Após substituir as instruções, teste com estas 3 perguntas:

### **Teste 1: Unidades**
**Envie:** "Quais unidades vocês tem?"

**Deve responder:**
```
Principais unidades:

• Mossoró
• Parnamirim
• Assú
(+ 8 outras unidades)

Qual cidade prefere?
```

✅ Validar:
- [ ] 5 linhas ou menos
- [ ] SEM asteriscos
- [ ] Lista CIDADES (não especialidades)
- [ ] Mensagem completa (não cortada)

---

### **Teste 2: Especialidades**
**Envie:** "Quais especialidades vocês tem?"

**Deve responder:**
```
Principais especialidades:

• Cardiologia
• Ginecologia
• Ortopedia
(+ 15 outras)

Qual especialidade deseja?
```

✅ Validar:
- [ ] 5 linhas ou menos
- [ ] Lista ESPECIALIDADES (não unidades)
- [ ] Resumido
- [ ] Sem descrições inventadas

---

### **Teste 3: Médicos**
**Envie:** "Quais médicos fazem ginecologia?"

**Deve responder:**
```
Médicos de Ginecologia:

• EDNA PATRICIA - Parnamirim
• JAILSON NOGUEIRA - Mossoró
• HUGO BRASIL - Mossoró
(+ 4 outros)

Qual prefere?
```

✅ Validar:
- [ ] Nomes REAIS (não inventados)
- [ ] SEM biografias ou procedimentos
- [ ] SEM datas inventadas
- [ ] Resumido se muitos médicos

---

## ⚠️ SE APÓS SUBSTITUIR AINDA HOUVER PROBLEMAS

### **Problema: Ainda usa asteriscos**

**Causa:** As instruções antigas ainda estão no campo.

**Solução:**
1. Verificar se o campo "Agent Instructions" foi realmente atualizado
2. Salvar novamente
3. Aguardar 1-2 minutos para propagar
4. Testar novamente

---

### **Problema: Ainda corta respostas**

**Causa 1:** Instruções não foram salvas.
**Solução:** Verificar se salvou corretamente.

**Causa 2:** O código de validação não está ativo.
**Solução:** Já aplicamos no servidor. Verificar logs:
```bash
pm2 logs whatsapp-bot
```
Deve aparecer: "📏 Validação: X linhas, Y caracteres"

---

### **Problema: Ainda confunde unidades com especialidades**

**Causa:** Instruções antigas ainda estão ativas.

**Solução:** As novas instruções têm esta seção:
```
═══════════════════════════════════════════════════════════
📍 DIFERENÇA CRÍTICA
═══════════════════════════════════════════════════════════

UNIDADES = Locais físicos (cidades)
ESPECIALIDADES = Áreas médicas
```

Se ainda confunde, as instruções não foram aplicadas corretamente.

---

## 📁 ARQUIVOS DISPONÍVEIS

1. **`AGENT_INSTRUCTIONS_CORRETAS_FINAL.txt`** ⚠️ COPIE ESTE!
   - Instruções completas e corretas
   - Pronto para colar no playground

2. **`COMPARACAO_INSTRUCOES.md`** (este arquivo)
   - Comparação antes/depois
   - Guia passo a passo

3. **`SOLUCAO_FINAL_RESPOSTAS_CORTADAS.md`**
   - Documentação técnica completa
   - Troubleshooting detalhado

---

## 🎉 RESULTADO FINAL ESPERADO

**Após substituir as instruções:**

✅ Respostas curtas (máximo 5 linhas)
✅ Sem asteriscos ou formatação problemática
✅ Sem cortes no meio da mensagem
✅ Listas resumidas com "(+ X outros)"
✅ Diferencia corretamente unidades de especialidades
✅ Não inventa informações médicas
✅ Tom profissional e objetivo

---

**PRÓXIMO PASSO:**
1. Abra `AGENT_INSTRUCTIONS_CORRETAS_FINAL.txt`
2. Copie todo o conteúdo
3. Cole no campo "Agent Instructions" do playground
4. Salve
5. Teste com as 3 perguntas acima

**Me avise o resultado! 🚀**
