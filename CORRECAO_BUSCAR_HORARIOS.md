# 🔧 Correção: Endpoint Buscar Horários Disponíveis

## ❌ Problema Identificado

### Sintomas
- Array `horarios` retorna vazio `[]`
- `info_vagas` mostra vagas disponíveis > 0
- **IMPACTO CRÍTICO**: Impossível finalizar agendamentos sem horários específicos

### Causa Raiz
A tabela **`AGENDA_HORARIOS` está vazia** para TODAS as agendas testadas (1, 2, 30, 33, 48).

O endpoint `buscar_horarios.php` depende 100% desta tabela para:
1. Identificar quais dias da semana a agenda funciona
2. Definir horários de início/fim (manhã e tarde)
3. Calcular quantas vagas há por dia

## ✅ Solução Implementada

### 1. Mensagem de Erro Descritiva (HTTP 404)
Quando não há horários configurados:

```json
{
  "erro": true,
  "tipo": "horario_nao_configurado",
  "mensagem": "Esta agenda não possui horários configurados para Segunda",
  "agenda_id": "2",
  "dia_semana": "Segunda",
  "data_solicitada": "2025-09-15",
  "sugestao": "Entre em contato com a clínica ou tente outro dia da semana",
  "horarios": [],
  "info_vagas": {
    "limite_total": 0,
    "ocupadas": 0,
    "disponiveis": 0
  },
  "info_encaixes": {
    "limite_total": 0,
    "ocupados": 0,
    "disponiveis": 0
  }
}
```

### 2. Modo Debug Adicionado
Quando há configuração mas array vazio:

```json
{
  "horarios": [],
  "info_vagas": {...},
  "debug": {
    "agenda_id": 2,
    "data": "2025-09-15",
    "dia_semana": "Segunda",
    "tempo_estimado_minutos": 30,
    "horario_funcionamento": {
      "manha_inicio": null,
      "manha_fim": null,
      "tarde_inicio": null,
      "tarde_fim": null,
      "funcionamento_continuo": false
    },
    "total_horarios_gerados": 0,
    "horarios_ocupados_count": 0,
    "bloqueios_horario_count": 0
  },
  "aviso": "Horários de funcionamento não configurados. Verifique os campos HORARIO_INICIO_MANHA, HORARIO_FIM_MANHA, HORARIO_INICIO_TARDE, HORARIO_FIM_TARDE na tabela AGENDA_HORARIOS"
}
```

## 📋 Como Resolver Definitivamente

### Opção 1: Popular a Tabela AGENDA_HORARIOS (Recomendado)

Para cada agenda, inserir registros com horários de funcionamento:

```sql
-- Exemplo: Agenda ID 1 - Cardiologia
INSERT INTO AGENDA_HORARIOS (
    AGENDA_ID,
    DIA_SEMANA,
    HORARIO_INICIO_MANHA,
    HORARIO_FIM_MANHA,
    HORARIO_INICIO_TARDE,
    HORARIO_FIM_TARDE,
    VAGAS_DIA
) VALUES (
    1,
    'Segunda',
    '08:00:00',
    '12:00:00',
    '14:00:00',
    '18:00:00',
    20
);

-- Repetir para: Terça, Quarta, Quinta, Sexta, Sábado
```

#### Script Automatizado

```php
<?php
// popular_agenda_horarios.php
include 'includes/connection.php';

$agenda_id = 1; // Alterar conforme necessário

$dias_semana = [
    'Segunda' => 20,
    'Terça' => 15,
    'Quarta' => 25,
    'Quinta' => 20,
    'Sexta' => 18,
    'Sábado' => 10
];

foreach ($dias_semana as $dia => $vagas) {
    $sql = "INSERT INTO AGENDA_HORARIOS (
        AGENDA_ID, DIA_SEMANA,
        HORARIO_INICIO_MANHA, HORARIO_FIM_MANHA,
        HORARIO_INICIO_TARDE, HORARIO_FIM_TARDE,
        VAGAS_DIA
    ) VALUES (?, ?, '08:00:00', '12:00:00', '14:00:00', '18:00:00', ?)";

    $stmt = ibase_prepare($conn, $sql);
    ibase_execute($stmt, $agenda_id, $dia, $vagas);
}

echo "Horários configurados com sucesso!";
?>
```

### Opção 2: Interface Admin para Configuração

Usar o arquivo existente: `form_editar_agenda.php` (linhas 111-123)

```php
// Busca os horários da agenda
$sqlDias = "SELECT * FROM AGENDA_HORARIOS WHERE AGENDA_ID = $id";
$resDias = ibase_query($conn, $sqlDias);

$horariosPorDia = [];
while ($r = ibase_fetch_assoc($resDias)) {
    $dia = trim(mb_convert_encoding($r['DIA_SEMANA'], 'UTF-8', 'WINDOWS-1252'));
    $horariosPorDia[$dia] = $r;
}
```

## 🧪 Testes Realizados

```bash
# Verificar agendas sem horários
$ QUERY_STRING="agenda_id=1" php verificar_agenda_horarios.php
{
  "agenda_id": "1",
  "total_registros": 0,
  "horarios_configurados": []
}

# Buscar horários (agora retorna erro descritivo)
$ QUERY_STRING="agenda_id=1&data=2025-09-08" php buscar_horarios.php
{
  "erro": true,
  "tipo": "horario_nao_configurado",
  "mensagem": "Esta agenda não possui horários configurados para Segunda",
  ...
}
```

## 📊 Estrutura da Tabela AGENDA_HORARIOS

| Campo | Tipo | Descrição |
|-------|------|-----------|
| AGENDA_ID | INTEGER | FK para tabela AGENDAS |
| DIA_SEMANA | VARCHAR | Segunda, Terça, Quarta, etc |
| HORARIO_INICIO_MANHA | TIME | Ex: 08:00:00 |
| HORARIO_FIM_MANHA | TIME | Ex: 12:00:00 |
| HORARIO_INICIO_TARDE | TIME | Ex: 14:00:00 |
| HORARIO_FIM_TARDE | TIME | Ex: 18:00:00 |
| VAGAS_DIA | INTEGER | Limite de atendimentos no dia |

## 🎯 Próximos Passos

1. ✅ Identificar todas as agendas ativas
2. ⏳ Popular AGENDA_HORARIOS para cada agenda
3. ⏳ Testar endpoint com horários reais
4. ⏳ Validar fluxo completo de agendamento

## 📝 Observações Importantes

- **Dias da Semana**: Usar encoding Windows-1252 (Terça com ç)
- **Formato de Hora**: TIME `HH:MM:SS`
- **Vagas por Dia**: Independente do limite global em AGENDAS
- **Funcionamento Contínuo**: Deixar campos *_FIM_MANHA e *_INICIO_TARDE vazios

---

**Data da Correção**: 13/10/2025
**Arquivos Modificados**:
- `buscar_horarios.php` (linhas 107-131, 402-438)

**Status**: ✅ Correção aplicada - Aguardando população de dados
