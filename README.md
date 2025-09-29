# API Sistema de Agendamento - Clínica Oitava Rosado

## 🚀 Visão Geral

API REST completa para gerenciamento de agendamentos médicos, desenvolvida em PHP com banco de dados Firebird. Sistema otimizado para **Agentes de IA** com endpoints inteligentes e automações avançadas.

**🌐 URL Base:** `http://sistema.clinicaoitavarosado.com.br/oitava/agenda/`

## ✨ Funcionalidades Principais

### 🏥 **Sistema de Agendamento**
- ✅ Consultas médicas por especialidade
- ✅ Procedimentos e exames com preparos
- ✅ Controle de vagas por dia da semana
- ✅ Sistema de encaixes inteligente
- ✅ Reagendamentos e cancelamentos

### 👥 **Gestão de Pacientes**
- ✅ Cadastro completo de pacientes
- ✅ Busca por nome, CPF ou data nascimento
- ✅ Histórico completo de agendamentos
- ✅ Validações automáticas

### 🤖 **Otimizado para Agentes de IA**
- ✅ **7 novos endpoints** específicos para IA
- ✅ Dados estruturados em JSON
- ✅ Validações robustas
- ✅ Notificações automáticas
- ✅ Auditoria completa

## 🔐 Autenticação

Sistema de **Bearer Token** com validade de 1 ano:

```bash
curl -X POST "/auth/token.php" \
  -H "Content-Type: application/json" \
  -d '{"client_name":"Meu App","client_email":"contato@app.com"}'
```

**Token de teste:** `OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0`

---

## 🆕 **ENDPOINTS PARA AGENTES DE IA**

### 1. 💰 **Consultar Preços**
`GET /consultar_precos.php`

Consulta valores por especialidade, procedimento e convênio.

```bash
curl -H "Authorization: Bearer TOKEN" \
  "/consultar_precos.php?especialidade_id=1&convenio_id=24"
```

**Resposta:**
```json
{
  "status": "sucesso",
  "total_precos": 1,
  "precos": [{
    "especialidade_nome": "Cardiologia",
    "convenio_nome": "Amil",
    "valor_consulta": 150.00,
    "valor_retorno": 80.00
  }]
}
```

---

### 2. 👤 **Cadastrar Paciente**
`POST /cadastrar_paciente.php`

Cadastro completo de novos pacientes com validações.

```bash
curl -X POST -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  "/cadastrar_paciente.php" \
  -d '{
    "nome": "João Silva",
    "cpf": "123.456.789-01",
    "data_nascimento": "1990-01-01",
    "telefone": "(84) 99999-9999",
    "email": "joao@email.com"
  }'
```

**Resposta:**
```json
{
  "status": "sucesso",
  "message": "Paciente cadastrado com sucesso",
  "paciente": {
    "id": 622690,
    "nome": "João Silva",
    "cpf": "123.456.789-01"
  }
}
```

---

### 3. 🏥 **Consultar Unidades**
`GET /consultar_unidades.php`

Informações completas das unidades com especialidades e médicos.

```bash
curl -H "Authorization: Bearer TOKEN" \
  "/consultar_unidades.php?unidade_id=1"
```

**Resposta:**
```json
{
  "status": "sucesso",
  "unidade": {
    "id": 1,
    "nome": "Mossoró",
    "endereco": "Rua Principal, 123",
    "telefone": "(84) 3421-1234",
    "servicos": {
      "especialidades": [
        {"id": 1, "nome": "Cardiologia"},
        {"id": 2, "nome": "Dermatologia"}
      ],
      "total_especialidades": 2
    },
    "horario_funcionamento": {
      "por_dia": {
        "SEGUNDA": [{"inicio": "07:00", "fim": "17:00"}]
      }
    }
  }
}
```

---

### 4. 📋 **Consultar Preparos**
`GET /consultar_preparos.php`

Instruções de preparos por exame ou procedimento.

```bash
curl -H "Authorization: Bearer TOKEN" \
  "/consultar_preparos.php?procedimento_id=34"
```

