# API Documentation - Clínica Oitava Rosado

**Versão:** 3.1
**URL Base:** `http://sistema.clinicaoitavarosado.com.br/oitava/agenda/`
**Formato:** JSON (UTF-8)
**Autenticação:** Bearer Token

---

## Índice

- [Autenticação](#autenticação)
- [Status Codes](#status-codes)
- [Endpoints](#endpoints)
  - [Agendas e Horários](#agendas-e-horários)
  - [Agendamentos](#agendamentos)
  - [Pacientes](#pacientes)
  - [Médicos e Especialidades](#médicos-e-especialidades)
  - [Convênios, Formas de Pagamento e Preços](#convênios-formas-de-pagamento-e-preços)
  - [Exames e Procedimentos](#exames-e-procedimentos)
  - [Unidades](#unidades)
  - [Auditoria](#auditoria)
- [Estruturas de Dados](#estruturas-de-dados)
- [Fluxos de Integração](#fluxos-de-integração)
- [Erros Comuns](#erros-comuns)

---

## Autenticação

A API utiliza **Bearer Token**. Todas as requisições devem incluir o token no header `Authorization`.

### Obter Token

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

**Resposta (200):**
```json
{
  "access_token": "OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0",
  "token_type": "Bearer",
  "expires_in": 31536000
}
```

### Usar Token

Header obrigatório em todas as requisições:

```
Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0
```

Validade: 1 ano (31.536.000 segundos).

---

## Status Codes

| Código | Descrição |
|--------|-----------|
| 200 | Sucesso |
| 201 | Criado com sucesso |
| 400 | Requisição inválida (parâmetros ausentes ou inválidos) |
| 401 | Não autorizado (token ausente, inválido ou expirado) |
| 404 | Recurso não encontrado |
| 405 | Método não permitido |
| 409 | Conflito (ex: CPF já cadastrado, horário ocupado) |
| 500 | Erro interno do servidor |

---

## Endpoints

### Agendas e Horários

#### Listar Agendas

Retorna agendas filtradas por tipo e especialidade/procedimento.

```http
GET /listar_agendas.php?tipo={tipo}&nome={nome}
```

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| tipo | string | Sim | `consulta` ou `procedimento` |
| nome | string | Sim | Nome da especialidade ou procedimento |
| dia | string | Não | Dia da semana (Segunda, Terça, etc.) |
| cidade | integer | Não | ID da cidade/unidade |

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
      "unidade": "Mossoró",
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
              "periodo": "manhã",
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

Versão com autenticação obrigatória e estrutura mais detalhada.

```http
GET /listar_agendas_json.php?tipo={tipo}&nome={nome}
Authorization: Bearer {token}
```

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| tipo | string | Sim | `consulta` ou `procedimento` |
| nome | string | Sim | Nome da especialidade ou procedimento |
| dia | string | Não | Dia da semana |
| cidade | integer | Não | ID da cidade/unidade |

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
            "periodo": "manhã",
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
        "observacoes": "NÃO ESTÁ ATENDENDO AMIL",
        "informacoes_fixas": "ATENDE SAÚDE BRASIL CRM 5991",
        "orientacoes": "Trazer exames anteriores"
      }
    }
  ]
}
```

---

#### Buscar Horários Disponíveis

```http
GET /buscar_horarios.php?agenda_id={id}&data={data}
```

| Parâmetro | Tipo | Obrigatório | Descrição |
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

**Parâmetros obrigatórios:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| agenda_id | integer | ID da agenda |
| data_agendamento | string | Data YYYY-MM-DD |
| hora_agendamento | string | Horário HH:MM (aceita também `horario_agendamento`) |
| nome_paciente | string | Nome completo |
| telefone_paciente | string | Telefone de contato |
| convenio_id | integer | ID do convênio |

**Parâmetros opcionais:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| paciente_id | integer | ID do paciente (se já cadastrado) |
| usar_paciente_existente | string | `"true"` ou `"false"` |
| tipo_consulta | string | `primeira_vez` ou `retorno` |
| observacoes | string | Observações adicionais |
| especialidade_id | integer | ID da especialidade |
| exames_ids | string | IDs separados por vírgula (ex: `"31,32,33"`) |
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
    "unidade": "Mossoró"
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
    "unidade": "Mossoró",
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

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| agendamento_id | integer | Sim | ID do agendamento |
| motivo | string | Sim | Motivo do cancelamento |
| usuario | string | Não | Usuário responsável |

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

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| agendamento_id | integer | ID do agendamento |
| status | string | AGENDADO, CONFIRMADO, CHEGOU, ATENDIDO, CANCELADO, FALTOU |
| usuario | string | Usuário responsável |

---

#### Consultar Agendamentos do Paciente

```http
GET /consultar_agendamentos_paciente.php?paciente_id={id}
Authorization: Bearer {token}
```

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| paciente_id | integer | Sim* | ID do paciente |
| cpf | string | Sim* | CPF (alternativa ao paciente_id) |
| status | string | Não | Filtrar por status |
| data_inicio | string | Não | Data inicial YYYY-MM-DD |
| data_fim | string | Não | Data final YYYY-MM-DD |
| limite | integer | Não | Limite de registros (default: 50) |

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
        "nome": "Mossoró"
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
  "observacao": "Paciente não compareceu",
  "enviar_notificacao": true,
  "usuario": "RECEPCAO"
}
```

---

### Pacientes

#### Buscar Paciente

Busca por nome (parcial, múltiplas palavras), CPF (com ou sem formatação) ou data de nascimento.

```http
POST /buscar_paciente.php
Content-Type: application/x-www-form-urlencoded
```

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| termo | string | Nome, CPF (com ou sem formatação) ou data de nascimento |

**Exemplos de busca:**
- Nome: `termo=JOAO SILVA` (busca todas as palavras em qualquer ordem)
- CPF formatado: `termo=086.357.094-11`
- CPF sem formatação: `termo=08635709411`
- Data nascimento: `termo=15/03/1990`

**Resposta:**
```json
{
  "status": "sucesso",
  "termo_busca": "08635709411",
  "total_encontrados": 1,
  "pacientes": [
    {
      "id": 636200,
      "nome": "Joao Silva Santos",
      "cpf": "086.357.094-11",
      "data_nascimento": "1990-03-15",
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

**Body (campos obrigatórios):**
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
  "cidade": "Mossoró",
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
  "message": "CPF já cadastrado",
  "paciente_existente_id": 622684
}
```

---

### Médicos e Especialidades

#### Listar Médicos

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

### Convênios, Formas de Pagamento e Preços

#### Listar Convênios

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

#### Buscar Convênios e Formas de Pagamento da Agenda

Retorna as categorias de convênio (Particular, Cartão de Desconto, etc.) e as formas de pagamento disponíveis (Dinheiro, PIX, Cartão Crédito, Débito, Parcelado) com o `lab_convenio_id` correto para consulta de preços.

A cidade é detectada **automaticamente** a partir da agenda (`AGENDAS.UNIDADE_ID` -> `LAB_CIDADES`).

```http
GET /buscar_convenios_agenda.php?agenda_id={id}
Authorization: Bearer {token}
```

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| agenda_id | integer | Sim | ID da agenda |

**Resposta:**
```json
{
  "status": "sucesso",
  "agenda_id": 84,
  "cidade": "Mossoró",
  "idlocal": 1,
  "medico": "CAMILO DE PAIVA CANTIDIO",
  "tipo": "consulta",
  "total_categorias": 3,
  "categorias": [
    {
      "categoria_id": 24,
      "categoria": "Particular",
      "tem_opcoes": true,
      "opcoes": [
        {
          "lab_convenio_id": 24,
          "nome": "OITAVA ROSADO MOSSORO",
          "forma_pagamento": "DINHEIRO",
          "particular": true,
          "pix": false,
          "cartao": false,
          "cartao_desconto": false,
          "socio": true
        },
        {
          "lab_convenio_id": 1665,
          "nome": "PIX MOSSORO",
          "forma_pagamento": "PIX",
          "particular": false,
          "pix": true,
          "cartao": false,
          "cartao_desconto": false,
          "socio": false
        },
        {
          "lab_convenio_id": 1613,
          "nome": "CARTAO CREDITO MOSSORO",
          "forma_pagamento": "CARTAO",
          "particular": true,
          "pix": false,
          "cartao": true,
          "cartao_desconto": false,
          "socio": false
        },
        {
          "lab_convenio_id": 1614,
          "nome": "CARTAO DEBITO MOSSORO",
          "forma_pagamento": "CARTAO",
          "particular": true,
          "pix": false,
          "cartao": true,
          "cartao_desconto": false,
          "socio": false
        },
        {
          "lab_convenio_id": 1615,
          "nome": "CARTAO PARCELADO MOSSORO",
          "forma_pagamento": "CARTAO",
          "particular": true,
          "pix": false,
          "cartao": true,
          "cartao_desconto": false,
          "socio": false
        }
      ]
    },
    {
      "categoria_id": 16,
      "categoria": "Cartão de Desconto",
      "tem_opcoes": true,
      "opcoes": [
        {
          "lab_convenio_id": 2118,
          "nome": "CARTAO DE DESCONTO MOSSORO",
          "forma_pagamento": "CARTAO_DESCONTO",
          "particular": false,
          "pix": false,
          "cartao": false,
          "cartao_desconto": true,
          "socio": true
        },
        {
          "lab_convenio_id": 2406,
          "nome": "PIX CARTAO DE DESCONTO",
          "forma_pagamento": "PIX",
          "particular": false,
          "pix": true,
          "cartao": false,
          "cartao_desconto": true,
          "socio": false
        }
      ]
    },
    {
      "categoria_id": 27,
      "categoria": "Exército",
      "tem_opcoes": false,
      "opcoes": []
    }
  ]
}
```

**Valores do campo `forma_pagamento`:**

| Valor | Descrição |
|-------|-----------|
| DINHEIRO | Pagamento em dinheiro |
| PIX | Pagamento via PIX |
| CARTAO | Cartão de crédito, débito ou parcelado |
| CARTAO_DESCONTO | Cartão de desconto (com ou sem cartão) |
| SOCIO | Sócio/conveniado |

**Exemplo de preços para RM CRÂNIO SEM CONTRASTE (Mossoró):**

| Categoria | Forma | lab_convenio_id | Valor |
|-----------|-------|-----------------|-------|
| Particular | Dinheiro | 24 | R$ 650,00 |
| Particular | PIX | 1665 | R$ 650,00 |
| Particular | Cartão Crédito | 1613 | R$ 760,50 |
| Particular | Cartão Débito | 1614 | R$ 760,50 |
| Particular | Cartão Parcelado | 1615 | R$ 760,50 |
| Cartão Desconto | Dinheiro | 2118 | R$ 500,00 |
| Cartão Desconto | PIX | 2406 | R$ 500,00 |
| Cartão Desconto | Crédito | 2403 | R$ 585,00 |
| Cartão Desconto | Débito | 2404 | R$ 585,00 |
| Cartão Desconto | Parcelado | 2405 | R$ 585,00 |

---

#### Consultar Preços

Usa o `lab_convenio_id` retornado pelo `buscar_convenios_agenda.php` para consultar o preço correto do exame.

```http
GET /consultar_precos.php?convenio_id={lab_convenio_id}
Authorization: Bearer {token}
```

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| convenio_id | integer | Sim | ID do LAB_CONVENIO (retornado por buscar_convenios_agenda) |
| busca | string | Não | Busca por nome do exame |
| exame_id | integer | Não | ID do exame |
| procedimento_id | integer | Não | ID do procedimento |

**Exemplo - PIX Mossoró:**
```bash
GET /consultar_precos.php?convenio_id=1665&busca=RM CRANIO SEM
```

**Resposta:**
```json
{
  "status": "sucesso",
  "total": 1,
  "resultados": [
    {
      "exame_id": 1234,
      "nome_exame": "RM CRANIO SEM CONTRASTE",
      "valor_unitario": 650.00,
      "convenio_id": 1665,
      "convenio_nome": "PIX MOSSORO"
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
    { "id": 31, "nome": "Ressonância Magnética - Crânio" },
    { "id": 32, "nome": "Ressonância Magnética - Coluna" },
    { "id": 33, "nome": "Ressonância Magnética - Joelho" }
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
    { "id": "34", "text": "Ressonância Magnética" },
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

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| exame_id | integer | Não | ID do exame |
| procedimento_id | integer | Não | ID do procedimento |
| busca | string | Não | Busca livre |

**Resposta:**
```json
{
  "status": "sucesso",
  "total_preparos": 1,
  "preparos": [
    {
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
          { "id": 5, "nome": "Clínico Geral" }
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

#### Histórico de um Agendamento

```http
GET /buscar_historico_agendamento.php?agendamento_id={id}
```

---

## Estruturas de Dados

### Status do Agendamento

| Status | Descrição |
|--------|-----------|
| AGENDADO | Agendamento criado, aguardando confirmação |
| CONFIRMADO | Paciente confirmou presença |
| CHEGOU | Paciente chegou na clínica |
| ATENDIDO | Atendimento realizado |
| CANCELADO | Agendamento cancelado |
| FALTOU | Paciente não compareceu (no-show) |

### Tipos de Agendamento

| Tipo | Descrição |
|------|-----------|
| NORMAL | Agendamento regular (conta para limite de vagas) |
| ENCAIXE | Encaixe extra (limite próprio separado) |

### Tipos de Consulta

| Tipo | Descrição |
|------|-----------|
| primeira_vez | Primeira consulta com o médico |
| retorno | Retorno de consulta anterior |

### Controle de Vagas

O sistema controla vagas por **dia da semana**, não por data:
- Cada agenda define vagas para cada dia (VAGAS_SEG, VAGAS_TER, etc.)
- O sistema verifica quantos agendamentos NORMAIS já existem na data
- Encaixes têm limite próprio separado

---

## Fluxos de Integração

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

### Fluxo 3: Consulta de Preços (Particular/Cartão de Desconto)

O preço varia conforme a forma de pagamento. Usar `buscar_convenios_agenda.php` para obter o `lab_convenio_id` correto.

```
1. GET /buscar_convenios_agenda.php?agenda_id=84
   -> Retorna categorias: Particular (tem_opcoes: true), Cartão de Desconto (tem_opcoes: true)
   -> Particular tem: Dinheiro (id=24), PIX (id=1665), Crédito (id=1613), Débito (id=1614), Parcelado (id=1615)

2. Bot pergunta: "Particular ou Cartão de Desconto?"
   -> Paciente: "Particular"

3. Bot pergunta: "Dinheiro, PIX, Crédito, Débito ou Parcelado?"
   -> Paciente: "PIX" -> lab_convenio_id = 1665

4. GET /consultar_precos.php?convenio_id=1665&busca=RM CRANIO
   -> Retorna: R$ 650,00
```

### Fluxo 4: Cancelamento

```
1. GET /consultar_agendamentos_paciente.php?cpf=123.456.789-01
2. POST /cancelar_agendamento.php  (agendamento_id + motivo)
```

### Fluxo 5: Busca de Paciente por CPF

```
1. POST /buscar_paciente.php  (termo=08635709411)
   -> Busca CPF com ou sem formatação
   -> Retorna paciente com id, nome, cpf, telefone, data_nascimento
2. Usar paciente_id no agendamento com usar_paciente_existente=true
```

---

## Erros Comuns

### Autenticação

| Mensagem | Causa |
|----------|-------|
| Authorization header missing | Header Authorization não enviado |
| Invalid authorization format | Formato incorreto (deve ser `Bearer {token}`) |
| Invalid token | Token não existe ou foi revogado |
| Token expired | Token expirado |

### Agendamentos

| Mensagem | Causa |
|----------|-------|
| Horário não disponível | Horário já ocupado |
| Agenda bloqueada | Agenda com bloqueio ativo |
| Limite de vagas atingido | Sem vagas para o dia |
| Paciente não encontrado | ID do paciente inválido |
| Agendamento não encontrado | ID do agendamento inválido |

### Geral

| Mensagem | Causa |
|----------|-------|
| Parâmetros inválidos | Campos obrigatórios ausentes |
| Erro interno do servidor | Falha no processamento |

---

## Observações Técnicas

- Todos os horários estão no fuso de Brasília (BRT/BRST)
- Datas no formato ISO 8601 (YYYY-MM-DD)
- O banco Firebird usa Windows-1252 internamente; a API converte para UTF-8
- Caracteres especiais (acentos, ç, ã) são corrigidos automaticamente
- Todas as operações são registradas na auditoria

---

**Versão:** 3.1
**Última atualização:** 19 Fevereiro 2026
