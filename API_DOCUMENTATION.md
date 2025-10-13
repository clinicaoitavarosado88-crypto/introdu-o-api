# API Sistema de Agendamento - Clínica Oitava Rosado

[![API Version](https://img.shields.io/badge/API-v2.4-green.svg)](README.md)
[![Status](https://img.shields.io/badge/Status-Production-success.svg)]()
[![Last Update](https://img.shields.io/badge/Updated-13%2F10%2F2025-blue.svg)]()

> 📢 **Versão 2.4** - Novos endpoints JSON estruturados, correções críticas e melhorias de UX

## 📑 Índice

- [Visão Geral](#visão-geral)
- [Novidades v2.4](#-novidades-versão-24)
- [Autenticação](#autenticação)
- [Status Codes](#status-codes)
- [Endpoints](#endpoints)
  - [Médicos](#médicos)
  - [Especialidades](#especialidades)
  - [Agendas e Horários](#agendas-e-horários)
  - [Procedimentos e Exames](#procedimentos-e-exames)
  - [Agendamentos](#agendamentos)
  - [Convênios](#convênios)
  - [Pacientes](#pacientes)
  - [Preços](#preços)
  - [Auditoria](#auditoria)
- [Collections Postman](#-collections-postman)
- [Erros Comuns](#erros-comuns)

---

## 🆕 Novidades Versão 2.4

### Correções Críticas

1. **✅ Processar Agendamento** - Aceita `hora_agendamento` E `horario_agendamento`
2. **✅ Buscar Horários** - Mensagens de erro HTTP 404 descritivas
3. **✅ Consultar Preços** - Corrigido para usar tabelas corretas

### Novas Funcionalidades

1. **⭐ Listar Agendas JSON** - Endpoint `listar_agendas_json.php`
   - Substitui HTML por JSON estruturado
   - ~60% menor, mais rápido
   - Ideal para IA/chatbots

2. **🎁 Campo `agenda` no Response** - Informações completas da agenda no retorno

**📄 Documentação:** [CORRECAO_PROCESSAR_AGENDAMENTO.md](CORRECAO_PROCESSAR_AGENDAMENTO.md) | [CONVERSAO_LISTAR_AGENDAS_JSON.md](CONVERSAO_LISTAR_AGENDAS_JSON.md)

---

## Visão Geral

Esta documentação descreve a API REST do Sistema de Agendamento da Clínica Oitava Rosado, desenvolvida em PHP utilizando banco de dados Firebird. A API permite gerenciar médicos, especialidades, horários e agendamentos.

**URL Base:** `http://sistema.clinicaoitavarosado.com.br/oitava/agenda/`

**Formato de Resposta:** JSON (UTF-8)

**Autenticação:** Bearer Token

**Banco de Dados:** Firebird (Windows-1252 → UTF-8)

## Autenticação

A API utiliza autenticação via **Bearer Token**. Todas as requisições devem incluir um token válido no cabeçalho de autorização.

### Obtendo um Token

**Requisição:**
```http
POST /auth/token.php
Content-Type: application/json
```

**Body:**
```json
{
  "client_name": "Nome da Aplicação",
  "client_email": "contato@aplicacao.com"
}
```

**Resposta:**
```json
{
  "access_token": "OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0",
  "token_type": "Bearer",
  "expires_in": 31536000
}
```

### Usando o Token

Inclua o token no cabeçalho `Authorization` de todas as requisições:

```bash
curl -X GET "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_medicos.php" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0"
```

**Token de Exemplo para Testes:**
`OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0`

**Validade:** Os tokens têm validade de 1 ano (31.536.000 segundos)

---

## Status Codes

| Status Code | Descrição |
|-------------|-----------|
| 200 | Sucesso |
| 400 | Requisição inválida |
| 401 | Não autorizado |
| 404 | Recurso não encontrado |
| 500 | Erro interno do servidor |

---

## Endpoints

### Médicos

#### Listar Médicos
Retorna lista de médicos disponíveis.

**Requisição:**
```http
GET /buscar_medicos.php?busca={termo_busca}
```

**Parâmetros de Query:**
- `busca` (string, opcional): Termo para filtrar médicos por nome

**Exemplo de Resposta:**
```json
{
  "results": [
    {
      "id": "2780",
      "text": "Dr. João Silva"
    },
    {
      "id": "2781", 
      "text": "Dra. Maria Santos"
    }
  ],
  "pagination": {
    "more": false
  }
}
```

---

### Especialidades

#### Listar Especialidades
Retorna lista de especialidades médicas disponíveis.

**Requisição:**
```http
GET /buscar_especialidades.php?busca={termo_busca}
```

**Parâmetros de Query:**
- `busca` (string, opcional): Termo para filtrar especialidades por nome

**Exemplo de Resposta:**
```json
{
  "results": [
    {
      "id": "1",
      "text": "Cardiologia"
    },
    {
      "id": "2",
      "text": "Dermatologia" 
    }
  ],
  "pagination": {
    "more": false
  }
}
```

---

### Agendas e Horários

#### Listar Agendas
Retorna lista de agendas por tipo e nome.

**Requisição:**
```http
GET /listar_agendas.php?tipo={tipo}&nome={nome}&cidade={cidade_id}
```

**Parâmetros de Query:**
- `tipo` (string): Tipo da agenda ("consulta" ou "procedimento")
- `nome` (string, opcional): Nome da especialidade ou procedimento para filtrar
- `cidade` (integer, opcional): ID da cidade/unidade

**Exemplo de Resposta (Consulta):**
```json
[
  {
    "id": "1",
    "nome": "Dr. João Silva - Cardiologia",
    "tipo": "consulta",
    "medico_id": "2780",
    "medico_nome": "Dr. João Silva",
    "especialidade_nome": "Cardiologia",
    "unidade": "Mossoró",
    "sala": "101",
    "telefone": "(84) 3421-1234",
    "tempo_estimado_minutos": 30,
    "vagas_por_dia": {
      "vagas_seg": 20,
      "vagas_ter": 15,
      "vagas_qua": 25,
      "vagas_qui": 20,
      "vagas_sex": 18,
      "vagas_sab": 10,
      "vagas_dom": 0
    },
    "limite_encaixes_dia": 5
  }
]
```

**Exemplo de Resposta (Procedimento):**
```json
[
  {
    "id": "30",
    "nome": "Ressonância Magnética",
    "tipo": "procedimento",
    "procedimento_nome": "Ressonância Magnética",
    "unidade": "Mossoró",
    "sala": "RM1",
    "telefone": "(84) 3421-1234",
    "tempo_estimado_minutos": 40,
    "vagas_por_dia": {
      "vagas_seg": 12,
      "vagas_ter": 8,
      "vagas_qua": 10,
      "vagas_qui": 12,
      "vagas_sex": 10,
      "vagas_sab": 6,
      "vagas_dom": 0
    },
    "medico_nome": null
  }
]
```

#### Listar Agendas (JSON Estruturado) ⭐ RECOMENDADO
Retorna lista detalhada de agendas em formato JSON estruturado. Substitui o endpoint HTML com dados completos incluindo horários, vagas, convênios e avisos.

**Endpoint:** `listar_agendas_json.php`

**Requisição:**
```http
GET /listar_agendas_json.php?tipo={tipo}&nome={nome}&dia={dia_semana}&cidade={cidade_id}
```

**Parâmetros de Query:**
- `tipo` (string, **obrigatório**): Tipo da agenda ("consulta" ou "procedimento")
- `nome` (string, **obrigatório**): Nome da especialidade (ex: "Cardiologista") ou procedimento (ex: "Ultrassonografia")
- `dia` (string, opcional): Dia da semana ("Segunda", "Terça", "Quarta", "Quinta", "Sexta", "Sábado", "Domingo")
- `cidade` (integer, opcional): ID da cidade/unidade

**Exemplo de Requisição:**
```bash
curl -X GET "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/listar_agendas_json.php?tipo=consulta&nome=Cardiologista&dia=Segunda" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0"
```

**Exemplo de Resposta (Consulta):**
```json
{
  "status": "sucesso",
  "total_agendas": 1,
  "filtros_aplicados": {
    "tipo": "consulta",
    "nome": "Cardiologista",
    "dia_semana": "Segunda",
    "cidade_id": null
  },
  "agendas": [
    {
      "id": 178,
      "tipo": "consulta",
      "medico": {
        "id": 2780,
        "nome": "CAMILO DE PAIVA CANTIDIO"
      },
      "especialidade": {
        "id": 5,
        "nome": "Cardiologista"
      },
      "localizacao": {
        "unidade_id": 1,
        "unidade_nome": "MOSSORÓ - RN",
        "sala": "201",
        "telefone": "(84) 3315-2773"
      },
      "configuracoes": {
        "tempo_estimado_minutos": 20,
        "idade_minima": null,
        "possui_retorno": true,
        "atende_comorbidade": false
      },
      "limites": {
        "vagas_dia": 20,
        "retornos_dia": 5,
        "encaixes_dia": 3
      },
      "horarios_por_dia": {
        "Segunda": [
          {
            "periodo": "manha",
            "inicio": "07:00",
            "fim": "13:20"
          }
        ],
        "Quarta": [
          {
            "periodo": "continuo",
            "inicio": "08:00",
            "fim": "17:00"
          }
        ]
      },
      "vagas_por_dia": {
        "Segunda": 20,
        "Quarta": 15
      },
      "convenios": [
        {
          "id": 1,
          "nome": "SUS"
        },
        {
          "id": 962,
          "nome": "PARTICULAR"
        }
      ],
      "avisos": {
        "observacoes": "NAO ESTA ATENDENDO AMIL",
        "informacoes_fixas": "ATENDE SAUDE BRASIL CRM 5991",
        "orientacoes": "Trazer exames anteriores"
      }
    }
  ]
}
```

**Exemplo de Resposta (Procedimento):**
```json
{
  "status": "sucesso",
  "total_agendas": 1,
  "filtros_aplicados": {
    "tipo": "procedimento",
    "nome": "Ultrassonografia",
    "dia_semana": null,
    "cidade_id": null
  },
  "agendas": [
    {
      "id": 30,
      "tipo": "procedimento",
      "procedimento": {
        "id": 34,
        "nome": "Ultrassonografia"
      },
      "medico": {
        "id": 1234,
        "nome": "MARIA SILVA"
      },
      "localizacao": {
        "unidade_id": 2,
        "unidade_nome": "ZONA SUL",
        "sala": "US-1",
        "telefone": "(84) 3315-8888"
      },
      "configuracoes": {
        "tempo_estimado_minutos": 30,
        "idade_minima": null,
        "possui_retorno": false,
        "atende_comorbidade": true
      },
      "limites": {
        "vagas_dia": 15,
        "retornos_dia": 0,
        "encaixes_dia": 2
      },
      "horarios_por_dia": {
        "Terça": [
          {
            "periodo": "manha",
            "inicio": "08:00",
            "fim": "12:00"
          }
        ],
        "Quinta": [
          {
            "periodo": "tarde",
            "inicio": "14:00",
            "fim": "18:00"
          }
        ]
      },
      "vagas_por_dia": {
        "Terça": 15,
        "Quinta": 12
      },
      "convenios": [
        {
          "id": 1,
          "nome": "SUS"
        },
        {
          "id": 24,
          "nome": "AMIL"
        }
      ],
      "avisos": {
        "observacoes": null,
        "informacoes_fixas": "JEJUM DE 8 HORAS",
        "orientacoes": null
      }
    }
  ]
}
```

**Códigos de Erro:**
- `400 Bad Request`: Parâmetro `tipo` ausente ou inválido
- `404 Not Found`: Especialidade ou procedimento não encontrado
- `401 Unauthorized`: Token de autenticação ausente ou inválido

**Vantagens sobre o endpoint HTML:**
- ✅ Dados estruturados em JSON puro (sem HTML)
- ✅ IDs numéricos acessíveis diretamente
- ✅ Horários estruturados por período (manhã/tarde/contínuo)
- ✅ Avisos importantes em campos dedicados
- ✅ Menor tamanho de resposta (~60% menor)
- ✅ Processável por IA/chatbots sem parser HTML

**Collection Postman:** `Listar_Agendas_JSON.postman_collection.json`

**Documentação Completa:** Ver `CONVERSAO_LISTAR_AGENDAS_JSON.md`

#### Buscar Horários Disponíveis
Retorna horários disponíveis para uma agenda em uma data específica.

**Requisição:**
```http
GET /buscar_horarios.php?agenda_id={id}&data={data}
```

**Parâmetros de Query:**
- `agenda_id` (integer): ID da agenda
- `data` (string): Data no formato YYYY-MM-DD

**Exemplo de Resposta:**
```json
{
  "horarios": [
    {
      "hora": "08:00",
      "disponivel": true
    },
    {
      "hora": "08:30", 
      "disponivel": false
    },
    {
      "hora": "09:00",
      "disponivel": true
    }
  ],
  "info_vagas": {
    "limite_para_hoje": 20,
    "ocupadas_hoje": 5,
    "disponiveis_hoje": 15,
    "dia_semana": "segunda"
  },
  "info_encaixes": {
    "limite_total": 5,
    "ocupados": 1,
    "disponiveis": 4
  }
}
```

#### Verificar Vagas Disponíveis
Verifica se há vagas disponíveis para um convênio específico.

**Requisição:**
```http
GET /verificar_vagas.php?agenda_id={id}&data={data}&convenio_id={convenio_id}
```

**Parâmetros de Query:**
- `agenda_id` (integer): ID da agenda
- `data` (string): Data no formato YYYY-MM-DD
- `convenio_id` (integer): ID do convênio

**Exemplo de Resposta:**
```json
{
  "tem_vagas": true,
  "vagas_disponiveis": 8,
  "limite_para_hoje": 20,
  "dia_semana": "segunda",
  "pode_agendar": true
}
```

---

### Procedimentos e Exames

#### Buscar Exames de uma Agenda
Retorna lista de exames disponíveis para uma agenda de procedimento.

**Requisição:**
```http
GET /buscar_exames_agenda.php?agenda_id={id}
```

**Parâmetros de Query:**
- `agenda_id` (integer): ID da agenda de procedimento

**Exemplo de Resposta:**
```json
{
  "status": "sucesso",
  "tipo_agenda": "procedimento",
  "procedimento_id": 34,
  "total_exames": 3,
  "exames": [
    {
      "id": 31,
      "nome": "Ressonância Magnética - Crânio"
    },
    {
      "id": 32,
      "nome": "Ressonância Magnética - Coluna"
    },
    {
      "id": 33,
      "nome": "Ressonância Magnética - Joelho"
    }
  ]
}
```

#### Buscar Procedimentos/Grupos de Exames
Retorna lista de procedimentos disponíveis (similar a especialidades).

**Requisição:**
```http
GET /buscar_procedimentos.php?busca={termo_busca}
```

**Parâmetros de Query:**
- `busca` (string, opcional): Termo para filtrar procedimentos por nome

**Exemplo de Resposta:**
```json
{
  "results": [
    {
      "id": "34",
      "text": "Ressonância Magnética"
    },
    {
      "id": "35",
      "text": "Tomografia Computadorizada"
    },
    {
      "id": "36",
      "text": "Ultrassonografia"
    }
  ],
  "pagination": {
    "more": false
  }
}
```

---

### Agendamentos

#### Buscar Agendamentos do Dia
Retorna todos os agendamentos de uma agenda em uma data específica.

**Requisição:**
```http
GET /buscar_agendamentos_dia.php?agenda_id={id}&data={data}
```

**Parâmetros de Query:**
- `agenda_id` (integer): ID da agenda
- `data` (string): Data no formato YYYY-MM-DD

**Exemplo de Resposta:**
```json
{
  "08:00": {
    "id": 123,
    "numero": 2415001,
    "hora": "08:00",
    "paciente": "João Silva Santos",
    "telefone": "(84) 99999-9999",
    "cpf": "123.456.789-01",
    "convenio": "Amil",
    "status": "AGENDADO",
    "tipo_consulta": "primeira_vez",
    "observacoes": "",
    "tipo_agendamento": "NORMAL",
    "confirmado": false,
    "ordem_chegada": null,
    "hora_chegada": null,
    "tem_os": false
  }
}
```

#### Buscar Detalhes de um Agendamento
Retorna informações detalhadas de um agendamento específico.

**Requisição:**
```http
GET /buscar_agendamento.php?id={agendamento_id}
```

**Parâmetros de Query:**
- `id` (integer): ID do agendamento

**Exemplo de Resposta:**
```json
{
  "sucesso": true,
  "id": 123,
  "numero": 2415001,
  "data": "2025-09-10",
  "horario": "08:00",
  "status": "AGENDADO",
  "tipo_consulta": "primeira_vez",
  "observacoes": "",
  "confirmado": false,
  "tipo_atendimento": "NORMAL",
  "paciente": {
    "id": 1,
    "nome": "João Silva Santos",
    "cpf": "123.456.789-01",
    "data_nascimento": "1990-01-01",
    "telefone": "(84) 99999-9999",
    "email": "joao@email.com"
  },
  "convenio": {
    "id": 24,
    "nome": "Amil"
  },
  "agenda": {
    "id": 1,
    "sala": "101",
    "telefone": "(84) 3421-1234",
    "unidade": "Mossoró",
    "medico": "Dr. João Silva",
    "especialidade": "Cardiologia"
  },
  "exames": [],
  "preparos": []
}
```

#### Criar Agendamento
Cria um novo agendamento.

**Requisição:**
```http
POST /processar_agendamento.php
Content-Type: application/x-www-form-urlencoded
```

**Parâmetros Obrigatórios:**
- `agenda_id` (integer): ID da agenda
- `data_agendamento` (string): Data no formato YYYY-MM-DD
- `hora_agendamento` OU `horario_agendamento` (string): Horário no formato HH:MM ⚠️ **AMBOS ACEITOS**
- `nome_paciente` (string): Nome completo do paciente
- `telefone_paciente` (string): Telefone de contato
- `convenio_id` (integer): ID do convênio

**Parâmetros Opcionais:**
- `paciente_id` (integer): ID do paciente se já cadastrado
- `usar_paciente_existente` (boolean string): `"true"` ou `"false"`
- `deve_cadastrar_paciente` (boolean string): `"true"` ou `"false"`
- `tipo_consulta` (string): `primeira_vez` ou `retorno`
- `observacoes` (string): Observações adicionais
- `especialidade_id` (integer): ID da especialidade (para médicos com múltiplas)
- `exames_ids` (string): IDs separados por vírgula (ex: `"31,32,33"`)
- `cpf_paciente` (string): CPF do paciente
- `data_nascimento` (string): Data de nascimento YYYY-MM-DD
- `sexo` (string): `M` ou `F`
- `email_paciente` (string): E-mail do paciente

**Exemplo de Requisição (Consulta):**
```bash
curl -X POST "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/processar_agendamento.php" \
  -d "agenda_id=1" \
  -d "data_agendamento=2025-09-10" \
  -d "hora_agendamento=08:00" \
  -d "paciente_id=1" \
  -d "convenio_id=24" \
  -d "tipo_consulta=primeira_vez" \
  -d "usar_paciente_existente=true" \
  -d "nome_paciente=João Silva" \
  -d "telefone_paciente=(84) 99999-9999" \
  -d "observacoes=Paciente relatou dores no peito" \
  -d "especialidade_id=1"
```

**Exemplo de Requisição (Procedimento com Exames):**
```bash
curl -X POST "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/processar_agendamento.php" \
  -d "agenda_id=30" \
  -d "data_agendamento=2025-09-10" \
  -d "horario_agendamento=08:00" \
  -d "paciente_id=1" \
  -d "convenio_id=24" \
  -d "tipo_consulta=primeira_vez" \
  -d "usar_paciente_existente=true" \
  -d "nome_paciente=Maria Santos" \
  -d "telefone_paciente=(84) 98888-8888" \
  -d "observacoes=Suspeita de lesão no joelho" \
  -d "exames_ids=31,32"
```

**Exemplo de Resposta (Sucesso - Consulta):**
```json
{
  "status": "sucesso",
  "mensagem": "Agendamento realizado com sucesso! Paciente vinculado ao cadastro existente.",
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
  "paciente_telefone": "(84) 98888-8888",
  "tipo_operacao": "paciente_existente",
  "paciente_cadastrado": false,
  "paciente_existente_usado": true,
  "agendamento_sem_cadastro": false,
  "endereco_salvo": false,
  "exames_ids": [],
  "exames_quantidade": 0,
  "exames_selecionados": false,
  "horario_agendamento": "14:30:00",
  "data_agendamento": "2025-10-21",
  "tipo": "AGENDAMENTO",
  "data_criacao": "2025-10-13 09:43:19",
  "auditoria_registrada": "nao",
  "usuario_criacao": "SISTEMA_WEB"
}
```

**Exemplo de Resposta (Sucesso - Procedimento):**
```json
{
  "status": "sucesso",
  "mensagem": "Agendamento realizado com sucesso!",
  "agendamento_id": 277,
  "numero_agendamento": "AGD-0022",
  "agenda": {
    "agenda_id": 30,
    "tipo_agenda": "procedimento",
    "procedimento": "Ressonância Magnética",
    "medico": "Dr. Silva Santos",
    "unidade": "Zona Norte"
  },
  "paciente_id": 636200,
  "paciente_nome": "MARIA SANTOS",
  "paciente_telefone": "(84) 99999-9999",
  "exames_ids": [31, 32, 33],
  "exames_quantidade": 3,
  "exames_selecionados": true,
  "horario_agendamento": "08:00:00",
  "data_agendamento": "2025-10-25",
  "tipo": "AGENDAMENTO"
}
```

**Exemplo de Resposta (Erro):**
```json
{
  "erro": true,
  "mensagem": "Horário não disponível para agendamento"
}
```

#### Cancelar Agendamento
Cancela um agendamento existente.

**Requisição:**
```http
POST /cancelar_agendamento.php
Content-Type: application/x-www-form-urlencoded
```

**Parâmetros do Body:**
```
agendamento_id=123
motivo=Paciente não pode comparecer
usuario=ADMIN
```

**Exemplo de Resposta (Sucesso):**
```json
{
  "sucesso": true,
  "mensagem": "Agendamento cancelado com sucesso!",
  "numero_agendamento": 2415001
}
```

#### Atualizar Status do Agendamento
Atualiza o status de um agendamento (confirmado, chegada, etc.).

**Requisição:**
```http
POST /atualizar_status_agendamento.php
Content-Type: application/x-www-form-urlencoded
```

**Parâmetros do Body:**
```
agendamento_id=123
status=CONFIRMADO
usuario=RECEPCAO
```

**Exemplo de Resposta:**
```json
{
  "sucesso": true,
  "mensagem": "Status do agendamento atualizado com sucesso!"
}
```

---

### Convênios

#### Buscar Convênios
Retorna lista de convênios disponíveis.

**Requisição:**
```http
GET /buscar_convenios.php?busca={termo_busca}
```

**Parâmetros de Query:**
- `busca` (string, opcional): Termo para filtrar convênios por nome

**Exemplo de Resposta:**
```json
{
  "results": [
    {
      "id": "1",
      "text": "SUS"
    },
    {
      "id": "24",
      "text": "Amil"
    },
    {
      "id": "962", 
      "text": "Particular"
    }
  ],
  "pagination": {
    "more": false
  }
}
```

---

### Pacientes

#### Buscar Pacientes
Busca pacientes por nome, CPF ou data de nascimento.

**Requisição:**
```http
POST /buscar_paciente.php
Content-Type: application/x-www-form-urlencoded
```

**Parâmetros do Body:**
```
termo=João Silva
```

**Exemplo de Resposta:**
```json
{
  "pacientes": [
    {
      "id": 1,
      "nome": "João Silva Santos",
      "cpf": "123.456.789-01",
      "data_nascimento": "1990-01-01",
      "telefone": "(84) 99999-9999",
      "email": "joao@email.com"
    }
  ]
}
```

---

## Estruturas de Dados

### Agendamento
```json
{
  "id": "integer",
  "numero": "integer", 
  "data": "string (YYYY-MM-DD)",
  "horario": "string (HH:MM)",
  "status": "string (AGENDADO|CONFIRMADO|CHEGOU|ATENDIDO|CANCELADO|FALTOU)",
  "tipo_consulta": "string (primeira_vez|retorno)",
  "tipo_agendamento": "string (NORMAL|ENCAIXE)",
  "observacoes": "string",
  "confirmado": "boolean",
  "ordem_chegada": "integer|null",
  "hora_chegada": "string|null"
}
```

### Paciente
```json
{
  "id": "integer",
  "nome": "string",
  "cpf": "string",
  "data_nascimento": "string (YYYY-MM-DD)",
  "telefone": "string",
  "email": "string"
}
```

### Agenda
```json
{
  "id": "integer",
  "nome": "string",
  "tipo": "string (consulta|procedimento)",
  "medico_id": "integer",
  "medico_nome": "string",
  "especialidade_nome": "string",
  "sala": "string",
  "telefone": "string",
  "tempo_estimado": "integer (minutos)"
}
```

---

## Códigos de Erro Comuns

### Agendamentos
- `"Horário não disponível"` - O horário solicitado já está ocupado
- `"Agenda bloqueada"` - A agenda está bloqueada para agendamentos
- `"Limite de vagas atingido"` - Não há mais vagas disponíveis para o dia da semana
- `"Paciente não encontrado"` - ID do paciente inválido
- `"Agendamento não encontrado"` - ID do agendamento inválido

### Autenticação
- `"Authorization header missing"` - Header Authorization não fornecido
- `"Invalid authorization format"` - Formato inválido do token (deve ser Bearer <token>)
- `"Invalid token"` - Token não encontrado ou inválido
- `"Token expired"` - Token expirado (validade de 1 ano)
- `"Token verification failed"` - Erro interno na verificação do token

**Nota:** Todos os endpoints marcados com ✅ CORRIGIDO agora utilizam a função `verify_api_token()` que retorna um array com status de validação e mensagem de erro, permitindo melhor controle do fluxo de autenticação.

### Geral
- `"Parâmetros inválidos"` - Parâmetros obrigatórios não fornecidos ou inválidos
- `"Erro interno do servidor"` - Erro no processamento da requisição

---

## Exemplos de Integração

### Fluxo Completo de Agendamento (Consulta)

1. **Buscar especialidades disponíveis:**
```bash
curl -X GET "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_especialidades.php?busca=cardio" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0"
```

2. **Listar agendas de cardiologia:**
```bash
curl -X GET "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/listar_agendas.php?tipo=consulta&nome=cardiologia" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0"
```

3. **Verificar horários disponíveis:**
```bash
curl -X GET "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_horarios.php?agenda_id=1&data=2025-09-10" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0"
```

4. **Buscar paciente:**
```bash
curl -X POST "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_paciente.php" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0" \
  -d "termo=João Silva"
```

5. **Criar agendamento:**
```bash
curl -X POST "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/processar_agendamento.php" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0" \
  -d "agenda_id=1&data_agendamento=2025-09-10&hora_agendamento=08:00&paciente_id=1&convenio_id=24&tipo_consulta=primeira_vez"
```

### Fluxo Completo de Agendamento (Procedimento)

1. **Buscar procedimentos disponíveis:**
```bash
curl -X GET "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_procedimentos.php?busca=ressonancia" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0"
```

2. **Listar agendas de ressonância:**
```bash
curl -X GET "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/listar_agendas.php?tipo=procedimento&nome=Ressonância Magnética" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0"
```

3. **Buscar exames disponíveis:**
```bash
curl -X GET "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_exames_agenda.php?agenda_id=30" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0"
```

4. **Verificar horários disponíveis:**
```bash
curl -X GET "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_horarios.php?agenda_id=30&data=2025-09-10" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0"
```

5. **Buscar paciente:**
```bash
curl -X POST "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_paciente.php" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0" \
  -d "termo=João Silva"
```

6. **Criar agendamento com exames:**
```bash
curl -X POST "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/processar_agendamento.php" \
  -H "Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0" \
  -d "agenda_id=30&data_agendamento=2025-09-10&hora_agendamento=08:00&paciente_id=1&convenio_id=24&exames[]=31&exames[]=32"
```

---

## Observações Importantes

- Todos os horários são no fuso horário de Brasília (BRT/BRST)
- As datas devem estar no formato ISO 8601 (YYYY-MM-DD)
- O sistema suporta agendamentos normais e encaixes
- Existe controle de limite de vagas por dia da semana e encaixes por dia
- O sistema registra auditoria de todas as operações
- Campos de texto suportam acentuação em português (UTF-8)

## Limitações

- **Rate Limiting:** Não implementado atualmente
- **Versionamento:** API não versionada
- **Timeout:** Requisições podem ter timeout de 30 segundos para consultas complexas
- **Encoding:** Sistema utiliza Windows-1252 internamente, com conversão para UTF-8 nas respostas

## Sistema de Controle de Vagas

O sistema utiliza **controle de vagas por dia da semana**, não por data específica:

### Como Funciona
O sistema utiliza campos específicos na tabela AGENDAS para cada dia da semana:
- **VAGAS_SEG**: 20 vagas (Segunda-feira)
- **VAGAS_TER**: 15 vagas (Terça-feira)  
- **VAGAS_QUA**: 25 vagas (Quarta-feira)
- **VAGAS_QUI**: 20 vagas (Quinta-feira)
- **VAGAS_SEX**: 18 vagas (Sexta-feira)
- **VAGAS_SAB**: 10 vagas (Sábado)
- **VAGAS_DOM**: 0 vagas (Domingo)

### Lógica de Controle
- Cada agenda define vagas específicas para cada dia da semana usando campos separados
- O sistema identifica o dia da semana da data solicitada
- Busca o valor do campo correspondente (ex: VAGAS_SEG para segunda-feira)
- Verifica quantos agendamentos NORMAIS já existem na data
- **Exemplo**: Se VAGAS_SEG = 20 e há 15 agendamentos numa segunda-feira, restam 5 vagas

### Tipos de Agendamento
- **NORMAL**: Conta para o limite de vagas do dia da semana
- **ENCAIXE**: Não conta para vagas normais, tem limite próprio separado

## Diferenças por Tipo de Agenda

### Consultas
- Requerem médico e especialidade
- Suportam retornos
- Controle de vagas usando campos específicos (VAGAS_SEG, VAGAS_TER, etc.)

### Procedimentos
- Podem não ter médico específico
- Requerem seleção de exames
- Agendamento sequencial para ressonância
- Preparos específicos por procedimento

---

---

## Endpoints para Agentes de IA

### Consultar Preços
Retorna valores por especialidade, procedimento e convênio para facilitar cotações.

**Requisição:**
```http
GET /consultar_precos.php?especialidade_id={id}&convenio_id={id}&unidade_id={id}
```

**Parâmetros de Query:**
- `especialidade_id` (integer, opcional): ID da especialidade
- `procedimento_id` (integer, opcional): ID do procedimento
- `convenio_id` (integer, obrigatório): ID do convênio
- `unidade_id` (integer, opcional): ID da unidade

**Exemplo de Resposta:**
```json
{
  "status": "sucesso",
  "total_precos": 1,
  "precos": [{
    "tipo_servico": "consulta",
    "especialidade_nome": "Cardiologia",
    "convenio_nome": "Amil",
    "valor_consulta": 150.00,
    "valor_retorno": 80.00
  }]
}
```

---

### Cadastrar Paciente ✅ TESTADO
Permite cadastro completo de novos pacientes com validações automáticas.

**Requisição:**
```http
POST /cadastrar_paciente.php
Content-Type: application/json
Authorization: Bearer {token}
```

**Body (campos obrigatórios):**
```json
{
  "nome": "João Silva Santos",
  "data_nascimento": "1990-01-01",
  "telefone": "(84) 99999-9999"
}
```

**Body (completo com opcionais):**
```json
{
  "nome": "João Silva Santos",
  "cpf": "123.456.789-01",
  "data_nascimento": "1990-01-01",
  "telefone": "(84) 99999-9999",
  "email": "joao@email.com",
  "endereco": "Rua Principal, 123",
  "cep": "59600-000",
  "cidade": "Mossoró",
  "estado": "RN",
  "nome_mae": "Maria Silva",
  "rg": "1234567",
  "profissao": "Analista",
  "sexo": "M"
}
```

**Exemplo de Resposta (201 Created):**
```json
{
  "status": "sucesso",
  "message": "Paciente cadastrado com sucesso",
  "paciente": {
    "id": 634794,
    "nome": "MARIA SANTOS TESTE POSTMAN",
    "cpf": "",
    "data_nascimento": "1988-12-25",
    "telefone": "84988887777",
    "email": "",
    "data_cadastro": "2025-10-06 10:14:40"
  }
}
```

**Resposta de Conflito (409):**
```json
{
  "error": "Conflict",
  "message": "CPF já cadastrado",
  "paciente_existente_id": 622684
}
```

---

### Consultar Unidades ✅ TESTADO
Fornece informações detalhadas das unidades incluindo especialidades, médicos e horários.

**Requisição:**
```http
GET /consultar_unidades.php?ativa_apenas=true
Authorization: Bearer {token}
```

**Parâmetros de Query:**
- `unidade_id` (integer, opcional): ID específico da unidade
- `ativa_apenas` (boolean, opcional): Apenas unidades ativas (default: true)

**Exemplo de Resposta Real (200 OK):**
```json
{
  "status": "sucesso",
  "total_unidades": 11,
  "filtros": {
    "ativa_apenas": true
  },
  "unidades": [
    {
      "id": 13,
      "nome": "Alto do Rodrigues",
      "endereco": "Av. Ângelo Varela, 499 - Centro, 59507-000 Alto do Rodrigues/RN | Telefone: (84) 3315-6900",
      "cnpj": "50.832.006/0001-01",
      "ativo": true,
      "horario_funcionamento": {
        "por_dia": []
      },
      "servicos": {
        "especialidades": [
          {"id": 6, "nome": "Cardiologista"},
          {"id": 5, "nome": "Clínico Geral"},
          {"id": 10, "nome": "Endocrinologista"}
        ],
        "procedimentos": [],
        "total_especialidades": 9,
        "total_procedimentos": 0
      },
      "medicos": {
        "lista": [
          {"id": 2857, "nome": "DR. ANTONIO MARCOS", "crm": "7654"}
        ],
        "total": 5
      }
    }
  ]
}
```

---

### Consultar Preparos
Lista instruções de preparos por exame ou procedimento sem necessidade de agendamento.

**Requisição:**
```http
GET /consultar_preparos.php?exame_id={id}&procedimento_id={id}&busca={termo}
```

**Parâmetros de Query:**
- `exame_id` (integer, opcional): ID do exame específico
- `procedimento_id` (integer, opcional): ID do procedimento
- `busca` (string, opcional): Termo para busca livre

**Exemplo de Resposta:**
```json
{
  "status": "sucesso",
  "total_preparos": 1,
  "preparos": [{
    "exame_nome": "Ressonância Magnética - Crânio",
    "titulo": "Preparo para RM",
    "instrucoes": [
      "Jejum de 4 horas antes do exame",
      "Retirar todos os objetos metálicos"
    ],
    "tempo_jejum_horas": 4,
    "anexos": [
      {
        "nome": "orientacoes_rm.pdf",
        "url_download": "/download_anexo_preparo.php?id=123"
      }
    ]
  }]
}
```

---

### Agendamentos por Paciente ✅ TESTADO
Facilita confirmações, cancelamentos e reagendamentos com histórico completo.

**Requisição:**
```http
GET /consultar_agendamentos_paciente.php?paciente_id=153738
Authorization: Bearer {token}
```

**Parâmetros de Query:**
- `paciente_id` (integer, obrigatório se não informar cpf): ID do paciente
- `cpf` (string, obrigatório se não informar paciente_id): CPF do paciente
- `status` (string, opcional): Filtrar por status (AGENDADO, CONFIRMADO, CANCELADO)
- `data_inicio` (string, opcional): Data inicial (YYYY-MM-DD)
- `data_fim` (string, opcional): Data final (YYYY-MM-DD)
- `limite` (integer, opcional): Limite de registros (default: 50)

**Exemplo de Resposta Real (200 OK):**
```json
{
  "status": "sucesso",
  "paciente": null,
  "total_agendamentos": 1,
  "filtros_aplicados": {
    "paciente_id": 153738,
    "status": null,
    "data_inicio": null,
    "data_fim": null,
    "limite": 50
  },
  "agendamentos": [
    {
      "id": 266,
      "numero": 0,
      "data": "2025-09-18",
      "horario": "06:30:00",
      "status": "CONFIRMADO",
      "tipo_consulta": "primeira_vez",
      "tipo_agendamento": "NORMAL",
      "observacoes": "",
      "confirmado": true,
      "ordem_chegada": null,
      "hora_chegada": null,
      "data_criacao": "2025-09-10 09:15:57",
      "usuario_criacao": null,
      "agenda": {
        "id": 30,
        "tipo": "procedimento",
        "sala": "",
        "telefone": "(84) 99818-6138"
      },
      "unidade": {
        "nome": "Mossoró",
        "endereco": "Rua: Juvenal Lamartine, 119 - Centro, 59600-155 Mossoró/RN | Telefone: (84) 3315-6900"
      },
      "medico": {
        "nome": "",
        "crm": ""
      },
      "especialidade": {
        "nome": ""
      },
      "procedimento": {
        "nome": "Ressonância Magnética"
      },
      "convenio": {
        "nome": "OITAVA ROSADO MOSSORO"
      },
      "exames": [],
      "ordem_servico": {
        "tem_os": false,
        "numero": null
      },
      "acoes_permitidas": {
        "pode_cancelar": false,
        "pode_reagendar": true,
        "pode_confirmar": false
      }
    }
  ]
}
```

---

### Processar No-Show
Define status específico que aciona automaticamente notificação para a equipe responsável.

**Requisição:**
```http
POST /processar_noshow.php
Content-Type: application/json
```

**Body:**
```json
{
  "agendamento_id": 123,
  "observacao": "Paciente não compareceu",
  "enviar_notificacao": true,
  "usuario": "RECEPCAO"
}
```

**Exemplo de Resposta:**
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
      {"tipo": "whatsapp_equipe", "destinatario": "Dr. João", "status": "enviado"},
      {"tipo": "email_equipe", "destinatario": "Coordenação", "status": "enviado"}
    ]
  }
}
```

---

### Consultar Valores para OS
Permite consulta de valores antes de criar Ordem de Serviço para validação de cobertura.

**Requisição:**
```http
GET /consultar_valores_os.php?convenio_id={id}&exames_ids={ids}&especialidade_id={id}
```

**Parâmetros de Query:**
- `convenio_id` (integer, obrigatório): ID do convênio
- `exame_id` (integer, opcional): ID de exame específico
- `exames_ids` (string, opcional): Lista de IDs separados por vírgula
- `especialidade_id` (integer, opcional): ID da especialidade
- `procedimento_id` (integer, opcional): ID do procedimento

**Exemplo de Resposta:**
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

## Fluxos Completos para Agentes de IA

### Fluxo de Agendamento de Consulta com Validação de Preços

1. **Consultar preços:**
```bash
curl -X GET "/consultar_precos.php?especialidade_id=1&convenio_id=24" \
  -H "Authorization: Bearer TOKEN"
```

2. **Buscar unidades disponíveis:**
```bash
curl -X GET "/consultar_unidades.php" \
  -H "Authorization: Bearer TOKEN"
```

3. **Cadastrar paciente (se necessário):**
```bash
curl -X POST "/cadastrar_paciente.php" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"nome":"João Silva","cpf":"123.456.789-01",...}'
```

4. **Criar agendamento:**
```bash
curl -X POST "/processar_agendamento.php" \
  -H "Authorization: Bearer TOKEN" \
  -d "agenda_id=1&data_agendamento=2025-09-10&..."
```

### Fluxo de Procedimento com Preparos e OS

1. **Consultar preparos:**
```bash
curl -X GET "/consultar_preparos.php?procedimento_id=34" \
  -H "Authorization: Bearer TOKEN"
```

2. **Validar valores da OS:**
```bash
curl -X GET "/consultar_valores_os.php?convenio_id=24&procedimento_id=34" \
  -H "Authorization: Bearer TOKEN"
```

3. **Criar agendamento e OS:**
```bash
curl -X POST "/processar_agendamento.php" \
  -H "Authorization: Bearer TOKEN"
# Seguido de
curl -X POST "/processar_ordem_servico.php" \
  -H "Authorization: Bearer TOKEN"
```

### Fluxo de Gestão de No-Show

1. **Consultar agendamentos do paciente:**
```bash
curl -X GET "/consultar_agendamentos_paciente.php?cpf=123.456.789-01" \
  -H "Authorization: Bearer TOKEN"
```

2. **Processar no-show com notificações:**
```bash
curl -X POST "/processar_noshow.php" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"agendamento_id":123,"enviar_notificacao":true}'
```

---

## Changelog

### v2.3 (06 Outubro 2025) - 🔧 **Correção de Estrutura de Banco**
- ✅ **CORREÇÃO CRÍTICA:** Mapeamento correto de todas as colunas do banco Firebird
- ✅ **TESTE COMPLETO:** Todos os 3 endpoints testados e aprovados (100% funcionando)
- ✅ **CORREÇÃO:** `consultar_unidades.php` - Colunas AGENDA_ATI, ID corrigidas
- ✅ **CORREÇÃO:** `cadastrar_paciente.php` - Mapeamento IDPACIENTE, PACIENTE, ANIVERSARIO, FONE1
- ✅ **CORREÇÃO:** `consultar_agendamentos_paciente.php` - JOINs e colunas corrigidos
- ✅ **MELHORIA:** Operador `??` para valores null
- ✅ **VALIDAÇÃO:** Endpoint retorna dados reais do banco com sucesso

### v2.2 (06 Outubro 2025) - 🔐 **Correção de Autenticação**
- ✅ **CORREÇÃO CRÍTICA:** Autenticação corrigida em `consultar_unidades.php`
- ✅ **CORREÇÃO CRÍTICA:** Autenticação corrigida em `cadastrar_paciente.php`
- ✅ **CORREÇÃO CRÍTICA:** Autenticação corrigida em `consultar_agendamentos_paciente.php`
- ✅ **CORREÇÃO:** Função `verify_api_token()` renomeada e refatorada
- ✅ **CORREÇÃO:** Retorno da função alterado para array com status e mensagem
- ✅ **MELHORIA:** Melhor tratamento de erros de autenticação
- ✅ **MELHORIA:** Mensagens de erro mais descritivas

### v2.1 (04 Outubro 2025) - 🔧 **Correções Críticas**
- ✅ **CORREÇÃO CRÍTICA:** Adicionado `ibase_commit()` em todos endpoints de leitura
- ✅ **CORREÇÃO CRÍTICA:** Adicionado `ibase_rollback()` em blocos catch para rollback de transações
- ✅ **CORREÇÃO:** `consultar_unidades.php` - Transações Firebird corrigidas
- ✅ **CORREÇÃO:** `cadastrar_paciente.php` - Commit após inserção implementado
- ✅ **CORREÇÃO:** `consultar_agendamentos_paciente.php` - Gerenciamento de transações
- ✅ **MELHORIA:** Validação de resultados de queries antes de processar
- ✅ **MELHORIA:** Tratamento de erros mais robusto em todas as APIs

### v2.0 (Setembro 2025) - 🤖 **Otimização para Agentes de IA**
- ✅ **7 novos endpoints** específicos para Agentes de IA
- ✅ **Consultar preços** por especialidade e convênio
- ✅ **Cadastrar pacientes** com validações automáticas
- ✅ **Informações completas** de unidades
- ✅ **Preparos de exames** sem necessidade de agendamento
- ✅ **Agendamentos por paciente** com ações permitidas
- ✅ **Fluxo de No-Show** com notificações automáticas
- ✅ **Consultar valores** para criação de OS
- ✅ **Auditoria expandida** para todas as operações
- ✅ **Notificações inteligentes** via WhatsApp e email

### v1.0 (Agosto 2025)
- Versão inicial da documentação
- Suporte completo para consultas e procedimentos
- Sistema de agendamento sequencial para ressonância
- Controle de vagas por dia da semana e encaixes por dia

---

## Notas Técnicas Importantes

### Gerenciamento de Transações Firebird
Todas as operações com banco de dados Firebird agora implementam corretamente:
- **Commit explícito** (`ibase_commit()`) após operações bem-sucedidas
- **Rollback automático** (`ibase_rollback()`) em caso de erros
- **Validação de resultados** antes de processar dados retornados

Isso garante:
- ✅ Consistência de dados
- ✅ Liberação adequada de recursos
- ✅ Prevenção de locks no banco
- ✅ Tratamento correto de erros

---

## Suporte

Para dúvidas sobre a API, entre em contato com a equipe de desenvolvimento.

**Versão da API:** 2.3
**Última atualização:** 06 Outubro 2025
**Status:** ✅ Testada e Funcionando 100%
**Otimizada para:** 🤖 Agentes de IA
