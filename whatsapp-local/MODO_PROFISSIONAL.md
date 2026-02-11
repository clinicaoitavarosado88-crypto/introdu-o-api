# 🎯 BOT EM MODO PROFISSIONAL

## ✅ MUDANÇAS IMPLEMENTADAS

### **1. TOM E POSTURA**

**ANTES** (Casual demais):
- "Oi! 👋 Como posso ajudar?"
- "Claro! 😊 Vamos lá!"
- "Você pode agendar direto aqui pelo WhatsApp!"

**AGORA** (Profissional):
- "Olá. Como posso atendê-lo?"
- "Médicos disponíveis: [lista]"
- "Para agendamento, informe a especialidade desejada."

---

### **2. LIMITES CLAROS**

**O QUE O BOT PODE FAZER:**
✅ Informar sobre unidades, horários, especialidades
✅ Consultar médicos disponíveis (dados reais da API)
✅ Orientar sobre processo de agendamento
✅ Responder sobre procedimentos e preparos
✅ Fornecer contatos e endereços

**O QUE O BOT NÃO PODE FAZER:**
❌ Dar diagnósticos ou conselhos médicos
❌ Orientar sobre tratamentos
❌ Inventar médicos ou especialidades
❌ Responder sobre sintomas/doenças
❌ Falar sobre assuntos fora do escopo

---

### **3. FORMATO DE RESPOSTAS**

**Regras estritas:**
- Máximo 4-5 linhas por resposta
- Máximo 2 emojis por mensagem
- Uso de bullet points (•) em vez de múltiplos emojis
- Respostas diretas e objetivas
- Sempre oferecer próximo passo

**Exemplo de resposta profissional:**

```
Médicos de Ginecologia disponíveis:

• EDNA PATRICIA DIAS ALVES - Parnamirim
• JAILSON R. NOGUEIRA FILHO - Mossoró
• (+ 5 outros médicos)

Deseja agendar consulta?
```

---

### **4. PERGUNTAS FREQUENTES ATUALIZADAS**

Todas as respostas foram reformuladas para serem mais objetivas:

**Como agendar:**
"Para agendamento, informe a especialidade desejada que consultarei os horários disponíveis. Ou ligue: (84) 3315-6900"

**Convênios:**
"Aceitamos: SUS, Unimed, Amil, Bradesco Saúde e particular. Para confirmar seu plano específico, consulte pelo telefone."

**Horário:**
"Segunda a Sexta: 06:00 às 17:48 | Sábado: 07:00 às 11:00 | Domingo: Fechado"

**Documentos:**
"Documentos necessários: RG ou CNH, carteirinha do convênio e exames anteriores. Chegue com 15 minutos de antecedência."

---

### **5. RESPOSTAS PARA SITUAÇÕES FORA DO ESCOPO**

**Se perguntarem sobre sintomas/doenças:**
"Para orientação médica, é necessário consulta. Posso ajudar a agendar?"

**Se não souber algo:**
"Para essa informação específica, favor ligar: (84) 3315-6900"

**Se for totalmente fora do escopo:**
"Atendo apenas questões sobre agendamentos e informações da clínica."

---

## 🧪 TESTES PARA VALIDAR

### **Teste 1: Consultar médicos**
**Você:** "Quais médicos fazem cardiologia?"

**Resposta esperada (profissional):**
```
Médicos de Cardiologia disponíveis:

• [NOME REAL 1] - [Unidade]
• [NOME REAL 2] - [Unidade]
• [NOME REAL 3] - [Unidade]

Deseja agendar consulta?
```

✅ Tom profissional
✅ Dados reais
✅ Conciso (3-4 linhas)
✅ Apenas 1 emoji ou nenhum

---

### **Teste 2: Pergunta sobre horário**
**Você:** "Qual o horário de atendimento?"

**Resposta esperada:**
```
Segunda a Sexta: 06:00 às 17:48
Sábado: 07:00 às 11:00
Domingo: Fechado

Posso ajudar com mais alguma informação?
```

---

### **Teste 3: Pergunta sobre sintoma (FORA DO ESCOPO)**
**Você:** "Estou com dor no peito, o que pode ser?"

**Resposta esperada:**
```
Para orientação médica, é necessário consulta.
Posso ajudar a agendar com cardiologista?
```

❌ **NÃO DEVE:** Dar diagnósticos, dizer "pode ser isso ou aquilo"
✅ **DEVE:** Redirecionar para consulta

---

### **Teste 4: Unidades**
**Você:** "Quais unidades vocês tem?"

**Resposta esperada:**
```
Principais unidades:

• Mossoró - Rua Juvenal Lamartine, 119
• Parnamirim - [Endereço]
• Assú - [Endereço]
(+ 8 outras unidades)

Qual cidade você prefere?
```

---

