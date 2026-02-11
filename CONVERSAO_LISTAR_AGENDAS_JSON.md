# 📋 Conversão: Listar Agendas HTML → JSON

## 🎯 Objetivo

Converter o endpoint `listar_agendas.php` que retorna HTML embutido em JSON para um formato JSON estruturado e processável por APIs e sistemas de IA.

---

## ❌ Problema Original

### Endpoint: `listar_agendas.php`

**Retorno atual:**
```json
[
  {
    "data": "<div class='flex justify-center'>\n<div class='grid grid-cols-1 md:grid-cols-2 gap-6 w-full max-w-screen-lg px-4'>\n<!-- Card da agenda -->\n<div onclick=\"carregarAgendamento(178, 5)\" title=\"Clique para agendar\" data-especialidade-id=\"5\" class=\"block bg-white border border-gray-200 rounded-lg shadow-md p-5 hover:bg-blue-50 hover:shadow-lg hover:scale-[1.02] transition-all duration-200 cursor-pointer\">\n<h3 class=\"text-base font-bold text-[#0C9C99] mb-4 leading-tight text-center\">Dr(a). LIDIANE AMARAL DE MEDEIROS – Clínico Geral</h3>..."
  }
]
```

### Problemas Identificados

1. **HTML dentro de JSON**: Dados estruturados convertidos em markup HTML
2. **Informação perdida em atributos**: `data-especialidade-id="5"`, `onclick="carregarAgendamento(178, 5)"`
3. **Avisos críticos embutidos no HTML**: "NAO ESTA ATENDENDO AMIL" fica escondido no markup
4. **Impossível de processar por IA/chatbots**: Requer parser JavaScript customizado
5. **Performance ruim**: Precisa parsear HTML para extrair dados

---

## ✅ Solução Implementada

### Novo Endpoint: `listar_agendas_json.php`

**Mesmos parâmetros de entrada:**
```
GET /listar_agendas_json.php?tipo=consulta&nome=Cardiologista&dia=Segunda&cidade=1
Authorization: Bearer <token>
```

