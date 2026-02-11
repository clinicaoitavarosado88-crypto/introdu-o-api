# API Sistema de Agendamento - Clinica Oitava Rosado

[![PHP Version](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net)
[![Firebird](https://img.shields.io/badge/Database-Firebird-orange.svg)](https://firebirdsql.org)
[![API Version](https://img.shields.io/badge/API-v3.0-green.svg)](API_DOCUMENTATION.md)

API REST para gerenciamento de agendamentos medicos, desenvolvida para integracao com Agentes de IA, chatbots e aplicacoes externas.

---

## URL Base

```
http://sistema.clinicaoitavarosado.com.br/oitava/agenda/
```

## Autenticacao

Todas as requisicoes exigem **Bearer Token** no header `Authorization`.

```bash
# 1. Obter token
curl -X POST "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/auth/token.php" \
  -H "Content-Type: application/json" \
  -d '{"client_name": "Minha App", "client_email": "contato@app.com"}'

# Resposta:
# { "access_token": "SEU_TOKEN", "token_type": "Bearer", "expires_in": 31536000 }

# 2. Usar token em todas as requisicoes
curl -H "Authorization: Bearer SEU_TOKEN" \
  "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_medicos.php"
```

Validade do token: **1 ano**.

---

## Endpoints Disponiveis

### Agendas e Horarios

| Endpoint | Metodo | Descricao |
|----------|--------|-----------|
| `listar_agendas.php` | GET | Listar agendas por tipo e especialidade |
| `listar_agendas_json.php` | GET | Listar agendas (JSON estruturado com auth) |
| `buscar_horarios.php` | GET | Horarios disponiveis por agenda e data |
| `verificar_vagas.php` | GET | Verificar vagas por convenio |
| `buscar_info_agenda.php` | GET | Informacoes completas da agenda |
| `buscar_bloqueios.php` | GET | Bloqueios de horario |
| `dias_disponiveis.php` | GET | Dias com disponibilidade |

### Agendamentos

| Endpoint | Metodo | Descricao |
|----------|--------|-----------|
| `processar_agendamento.php` | POST | Criar agendamento |
| `buscar_agendamento.php` | GET | Detalhes de um agendamento |
| `buscar_agendamentos_dia.php` | GET | Agendamentos do dia |
| `editar_agendamento.php` | POST | Editar agendamento |
| `mover_agendamento.php` | POST | Mover agendamento |
| `cancelar_agendamento.php` | POST | Cancelar agendamento |
| `atualizar_status_agendamento.php` | POST | Atualizar status |
| `marcar_chegada.php` | POST | Registrar chegada |
| `processar_noshow.php` | POST | Registrar falta |
| `consultar_agendamentos_paciente.php` | GET | Historico do paciente |

### Pacientes

| Endpoint | Metodo | Descricao |
|----------|--------|-----------|
| `buscar_paciente.php` | POST | Buscar por nome, CPF ou telefone |
| `cadastrar_paciente.php` | POST | Cadastrar novo paciente |
| `validar_paciente.php` | GET | Validar dados do paciente |

### Medicos, Especialidades e Convenios

| Endpoint | Metodo | Descricao |
|----------|--------|-----------|
| `buscar_medicos.php` | GET | Listar medicos |
| `buscar_especialidades.php` | GET | Listar especialidades |
| `buscar_convenios.php` | GET | Listar convenios |
| `consultar_unidades.php` | GET | Unidades com especialidades |
| `consultar_precos.php` | GET | Precos por convenio |

### Exames e Procedimentos

| Endpoint | Metodo | Descricao |
|----------|--------|-----------|
| `buscar_exames_agenda.php` | GET | Exames de uma agenda |
| `buscar_procedimentos.php` | GET | Listar procedimentos |
| `consultar_preparos.php` | GET | Instrucoes de preparo |
| `buscar_exames_convenio.php` | GET | Exames por convenio |

### Auditoria

| Endpoint | Metodo | Descricao |
|----------|--------|-----------|
| `consultar_auditoria.php` | GET | Historico de acoes |
| `buscar_historico_agendamento.php` | GET | Historico de um agendamento |

---

## Quick Start - Fluxo de Agendamento

```bash
TOKEN="SEU_TOKEN"
BASE="http://sistema.clinicaoitavarosado.com.br/oitava/agenda"

# 1. Listar agendas de cardiologia
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/listar_agendas_json.php?tipo=consulta&nome=Cardiologista"

# 2. Ver horarios disponiveis
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/buscar_horarios.php?agenda_id=84&data=2026-03-10"

# 3. Buscar paciente
curl -X POST -H "Authorization: Bearer $TOKEN" \
  "$BASE/buscar_paciente.php" -d "termo=Joao Silva"

# 4. Criar agendamento
curl -X POST -H "Authorization: Bearer $TOKEN" \
  "$BASE/processar_agendamento.php" \
  -d "agenda_id=84" \
  -d "data_agendamento=2026-03-10" \
  -d "hora_agendamento=11:00" \
  -d "paciente_id=636200" \
  -d "convenio_id=24" \
  -d "nome_paciente=Joao Silva" \
  -d "telefone_paciente=(84) 99999-9999" \
  -d "usar_paciente_existente=true"
```

---

## Formato de Resposta

Todos os endpoints retornam **JSON puro** com encoding **UTF-8**.

```json
{
  "status": "sucesso",
  "dados": { ... }
}
```

Erros retornam:
```json
{
  "erro": true,
  "mensagem": "Descricao do erro"
}
```

### Status Codes

| Codigo | Descricao |
|--------|-----------|
| 200 | Sucesso |
| 400 | Requisicao invalida |
| 401 | Nao autorizado |
| 404 | Recurso nao encontrado |
| 409 | Conflito (ex: CPF duplicado) |
| 500 | Erro interno |

---

## Requisitos Tecnicos

- **PHP:** 7.4+
- **Banco de Dados:** Firebird 2.5+
- **Extensoes PHP:** php-interbase, php-mbstring, php-curl
- **Encoding:** UTF-8 (API) / Windows-1252 (Banco)

---

## Documentacao Completa

Consulte **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)** para detalhes de cada endpoint, parametros, exemplos de request/response e fluxos completos.

---

## Suporte

- **Telefone:** (84) 3315-6900
- **Endereco:** Mossoro - RN

(c) 2026 Clinica Oitava Rosado. Todos os direitos reservados.
