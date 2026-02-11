# API Documentation - Clinica Oitava Rosado

**Versao:** 3.0
**URL Base:** `http://sistema.clinicaoitavarosado.com.br/oitava/agenda/`
**Formato:** JSON (UTF-8)
**Autenticacao:** Bearer Token

---

## Indice

- [Autenticacao](#autenticacao)
- [Status Codes](#status-codes)
- [Endpoints](#endpoints)
  - [Agendas e Horarios](#agendas-e-horarios)
  - [Agendamentos](#agendamentos)
  - [Pacientes](#pacientes)
  - [Medicos e Especialidades](#medicos-e-especialidades)
  - [Convenios e Precos](#convenios-e-precos)
  - [Exames e Procedimentos](#exames-e-procedimentos)
  - [Unidades](#unidades)
  - [Auditoria](#auditoria)
- [Estruturas de Dados](#estruturas-de-dados)
- [Fluxos de Integracao](#fluxos-de-integracao)
- [Erros Comuns](#erros-comuns)

---

## Autenticacao

A API utiliza **Bearer Token**. Todas as requisicoes devem incluir o token no header `Authorization`.

### Obter Token

```http
POST /auth/token.php
Content-Type: application/json
```

**Body:**
```json
{
  "client_name": "Nome da Aplicacao",
  "client_email": "contato@aplicacao.com"
}
```

**Resposta (200):**
```json
{
  "access_token": "OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0",
  "token_type": "Bearer",
  "expires_in": 31536000
}
```

### Usar Token

Header obrigatorio em todas as requisicoes:

```
Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0
```

Validade: 1 ano (31.536.000 segundos).

---

## Status Codes

| Codigo | Descricao |
|--------|-----------|
| 200 | Sucesso |
| 201 | Criado com sucesso |
| 400 | Requisicao invalida (parametros ausentes ou invalidos) |
| 401 | Nao autorizado (token ausente, invalido ou expirado) |
| 404 | Recurso nao encontrado |
| 405 | Metodo nao permitido |
| 409 | Conflito (ex: CPF ja cadastrado, horario ocupado) |
| 500 | Erro interno do servidor |

---

## Endpoints

### Agendas e Horarios

#### Listar Agendas

Retorna agendas filtradas por tipo e especialidade/procedimento.

```http
GET /listar_agendas.php?tipo={tipo}&nome={nome}
```

| Parametro | Tipo | Obrigatorio | Descricao |
|-----------|------|-------------|-----------|
| tipo | string | Sim | `consulta` ou `procedimento` |
| nome | string | Sim | Nome da especialidade ou procedimento |
| dia | string | Nao | Dia da semana (Segunda, Terca, etc.) |
| cidade | integer | Nao | ID da cidade/unidade |

**Resposta:**
```json
{
  "status": "sucesso",
  "tipo": "consulta",
  "filtro": {
    "nome": "Cardiologista",
    "dia": null,
    "cidade_id": null
  },
  "total": 17,
  "agendas": [
    {
      "id": 84,
      "tipo": "consulta",
      "nome_display": "Dr(a). CAMILO DE PAIVA CANTIDIO",
      "unidade": "Mossoro",
      "sala": "2o andar",
      "telefone": "(84) 3315-6900",
      "tempo_estimado_minutos": 20,
      "possui_retorno": true,
      "atende_comorbidade": false,
      "horarios": [
        {
          "dia_semana": "Segunda",
          "turnos": [
            {
              "periodo": "manha",
              "inicio": "08:00",
              "fim": "11:00"
            }
          ],
          "vagas_dia": 30
        }
      ],
      "vagas_por_dia": {
        "Segunda": 30,
        "Quarta": 25
      },
      "convenios": ["AMIL", "UNIMED", "SUS", "PARTICULAR"],
      "observacoes": "Trazer exames anteriores",
      "medico": "CAMILO DE PAIVA CANTIDIO",
      "especialidade": "Cardiologista",
      "especialidade_id": 6
    }
  ]
}
```

---

#### Listar Agendas (JSON Estruturado com Auth)

Versao com autenticacao obrigatoria e estrutura mais detalhada.

```http
GET /listar_agendas_json.php?tipo={tipo}&nome={nome}
Authorization: Bearer {token}
```

| Parametro | Tipo | Obrigatorio | Descricao |
|-----------|------|-------------|-----------|
| tipo | string | Sim | `consulta` ou `procedimento` |
| nome | string | Sim | Nome da especialidade ou procedimento |
| dia | string | Nao | Dia da semana |
| cidade | integer | Nao | ID da cidade/unidade |

**Resposta:**
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
        "unidade_nome": "MOSSORO - RN",
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
        ]
      },
      "vagas_por_dia": {
        "Segunda": 20,
        "Quarta": 15
      },
      "convenios": [
        { "id": 1, "nome": "SUS" },
        { "id": 962, "nome": "PARTICULAR" }
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

---

#### Buscar Horarios Disponiveis

```http
GET /buscar_horarios.php?agenda_id={id}&data={data}
```

| Parametro | Tipo | Obrigatorio | Descricao |
|-----------|------|-------------|-----------|
| agenda_id | integer | Sim | ID da agenda |
| data | string | Sim | Data no formato YYYY-MM-DD |

**Resposta:**
```json
{
  "horarios": [
    { "hora": "08:00", "disponivel": true },
    { "hora": "08:30", "disponivel": false },
    { "hora": "09:00", "disponivel": true }
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

---

#### Verificar Vagas

```http
GET /verificar_vagas.php?agenda_id={id}&data={data}&convenio_id={convenio_id}
```

**Resposta:**
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

### Agendamentos

#### Criar Agendamento

```http
POST /processar_agendamento.php
Content-Type: application/x-www-form-urlencoded
```

**Parametros obrigatorios:**

| Parametro | Tipo | Descricao |
|-----------|------|-----------|
| agenda_id | integer | ID da agenda |
| data_agendamento | string | Data YYYY-MM-DD |
| hora_agendamento | string | Horario HH:MM (aceita tambem `horario_agendamento`) |
| nome_paciente | string | Nome completo |
| telefone_paciente | string | Telefone de contato |
| convenio_id | integer | ID do convenio |

**Parametros opcionais:**

| Parametro | Tipo | Descricao |
|-----------|------|-----------|
| paciente_id | integer | ID do paciente (se ja cadastrado) |
| usar_paciente_existente | string | `"true"` ou `"false"` |
| tipo_consulta | string | `primeira_vez` ou `retorno` |
| observacoes | string | Observacoes adicionais |
| especialidade_id | integer | ID da especialidade |
| exames_ids | string | IDs separados por virgula (ex: `"31,32,33"`) |
| cpf_paciente | string | CPF do paciente |
| data_nascimento | string | Data YYYY-MM-DD |
| sexo | string | `M` ou `F` |
| email_paciente | string | E-mail |

**Resposta (200):**
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
    "unidade": "Mossoro"
  },
  "paciente_id": 636200,
  "paciente_nome": "JOAO SILVA",
  "horario_agendamento": "14:30:00",
  "data_agendamento": "2026-03-10"
}
```

---

#### Buscar Agendamento por ID

```http
GET /buscar_agendamento.php?id={agendamento_id}
```

**Resposta:**
```json
{
  "sucesso": true,
  "id": 123,
  "numero": 2415001,
  "data": "2026-03-10",
  "horario": "08:00",
  "status": "AGENDADO",
  "tipo_consulta": "primeira_vez",
  "paciente": {
    "id": 1,
    "nome": "Joao Silva Santos",
    "cpf": "123.456.789-01",
    "data_nascimento": "1990-01-01",
    "telefone": "(84) 99999-9999"
  },
  "convenio": {
    "id": 24,
    "nome": "Amil"
  },
  "agenda": {
    "id": 1,
    "sala": "101",
    "telefone": "(84) 3421-1234",
    "unidade": "Mossoro",
    "medico": "Dr. Joao Silva",
    "especialidade": "Cardiologia"
  },
  "exames": [],
  "preparos": []
}
```

---

#### Buscar Agendamentos do Dia

```http
GET /buscar_agendamentos_dia.php?agenda_id={id}&data={data}
```

**Resposta:**
```json
{
  "08:00": {
    "id": 123,
    "numero": 2415001,
    "hora": "08:00",
    "paciente": "Joao Silva Santos",
    "telefone": "(84) 99999-9999",
    "convenio": "Amil",
    "status": "AGENDADO",
    "tipo_consulta": "primeira_vez",
    "tipo_agendamento": "NORMAL",
    "confirmado": false,
    "tem_os": false
  }
}
```

---

#### Cancelar Agendamento

```http
POST /cancelar_agendamento.php
Content-Type: application/x-www-form-urlencoded
```

| Parametro | Tipo | Obrigatorio | Descricao |
|-----------|------|-------------|-----------|
| agendamento_id | integer | Sim | ID do agendamento |
| motivo | string | Sim | Motivo do cancelamento |
| usuario | string | Nao | Usuario responsavel |

**Resposta:**
```json
{
  "sucesso": true,
  "mensagem": "Agendamento cancelado com sucesso!",
  "numero_agendamento": 2415001
}
```

---

#### Atualizar Status

```http
POST /atualizar_status_agendamento.php
Content-Type: application/x-www-form-urlencoded
```

| Parametro | Tipo | Descricao |
|-----------|------|-----------|
| agendamento_id | integer | ID do agendamento |
| status | string | AGENDADO, CONFIRMADO, CHEGOU, ATENDIDO, CANCELADO, FALTOU |
| usuario | string | Usuario responsavel |

---

#### Consultar Agendamentos do Paciente

```http
GET /consultar_agendamentos_paciente.php?paciente_id={id}
Authorization: Bearer {token}
```

| Parametro | Tipo | Obrigatorio | Descricao |
|-----------|------|-------------|-----------|
| paciente_id | integer | Sim* | ID do paciente |
| cpf | string | Sim* | CPF (alternativa ao paciente_id) |
| status | string | Nao | Filtrar por status |
| data_inicio | string | Nao | Data inicial YYYY-MM-DD |
| data_fim | string | Nao | Data final YYYY-MM-DD |
| limite | integer | Nao | Limite de registros (default: 50) |

*Informar `paciente_id` ou `cpf`.

**Resposta:**
```json
{
  "status": "sucesso",
  "total_agendamentos": 1,
  "agendamentos": [
    {
      "id": 266,
      "data": "2026-03-10",
      "horario": "06:30:00",
      "status": "CONFIRMADO",
      "tipo_consulta": "primeira_vez",
      "tipo_agendamento": "NORMAL",
      "agenda": {
        "id": 30,
        "tipo": "procedimento"
      },
      "unidade": {
        "nome": "Mossoro"
      },
      "medico": {
        "nome": "Dr. Silva"
      },
      "convenio": {
        "nome": "AMIL"
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

#### Registrar No-Show

```http
POST /processar_noshow.php
Content-Type: application/json
```

**Body:**
```json
{
  "agendamento_id": 123,
  "observacao": "Paciente nao compareceu",
  "enviar_notificacao": true,
  "usuario": "RECEPCAO"
}
```

---

### Pacientes

#### Buscar Paciente

```http
POST /buscar_paciente.php
Content-Type: application/x-www-form-urlencoded
```

| Parametro | Tipo | Descricao |
|-----------|------|-----------|
| termo | string | Nome, CPF, data de nascimento ou telefone |

**Resposta:**
```json
{
  "pacientes": [
    {
      "id": 1,
      "nome": "Joao Silva Santos",
      "cpf": "123.456.789-01",
      "data_nascimento": "1990-01-01",
      "telefone": "(84) 99999-9999",
      "email": "joao@email.com"
    }
  ]
}
```

---

#### Cadastrar Paciente

```http
POST /cadastrar_paciente.php
Content-Type: application/json
Authorization: Bearer {token}
```

**Body (campos obrigatorios):**
```json
{
  "nome": "Joao Silva Santos",
  "data_nascimento": "1990-01-01",
  "telefone": "(84) 99999-9999"
}
```

**Body (completo):**
```json
{
  "nome": "Joao Silva Santos",
  "cpf": "123.456.789-01",
  "data_nascimento": "1990-01-01",
  "telefone": "(84) 99999-9999",
  "email": "joao@email.com",
  "endereco": "Rua Principal, 123",
  "cep": "59600-000",
  "cidade": "Mossoro",
  "estado": "RN",
  "nome_mae": "Maria Silva",
  "rg": "1234567",
  "profissao": "Analista",
  "sexo": "M"
}
```

**Resposta (201):**
```json
{
  "status": "sucesso",
  "message": "Paciente cadastrado com sucesso",
  "paciente": {
    "id": 634794,
    "nome": "JOAO SILVA SANTOS",
    "cpf": "123.456.789-01",
    "data_nascimento": "1990-01-01",
    "telefone": "84999999999",
    "data_cadastro": "2026-02-11 10:14:40"
  }
}
```

**Conflito (409):**
```json
{
  "error": "Conflict",
  "message": "CPF ja cadastrado",
  "paciente_existente_id": 622684
}
```

---

### Medicos e Especialidades

#### Listar Medicos

```http
GET /buscar_medicos.php?busca={termo}
```

**Resposta:**
```json
{
  "results": [
    { "id": "2780", "text": "Dr. Joao Silva" },
    { "id": "2781", "text": "Dra. Maria Santos" }
  ],
  "pagination": { "more": false }
}
```

---

#### Listar Especialidades

```http
GET /buscar_especialidades.php?busca={termo}
```

**Resposta:**
```json
{
  "results": [
    { "id": "1", "text": "Cardiologia" },
    { "id": "2", "text": "Dermatologia" }
  ],
  "pagination": { "more": false }
}
```

---

### Convenios e Precos

#### Listar Convenios

```http
GET /buscar_convenios.php?busca={termo}
```

**Resposta:**
```json
{
  "results": [
    { "id": "1", "text": "SUS" },
    { "id": "24", "text": "Amil" },
    { "id": "962", "text": "Particular" }
  ],
  "pagination": { "more": false }
}
```

---

#### Consultar Precos

```http
GET /consultar_precos.php?convenio_id={id}
Authorization: Bearer {token}
```

| Parametro | Tipo | Obrigatorio | Descricao |
|-----------|------|-------------|-----------|
| convenio_id | integer | Sim | ID do convenio |
| especialidade_id | integer | Nao | ID da especialidade |
| procedimento_id | integer | Nao | ID do procedimento |
| unidade_id | integer | Nao | ID da unidade |

**Resposta:**
```json
{
  "status": "sucesso",
  "total_precos": 1,
  "precos": [
    {
      "tipo_servico": "consulta",
      "especialidade_nome": "Cardiologia",
      "convenio_nome": "Amil",
      "valor_consulta": 150.00,
      "valor_retorno": 80.00
    }
  ]
}
```

---

### Exames e Procedimentos

#### Buscar Exames da Agenda

```http
GET /buscar_exames_agenda.php?agenda_id={id}
```

**Resposta:**
```json
{
  "status": "sucesso",
  "tipo_agenda": "procedimento",
  "procedimento_id": 34,
  "total_exames": 3,
  "exames": [
    { "id": 31, "nome": "Ressonancia Magnetica - Cranio" },
    { "id": 32, "nome": "Ressonancia Magnetica - Coluna" },
    { "id": 33, "nome": "Ressonancia Magnetica - Joelho" }
  ]
}
```

---

#### Listar Procedimentos

```http
GET /buscar_procedimentos.php?busca={termo}
```

**Resposta:**
```json
{
  "results": [
    { "id": "34", "text": "Ressonancia Magnetica" },
    { "id": "35", "text": "Tomografia Computadorizada" },
    { "id": "36", "text": "Ultrassonografia" }
  ],
  "pagination": { "more": false }
}
```

---

#### Consultar Preparos

```http
GET /consultar_preparos.php?exame_id={id}
Authorization: Bearer {token}
```

| Parametro | Tipo | Obrigatorio | Descricao |
|-----------|------|-------------|-----------|
| exame_id | integer | Nao | ID do exame |
| procedimento_id | integer | Nao | ID do procedimento |
| busca | string | Nao | Busca livre |

**Resposta:**
```json
{
  "status": "sucesso",
  "total_preparos": 1,
  "preparos": [
    {
      "exame_nome": "Ressonancia Magnetica - Cranio",
      "titulo": "Preparo para RM",
      "instrucoes": [
        "Jejum de 4 horas antes do exame",
        "Retirar todos os objetos metalicos"
      ],
      "tempo_jejum_horas": 4,
      "anexos": [
        {
          "nome": "orientacoes_rm.pdf",
          "url_download": "/download_anexo_preparo.php?id=123"
        }
      ]
    }
  ]
}
```

---

### Unidades

#### Consultar Unidades

```http
GET /consultar_unidades.php?ativa_apenas=true
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "status": "sucesso",
  "total_unidades": 11,
  "unidades": [
    {
      "id": 13,
      "nome": "Alto do Rodrigues",
      "endereco": "Av. Angelo Varela, 499 - Centro",
      "cnpj": "50.832.006/0001-01",
      "ativo": true,
      "servicos": {
        "especialidades": [
          { "id": 6, "nome": "Cardiologista" },
          { "id": 5, "nome": "Clinico Geral" }
        ],
        "procedimentos": [],
        "total_especialidades": 9,
        "total_procedimentos": 0
      },
      "medicos": {
        "lista": [
          { "id": 2857, "nome": "DR. ANTONIO MARCOS", "crm": "7654" }
        ],
        "total": 5
      }
    }
  ]
}
```

---

### Auditoria

#### Consultar Auditoria

```http
GET /consultar_auditoria.php?data_inicio={data}&data_fim={data}
```

#### Historico de um Agendamento

```http
GET /buscar_historico_agendamento.php?agendamento_id={id}
```

---

## Estruturas de Dados

### Status do Agendamento

| Status | Descricao |
|--------|-----------|
| AGENDADO | Agendamento criado, aguardando confirmacao |
| CONFIRMADO | Paciente confirmou presenca |
| CHEGOU | Paciente chegou na clinica |
| ATENDIDO | Atendimento realizado |
| CANCELADO | Agendamento cancelado |
| FALTOU | Paciente nao compareceu (no-show) |

### Tipos de Agendamento

| Tipo | Descricao |
|------|-----------|
| NORMAL | Agendamento regular (conta para limite de vagas) |
| ENCAIXE | Encaixe extra (limite proprio separado) |

### Tipos de Consulta

| Tipo | Descricao |
|------|-----------|
| primeira_vez | Primeira consulta com o medico |
| retorno | Retorno de consulta anterior |

### Controle de Vagas

O sistema controla vagas por **dia da semana**, nao por data:
- Cada agenda define vagas para cada dia (VAGAS_SEG, VAGAS_TER, etc.)
- O sistema verifica quantos agendamentos NORMAIS ja existem na data
- Encaixes tem limite proprio separado

---

## Fluxos de Integracao

### Fluxo 1: Agendamento de Consulta

```
1. GET /buscar_especialidades.php?busca=cardio
2. GET /listar_agendas_json.php?tipo=consulta&nome=Cardiologista
3. GET /buscar_horarios.php?agenda_id=84&data=2026-03-10
4. POST /buscar_paciente.php  (termo=nome ou CPF)
5. POST /processar_agendamento.php  (com todos os dados)
```

### Fluxo 2: Agendamento de Procedimento com Exames

```
1. GET /buscar_procedimentos.php?busca=ressonancia
2. GET /listar_agendas_json.php?tipo=procedimento&nome=Ressonancia
3. GET /buscar_exames_agenda.php?agenda_id=30
4. GET /consultar_preparos.php?procedimento_id=34
5. GET /buscar_horarios.php?agenda_id=30&data=2026-03-10
6. POST /processar_agendamento.php  (com exames_ids=31,32)
```

### Fluxo 3: Consulta de Precos

```
1. GET /buscar_convenios.php?busca=amil
2. GET /consultar_precos.php?especialidade_id=1&convenio_id=24
```

### Fluxo 4: Cancelamento

```
1. GET /consultar_agendamentos_paciente.php?cpf=123.456.789-01
2. POST /cancelar_agendamento.php  (agendamento_id + motivo)
```

---

## Erros Comuns

### Autenticacao

| Mensagem | Causa |
|----------|-------|
| Authorization header missing | Header Authorization nao enviado |
| Invalid authorization format | Formato incorreto (deve ser `Bearer {token}`) |
| Invalid token | Token nao existe ou foi revogado |
| Token expired | Token expirado |

### Agendamentos

| Mensagem | Causa |
|----------|-------|
| Horario nao disponivel | Horario ja ocupado |
| Agenda bloqueada | Agenda com bloqueio ativo |
| Limite de vagas atingido | Sem vagas para o dia |
| Paciente nao encontrado | ID do paciente invalido |
| Agendamento nao encontrado | ID do agendamento invalido |

### Geral

| Mensagem | Causa |
|----------|-------|
| Parametros invalidos | Campos obrigatorios ausentes |
| Erro interno do servidor | Falha no processamento |

---

## Observacoes Tecnicas

- Todos os horarios estao no fuso de Brasilia (BRT/BRST)
- Datas no formato ISO 8601 (YYYY-MM-DD)
- O banco Firebird usa Windows-1252 internamente; a API converte para UTF-8
- Caracteres especiais (acentos, o, a) sao corrigidos automaticamente
- Todas as operacoes sao registradas na auditoria

---

**Versao:** 3.0
**Ultima atualizacao:** Fevereiro 2026