**Resposta:**
```json
{
  "status": "sucesso",
  "total_preparos": 1,
  "preparos": [{
    "exame_nome": "Ressonância Magnética",
    "titulo": "Preparo para RM",
    "instrucoes": [
      "Jejum de 4 horas",
      "Retirar objetos metálicos"
    ],
    "tempo_jejum_horas": 4,
    "anexos": [
      {"nome": "orientacoes.pdf", "url_download": "..."}
    ]
  }]
}
```

---

### 5. 📅 **Agendamentos por Paciente**
`GET /consultar_agendamentos_paciente.php`

Histórico completo com ações permitidas.

```bash
curl -H "Authorization: Bearer TOKEN" \
  "/consultar_agendamentos_paciente.php?paciente_id=1&status=AGENDADO"
```

**Resposta:**
```json
{
  "status": "sucesso",
  "paciente": {
    "id": 1,
    "nome": "João Silva",
    "cpf": "123.456.789-01"
  },
  "total_agendamentos": 2,
  "agendamentos": [{
    "id": 123,
    "numero": 2415001,
    "data": "2025-09-10",
    "horario": "08:00",
    "status": "AGENDADO",
    "especialidade": {"nome": "Cardiologia"},
    "unidade": {"nome": "Mossoró"},
    "acoes_permitidas": {
      "pode_cancelar": true,
      "pode_reagendar": true,
      "pode_confirmar": true
    }
  }]
}
```

---

### 6. 🚫 **Processar No-Show**
`POST /processar_noshow.php`

Registra falta com notificações automáticas.

```bash
curl -X POST -H "Authorization: Bearer TOKEN" \
  "/processar_noshow.php" \
  -d '{
    "agendamento_id": 123,
    "observacao": "Paciente não compareceu",
    "enviar_notificacao": true,
    "usuario": "RECEPCAO"
  }'
```

**Resposta:**
```json
{
  "status": "sucesso",
  "message": "No-show registrado com sucesso",
  "agendamento": {
    "numero": 2415001,
    "status_anterior": "AGENDADO",
    "status_atual": "FALTOU"
  },
  "notificacoes": {
    "total_enviadas": 3,
    "enviadas": [
      {"tipo": "whatsapp_equipe", "status": "enviado"},
      {"tipo": "email_equipe", "status": "enviado"}
    ]
  }
}
```

---

### 7. 💵 **Consultar Valores OS**
`GET /consultar_valores_os.php`

Valores para criação de Ordem de Serviço.

```bash
curl -H "Authorization: Bearer TOKEN" \
  "/consultar_valores_os.php?convenio_id=24&exames_ids=31,32"
```

**Resposta:**
```json
{
  "status": "sucesso",
  "convenio": {"nome": "Amil"},
  "resumo": {
    "valor_total_geral": 350.50,
    "itens_com_valor": 2,
    "itens_sem_cobertura": 0
  },
  "valores": [{
    "tipo": "exame",
    "exame_id": 31,
    "nome": "Ressonância Magnética - Crânio",
    "valor_unitario": 250.00,
    "coberto_convenio": true,
    "pode_adicionar": true
  }]
}
```

---

## 📚 **ENDPOINTS PRINCIPAIS**

### **Buscar Dados**
- `GET /buscar_especialidades.php?busca=cardio`
- `GET /buscar_medicos.php?busca=joão`
- `GET /buscar_convenios.php?busca=amil`
- `POST /buscar_paciente.php` - `{"termo": "João Silva"}`

### **Agendar**
- `GET /listar_agendas.php?tipo=consulta&nome=cardiologia`
- `GET /buscar_horarios.php?agenda_id=1&data=2025-09-10`
- `GET /verificar_vagas.php?agenda_id=1&data=2025-09-10&convenio_id=24`
- `POST /processar_agendamento.php`

### **Gerenciar**
- `GET /buscar_agendamentos_dia.php?agenda_id=1&data=2025-09-10`
- `GET /buscar_agendamento.php?id=123`
- `POST /cancelar_agendamento.php`
- `POST /atualizar_status_agendamento.php`

---

## 🔄 **FLUXO COMPLETO PARA IA**

