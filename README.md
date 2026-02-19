# API Sistema de Agendamento - Clínica Oitava Rosado

[![PHP Version](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net)
[![Firebird](https://img.shields.io/badge/Database-Firebird-orange.svg)](https://firebirdsql.org)
[![API Version](https://img.shields.io/badge/API-v3.2-green.svg)](API_DOCUMENTATION.md)

API REST para gerenciamento de agendamentos médicos, desenvolvida para integração com Agentes de IA, chatbots e aplicações externas.

---

## URL Base

```
http://sistema.clinicaoitavarosado.com.br/oitava/agenda/
```

## Autenticação

Todas as requisições exigem **Bearer Token** no header `Authorization`.

```bash
# 1. Obter token
curl -X POST "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/auth/token.php" \
  -H "Content-Type: application/json" \
  -d '{"client_name": "Minha App", "client_email": "contato@app.com"}'

# Resposta:
# { "access_token": "SEU_TOKEN", "token_type": "Bearer", "expires_in": 31536000 }

# 2. Usar token em todas as requisições
curl -H "Authorization: Bearer SEU_TOKEN" \
  "http://sistema.clinicaoitavarosado.com.br/oitava/agenda/buscar_medicos.php"
```

Validade do token: **1 ano**.

---

## Endpoints Disponíveis

### Agendas e Horários

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `listar_agendas.php` | GET | Listar agendas por tipo e especialidade |
| `listar_agendas_json.php` | GET | Listar agendas (JSON estruturado com auth) |
| `buscar_horarios.php` | GET | Horários disponíveis por agenda e data |
| `verificar_vagas.php` | GET | Verificar vagas por convênio |
| `buscar_info_agenda.php` | GET | Informações completas da agenda |
| `buscar_bloqueios.php` | GET | Bloqueios de horário |
| `dias_disponiveis.php` | GET | Dias com disponibilidade |

### Agendamentos

| Endpoint | Método | Descrição |
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
| `consultar_agendamentos_paciente.php` | GET | Histórico do paciente |

### Pacientes

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `buscar_paciente.php` | POST | Buscar por nome, CPF ou telefone |
| `cadastrar_paciente.php` | POST | Cadastrar novo paciente |
| `validar_paciente.php` | GET | Validar dados do paciente |

### Médicos, Especialidades e Convênios

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `buscar_medicos.php` | GET | Listar médicos |
| `buscar_especialidades.php` | GET | Listar especialidades |
| `buscar_convenios.php` | GET | Listar convênios |
| `buscar_convenios_agenda.php` | GET | **Convênios e formas de pagamento por agenda** |
| `consultar_unidades.php` | GET | Unidades com especialidades |
| `consultar_precos.php` | GET | **Verificar disponibilidade e preços por convênio** |

### Exames e Procedimentos

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `buscar_exames_agenda.php` | GET | Exames de uma agenda |
| `buscar_procedimentos.php` | GET | Listar procedimentos |
| `consultar_preparos.php` | GET | Instruções de preparo |
| `buscar_exames_convenio.php` | GET | Exames por convênio |

### Auditoria

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `consultar_auditoria.php` | GET | Histórico de ações |
| `buscar_historico_agendamento.php` | GET | Histórico de um agendamento |

---

## Quick Start - Fluxo de Agendamento

```bash
TOKEN="SEU_TOKEN"
BASE="http://sistema.clinicaoitavarosado.com.br/oitava/agenda"

# 1. Listar agendas de cardiologia
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/listar_agendas_json.php?tipo=consulta&nome=Cardiologista"

# 2. Ver horários disponíveis
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/buscar_horarios.php?agenda_id=84&data=2026-03-10"

# 3. Buscar paciente por nome ou CPF
curl -X POST -H "Authorization: Bearer $TOKEN" \
  "$BASE/buscar_paciente.php" -d "termo=08635709411"

# 4. Consultar convênios da agenda
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/buscar_convenios_agenda.php?agenda_id=84"

# 5. Verificar se exame está disponível no convênio
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/consultar_precos.php?convenio_id=7&busca=RM CRANIO"
# Convênio normal (AMIL): retorna disponivel=true, sem valor
# Particular/Cartão de Desconto: retorna disponivel=true + valor

# 6. Criar agendamento
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

### Verificação de Disponibilidade e Preços

```bash
# 1. Buscar convênios da agenda (detecta cidade automaticamente)
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/buscar_convenios_agenda.php?agenda_id=84"
# Retorna TODOS os convênios: Amil, Particular, Cartão de Desconto, Cassi, etc.
# Cada convênio com seu lab_convenio_id

# 2. Convênio normal (AMIL, lab_convenio_id=7): verifica disponibilidade
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/consultar_precos.php?convenio_id=7&busca=RM CRANIO"
# Retorna: disponivel=true (sem valor)

# 3. Particular PIX (lab_convenio_id=1665): retorna com valor
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/consultar_precos.php?convenio_id=1665&busca=RM CRANIO"
# Retorna: disponivel=true + R$ 650,00

# 4. Particular Cartão Crédito (lab_convenio_id=1613): retorna com valor
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/consultar_precos.php?convenio_id=1613&busca=RM CRANIO"
# Retorna: disponivel=true + R$ 760,50

# 5. Cartão de Desconto Dinheiro (lab_convenio_id=2118): retorna com valor
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/consultar_precos.php?convenio_id=2118&busca=RM CRANIO"
# Retorna: disponivel=true + R$ 500,00

# Se total=0: exame NÃO disponível ou inativo naquele convênio
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
  "mensagem": "Descrição do erro"
}
```

### Status Codes

| Código | Descrição |
|--------|-----------|
| 200 | Sucesso |
| 400 | Requisição inválida |
| 401 | Não autorizado |
| 404 | Recurso não encontrado |
| 409 | Conflito (ex: CPF duplicado) |
| 500 | Erro interno |

---

## Requisitos Técnicos

- **PHP:** 7.4+
- **Banco de Dados:** Firebird 2.5+
- **Extensões PHP:** php-interbase, php-mbstring, php-curl
- **Encoding:** UTF-8 (API) / Windows-1252 (Banco)

---

## Documentação Completa

Consulte **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)** para detalhes de cada endpoint, parâmetros, exemplos de request/response e fluxos completos.

---

## Suporte

- **Telefone:** (84) 3315-6900
- **Endereço:** Mossoró - RN

(c) 2026 Clínica Oitava Rosado. Todos os direitos reservados.