**Novo retorno estruturado:**
```json
{
  "status": "sucesso",
  "total_agendas": 1,
  "filtros_aplicados": {
    "tipo": "consulta",
    "nome": "Cardiologista",
    "dia_semana": "Segunda",
    "cidade_id": "1"
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

---

## 📊 Comparação Detalhada

| Aspecto | HTML (Antigo) | JSON (Novo) |
|---------|---------------|-------------|
| **Formato** | HTML string dentro de JSON | JSON estruturado nativo |
| **Tamanho** | ~8KB por agenda (muito HTML) | ~2KB por agenda (apenas dados) |
| **Processamento** | Requer parser DOM/regex | Direto via `json_decode()` |
| **IDs numéricos** | Escondidos em atributos HTML | Campos dedicados (`id`, `medico.id`, etc) |
| **Horários** | Texto formatado (`"08:00 às 12:00"`) | Objetos estruturados (`{"periodo": "manha", "inicio": "08:00", "fim": "12:00"}`) |
| **Avisos** | Embutidos em tags `<p>` e `<strong>` | Objeto dedicado `avisos` com 3 campos |
| **Convênios** | Lista de badges HTML coloridos | Array de objetos `{id, nome}` |
| **Vagas** | Spans com classes Tailwind | Objeto simples `{"Segunda": 20, "Quarta": 15}` |
| **Encoding** | Pode ter problemas com UTF-8 | Garantido via `mb_convert_encoding()` |

---

## 🔄 Mapeamento de Campos

### Do HTML para JSON

| Dado no HTML | Localização JSON |
|--------------|------------------|
| `onclick="carregarAgendamento(178, 5)"` | `agenda.id = 178`, `agenda.especialidade.id = 5` |
| `<h3>Dr(a). LIDIANE – Clínico Geral</h3>` | `agenda.medico.nome`, `agenda.especialidade.nome` |
| `<span>Zona Norte</span>` | `agenda.localizacao.unidade_nome` |
| `<span>Sala 201</span>` | `agenda.localizacao.sala` |
| `<span>15 min</span>` | `agenda.configuracoes.tempo_estimado_minutos = 15` |
| `<strong>Segunda:</strong> 08:00 às 12:00` | `agenda.horarios_por_dia.Segunda[0] = {periodo: "manha", inicio: "08:00", fim: "12:00"}` |
| `<span class="bg-blue-100">Segunda: 20 vagas</span>` | `agenda.vagas_por_dia.Segunda = 20` |
| `<span class="text-xs">SUS</span>` | `agenda.convenios[0] = {id: 1, nome: "SUS"}` |
| `<p><strong>Obs:</strong> NAO ESTA ATENDENDO AMIL</p>` | `agenda.avisos.observacoes = "NAO ESTA ATENDENDO AMIL"` |

---

## 🛠️ Implementação Técnica

### Recursos Utilizados

1. **BLOB Reading**:
   - Campos `OBSERVACOES`, `INFORMACOES_FIXAS`, `ORIENTACOES` são BLOBs do Firebird
   - Função `lerBlob()` implementada para ler conteúdo em chunks de 4KB

2. **Encoding Consistency**:
   - Todos os campos convertidos de Windows-1252 → UTF-8
   - Uso de `mb_convert_encoding()` em todos os textos

3. **Queries Adicionais**:
   - Query principal: Busca dados da agenda
   - Query de convênios: `SELECT FROM AGENDA_CONVENIOS JOIN CONVENIOS`
   - Query de horários: `SELECT FROM AGENDA_HORARIOS`

4. **Lógica de Horários**:
   - Detecta funcionamento contínuo (manhã_inicio + tarde_fim, sem manhã_fim/tarde_inicio)
   - Separa horários em períodos: `"manha"`, `"tarde"`, `"continuo"`

---

## 📝 Parâmetros da API

### Obrigatórios

| Parâmetro | Tipo | Valores | Descrição |
|-----------|------|---------|-----------|
| `tipo` | string | `consulta`, `procedimento` | Tipo de agenda a buscar |
| `nome` | string | Nome da especialidade ou procedimento | Ex: "Cardiologista", "Ultrassonografia" |

### Opcionais

| Parâmetro | Tipo | Valores | Descrição |
|-----------|------|---------|-----------|
| `dia` | string | `Segunda`, `Terça`, `Quarta`, `Quinta`, `Sexta`, `Sábado`, `Domingo` | Filtrar por dia da semana |
| `cidade` | integer | ID da cidade/unidade | Ex: `1`, `2` |

### Headers

```
Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0
```

---

## 🧪 Testes

### Via Postman

Importar a collection: `Listar_Agendas_JSON.postman_collection.json`

**Requests incluídos:**
1. ✅ Consultas por especialidade (Cardiologista, Clínico Geral, Endocrinologista)
2. ✅ Procedimentos por tipo (Ultrassonografia, Ecocardiograma)
3. ✅ Filtro por dia da semana (Segunda, Quarta, Sexta)
4. ✅ Filtro por cidade/unidade
5. ✅ Filtros combinados
6. ❌ Casos de erro (sem tipo, especialidade inexistente, sem auth)

### Via PHP CLI

```bash
# Listar cardiologistas
QUERY_STRING="tipo=consulta&nome=Cardiologista" php listar_agendas_json.php

# Listar cardiologistas de segunda-feira
QUERY_STRING="tipo=consulta&nome=Cardiologista&dia=Segunda" php listar_agendas_json.php

# Listar ultrassonografias
QUERY_STRING="tipo=procedimento&nome=Ultrassonografia" php listar_agendas_json.php
```

---

## 🎯 Casos de Uso

### 1. Chatbot/IA

```javascript
// Antes (HTML): Impossível processar diretamente
const html = response.data[0].data;
// Precisa parsear: /<h3>(.*?)<\/h3>/g

