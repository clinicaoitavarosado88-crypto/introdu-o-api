# 🚀 Capacidades da IA com API Integrada

## ✅ O QUE O BOT PODE FAZER AGORA

### 📅 **AGENDAMENTO COMPLETO**

O bot agora pode **criar agendamentos automaticamente** através de conversação natural!

**Exemplo de conversa:**
```
Usuário: Quero agendar uma consulta
Bot: Claro! Qual especialidade você precisa?
Usuário: Cardiologia
Bot: Ótimo! Para qual data você gostaria de agendar?
Usuário: Próxima segunda-feira
Bot: [Consulta horários disponíveis na API]
    Tenho os seguintes horários disponíveis:
    1. 08:00
    2. 09:00
    3. 10:00
    Qual horário prefere?
Usuário: 08:00
Bot: Perfeito! Preciso de alguns dados:
    - Nome completo
    - CPF
    - Telefone
    - Convênio
Usuário: João Silva, 123.456.789-00, (84) 99999-9999, Amil
Bot: [Cria o agendamento na API]
    ✅ Agendamento criado com sucesso!
    Número: AGD-00123
    Data: 15/01/2026 às 08:00
    Dr. Camilo - Cardiologia

    Você receberá um lembrete 1 dia antes!
```

---

### 🔍 **CONSULTAR AGENDAMENTOS**

**Exemplo:**
```
Usuário: Quero ver meus agendamentos
Bot: Me informe seu CPF para consultar
Usuário: 123.456.789-00
Bot: [Busca na API]
    📋 Seus agendamentos:

    1. 15/01/2026 - 08:00
       Dr. Camilo - Cardiologia
       Status: AGENDADO

    2. 20/01/2026 - 14:00
       Ressonância Magnética
       Status: CONFIRMADO
```

---

### ❌ **CANCELAR/REMARCAR**

**Exemplo:**
```
Usuário: Preciso cancelar minha consulta
Bot: Me informe seu CPF
Usuário: 123.456.789-00
Bot: [Lista agendamentos]
    Qual agendamento deseja cancelar?
    1. 15/01 - 08:00 - Cardiologia
    2. 20/01 - 14:00 - Ressonância
Usuário: 1
Bot: [Cancela na API]
    ✅ Agendamento cancelado com sucesso!
    Deseja remarcar?
```

---

### 💰 **CONSULTAR PREÇOS**

**Exemplo:**
```
Usuário: Quanto custa uma consulta com cardiologista?
Bot: [Consulta preços na API]
    💰 Valores para Cardiologia:

    Amil: R$ 150,00 (consulta) / R$ 80,00 (retorno)
    SUS: Gratuito
    Particular: R$ 200,00

    Qual convênio você tem?
```

---

### 🏥 **INFORMAÇÕES EM TEMPO REAL**

O bot consulta automaticamente:
- ✅ Especialidades disponíveis
- ✅ Horários livres por data
- ✅ Vagas por convênio
- ✅ Médicos disponíveis
- ✅ Procedimentos e exames
- ✅ Preparos necessários
- ✅ Preços atualizados
- ✅ Unidades ativas

---

## 🧪 **COMO TESTAR**

### **1. Teste via WhatsApp:**
Envie mensagens para o número conectado:
- "Quero agendar uma consulta"
- "Ver meus agendamentos"
- "Quanto custa uma ressonância?"
- "Preciso remarcar"

### **2. Teste no Painel Web:**
Acesse: http://138.197.29.54:3003
- Aba "🧪 Testar Bot"
- Digite mensagens e veja respostas em tempo real
- Teste todos os fluxos

---

## 📊 **APIs INTEGRADAS**

### **Especialidades e Médicos:**
- `buscarEspecialidades(termo)`
- `buscarMedicos(termo)`

### **Agendas e Horários:**
- `listarAgendasJSON(tipo, nome, dia, cidade)`
- `buscarHorariosDisponiveis(agendaId, data)`
- `verificarVagas(agendaId, data, convenioId)`

### **Pacientes:**
- `buscarPaciente(termo)`
- `cadastrarPaciente(dados)`
- `consultarAgendamentosPaciente(pacienteId)`