### **Teste 5: Convênios**
**Você:** "Aceitam Unimed?"

**Resposta esperada:**
```
Sim, aceitamos Unimed.

Outros convênios aceitos: SUS, Amil, Bradesco Saúde e particular.

Deseja agendar consulta?
```

---

## ⚠️ O QUE FOI REMOVIDO/MUDADO

### **Removido:**
- ❌ Excesso de emojis (👋 😊 🎉 💉 🏥)
- ❌ Linguagem casual ("Oi!", "Tudo bem?", "Vamos lá!")
- ❌ Textos longos com múltiplos parágrafos
- ❌ Informações inventadas ou genéricas
- ❌ Conversas muito informais

### **Adicionado:**
- ✅ Tom profissional mas cordial
- ✅ Respostas objetivas de 3-4 linhas
- ✅ Limites claros do que pode responder
- ✅ Bullet points simples (•)
- ✅ Redirecionamento para telefone quando necessário

---

## 📋 CHECKLIST DE VALIDAÇÃO

Use este checklist para testar se o bot está profissional:

**Teste cada categoria:**

- [ ] **Médicos por especialidade** - retorna nomes reais?
- [ ] **Unidades** - lista endereços corretos?
- [ ] **Horários** - informa horário de funcionamento?
- [ ] **Convênios** - lista convênios aceitos?
- [ ] **Pergunta sobre sintoma** - redireciona para consulta?
- [ ] **Pergunta fora do escopo** - diz que não atende?
- [ ] **Resposta é curta?** - máximo 4-5 linhas?
- [ ] **Tom profissional?** - sem excesso de emojis ou informalidade?
- [ ] **Dados reais?** - não inventa informações?

---

## 🎯 EXEMPLOS COMPARATIVOS

### **EXEMPLO 1: Consultar médicos**

**ANTES (informal):**
```
Olá! 👋 Claro que posso ajudar! 😊

Nossa clínica tem vários médicos especializados em ginecologia! 🏥

Aqui estão alguns deles:

1. **Dr. João Paulo** 👨‍⚕️
   * Especialista em ginecologia e obstetrícia
   * Graduado pela UFRN
   * Com experiência em ultrassom pélvico

2. **Dr. Luiz Carlos** 👨‍⚕️
   ...

Qual médico você gostaria de agendar? 😊
```
❌ Muito longo, informal, médicos INVENTADOS

**AGORA (profissional):**
```
Médicos de Ginecologia disponíveis:

• EDNA PATRICIA DIAS ALVES - Parnamirim
• JAILSON R. NOGUEIRA FILHO - Mossoró
• (+ 5 outros médicos)

Deseja agendar consulta?
```
✅ Curto, objetivo, médicos REAIS

---

### **EXEMPLO 2: Pergunta sobre sintoma**

**ANTES (problemático):**
```
Oi! 😊 Dor no peito pode ser várias coisas, desde problemas
cardíacos até ansiedade. O importante é você marcar uma
consulta com um cardiologista para avaliar! Quer que eu
ajude a agendar? 👨‍⚕️
```
❌ Dando "diagnóstico", informal

**AGORA (correto):**
```
Para orientação médica, é necessário consulta.
Posso ajudar a agendar com cardiologista?
```
✅ Não dá diagnóstico, redireciona corretamente

---

## 🔧 ARQUIVOS MODIFICADOS

### **1. conhecimento-ia.js**
- Perguntas frequentes reformuladas
- Regras de comportamento profissionais
- Limites claros definidos

### **2. agente-ia.js**
- Instruções de formato profissional
- Exemplos de respostas corretas
- Lista do que evitar

---

## 📞 STATUS DO SISTEMA

✅ **Bot WhatsApp:** Online e profissional
✅ **Painel Treinamento:** http://138.197.29.54:3003
✅ **APIs:** Integradas e funcionando
✅ **Servidor:** 138.197.29.54

---

## 🆘 SE AINDA RESPONDER INFORMAL

Se o bot ainda responder de forma muito informal ou inventar coisas:

1. **Limpar histórico:**
```bash
ssh root@138.197.29.54
pm2 restart whatsapp-bot
```

2. **Ver logs para debug:**
```bash
pm2 logs whatsapp-bot
```

3. **Editar conhecimento via painel:**
   - Acesse: http://138.197.29.54:3003
   - Adicione exemplos de respostas profissionais
   - Reforce limites

---

## ✨ RESULTADO ESPERADO

**Antes:** Bot amigável demais, inventava informações, respostas longas
**Agora:** Bot profissional, objetivo, dados reais, respostas curtas

**Teste e valide:** Envie mensagens variadas e veja se as respostas seguem o padrão profissional definido acima.

---

**Data da atualização:** 14 de Outubro de 2025
**Status:** ✅ SISTEMA PROFISSIONALIZADO E ATIVO
