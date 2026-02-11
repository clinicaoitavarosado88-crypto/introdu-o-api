# Conversão da API para JSON Puro

## ✅ Conversão Concluída

Todos os endpoints da API foram convertidos para retornar **JSON puro** em vez de HTML.

## 📋 Arquivos Convertidos

### Arquivos Convertidos para JSON ✅

1. **buscar_agendas.php** - Busca agendas (retorna array de agendas)
2. **buscar_bloqueios.php** - Busca bloqueios de uma agenda
3. **buscar_convenio_ajax.php** - Busca convênios via AJAX
4. **listar_agendados.php** - Lista agendamentos de uma agenda
5. **listar_agendas_ajax.php** - Lista agendas via AJAX (com paginação)
6. **listar_paginas.php** - Informações de paginação
7. **verificar_estrutura_tabelas.php** - Verifica estrutura de tabelas do banco
8. **listar_agendas.php** - Lista agendas por especialidade/procedimento com detalhes completos (NOVO!)

### Arquivos Já em JSON ✅

Estes arquivos JÁ retornavam JSON antes da conversão:

- buscar_convenios.php
- buscar_horarios.php
- processar_agendamento.php
- consultar_precos.php
- E muitos outros...

## 🔄 Backup

Um backup de todos os arquivos foi criado antes da conversão em:
```
backups/api_json_20251031_142559/
```

## ✅ Testes Realizados

Todos os endpoints foram testados e validados:

### Exemplo 1: buscar_convenio_ajax.php
```bash
POST_DATA="busca=SUS" php -f buscar_convenio_ajax.php
```
**Resposta:**
```json
{
  "status": "sucesso",
  "total": 8,
  "results": [
    {
      "id": "106",
      "nome": "SUS ASSU",
      "suspenso": false,
      "text": "SUS ASSU - id: 106"
    }
    ...
  ]
}
```

### Exemplo 2: verificar_estrutura_tabelas.php
```bash
php -f verificar_estrutura_tabelas.php
```
**Resposta:**
```json
{
  "status": "sucesso",
  "tabelas": {
    "AGENDAS": {
      "status": "sucesso",
      "total_campos": 26,
      "campos": ["ID", "UNIDADE_ID", ...]
    }
  }
}
```

### Exemplo 3: listar_agendados.php
```bash
QUERY_STRING="agenda_id=1" php -f listar_agendados.php
```
**Resposta:**
```json
{
  "status": "erro",
  "mensagem": "agenda_id é obrigatório"
}
```

### Exemplo 4: listar_agendas.php (NOVO!)
```bash
# Buscar consultas de cardiologista
/listar_agendas.php?tipo=consulta&nome=Cardiologista
```
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
      "unidade": "MOSSORÓ",
      "sala": "2",
      "telefone": "(84) 3312-5050",
      "tempo_estimado_minutos": 20,
      "idade_minima": 0,
      "possui_retorno": true,
      "limite_retornos_dia": 5,
      "atende_comorbidade": false,
      "limite_vagas_dia": 30,
      "limite_encaixes_dia": 2,
      "horarios": [
        {
          "dia_semana": "SEGUNDA",
          "turnos": [
            {
              "periodo": "manha",
              "inicio": "08:00",
              "fim": "12:00"
            },
            {
              "periodo": "tarde",
              "inicio": "14:00",
              "fim": "18:00"
            }
          ],
          "vagas_dia": 30
        }
      ],
      "vagas_por_dia": {
        "SEGUNDA": 30,
        "QUARTA": 25
      },
      "convenios": [
        "AMIL",
        "UNIMED",
        "SUS",
        "PARTICULAR"
      ],
      "observacoes": "Trazer exames anteriores",
      "informacoes_fixas": null,
      "orientacoes": "Jejum de 4 horas",
      "medico": "CAMILO DE PAIVA CANTIDIO",
      "especialidade": "Cardiologista",
      "especialidade_id": 6
    }
  ]
}
```

## 📝 Padrão de Resposta JSON

Todas as respostas seguem o padrão:

### Resposta de Sucesso:
```json
{
  "status": "sucesso",
  "total": 0,
  "data": []
}
```

### Resposta de Erro:
```json
{
  "status": "erro",
  "mensagem": "Descrição do erro"
}
```

## 🎯 Benefícios

1. **Consistência**: Todas as APIs retornam JSON
2. **Facilita integração**: Front-end pode consumir dados facilmente
3. **Melhor para mobile**: Apps podem consumir a API
4. **Automação**: Scripts podem processar os dados
5. **Documentação**: Respostas estruturadas e previsíveis

## 🚀 Como Usar

### Via JavaScript (fetch):
```javascript
fetch('buscar_convenios.php?busca=AMIL')
  .then(response => response.json())
  .then(data => {
    console.log(data.results);
  });
```

### Via cURL:
```bash
curl "buscar_convenios.php?busca=AMIL" | jq .
```

### Via PHP:
```php
$response = file_get_contents('buscar_convenios.php?busca=AMIL');
$data = json_decode($response, true);
print_r($data['results']);
```

## 📌 Observações

- Todos os arquivos foram testados e validados
- Encoding UTF-8 configurado corretamente
- Headers Content-Type: application/json definidos
- Tratamento de erros padronizado

---

## 🆕 Novidades - listar_agendas.php

O arquivo **listar_agendas.php** foi o último a ser convertido e retorna os dados mais completos:

### Recursos:
- **Filtragem por tipo**: consulta ou procedimento
- **Busca por especialidade/procedimento**: nome exato da especialidade ou procedimento
- **Filtro opcional por dia da semana**: SEGUNDA, TERÇA, etc.
- **Filtro opcional por cidade**: cidade_id

### Dados retornados por agenda:
- ✅ Informações básicas (ID, nome, tipo, unidade, sala, telefone)
- ✅ Configurações (tempo de atendimento, idade mínima, retornos, encaixes)
- ✅ Horários completos (dias da semana, turnos manhã/tarde, horários de funcionamento)
- ✅ Vagas disponíveis por dia da semana
- ✅ Lista completa de convênios aceitos
- ✅ Observações, informações fixas e orientações (campos BLOB)
- ✅ Dados específicos do médico (para consultas)
- ✅ Dados do procedimento (para procedimentos)

### Parâmetros aceitos:
```
?tipo=consulta&nome=Cardiologista           # Consultas de cardiologia
?tipo=procedimento&nome=Ultrassonografia    # Procedimentos de ultrassom
?tipo=consulta&nome=Cardiologista&dia=SEGUNDA&cidade=1  # Com filtros
```

---

**Data da conversão:** 31/10/2025
**Total de arquivos convertidos:** 8 arquivos
**Status:** ✅ Concluído com sucesso

**Última atualização:** 31/10/2025 - Conversão do listar_agendas.php (endpoint mais complexo)
