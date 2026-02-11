# 🎉 BOT AGORA USA DADOS REAIS DAS APIs!

## ✅ O QUE FOI IMPLEMENTADO

O bot agora **detecta automaticamente** quando você pergunta sobre informações em tempo real e **busca os dados reais** da API antes de responder!

---

## 🔍 DETECÇÃO AUTOMÁTICA DE INTENÇÃO

O sistema analisa sua mensagem e identifica se você está perguntando sobre:

### 📍 **UNIDADES**
**Palavras-chave:** unidade, unidades, local, locais

**Exemplo de perguntas:**
- "Quais unidades vocês tem?"
- "Onde tem atendimento?"
- "Quais são os locais?"
- "Mostre as unidades"

**O que acontece:**
1. Bot detecta intenção: CONSULTAR UNIDADES
2. Chama API: `consultarUnidades()`
3. Recebe dados reais de 11 unidades
4. Agente formata resposta com dados reais

---

### 🏥 **ESPECIALIDADES**
**Palavras-chave:** especialidade, especialidades, médico, medico, doutor, doutora

**Exemplo de perguntas:**
- "Quais especialidades vocês tem?"
- "Que tipo de médico tem?"
- "Quais doutores atendem?"
- "Mostre as especialidades"

**O que acontece:**
1. Bot detecta intenção: CONSULTAR ESPECIALIDADES
2. Chama API: `buscarEspecialidades()`
3. Recebe dados reais (Cardiologia, Endocrinologia, etc.)
4. Agente formata resposta com dados reais

---

### 💳 **CONVÊNIOS**
**Palavras-chave:** convenio, convênio, plano, seguro

**Exemplo de perguntas:**
- "Quais convênios vocês aceitam?"
- "Aceita Unimed?"
- "Quais planos de saúde?"
- "Trabalham com SUS?"

**O que acontece:**
1. Bot detecta intenção: CONSULTAR CONVÊNIOS
2. Chama API: `buscarConvenios()`
3. Recebe dados reais (Unimed, Amil, SUS, etc.)
4. Agente formata resposta com dados reais

---

### 💰 **PREÇOS**
**Palavras-chave:** preço, preco, valor, quanto custa, quanto é

**Exemplo de perguntas:**
- "Quanto custa uma consulta?"
- "Qual o valor do ultrassom?"
- "Preço de ressonância"
- "Quanto é a consulta com cardiologista?"

**O que acontece:**
1. Bot detecta intenção: CONSULTAR PREÇOS
2. Chama API: `consultarPrecos()`
3. Recebe tabela de preços real
4. Agente formata resposta com valores reais

---

### 📅 **HORÁRIOS E AGENDAS**
**Palavras-chave:** horário, horario, agenda, disponível, disponivel, vaga

**Exemplo de perguntas:**
- "Que horários tem disponível?"
- "Quando posso agendar?"
- "Tem vaga para cardiologista?"
- "Mostre as agendas"

**O que acontece:**
1. Bot detecta intenção: CONSULTAR AGENDAS
2. Chama API: `listarAgendasJSON()`
3. Recebe agendas reais disponíveis
4. Agente formata resposta com horários reais

---

### 📋 **CONSULTAR AGENDAMENTOS (precisa CPF)**
**Palavras-chave:** meu agendamento, minha consulta, meu cpf, meus dados

**Exemplo de perguntas:**
- "Quero ver meu agendamento"
- "Consultar minha consulta"
- "Ver meus dados"

**O que acontece:**
1. Bot detecta intenção: CONSULTA DE PACIENTE
2. Bot pede o CPF
3. Quando usuário informa CPF, busca na API
4. Retorna agendamentos reais do paciente

---

## 🧪 COMO TESTAR

### **1. Envie uma mensagem via WhatsApp**

Envie para o número conectado no bot qualquer uma das perguntas acima.

**Exemplo:**
```
Você: Quais unidades vocês tem?
```

### **2. O que vai acontecer nos logs:**

```
💬 Mensagem recebida
🔍 Analisando intenção da mensagem...
✅ Intenção detectada: CONSULTAR UNIDADES
📊 Dados obtidos: 11 unidades
🤖 Consultando agente inteligente...
✅ Resposta do agente gerada!
📊 API utilizada: unidades
📤 Enviando resposta...
✅ Resposta enviada com sucesso!
```

### **3. Você vai receber:**

Uma resposta com **dados REAIS** do sistema, por exemplo:

```
Claro! 😊 Temos 11 unidades da Clínica Oitava Rosado:

🏥 **Mossoró**
📍 Rua: Juvenal Lamartine, 119 - Centro
📞 (84) 3315-6900
👨‍⚕️ 38 médicos | 16 especialidades

🏥 **Parnamirim**
📍 Endereço completo...
📞 Telefone...
👨‍⚕️ 60 médicos | 26 especialidades

🏥 **Assú**
📍 Endereço...
...

Posso ajudar com mais alguma coisa?
```

