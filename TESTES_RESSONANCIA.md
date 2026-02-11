# 🧪 Testes do Sistema de Ressonância

## 📋 Checklist de Testes:

### ✅ **1. Campos Criados no Banco**
```bash
# Verificar se campos existem
php -r "
include 'includes/connection.php';
\$sql = 'SELECT TEM_MEDICO, ACEITA_ANESTESIA, LIMITE_ANESTESIAS FROM AGENDA_HORARIOS WHERE AGENDA_ID = 30';
\$res = ibase_query(\$conn, \$sql);
if (\$res) echo '✅ Campos criados com sucesso!\n';
else echo '❌ Erro: Campos não encontrados\n';
"
```

### ✅ **2. Buscar Horários Sem Filtro** (deve funcionar normal)
```bash
# Teste: Segunda-feira sem especificar exame
QUERY_STRING="agenda_id=30&data=2026-01-19" \
  php buscar_horarios_ressonancia.php | head -50

# Resultado esperado: JSON com horários disponíveis
```

### ✅ **3. Buscar Horários em Quinta** (dia com anestesia)
```bash
# Teste: Quinta-feira deve mostrar info de anestesia
QUERY_STRING="agenda_id=30&data=2026-01-22" \
  php buscar_horarios_ressonancia.php 2>/dev/null | grep -o '"aceita_anestesia":[^,]*'

# Resultado esperado: "aceita_anestesia":true
```

### ✅ **4. Configurar Médico** (testar contraste)
```bash
# Marcar que Segunda tem médico
php -r "
include 'includes/connection.php';
\$sql = \"UPDATE AGENDA_HORARIOS SET TEM_MEDICO = 'S' WHERE AGENDA_ID = 30 AND TRIM(DIA_SEMANA) = 'Segunda'\";
ibase_query(\$conn, \$sql);
ibase_commit(\$conn);
echo '✅ Segunda-feira agora tem médico (aceita contraste)\n';
"

# Verificar
QUERY_STRING="agenda_id=30&data=2026-01-19" \
  php buscar_horarios_ressonancia.php 2>/dev/null | grep -o '"tem_medico":[^,]*'

# Resultado esperado: "tem_medico":true
```

### ✅ **5. Marcar Exame que Precisa de Anestesia**
```bash
# Exemplo: Marcar exame ID 638 (ANGIORESSONANCIA CRANIO) como precisando de anestesia
php -r "
include 'includes/connection.php';
\$sql = \"UPDATE LAB_EXAMES SET PRECISA_ANESTESIA = 'S' WHERE IDEXAME = 638\";
ibase_query(\$conn, \$sql);
ibase_commit(\$conn);
echo '✅ Exame 638 marcado como precisando de anestesia\n';
"
```

### ✅ **6. Testar Bloqueio de Anestesia em Dia Errado**
```bash
# Tentar agendar exame com anestesia na SEGUNDA (não aceita)
QUERY_STRING="agenda_id=30&data=2026-01-19&exame_id=638" \
  php buscar_horarios_ressonancia.php 2>/dev/null | grep -o '"tipo":"[^"]*"'

# Resultado esperado: "tipo":"anestesia_indisponivel"
```

### ✅ **7. Testar Anestesia em Quinta** (deve funcionar)
```bash
# Agendar exame com anestesia na QUINTA (aceita)
QUERY_STRING="agenda_id=30&data=2026-01-22&exame_id=638" \
  php buscar_horarios_ressonancia.php 2>/dev/null | grep -o '"anestesias_disponiveis":[^,]*'

# Resultado esperado: "anestesias_disponiveis":2
```

### ✅ **8. Ver Configuração Completa**
```bash
php -r "
include 'includes/connection.php';
echo \"=== CONFIGURAÇÃO ATUAL ===\n\n\";

\$sql = \"SELECT TRIM(DIA_SEMANA) as DIA, TEM_MEDICO, ACEITA_ANESTESIA, LIMITE_ANESTESIAS
        FROM AGENDA_HORARIOS
        WHERE AGENDA_ID = 30
        ORDER BY DIA_SEMANA\";

\$res = ibase_query(\$conn, \$sql);
echo str_pad('Dia', 12) . str_pad('Médico', 10) . str_pad('Anestesia', 12) . \"Limite\n\";
echo str_repeat('-', 50) . \"\n\";

while (\$row = ibase_fetch_assoc(\$res)) {
    \$dia = mb_convert_encoding(\$row['DIA'], 'UTF-8', 'Windows-1252');
    echo str_pad(\$dia, 12) .
         str_pad(\$row['TEM_MEDICO'] ?: 'N', 10) .
         str_pad(\$row['ACEITA_ANESTESIA'] ?: 'N', 12) .
         (\$row['LIMITE_ANESTESIAS'] ?: '0') . \"\n\";
}
"
```