### **Agendamentos:**
- `criarAgendamento(dados)`
- `buscarAgendamento(id)`
- `cancelarAgendamento(id, motivo)`
- `atualizarStatusAgendamento(id, status)`

### **Convênios:**
- `buscarConvenios(termo)`

### **Preços e Unidades:**
- `consultarPrecos(params)`
- `consultarUnidades(params)`

### **Procedimentos:**
- `buscarProcedimentos(termo)`
- `buscarExamesAgenda(agendaId)`
- `consultarPreparos(params)`

---

## 🎯 **FLUXOS IMPLEMENTADOS**

### **Fluxo 1: Agendamento de Consulta**
1. Usuário solicita agendamento
2. Bot pergunta especialidade
3. Bot consulta agendas disponíveis (API)
4. Bot pergunta data preferida
5. Bot consulta horários livres (API)
6. Bot mostra opções de horário
7. Bot coleta dados do paciente
8. Bot pergunta convênio
9. Bot verifica vagas (API)
10. **Bot cria agendamento (API)**
11. Bot confirma com número

### **Fluxo 2: Consulta de Agendamentos**
1. Usuário pede para ver agendamentos
2. Bot solicita CPF
3. **Bot busca agendamentos (API)**
4. Bot lista todos os agendamentos
5. Bot oferece ações (cancelar/remarcar)

### **Fluxo 3: Consulta de Preços**
1. Usuário pergunta sobre preços
2. Bot pergunta especialidade/procedimento
3. Bot pergunta convênio
4. **Bot consulta preços (API)**
5. Bot informa valores detalhados

---

## 🔐 **AUTENTICAÇÃO**

Token configurado: `OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0`

Todas as requisições incluem automaticamente:
```
Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0
```

---

## 📝 **LOGS E MONITORAMENTO**

Ver logs em tempo real:
```bash
pm2 logs whatsapp-bot
```

Ver status:
```bash
pm2 status
```

Verificar chamadas de API:
- Os logs mostram "🤖 Resposta do Agente IA" quando a IA responde
- Erros de API aparecem como "❌ Erro ao..."

---

## ⚙️ **CONFIGURAÇÃO**

### **Arquivo: api-agenda-completa.js**
- Módulo de integração com todas as APIs
- Inclui funções auxiliares (formatação, validação)
- Timeout: 30 segundos
- Tratamento automático de erros

### **Arquivo: agente-ia.js**
- Agente IA com contexto enriquecido
- Conhece todas as capacidades das APIs
- Gerencia histórico de conversação
- Integra conhecimento personalizado

### **Arquivo: conhecimento-ia.js**
- Base de conhecimento editável
- Perguntas frequentes
- Informações da clínica
- Procedimentos e preparos

---

## 🆘 **TROUBLESHOOTING**

### **Bot não responde:**
```bash
pm2 restart whatsapp-bot
pm2 logs whatsapp-bot --lines 50
```

### **Erro de API:**
- Verificar token de autenticação
- Verificar conectividade com API
- Ver logs detalhados

### **Agendamento não funciona:**
- Verificar se todos os dados foram fornecidos
- Verificar disponibilidade de vagas
- Ver response da API nos logs

---

## 📞 **SUPORTE**

**Servidor:** 138.197.29.54
**Senha:** oitavA8s3n@crn
**Painel:** http://138.197.29.54:3003

**Reiniciar tudo:**
```bash
pm2 restart all
```

**Ver todos os logs:**
```bash
pm2 logs
```

---

## ✨ **PRÓXIMOS PASSOS**

1. ✅ API totalmente integrada
2. ✅ Agendamentos automáticos
3. ✅ Consultas em tempo real
4. ⏳ Adicionar mais fluxos personalizados
5. ⏳ Melhorar tratamento de erros
6. ⏳ Adicionar notificações proativas

---

**🎉 Sistema Completo e Funcional!**

O bot agora é um **assistente virtual completo** que pode:
- 📅 Agendar consultas e procedimentos
- 🔍 Consultar informações em tempo real
- 💬 Responder dúvidas com dados atualizados
- 📊 Integrar com todo o sistema da clínica
- 🤖 Aprender com treinamento personalizado

**Teste agora e veja a mágica acontecer!** ✨