### **1. Consulta → Agendamento**
```bash
# 1. Buscar especialidades
GET /buscar_especialidades.php?busca=cardio

# 2. Listar agendas disponíveis
GET /listar_agendas.php?tipo=consulta&nome=cardiologia

# 3. Verificar preços
GET /consultar_precos.php?especialidade_id=1&convenio_id=24

# 4. Verificar horários
GET /buscar_horarios.php?agenda_id=1&data=2025-09-10

# 5. Buscar/Cadastrar paciente
POST /buscar_paciente.php
# OU
POST /cadastrar_paciente.php

# 6. Criar agendamento
POST /processar_agendamento.php
```

### **2. Procedimento → Agendamento**
```bash
# 1. Consultar preparos
GET /consultar_preparos.php?procedimento_id=34

# 2. Consultar valores
GET /consultar_valores_os.php?convenio_id=24&procedimento_id=34

# 3. Listar agendas de procedimento
GET /listar_agendas.php?tipo=procedimento&nome=Ressonância

# 4. Criar agendamento com exames
POST /processar_agendamento.php
```

---

## 🤖 **RECURSOS PARA IA**

### **Validações Automáticas:**
- ✅ **CPF duplicado** - Verificação automática
- ✅ **Cobertura convênio** - Validação em tempo real
- ✅ **Disponibilidade horário** - Controle de conflitos
- ✅ **Limite de vagas** - Por dia da semana

### **Notificações Automáticas:**
- 📱 **WhatsApp** - Lembretes e confirmações
- 📧 **Email** - Notificações para equipe
- 🚨 **No-Show** - Alertas automáticos

### **Auditoria Completa:**
- 📝 **Todas as operações** registradas
- 👤 **Usuário e timestamp** em cada ação
- 🔍 **Histórico detalhado** por agendamento
- 📊 **Relatórios** de atividades

---

## ⚠️ **CÓDIGOS DE ERRO**

| Código | Descrição |
|--------|-----------|
| 200 | ✅ Sucesso |
| 400 | ❌ Parâmetros inválidos |
| 401 | 🔒 Token inválido/expirado |
| 404 | 🔍 Recurso não encontrado |
| 409 | ⚡ Conflito (ex: CPF duplicado) |
| 500 | 🔥 Erro interno |

---

## 🛠️ **CONFIGURAÇÃO**

### **Requisitos:**
- PHP 7.4+
- Firebird 3.0+
- Extensões: `php-firebird`, `php-json`, `php-mbstring`

### **Instalação:**
```bash
git clone [repositorio]
cd agenda
cp includes/connection.php.example includes/connection.php
# Configurar banco de dados
```

### **WhatsApp (Opcional):**
```bash
./whatsapp_setup.sh
# Configura Evolution API
```

---

## 📈 **PERFORMANCE**

### **Cache Implementado:**
- ✅ **Especialidades** - 15 min
- ✅ **Convênios** - 30 min
- ✅ **Horários** - 5 min
- ✅ **Preparos** - 1 hora

### **Otimizações:**
- 🚀 **Queries otimizadas** com índices
- 📦 **Respostas comprimidas** (gzip)
- 🔄 **Conexão persistente** com banco
- 📊 **Logs estruturados** para monitoramento

---

## 🔒 **SEGURANÇA**

- 🛡️ **Autenticação Bearer Token**
- 🔐 **Sanitização SQL** completa
- 📝 **Auditoria** de todas as operações
- 🚫 **Rate limiting** por IP
- 🔍 **Validação** rigorosa de entrada

---

## 📞 **SUPORTE**

- 📧 **Email:** suporte@clinicaoitavarosado.com.br
- 📱 **WhatsApp:** (84) 99999-9999
- 📚 **Documentação:** Ver `API_DOCUMENTATION.md`

---

## 📄 **LICENÇA**

Propriedade da **Clínica Oitava Rosado** - Todos os direitos reservados.

**Versão:** 2.0
**Última atualização:** Setembro 2025

---

🤖 **Sistema otimizado para Agentes de IA com automações inteligentes!**
