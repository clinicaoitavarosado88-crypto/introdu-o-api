# API Sistema de Agendamento - Clínica Oitava Rosado

## Visão Geral

Esta documentação descreve a API REST do Sistema de Agendamento da Clínica Oitava Rosado, desenvolvida em PHP utilizando banco de dados Firebird. A API permite gerenciar médicos, especialidades, horários e agendamentos.

**URL Base:** `http://sistema.clinicaoitavarosado.com.br/oitava/agenda/`

**Formato de Resposta:** JSON

**Autenticação:** Token de API (Bearer Token)

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

**Parâmetros do Body (Consulta):**
```
agenda_id=1
data_agendamento=2025-09-10
hora_agendamento=08:00
paciente_id=1
convenio_id=24
tipo_consulta=primeira_vez
observacoes=Paciente relatou dores no peito
especialidade_id=1
```

**Parâmetros do Body (Procedimento com Exames):**
```
agenda_id=30
data_agendamento=2025-09-10
hora_agendamento=08:00
paciente_id=1
convenio_id=24
tipo_consulta=primeira_vez
observacoes=Suspeita de lesão no joelho
exames[]=31&exames[]=32
```

**Exemplo de Resposta (Sucesso):**
```json
{
  "sucesso": true,
  "mensagem": "Agendamento realizado com sucesso!",
  "numero_agendamento": 2415002,
  "detalhes": {
    "paciente": "João Silva Santos",
    "data": "2025-09-10",
    "hora": "08:00",
    "medico": "Dr. João Silva",
    "especialidade": "Cardiologia"
  }
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
- `"Token expired"` - Token expirado
- `"Token verification failed"` - Erro interno na verificação do token

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

## Changelog

### v1.0 (Setembro 2025)
- Versão inicial da documentação
- Suporte completo para consultas e procedimentos
- Sistema de agendamento sequencial para ressonância
- Controle de vagas por dia da semana e encaixes por dia

---

## Suporte

Para dúvidas sobre a API, entre em contato com a equipe de desenvolvimento.

**Versão da API:** 1.0  
**Última atualização:** Setembro 2025
