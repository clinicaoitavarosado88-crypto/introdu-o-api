# Teste de Consulta de Exames via Agente IA

## 🎯 Objetivo
Demonstrar como o agente de IA responde quando o paciente solicita informações sobre exames/procedimentos.

---

## 📋 Cenários de Teste

### Cenário 1: Paciente pergunta sobre Ressonância Magnética

**Mensagem do Paciente:**
```
"Quero fazer uma ressonância magnética"
```

**Fluxo Interno do Agente:**

1. **Detecção de Intenção (agente-ia.js:103)**
   - Detecta palavra-chave: "ressonancia"
   - Mapeia para nome formal: "Ressonância Magnética"
   - ✅ Intenção: `CONSULTAR EXAMES/PROCEDIMENTOS`

2. **Chamada à API (agente-ia.js:126)**
   ```javascript
   apiAgenda.listarAgendasJSON('procedimento', 'Ressonância Magnética')
   ```

3. **Requisição HTTP Real:**
   ```http
   GET http://sistema.clinicaoitavarosado.com.br/oitava/agenda/listar_agendas_json.php?tipo=procedimento&nome=Ressonância Magnética
   Authorization: Bearer OWY2NGE0YTQtNGQ0MS00ZjVkLWI3ZTUtOGY2ZDZhNGE0YTQ0
   ```

4. **Resposta Esperada da API:**
   ```json
   {
     "status": "sucesso",
     "tipo": "procedimento",
     "total_agendas": 2,
     "agendas": [
       {
         "id": 30,
         "tipo": "procedimento",
         "procedimento": {
           "id": 34,
           "nome": "Ressonância Magnética"
         },
         "medico": {
           "id": 2780,
           "nome": "DR. JOÃO SILVA"
         },
         "localizacao": {
           "unidade_id": 1,
           "unidade_nome": "MOSSORÓ - RN",
           "sala": "RM-1",
           "telefone": "(84) 3315-6900"
         },
         "convenios": [
           {"id": 1, "nome": "SUS"},
           {"id": 24, "nome": "AMIL"},
           {"id": 962, "nome": "PARTICULAR"}
         ],
         "horarios_por_dia": {
           "Segunda": [{"periodo": "manha", "inicio": "08:00", "fim": "12:00"}],
           "Quarta": [{"periodo": "tarde", "inicio": "14:00", "fim": "18:00"}]
         }
       },
       {
         "id": 45,
         "tipo": "procedimento",
         "procedimento": {
           "nome": "Ressonância Magnética"
         },
         "localizacao": {
           "unidade_nome": "PARNAMIRIM - RN",
           "telefone": "(84) 3315-8800"
         }
       }
     ]
   }
   ```

5. **Processamento do Agente (agente-ia.js:133-146)**
   - Extrai dados estruturados de cada agenda
   - Cria array `procedimentosDisponiveis` com informações organizadas

6. **Contexto Enviado ao Agente IA:**
   ```
   Procedimentos/Exames REAIS disponíveis para "Ressonância Magnética":

   [
     {
       "id": 30,
       "procedimento": "Ressonância Magnética",
       "medico": "DR. JOÃO SILVA",
       "unidade": "MOSSORÓ - RN",
       "telefone": "(84) 3315-6900",
       "convenios": [{"id": 1, "nome": "SUS"}, ...],
       "horarios_disponiveis": {...}
     },
     {
       "id": 45,
       "procedimento": "Ressonância Magnética",
       "unidade": "PARNAMIRIM - RN",
       "telefone": "(84) 3315-8800"
     }
   ]

   **IMPORTANTE: Use EXATAMENTE estas informações. NÃO INVENTE!**
   ```

7. **Resposta Final ao Paciente (gerada pela IA):**
   ```
   Ressonância Magnética disponível em:

   • Mossoró - RN
     Telefone: (84) 3315-6900
     Convênios: SUS, Amil, Particular

   • Parnamirim - RN
     Telefone: (84) 3315-8800

   Qual cidade prefere?
   ```

---

### Cenário 2: Paciente pergunta sobre Ultrassom

**Mensagem do Paciente:**
```
"Preciso fazer um ultrassom"
```

**Fluxo:**
1. Detecta: "ultrassom"
2. Mapeia para: "Ultrassonografia"
3. Chama API: `listarAgendasJSON('procedimento', 'Ultrassonografia')`
4. Recebe dados reais das unidades que fazem ultrassom
5. Responde com informações estruturadas

---

### Cenário 3: Procedimento NÃO encontrado