---

## 🎯 Testes de Integração Frontend:

### **Exemplo JavaScript:**
```javascript
// 1. Buscar horários com exame específico
async function buscarHorariosRessonancia(agendaId, data, exameId) {
    const url = `/agenda/buscar_horarios_ressonancia.php?agenda_id=${agendaId}&data=${data}&exame_id=${exameId}`;

    try {
        const response = await fetch(url);
        const data = await response.json();

        if (data.erro) {
            // Exibir mensagem de erro
            mostrarAlerta(data.mensagem, data.sugestao);
            return null;
        }

        // Sucesso - renderizar horários
        return data;
    } catch (error) {
        console.error('Erro ao buscar horários:', error);
        return null;
    }
}

// 2. Renderizar com indicadores
function renderizarHorarios(data) {
    const container = document.getElementById('horarios');

    // Mostrar indicadores no topo
    let html = '<div class="info-horario">';

    if (data.info_horario.tem_medico) {
        html += '<span class="badge bg-success">🩺 Médico Presente - Aceita Contraste</span>';
    }

    if (data.info_horario.aceita_anestesia) {
        const disponiveis = data.info_horario.anestesias_disponiveis;
        html += `<span class="badge bg-warning">💉 Aceita Anestesia (${disponiveis} disponíveis)</span>`;
    }

    html += '</div>';

    // Renderizar horários
    html += '<div class="horarios-grid">';
    data.horarios.forEach(h => {
        if (h.disponivel) {
            html += `<button class="horario-btn" onclick="agendarHorario('${h.hora}')">
                        ${h.hora}
                    </button>`;
        }
    });
    html += '</div>';

    container.innerHTML = html;
}

// 3. Uso
buscarHorariosRessonancia(30, '2026-01-22', 638)
    .then(data => {
        if (data) renderizarHorarios(data);
    });
```

---

## 📊 Exemplos de Configuração:

### **Cenário 1: Médico só pela TARDE**
```sql
UPDATE AGENDA_HORARIOS
SET TEM_MEDICO = 'S'
WHERE AGENDA_ID = 30
  AND HORARIO_INICIO_TARDE IS NOT NULL;
```

### **Cenário 2: Anestesia em 2 dias (Terça e Quinta)**
```sql
UPDATE AGENDA_HORARIOS
SET ACEITA_ANESTESIA = 'S',
    LIMITE_ANESTESIAS = 2
WHERE AGENDA_ID = 30
  AND TRIM(DIA_SEMANA) IN ('Terça', 'Quinta');
```

### **Cenário 3: Todos os exames de ressonância com tempo específico**
```sql
-- Ver exames sem tempo definido
SELECT IDEXAME, EXAME, TEMPO_EXAME
FROM LAB_EXAMES
WHERE UPPER(EXAME) LIKE '%RESSON%'
  AND (TEMPO_EXAME IS NULL OR TEMPO_EXAME = 0);

-- Definir tempo padrão de 45 minutos
UPDATE LAB_EXAMES
SET TEMPO_EXAME = 45
WHERE UPPER(EXAME) LIKE '%RESSON%'
  AND (TEMPO_EXAME IS NULL OR TEMPO_EXAME = 0);
```

---

## ✅ Resultado Final Esperado:

```
✅ Campos criados no banco
✅ Quinta-feira aceita anestesia (limite: 2)
✅ API filtra por requisitos do exame
✅ Bloqueia agendamentos inválidos
✅ Usa tempo do exame para calcular horários
✅ Retorna informações detalhadas
```

---

**Todos os testes devem passar! Se algum falhar, verifique:**
1. Campos foram criados corretamente
2. Configuração está aplicada (TEM_MEDICO, ACEITA_ANESTESIA)
3. Exames estão marcados corretamente (USA_CONTRASTE, PRECISA_ANESTESIA, TEMPO_EXAME)
