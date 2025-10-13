# 🏥 API Sistema de Agendamento - Clínica Oitava Rosado

[![PHP Version](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net)
[![Firebird](https://img.shields.io/badge/Database-Firebird-orange.svg)](https://firebirdsql.org)
[![API Version](https://img.shields.io/badge/API-v2.4-green.svg)](API_DOCUMENTATION.md)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)]()

> Sistema completo de agendamento médico com API REST otimizada para integração com Agentes de IA, chatbots e aplicações web/mobile.

---

## 🚀 Visão Geral

API REST completa para gerenciamento de agendamentos médicos, desenvolvida em PHP com banco de dados Firebird. Sistema robusto com autenticação via Bearer Token, validações inteligentes e automações avançadas.

**🌐 URL Base:** `http://sistema.clinicaoitavarosado.com.br/oitava/agenda/`

**📚 Documentação Completa:** [API_DOCUMENTATION.md](API_DOCUMENTATION.md)

---

## ✨ Funcionalidades Principais

### 🏥 Sistema de Agendamento
- ✅ Consultas médicas por especialidade
- ✅ Procedimentos e exames com múltiplos itens
- ✅ Controle de vagas por dia da semana
- ✅ Sistema de encaixes inteligente
- ✅ Reagendamentos e cancelamentos
- ✅ Bloqueios de horários (permanente, temporário, dia, horário)
- ✅ Limites por convênio

### 👥 Gestão de Pacientes
- ✅ Cadastro completo com validação de CPF
- ✅ Busca avançada (nome, CPF, data nascimento, telefone)
- ✅ Histórico completo de agendamentos
- ✅ Endereço completo com CEP
- ✅ Múltiplos telefones

### 📊 Consultas e Relatórios
- ✅ Listar agendas com horários estruturados (JSON)
- ✅ Buscar horários disponíveis por agenda
- ✅ Verificar vagas por convênio
- ✅ Consultar preços de exames e consultas
- ✅ Histórico de agendamentos por paciente
- ✅ Auditoria completa de ações

### 🤖 Otimizado para Agentes de IA
- ✅ Dados estruturados em JSON puro (sem HTML)
- ✅ Validações robustas com mensagens descritivas
- ✅ Campos `agenda` com informações completas no response
- ✅ Suporte a múltiplos formatos de campos
- ✅ Encoding UTF-8 garantido

---

## 🆕 Novidades - Versão 2.4 (13/10/2025)

### ✅ Correções Críticas

1. **Processar Agendamento** - Aceita AMBOS os formatos de horário
   - ✅ `hora_agendamento` (novo formato)
   - ✅ `horario_agendamento` (formato legado)
   - 📄 Doc: [CORRECAO_PROCESSAR_AGENDAMENTO.md](CORRECAO_PROCESSAR_AGENDAMENTO.md)

2. **Buscar Horários** - Mensagens de erro descritivas
   - ✅ Retorna HTTP 404 quando agenda não tem horários configurados
   - ✅ Modo debug com informações detalhadas
   - 📄 Doc: [CORRECAO_BUSCAR_HORARIOS.md](CORRECAO_BUSCAR_HORARIOS.md)

3. **Consultar Preços** - Tabelas corretas
   - ✅ Corrigido para usar `LAB_CONVENIOSTAB_IT`
   - ✅ Filtro apenas para convênios particulares
   - ✅ Incluindo especialidades

### 🎁 Melhorias

1. **Listar Agendas (JSON Estruturado)** ⭐ RECOMENDADO
   - ✅ Substitui retorno HTML por JSON puro
   - ✅ Horários estruturados por período (manhã/tarde/contínuo)
   - ✅ Convênios, vagas, avisos em campos dedicados
   - ✅ ~60% menor que versão HTML
   - 📄 Doc: [CONVERSAO_LISTAR_AGENDAS_JSON.md](CONVERSAO_LISTAR_AGENDAS_JSON.md)

2. **Campo `agenda` no Response de Agendamento**
   - ✅ Retorna informações completas da agenda
   - ✅ Médico, especialidade, procedimento, unidade
   - ✅ Melhor UX - confirmação visual imediata

---

## 🔐 Autenticação

Sistema de **Bearer Token** com validade de 1 ano:

### Obter Token

```bash
curl -X POST "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/auth/token.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_name": "Minha Aplicação",
    "client_email": "contato@app.com"
  }'
```

**Resposta:**
```json
{
  "access_token": "OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0",
  "token_type": "Bearer",
  "expires_in": 31536000
}
```

### Usar Token

Incluir em todas as requisições:

```bash
curl -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0" \
  "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_medicos.php"
```

**Token de Teste:** `OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0`

---

## 📋 Endpoints Principais

### 🏥 Agendas

| Endpoint | Método | Descrição | Status |
|----------|--------|-----------|--------|
| `/listar_agendas_json.php` | GET | **⭐ Listar agendas (JSON estruturado)** | ✅ v2.4 |
| `/buscar_horarios.php` | GET | Buscar horários disponíveis | ✅ v2.4 |
| `/verificar_vagas.php` | GET | Verificar vagas por convênio | ✅ |
| `/buscar_info_agenda.php` | GET | Informações completas da agenda | ✅ |
| `/buscar_exames_agenda.php` | GET | Exames disponíveis para procedimento | ✅ |

### 📅 Agendamentos

| Endpoint | Método | Descrição | Status |
|----------|--------|-----------|--------|
| `/processar_agendamento.php` | POST | **Criar agendamento** | ✅ v2.4 |
| `/buscar_agendamento.php` | GET | Buscar agendamento por ID | ✅ |
| `/buscar_agendamentos_dia.php` | GET | Listar agendamentos do dia | ✅ |
| `/cancelar_agendamento.php` | POST | Cancelar agendamento | ✅ |
| `/atualizar_status_agendamento.php` | POST | Atualizar status | ✅ |
| `/marcar_chegada.php` | POST | Registrar chegada do paciente | ✅ |

### 👤 Pacientes

| Endpoint | Método | Descrição | Status |
|----------|--------|-----------|--------|
| `/cadastrar_paciente.php` | POST | Cadastrar novo paciente | ✅ v2.3 |
| `/buscar_paciente.php` | POST | Buscar paciente (nome/CPF/tel) | ✅ |
| `/consultar_agendamentos_paciente.php` | GET | Histórico de agendamentos | ✅ v2.3 |

### 💰 Preços e Convênios

| Endpoint | Método | Descrição | Status |
|----------|--------|-----------|--------|
| `/consultar_precos.php` | GET | Consultar preços por convênio | ✅ v2.4 |
| `/buscar_convenios.php` | GET | Listar convênios | ✅ |
| `/buscar_especialidades.php` | GET | Listar especialidades | ✅ |

### 🔍 Consultas

| Endpoint | Método | Descrição | Status |
|----------|--------|-----------|--------|
| `/buscar_medicos.php` | GET | Listar médicos | ✅ |
| `/buscar_postos.php` | GET | Listar unidades | ✅ |
| `/consultar_unidades.php` | GET | Unidades com especialidades | ✅ v2.3 |
| `/consultar_preparos.php` | GET | Preparos para exames | ✅ |

### 📊 Auditoria

| Endpoint | Método | Descrição | Status |
|----------|--------|-----------|--------|
| `/consultar_auditoria.php` | GET | Histórico de ações | ✅ |
| `/buscar_historico_agendamento.php` | GET | Histórico de um agendamento | ✅ |

---

## 📦 Collections Postman

Collections prontas para importar no Postman:

| Collection | Arquivo | Requests | Descrição |
|------------|---------|----------|-----------|
| **API Completa** | `Clinica_Oitava_API.postman_collection.json` | 45+ | Collection principal |
| **Processar Agendamento** | `Processar_Agendamento.postman_collection.json` | 12 | Criar agendamentos |
| **Listar Agendas JSON** | `Listar_Agendas_JSON.postman_collection.json` | 15 | Listar agendas estruturadas |
| **Buscar Horários** | `Buscar_Horarios.postman_collection.json` | 16 | Horários disponíveis |
| **Consultar Preços** | `Consultar_Precos.postman_collection.json` | 8 | Preços por convênio |

**Todas as collections incluem:**
- ✅ Bearer Token pré-configurado
- ✅ Exemplos de sucesso e erro
- ✅ Documentação inline
- ✅ Variáveis de ambiente

---

## 🚀 Quick Start

### 1. Obter Token de Autenticação

```bash
curl -X POST "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/auth/token.php" \
  -H "Content-Type: application/json" \
  -d '{"client_name":"Teste","client_email":"teste@email.com"}'
```

### 2. Listar Agendas Disponíveis

```bash
curl -H "Authorization: Bearer SEU_TOKEN" \
  "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/listar_agendas_json.php?tipo=consulta&nome=Cardiologista"
```

### 3. Buscar Horários Disponíveis

```bash
curl -H "Authorization: Bearer SEU_TOKEN" \
  "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_horarios.php?agenda_id=84&data=2025-10-21"
```

### 4. Criar Agendamento

```bash
curl -X POST "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/processar_agendamento.php" \
  -d "agenda_id=84" \
  -d "data_agendamento=2025-10-21" \
  -d "hora_agendamento=11:00" \
  -d "paciente_id=636200" \
  -d "convenio_id=24" \
  -d "nome_paciente=João Silva" \
  -d "telefone_paciente=(84) 99999-9999" \
  -d "usar_paciente_existente=true"
```

---

## 📖 Exemplos de Response

### Listar Agendas (JSON Estruturado)

```json
{
  "status": "sucesso",
  "total_agendas": 1,
  "agendas": [
    {
      "id": 84,
      "tipo": "consulta",
      "medico": {
        "id": 2714,
        "nome": "CAMILO DE PAIVA CANTIDIO"
      },
      "especialidade": {
        "id": 6,
        "nome": "Cardiologista"
      },
      "localizacao": {
        "unidade_nome": "Mossoró",
        "sala": "2º ANDAR",
        "telefone": "(84) 3315-6900"
      },
      "horarios_por_dia": {
        "Segunda": [
          {
            "periodo": "manha",
            "inicio": "07:00",
            "fim": "15:00"
          }
        ]
      },
      "vagas_por_dia": {
        "Segunda": 20
      },
      "convenios": [
        {"id": 1, "nome": "Amil"},
        {"id": 24, "nome": "SUS"}
      ]
    }
  ]
}
```

### Processar Agendamento

```json
{
  "status": "sucesso",
  "mensagem": "Agendamento realizado com sucesso!",
  "agendamento_id": 276,
  "numero_agendamento": "AGD-0021",
  "agenda": {
    "agenda_id": 84,
    "tipo_agenda": "consulta",
    "medico": "CAMILO DE PAIVA CANTIDIO",
    "especialidade": "Cardiologista",
    "unidade": "Mossoró"
  },
  "paciente_id": 636200,
  "paciente_nome": "YAGO MERCHAN KAMIMURA",
  "horario_agendamento": "14:30:00",
  "data_agendamento": "2025-10-21"
}
```

---

## 🔧 Estrutura do Banco de Dados

### Principais Tabelas

| Tabela | Descrição | Campos Principais |
|--------|-----------|-------------------|
| `AGENDAS` | Configuração de agendas | ID, TIPO, MEDICO_ID, PROCEDIMENTO_ID |
| `AGENDA_HORARIOS` | Horários de funcionamento | AGENDA_ID, DIA_SEMANA, HORARIO_INICIO |
| `AGENDAMENTOS` | Agendamentos realizados | ID, AGENDA_ID, PACIENTE_ID, DATA |
| `LAB_PACIENTES` | Cadastro de pacientes | IDPACIENTE, PACIENTE, CPF, FONE1 |
| `LAB_MEDICOS_PRES` | Cadastro de médicos | ID, NOME |
| `ESPECIALIDADES` | Especialidades médicas | ID, NOME |
| `GRUPO_EXAMES` | Grupos de procedimentos | ID, NOME |
| `LAB_CONVENIOS` | Convênios aceitos | IDCONVENIO, CONVENIO |
| `CONVENIOS` | Configuração de convênios | ID, NOME |

**Encoding:** Windows-1252 (banco) → UTF-8 (API)

---

## 📝 Documentação Adicional

### Correções e Melhorias

- 📄 [CORRECAO_PROCESSAR_AGENDAMENTO.md](CORRECAO_PROCESSAR_AGENDAMENTO.md) - Aceitar múltiplos formatos de horário
- 📄 [CORRECAO_BUSCAR_HORARIOS.md](CORRECAO_BUSCAR_HORARIOS.md) - Mensagens de erro descritivas
- 📄 [CONVERSAO_LISTAR_AGENDAS_JSON.md](CONVERSAO_LISTAR_AGENDAS_JSON.md) - HTML → JSON estruturado

### Guias de Implementação

- 📄 [API_DOCUMENTATION.md](API_DOCUMENTATION.md) - Documentação completa da API
- 📄 [IMPLEMENTACAO_ORDEM_SERVICO.md](IMPLEMENTACAO_ORDEM_SERVICO.md) - Sistema de Ordem de Serviço
- 📄 [MANUAL_WHATSAPP.md](MANUAL_WHATSAPP.md) - Integração WhatsApp

### Release Notes

- 📄 [RELEASE_NOTES_v2.3.md](RELEASE_NOTES_v2.3.md) - Versão 2.3
- 📄 [CONTRIBUTING.md](CONTRIBUTING.md) - Como contribuir

---

## ⚙️ Requisitos Técnicos

- **PHP:** 7.4 ou superior
- **Banco de Dados:** Firebird 2.5+
- **Extensões PHP:** `php-interbase`, `php-mbstring`, `php-curl`
- **Servidor Web:** Apache 2.4+ ou Nginx
- **Encoding:** UTF-8 (API) / Windows-1252 (Banco)

---

## 🔒 Segurança

- ✅ Autenticação via Bearer Token
- ✅ Validação de todos os inputs
- ✅ Prepared statements (proteção SQL Injection)
- ✅ Rate limiting configurável
- ✅ CORS habilitado
- ✅ Logs de auditoria completos
- ✅ Encoding validation

---

## 📊 Status dos Endpoints

| Categoria | Total | Funcionando | Em Desenvolvimento |
|-----------|-------|-------------|-------------------|
| Agendas | 8 | 8 ✅ | - |
| Agendamentos | 10 | 10 ✅ | - |
| Pacientes | 4 | 4 ✅ | - |
| Preços/Convênios | 5 | 5 ✅ | - |
| Consultas | 6 | 6 ✅ | - |
| Auditoria | 2 | 2 ✅ | - |
| **TOTAL** | **35** | **35 ✅** | **0** |

**Última atualização:** 13/10/2025

---

## 🤝 Suporte

- 📧 **Email:** suporte@clinicaoitavarosado.com.br
- 📞 **Telefone:** (84) 3315-6900
- 📍 **Endereço:** Mossoró - RN

---

## 📄 Licença

© 2025 Clínica Oitava Rosado. Todos os direitos reservados.

Sistema proprietário desenvolvido para uso exclusivo da Clínica Oitava Rosado.

---

## 🏆 Histórico de Versões

### v2.4 (13/10/2025) - Current
- ✨ Novo endpoint `listar_agendas_json.php` (JSON estruturado)
- 🔧 Correção aceitar `hora_agendamento` E `horario_agendamento`
- 🎁 Campo `agenda` completo no response de agendamento
- 📝 Mensagens de erro descritivas em `buscar_horarios.php`
- 📚 5 collections Postman atualizadas

### v2.3 (06/10/2025)
- ✅ Endpoints para agentes IA 100% funcionais
- 🔧 Correção estrutura banco de dados
- 📝 Documentação completa

### v2.0 (Setembro 2025)
- 🚀 Sistema de agendamento completo
- 🔐 Autenticação Bearer Token
- 📊 Auditoria de ações

---

<div align="center">

**Desenvolvido com ❤️ pela equipe Clínica Oitava Rosado**

[Documentação](API_DOCUMENTATION.md) • [Postman Collections](/) • [Suporte](mailto:suporte@clinicaoitavarosado.com.br)

</div>