---

## 📊 VERIFICAR SE ESTÁ FUNCIONANDO

### **Ver logs em tempo real:**
```bash
pm2 logs whatsapp-bot
```

### **O que procurar nos logs:**

✅ **Funcionando corretamente:**
```
🔍 Analisando intenção da mensagem...
✅ Intenção detectada: CONSULTAR UNIDADES
📊 Dados obtidos: 11 unidades
📊 API utilizada: unidades
```

❌ **NÃO está usando API:**
```
🤖 Consultando agente inteligente...
✅ Resposta do agente gerada!
# (sem mensagem de intenção detectada ou API utilizada)
```

---

## 🎯 EXEMPLOS DE TESTES COMPLETOS

### **Teste 1: Unidades**
```
Você: Quais unidades tem?
Bot: [Lista com 11 unidades reais: Mossoró, Parnamirim, Assú, etc.]
```

### **Teste 2: Especialidades**
```
Você: Que especialidades vocês tem?
Bot: [Lista com todas especialidades reais: Cardiologia, Endocrinologia, etc.]
```

### **Teste 3: Convênios**
```
Você: Quais convênios aceitam?
Bot: [Lista com convênios reais: Unimed, Amil, SUS, Bradesco Saúde, etc.]
```

### **Teste 4: Preços**
```
Você: Quanto custa uma consulta?
Bot: [Tabela de preços real por especialidade e convênio]
```

### **Teste 5: Horários**
```
Você: Tem vaga para cardiologista?
Bot: [Lista de agendas disponíveis com horários reais]
```

---

## 🔧 ARQUIVOS MODIFICADOS

### **1. agente-ia.js** (NOVO)
- Sistema de detecção de intenção
- Chamadas automáticas às APIs
- Integração com Digital Ocean Agent
- Gerenciamento de histórico de conversas

**Localização:** `/opt/whatsapp-web-js/agente-ia.js`

### **2. bot.js** (ATUALIZADO)
- Agora usa `agenteIA.consultarAgente()`
- Modo headless para servidor
- Logs aprimorados com indicação de uso de API

**Localização:** `/opt/whatsapp-web-js/bot.js`

---

## 📝 LOGS DETALHADOS

Quando uma API é chamada, você verá nos logs:

```
💬 ─────────────────────────────────────────────
📱 De: 558498186138@c.us
📝 Mensagem: Quais unidades tem?
─────────────────────────────────────────────
🤖 Consultando agente inteligente...
🔍 Analisando intenção da mensagem...
✅ Intenção detectada: CONSULTAR UNIDADES
📊 Dados obtidos: { status: 'sucesso', total_unidades: 11, ... }
📊 Total de mensagens: 3
✅ Incluindo dados da API: unidades
✅ Resposta recebida do agente!
📤 Enviando: Claro! 😊 Temos 11 unidades da Clínica...
✅ Resposta enviada com sucesso!
```

---

## ⚙️ STATUS DO BOT

### **Ver status:**
```bash
pm2 status whatsapp-bot
```

### **Ver logs:**
```bash
pm2 logs whatsapp-bot
```

### **Reiniciar bot:**
```bash
pm2 restart whatsapp-bot
```

---

## 🆘 TROUBLESHOOTING

### **Bot não detecta intenção:**

**Problema:** Usuário pergunta sobre unidades mas não usa palavras-chave

**Solução:** Pergunte de forma mais clara:
- ❌ "Onde fica?"
- ✅ "Quais unidades tem?"

### **API retorna erro:**

**Verifique:**
1. Token de autenticação está correto
2. API está acessível: `http://sistema.clinicaoitavarosado.com.br/oitava/agenda/`
3. Ver logs de erro: `pm2 logs whatsapp-bot --err`

### **Bot não responde:**

**Verifique:**
1. Bot está online: `pm2 status whatsapp-bot`
2. WhatsApp está conectado (ver logs)
3. Reinicie: `pm2 restart whatsapp-bot`

---

## 🎉 PRÓXIMOS PASSOS

Agora que o bot está usando dados reais, você pode:

1. ✅ **Testar todos os fluxos** (unidades, especialidades, convênios, etc.)
2. ✅ **Treinar o bot** via painel: http://138.197.29.54:3003
3. ✅ **Adicionar mais perguntas** ao conhecimento
4. ✅ **Implementar agendamento completo** (próxima feature)

---

## 📞 INFORMAÇÕES DO SISTEMA

**Servidor:** 138.197.29.54
**Senha:** oitavA8s3n@crn
**Painel de Treinamento:** http://138.197.29.54:3003
**Bot WhatsApp:** PM2 process "whatsapp-bot"

**Status atual:** ✅ **ONLINE E FUNCIONANDO COM APIs REAIS!**

---

**🎉 Teste agora e veja a diferença!**

O bot agora responde com **informações REAIS** do sistema da clínica! ✨