// Depois (JSON): Acesso direto aos dados
const agenda = response.agendas[0];
console.log(`Médico: ${agenda.medico.nome}`);
console.log(`Especialidade: ${agenda.especialidade.nome}`);
console.log(`Aviso: ${agenda.avisos.observacoes}`);
```

### 2. Frontend React/Vue

```javascript
// Renderizar lista de agendas
agendas.map(agenda => (
  <AgendaCard
    key={agenda.id}
    medico={agenda.medico.nome}
    especialidade={agenda.especialidade.nome}
    horarios={agenda.horarios_por_dia}
    convenios={agenda.convenios}
    avisos={agenda.avisos}
  />
))
```

### 3. Integrações Externas

```php
// API de agendamento externo
$response = json_decode(file_get_contents($url), true);

foreach ($response['agendas'] as $agenda) {
    // Verificar se aceita convênio SUS
    $aceita_sus = array_filter($agenda['convenios'], fn($c) => $c['id'] === 1);

    // Verificar disponibilidade às segundas
    $segunda_disponivel = isset($agenda['horarios_por_dia']['Segunda']);

    // Ler avisos importantes
    if ($agenda['avisos']['observacoes']) {
        notificar_usuario($agenda['avisos']['observacoes']);
    }
}
```

---

## 📈 Benefícios

### Performance
- ✅ **Tamanho reduzido**: ~60% menos bytes transferidos
- ✅ **Parse mais rápido**: JSON nativo vs HTML parsing
- ✅ **Cache eficiente**: Dados estruturados são mais cache-friendly

### Manutenibilidade
- ✅ **Sem dependência de CSS**: Não precisa entender classes Tailwind
- ✅ **Versionamento fácil**: JSON schema pode ser versionado
- ✅ **Documentação clara**: Estrutura auto-documentada

### Integrações
- ✅ **IA/ML ready**: Pode ser usado diretamente por modelos de linguagem
- ✅ **Mobile friendly**: Apps nativos processam JSON nativamente
- ✅ **API-first**: Segue padrões REST modernos

---

## 🔐 Autenticação

Mesmo sistema Bearer Token do endpoint original:

```
Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0
```

Validação via `includes/auth_middleware.php`

---

## ⚠️ Avisos Importantes

### Encoding de Dias da Semana

Os dias da semana no banco estão em **Windows-1252**:
- ✅ Correto: `Terça` (com cedilha)
- ❌ Errado: `Terca` (sem cedilha)

O endpoint converte automaticamente para UTF-8.

### BLOBs Vazios

Campos BLOB (`OBSERVACOES`, `INFORMACOES_FIXAS`, `ORIENTACOES`) podem estar vazios.
Neste caso, retorna `null` no JSON.

### Horário Contínuo

Quando uma agenda funciona sem pausa entre manhã/tarde:
- Banco: `HORARIO_INICIO_MANHA = 07:00`, `HORARIO_FIM_TARDE = 17:00`, outros campos vazios
- JSON: `{"periodo": "continuo", "inicio": "07:00", "fim": "17:00"}`

---

## 📊 Status da Implementação

| Item | Status |
|------|--------|
| ✅ Endpoint criado | COMPLETO |
| ✅ Autenticação JWT | COMPLETO |
| ✅ Leitura de BLOBs | COMPLETO |
| ✅ Encoding UTF-8 | COMPLETO |
| ✅ Filtros (tipo, nome, dia, cidade) | COMPLETO |
| ✅ Collection Postman | COMPLETO |
| ✅ Documentação | COMPLETO |
| ⏳ Testes em produção | PENDENTE |
| ⏳ Migração de integrações existentes | PENDENTE |

---

## 🔄 Migração Gradual

### Fase 1: Coexistência ✅
- `listar_agendas.php` continua funcionando (HTML)
- `listar_agendas_json.php` disponível (JSON)
- Clientes podem escolher qual usar

### Fase 2: Transição
- Novas integrações usam apenas JSON
- Integrações antigas migram gradualmente

### Fase 3: Depreciação (Futuro)
- Marcar HTML endpoint como deprecated
- Remover após período de grace

---

**Data de Implementação**: 13/10/2025
**Arquivos Criados**:
- `listar_agendas_json.php` (330 linhas)
- `Listar_Agendas_JSON.postman_collection.json` (7 categorias, 15 requests)
- `CONVERSAO_LISTAR_AGENDAS_JSON.md` (este arquivo)

**Status**: ✅ IMPLEMENTADO - Aguardando testes em produção