**Mensagem do Paciente:**
```
"Vocês fazem cirurgia de apendicite?"
```

**Fluxo:**
1. Detecta palavra não mapeada
2. Tenta buscar como termo genérico
3. API retorna: `{"status": "erro", "total_agendas": 0}`
4. Agente retorna contexto: `procedimento_nao_encontrado`
5. **Resposta ao paciente:**
   ```
   Para verificar disponibilidade de procedimentos cirúrgicos, favor ligar: (84) 3315-6900
   ```

---

## ✅ Procedimentos Suportados (Mapeamento)

| Termo do Paciente | Nome na API | Status |
|-------------------|-------------|--------|
| ressonância, ressonancia | Ressonância Magnética | ✅ |
| ultrassom, ultrasom | Ultrassonografia | ✅ |
| raio-x, raio x | Raio-X | ✅ |
| tomografia | Tomografia | ✅ |
| eletrocardiograma, eletro | Eletrocardiograma | ✅ |
| ecocardiograma | Ecocardiograma | ✅ |
| mamografia | Mamografia | ✅ |
| endoscopia | Endoscopia | ✅ |
| colonoscopia | Colonoscopia | ✅ |
| doppler | Doppler | ✅ |
| holter | Holter | ✅ |
| densitometria | Densitometria | ✅ |

---

## 🔒 Garantias do Sistema

### ✅ O que o agente SEMPRE faz:
1. **Consulta dados REAIS** da API antes de responder
2. **Lista APENAS unidades** que realmente oferecem o procedimento
3. **Mostra telefones reais** das unidades
4. **Exibe convênios aceitos** conforme cadastro
5. **Não inventa** datas, horários ou valores

### ❌ O que o agente NUNCA faz:
1. Inventar locais ou unidades
2. Criar informações sobre médicos
3. Sugerir datas sem consultar horários reais
4. Inventar preços ou valores
5. Dar orientações médicas ou diagnósticos

---

## 🧪 Como Testar no Servidor

### Pré-requisitos:
```bash
ssh root@138.197.29.54
cd /opt/whatsapp-web-js
```

### Verificar se o agente está rodando:
```bash
pm2 status
```

### Ver logs em tempo real:
```bash
pm2 logs whatsapp-bot --lines 50
```

### Testar via WhatsApp:
1. Envie mensagem para o número da clínica
2. Digite: "Quero fazer uma ressonância"
3. Observe os logs para ver:
   - ✅ Intenção detectada: CONSULTAR EXAMES/PROCEDIMENTOS
   - 🔬 Procedimento identificado: Ressonância Magnética
   - 📊 Dados obtidos: X agendas para Ressonância Magnética

---

## 📊 Métricas de Sucesso

- **Taxa de detecção de intenção**: >95% para termos mapeados
- **Precisão de dados**: 100% (sempre consulta API)
- **Tempo de resposta**: <5 segundos (incluindo chamada API)
- **Taxa de fallback**: <10% (quando procedimento não existe)

---

## 🔄 Fluxo Completo Resumido

```
Paciente: "Quero ressonância"
    ↓
Agente detecta intenção: EXAME
    ↓
Mapeia: "ressonância" → "Ressonância Magnética"
    ↓
Chama API: GET /listar_agendas_json.php?tipo=procedimento&nome=Ressonância Magnética
    ↓
Recebe dados: [Mossoró, Parnamirim, ...]
    ↓
Envia contexto para IA do Digital Ocean
    ↓
IA gera resposta formatada com dados REAIS
    ↓
Paciente recebe: Lista de unidades + telefones
```

---

## 📝 Observações Importantes

1. **A API sempre retorna UTF-8 corrigido** - caracteres especiais funcionam corretamente
2. **Timeout configurado para 30 segundos** - suficiente para queries complexas
3. **Histórico de conversa mantido** - contexto preservado entre mensagens
4. **Token válido por 1 ano** - não expira durante testes

---

## 🆘 Troubleshooting

### Problema: "Nenhuma agenda encontrada"
**Causa:** Nome do procedimento não cadastrado no banco
**Solução:** Verificar nome exato no banco via query SQL

### Problema: API timeout
**Causa:** Banco Firebird lento ou muitos resultados
**Solução:** Aumentar timeout em `api-agenda-completa.js:6`

### Problema: Caracteres corrompidos
**Causa:** Encoding do Firebird (Windows-1252)
**Solução:** Já resolvido com função `corrigirCaracteres()` na API

---

**Data:** 13/11/2025
**Versão do Agente:** 2.0
**Versão da API:** 2.5
**Status:** ✅ Testado e Funcionando
