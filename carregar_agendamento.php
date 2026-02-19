<?php
// carregar_agendamento.php - VERSÃO SEM BOTÃO INTERMEDIÁRIO
include 'includes/connection.php';

$agenda_id = $_GET['agenda_id'] ?? 0;
$especialidade_id = $_GET['especialidade_id'] ?? null;

if (!$agenda_id) {
    echo '<div class="text-red-600 text-center p-8">ID da agenda não fornecido.</div>';
    exit;
}

// Busca informações da agenda
if ($especialidade_id) {
    // Se uma especialidade específica foi selecionada, buscar apenas essa especialidade
    $query = "SELECT a.*, u.NOME_UNIDADE as UNIDADE_NOME, m.NOME as MEDICO_NOME, e.NOME as ESPECIALIDADE_NOME,
                     pr.NOME as PROCEDIMENTO_NOME,
                     COALESCE(a.IDADE_MINIMA_ATENDIMENTO, me.IDADE_MINIMA) AS IDADE_MINIMA,
                     a.LIMITE_VAGAS_DIA, a.LIMITE_RETORNOS_DIA, a.LIMITE_ENCAIXES_DIA
              FROM AGENDAS a
              LEFT JOIN LAB_CIDADES u ON a.UNIDADE_ID = u.ID
              LEFT JOIN LAB_MEDICOS_PRES m ON a.MEDICO_ID = m.ID
              LEFT JOIN LAB_MEDICOS_ESPECIALIDADES me ON me.MEDICO_ID = m.ID AND me.ESPECIALIDADE_ID = ?
              LEFT JOIN ESPECIALIDADES e ON e.ID = ?
              LEFT JOIN GRUPO_EXAMES pr ON a.PROCEDIMENTO_ID = pr.ID
              WHERE a.ID = ?";
    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt, $especialidade_id, $especialidade_id, $agenda_id);
} else {
    // Busca padrão sem especialidade específica
    $query = "SELECT a.*, u.NOME_UNIDADE as UNIDADE_NOME, m.NOME as MEDICO_NOME, e.NOME as ESPECIALIDADE_NOME,
                     pr.NOME as PROCEDIMENTO_NOME,
                     COALESCE(a.IDADE_MINIMA_ATENDIMENTO, me.IDADE_MINIMA) AS IDADE_MINIMA,
                     a.LIMITE_VAGAS_DIA, a.LIMITE_RETORNOS_DIA, a.LIMITE_ENCAIXES_DIA
              FROM AGENDAS a
              LEFT JOIN LAB_CIDADES u ON a.UNIDADE_ID = u.ID
              LEFT JOIN LAB_MEDICOS_PRES m ON a.MEDICO_ID = m.ID
              LEFT JOIN LAB_MEDICOS_ESPECIALIDADES me ON me.MEDICO_ID = m.ID
              LEFT JOIN ESPECIALIDADES e ON e.ID = me.ESPECIALIDADE_ID
              LEFT JOIN GRUPO_EXAMES pr ON a.PROCEDIMENTO_ID = pr.ID
              WHERE a.ID = ?";
    $stmt = ibase_prepare($conn, $query);
    $result = ibase_execute($stmt, $agenda_id);
}

$agenda = ibase_fetch_assoc($result);

if (!$agenda) {
    echo '<div class="text-red-600 text-center p-8">Agenda não encontrada.</div>';
    exit;
}

// Função para ler BLOBs
function lerBlob($conn, $blobId) {
    if (!is_string($blobId) || empty($blobId)) return '';
    try {
        $blob = ibase_blob_open($conn, $blobId);
        $conteudo = '';
        while ($segmento = ibase_blob_get($blob, 4096)) {
            $conteudo .= $segmento;
        }
        ibase_blob_close($blob);
        return trim($conteudo);
    } catch (Exception $e) {
        return '';
    }
}

// Lê as observações
$observacoes = utf8_encode(lerBlob($conn, $agenda['OBSERVACOES'] ?? ''));
$informacoes_fixas = utf8_encode(lerBlob($conn, $agenda['INFORMACOES_FIXAS'] ?? ''));
$orientacoes = utf8_encode(lerBlob($conn, $agenda['ORIENTACOES'] ?? ''));

// Carregar preparos da agenda
$preparos = [];
$sqlPreparos = "SELECT p.TITULO, p.INSTRUCOES, p.ORDEM, p.ID as PREPARO_ID 
                FROM AGENDA_PREPAROS p 
                WHERE p.AGENDA_ID = ? 
                ORDER BY p.ORDEM";
$stmtPreparos = ibase_prepare($conn, $sqlPreparos);
$resPreparos = ibase_execute($stmtPreparos, $agenda_id);

while ($prep = ibase_fetch_assoc($resPreparos)) {
    // Sanitizar strings para exibição
    $titulo = trim($prep['TITULO']);
    $instrucoes = lerBlob($conn, $prep['INSTRUCOES']);
    $preparoId = $prep['PREPARO_ID'];
    
    // Garantir UTF-8 válido
    if (!mb_check_encoding($titulo, 'UTF-8')) {
        $titulo = mb_convert_encoding($titulo, 'UTF-8', 'ISO-8859-1');
    }
    if (!mb_check_encoding($instrucoes, 'UTF-8')) {
        $instrucoes = mb_convert_encoding($instrucoes, 'UTF-8', 'ISO-8859-1');
    }
    
    // Remover caracteres de controle que podem causar problemas
    $titulo = preg_replace('/[\x00-\x1F\x7F]/', '', $titulo);
    $instrucoes = preg_replace('/[\x00-\x1F\x7F]/', '', $instrucoes);
    
    // Carregar anexos do preparo
    $anexos = [];
    if ($preparoId) {
        $sqlAnexos = "SELECT ID, NOME_ORIGINAL, TIPO_ARQUIVO, TAMANHO_ARQUIVO 
                      FROM AGENDA_PREPAROS_ANEXOS 
                      WHERE AGENDA_ID = ? AND PREPARO_ID = ?
                      ORDER BY NOME_ORIGINAL";
        $stmtAnexos = ibase_prepare($conn, $sqlAnexos);
        $resAnexos = ibase_execute($stmtAnexos, $agenda_id, $preparoId);
        
        while ($anexo = ibase_fetch_assoc($resAnexos)) {
            $anexos[] = [
                'id' => $anexo['ID'],
                'nome' => $anexo['NOME_ORIGINAL'],
                'tipo' => $anexo['TIPO_ARQUIVO'],
                'tamanho' => $anexo['TAMANHO_ARQUIVO']
            ];
        }
    }
    
    if (!empty($titulo)) {
        $preparos[] = [
            'titulo' => $titulo,
            'instrucoes' => $instrucoes,
            'anexos' => $anexos
        ];
    }
}

// Determina o tipo de agenda
$tipo_agenda = !empty($agenda['ESPECIALIDADE_ID']) ? 'consulta' : 'procedimento';

// Nome dinâmico baseado no tipo de agenda
if (isset($agenda['MEDICO_NOME']) && !empty($agenda['MEDICO_NOME'])) {
    $medico = mb_convert_encoding($agenda['MEDICO_NOME'], 'UTF-8', 'Windows-1252');
    $nome_servico = "Dr(a). " . htmlspecialchars($medico);
    
    // Adicionar especialidade se for consulta
    if (isset($agenda['ESPECIALIDADE_NOME']) && !empty($agenda['ESPECIALIDADE_NOME'])) {
        $especialidade = mb_convert_encoding($agenda['ESPECIALIDADE_NOME'], 'UTF-8', 'Windows-1252');
        $nome_servico .= " – " . htmlspecialchars($especialidade);
    }
} elseif (isset($agenda['PROCEDIMENTO_NOME']) && !empty($agenda['PROCEDIMENTO_NOME'])) {
    $procedimento = mb_convert_encoding($agenda['PROCEDIMENTO_NOME'], 'UTF-8', 'Windows-1252');
    $nome_servico = htmlspecialchars($procedimento);
} else {
    $nome_servico = "Agenda não identificada";
}

// Busca horários da agenda
$query_horarios = "SELECT * FROM AGENDA_HORARIOS WHERE AGENDA_ID = ? ORDER BY 
                   CASE TRIM(DIA_SEMANA)
                     WHEN 'Segunda' THEN 1
                     WHEN 'Segunda-feira' THEN 1
                     WHEN 'Terça' THEN 2
                     WHEN 'Terça-feira' THEN 2
                     WHEN 'Quarta' THEN 3
                     WHEN 'Quarta-feira' THEN 3
                     WHEN 'Quinta' THEN 4
                     WHEN 'Quinta-feira' THEN 4
                     WHEN 'Sexta' THEN 5
                     WHEN 'Sexta-feira' THEN 5
                     WHEN 'Sábado' THEN 6
                     WHEN 'Sabado' THEN 6
                     WHEN 'Domingo' THEN 7
                     ELSE 8
                   END";

$stmt_horarios = ibase_prepare($conn, $query_horarios);
$result_horarios = ibase_execute($stmt_horarios, $agenda_id);

$dias_funcionamento = [];
while ($horario = ibase_fetch_assoc($result_horarios)) {
    $dias_funcionamento[] = [
        'dia' => utf8_encode(trim($horario['DIA_SEMANA'])),
        'manha_inicio' => $horario['HORARIO_INICIO_MANHA'],
        'manha_fim' => $horario['HORARIO_FIM_MANHA'],
        'tarde_inicio' => $horario['HORARIO_INICIO_TARDE'],
        'tarde_fim' => $horario['HORARIO_FIM_TARDE']
    ];
}

// Busca convênios atendidos
$query_convenios = "SELECT c.NOME, ac.LIMITE_ATENDIMENTOS, ac.QTD_RETORNOS
                    FROM AGENDA_CONVENIOS ac
                    JOIN CONVENIOS c ON c.ID = ac.CONVENIO_ID
                    WHERE ac.AGENDA_ID = ?
                    ORDER BY c.NOME";

$stmt_convenios = ibase_prepare($conn, $query_convenios);
$result_convenios = ibase_execute($stmt_convenios, $agenda_id);

$convenios = [];
while ($convenio = ibase_fetch_assoc($result_convenios)) {
    $convenios[] = [
        'nome' => utf8_encode($convenio['NOME']),
        'limite' => $convenio['LIMITE_ATENDIMENTOS'],
        'retornos' => $convenio['QTD_RETORNOS']
    ];
}

// Gera calendário do mês atual
$hoje = new DateTime();
$mes_atual = $hoje->format('n');
$ano_atual = $hoje->format('Y');
$primeiro_dia = new DateTime("$ano_atual-$mes_atual-01");
$ultimo_dia = clone $primeiro_dia;
$ultimo_dia->modify('last day of this month');

// Mapeia dias da semana
$dias_semana_map = [
    'Sunday' => 'Domingo',
    'Monday' => 'Segunda', 
    'Tuesday' => 'Terça',
    'Wednesday' => 'Quarta',
    'Thursday' => 'Quinta',
    'Friday' => 'Sexta',
    'Saturday' => 'Sábado'
];

$meses_portugues = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

?>


<!-- Cabeçalho do Agendamento -->
<div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
        <div class="flex-1">
            <h2 class="text-2xl font-bold text-[#0C9C99] mb-3">
                <?= htmlspecialchars($nome_servico) ?>
            </h2>
            
            <!-- Informações com badges -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Localização e Contato -->
                <div class="space-y-3">
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <i class="bi bi-geo-alt text-[#0C9C99] mr-1"></i> Localização & Contato
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        <?php if (isset($agenda['UNIDADE_NOME']) && !empty($agenda['UNIDADE_NOME'])): ?>
                        <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm border">
                            <i class="bi bi-building mr-1"></i> <?= htmlspecialchars(mb_convert_encoding($agenda['UNIDADE_NOME'], 'UTF-8', 'Windows-1252')) ?>
                        </span>
                        <?php endif; ?>
                        
                        <?php if (isset($agenda['SALA']) && !empty($agenda['SALA'])): ?>
                        <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm border">
                            <i class="bi bi-door-open mr-1"></i> Sala <?= htmlspecialchars($agenda['SALA']) ?>
                        </span>
                        <?php endif; ?>
                        
                        <?php if (isset($agenda['TELEFONE']) && !empty($agenda['TELEFONE'])): ?>
                        <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm border">
                            <i class="bi bi-telephone mr-1"></i> <?= htmlspecialchars($agenda['TELEFONE']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Configurações e Recursos -->
                <div class="space-y-3">
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <i class="bi bi-gear text-[#0C9C99] mr-1"></i> Configurações & Recursos
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm border">
                            <i class="bi bi-calendar-range mr-1"></i> Horário Marcado
                        </span>
                        
                        <?php if (isset($agenda['TEMPO_ESTIMADO_MINUTOS']) && !empty($agenda['TEMPO_ESTIMADO_MINUTOS'])): ?>
                        <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm border">
                            <i class="bi bi-clock mr-1"></i> <?= $agenda['TEMPO_ESTIMADO_MINUTOS'] ?> min
                        </span>
                        <?php endif; ?>
                        
                        <?php if (isset($agenda['IDADE_MINIMA']) && !empty($agenda['IDADE_MINIMA'])): ?>
                        <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm border">
                            <i class="bi bi-calendar-event mr-1"></i> Idade mín: <?= $agenda['IDADE_MINIMA'] ?> anos
                        </span>
                        <?php endif; ?>
                        
                        <!-- Retornos -->
                        <?php if (isset($agenda['LIMITE_RETORNOS_DIA'])): ?>
                        <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm border">
                            <i class="bi bi-arrow-return-left mr-1"></i> Retorno: <?= ($agenda['LIMITE_RETORNOS_DIA'] > 0) ? $agenda['LIMITE_RETORNOS_DIA'] . '/dia' : 'Não' ?>
                        </span>
                        <?php elseif (isset($agenda['POSSUI_RETORNO'])): ?>
                        <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm border">
                            <i class="bi bi-arrow-return-left mr-1"></i> Retorno: <?= ($agenda['POSSUI_RETORNO'] === 'S' || $agenda['POSSUI_RETORNO'] === 1) ? 'Sim' : 'Não' ?>
                        </span>
                        <?php endif; ?>
                        
                        <!-- Encaixes -->
                        <?php if (isset($agenda['LIMITE_ENCAIXES_DIA']) && $agenda['LIMITE_ENCAIXES_DIA'] > 0): ?>
                        <span class="inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm border border-yellow-300">
                            <i class="bi bi-plus-circle mr-1"></i> Encaixe: <?= $agenda['LIMITE_ENCAIXES_DIA'] ?>/dia
                        </span>
                        <?php endif; ?>
                        
                        <?php if (isset($agenda['ATENDE_COMORBIDADE'])): ?>
                        <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm border">
                            <i class="bi bi-heart-pulse mr-1"></i> Comorbidade: <?= ($agenda['ATENDE_COMORBIDADE'] === 'S' || $agenda['ATENDE_COMORBIDADE'] === 1) ? 'Sim' : 'Não' ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Controles de Visualização -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
    <div class="flex space-x-2 mb-4 sm:mb-0">
        <button class="btn-visualizacao px-4 py-2 text-sm border rounded bg-teal-600 text-white" data-tipo="dia">
            Dia
        </button>
        <button class="btn-visualizacao px-4 py-2 text-sm border rounded bg-white text-gray-700 hover:bg-gray-50" data-tipo="semana">
            Semana  
        </button>
        <button class="btn-visualizacao px-4 py-2 text-sm border rounded bg-white text-gray-700 hover:bg-gray-50" data-tipo="mes">
            Mês
        </button>
    </div>
    
    <!-- Instrução para o usuário -->
    <div class="text-sm text-gray-600 bg-blue-50 px-4 py-2 rounded-lg border border-blue-200">
        <i class="bi bi-info-circle mr-2 text-blue-600"></i>
        <span class="text-blue-800">Clique diretamente em um horário disponível para agendar</span>
    </div>
</div>

<!-- Container do Calendário -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Calendário Compacto -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
            <!-- Cabeçalho do calendário -->
            <div class="flex items-center justify-between mb-3">
                <button class="nav-calendario p-1 hover:bg-gray-100 rounded text-sm" data-direcao="prev">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">
                    <?= $meses_portugues[$mes_atual] ?> <?= $ano_atual ?>
                </h3>
                <button class="nav-calendario p-1 hover:bg-gray-100 rounded text-sm" data-direcao="next">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
            
            <!-- Grade do calendário compacto -->
            <div id="container-calendario">
                <div class="grid grid-cols-7 gap-1 mb-1">
                    <div class="text-center text-xs font-medium text-gray-500 py-1">D</div>
                    <div class="text-center text-xs font-medium text-gray-500 py-1">S</div>
                    <div class="text-center text-xs font-medium text-gray-500 py-1">T</div>
                    <div class="text-center text-xs font-medium text-gray-500 py-1">Q</div>
                    <div class="text-center text-xs font-medium text-gray-500 py-1">Q</div>
                    <div class="text-center text-xs font-medium text-gray-500 py-1">S</div>
                    <div class="text-center text-xs font-medium text-gray-500 py-1">S</div>
                </div>
                
                <div class="grid grid-cols-7 gap-1">
                    <?php
                    // Calcula os dias para exibir no calendário
                    $primeiro_dia_semana = (int)$primeiro_dia->format('w'); // 0=domingo, 6=sábado
                    
                    // Dias do mês anterior (para preencher início)
                    if ($primeiro_dia_semana > 0) {
                        $mes_anterior = clone $primeiro_dia;
                        $mes_anterior->modify('-1 month');
                        $dias_mes_anterior = (int)$mes_anterior->format('t');
                        
                        for ($i = $primeiro_dia_semana - 1; $i >= 0; $i--) {
                            $dia_anterior = $dias_mes_anterior - $i;
                            echo "<div class='text-center py-1 text-xs text-gray-300'>$dia_anterior</div>";
                        }
                    }
                    
                    // Dias do mês atual
                    $dias_no_mes = (int)$ultimo_dia->format('t');
                    $hoje_dia = (int)$hoje->format('j');
                    $hoje_mes = (int)$hoje->format('n');
                    $hoje_ano = (int)$hoje->format('Y');
                    
                    // Criar array de dias com funcionamento para verificação rápida
                    $dias_com_funcionamento = [];
                    foreach ($dias_funcionamento as $funcionamento) {
                        $dias_com_funcionamento[] = strtolower(trim($funcionamento['dia']));
                    }
                    
                    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
                        $data_completa = "$ano_atual-" . str_pad($mes_atual, 2, '0', STR_PAD_LEFT) . "-" . str_pad($dia, 2, '0', STR_PAD_LEFT);
                        $data_obj = new DateTime($data_completa);
                        $dia_semana_ingles = $data_obj->format('l');
                        $dia_semana_portugues = $dias_semana_map[$dia_semana_ingles];
                        
                        // Verifica se o dia está nos horários de funcionamento
                        $tem_funcionamento = in_array(strtolower($dia_semana_portugues), $dias_com_funcionamento) ||
                                           in_array(strtolower($dia_semana_portugues . '-feira'), $dias_com_funcionamento);
                        
                        // Define classes CSS para calendário compacto
                        $classes = ['text-center', 'py-1', 'text-xs', 'cursor-pointer', 'hover:bg-gray-100', 'rounded', 'dia-calendario'];
                        
                        if ($dia == $hoje_dia && $mes_atual == $hoje_mes && $ano_atual == $hoje_ano) {
                            $classes[] = 'bg-teal-500';
                            $classes[] = 'text-white';
                        } else if ($tem_funcionamento && $data_obj >= $hoje) {
                            $classes[] = 'text-gray-700';
                            $classes[] = 'hover:bg-teal-50';
                        } else {
                            $classes[] = 'text-gray-400';
                            $classes[] = 'cursor-not-allowed';
                        }
                        
                        $class_string = implode(' ', $classes);
                        // ✅ CORREÇÃO: Comparar apenas a data, não horário
                        $hoje_data_apenas = new DateTime($hoje->format('Y-m-d'));
                        $eh_hoje = ($data_completa === $hoje->format('Y-m-d'));
                        
                        // ✅ DEBUG: Log quando for o dia atual
                        if ($eh_hoje) {
                            error_log("DEBUG: Dia atual - Data: $data_completa, Tem funcionamento: " . ($tem_funcionamento ? 'SIM' : 'NÃO') . ", Dia semana: $dia_semana_portugues");
                        }
                        
                        // ✅ GARANTIR: Dia atual SEMPRE clicável se tiver funcionamento
                        if ($eh_hoje && $tem_funcionamento) {
                            $disabled = ''; // Forçar habilitado para hoje
                        } else {
                            $disabled = (!$tem_funcionamento || $data_obj < $hoje_data_apenas) ? 'disabled' : '';
                        }
                        
                        echo "<div class='$class_string' data-data='$data_completa' $disabled>$dia</div>";
                    }
                    
                    // Completa a grade com dias do próximo mês se necessário
                    $total_celulas = ($primeiro_dia_semana + $dias_no_mes);
                    $celulas_restantes = 42 - $total_celulas; // 6 semanas * 7 dias
                    
                    for ($i = 1; $i <= min($celulas_restantes, 14) && $total_celulas < 35; $i++) {
                        echo "<div class='text-center py-1 text-xs text-gray-300'>$i</div>";
                        $total_celulas++;
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Convênios atendidos -->
        <?php if (!empty($convenios)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mt-4">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Convênios atendidos</h4>
            <div class="space-y-2">
                <?php foreach ($convenios as $convenio): ?>
                <div class="flex justify-between items-center text-xs">
                    <div class="flex items-center gap-2">
                        <?php 
                        $nomeConvenio = strtolower($convenio['nome']);
                        if (stripos($nomeConvenio, 'particular') !== false): 
                        ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                <i class="bi bi-star-fill mr-1"></i>Particular
                            </span>
                        <?php elseif (stripos($nomeConvenio, 'cartão') !== false || stripos($nomeConvenio, 'desconto') !== false): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                <i class="bi bi-credit-card mr-1"></i>Cartão Desconto
                            </span>
                        <?php else: ?>
                            <span class="text-gray-600">
                                <?= htmlspecialchars($convenio['nome']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($convenio['limite']): ?>
                    <span class="text-gray-500"><?= $convenio['limite'] ?> vagas</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Informações detalhadas -->
        <?php if ($observacoes || $informacoes_fixas || !empty($preparos)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mt-4">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Informações Detalhadas</h4>
            <div class="text-sm space-y-3">
                <?php if ($observacoes): ?>
                <div class="p-3 bg-gray-50 dark:bg-gray-900/20 rounded border-l-4 border-gray-400">
                    <strong class="text-gray-700 dark:text-gray-300">Observações:</strong>
                    <div class="mt-1 text-gray-700 dark:text-gray-300"><?= nl2br(htmlspecialchars($observacoes)) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($informacoes_fixas): ?>
                <div class="p-3 bg-gray-50 dark:bg-gray-900/20 rounded border-l-4 border-gray-400">
                    <strong class="text-gray-700 dark:text-gray-300">Informações fixas:</strong>
                    <div class="mt-1 text-gray-700 dark:text-gray-300"><?= nl2br(htmlspecialchars($informacoes_fixas)) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($preparos)): ?>
                <div class="p-3 bg-gray-50 dark:bg-gray-900/20 rounded border-l-4 border-gray-400">
                    <strong class="text-gray-700 dark:text-gray-300 flex items-center mb-2">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Preparos e Orientações:
                    </strong>
                    <div class="space-y-2">
                        <?php foreach ($preparos as $index => $preparo): ?>
                        <div class="<?= $index > 0 ? 'pt-2 border-t border-gray-200 dark:border-gray-700' : '' ?>">
                            <button 
                                type="button" 
                                class="text-left w-full font-medium text-gray-800 dark:text-gray-200 hover:text-gray-600 dark:hover:text-gray-100 hover:underline focus:outline-none focus:ring-2 focus:ring-gray-300 rounded p-1 -m-1 transition-colors duration-200"
                                onclick="abrirModalPreparoDetalhes('<?= htmlspecialchars(addslashes($preparo['titulo']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($preparo['instrucoes']), ENT_QUOTES, 'UTF-8') ?>', <?= htmlspecialchars(json_encode($preparo['anexos']), ENT_QUOTES, 'UTF-8') ?>)"
                                title="Clique para ver as instruções completas"
                            >
                                <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                </svg>
                                <?= htmlspecialchars($preparo['titulo']) ?>
                                <?php if (!empty($preparo['anexos'])): ?>
                                    <span class="text-xs text-blue-600 ml-1">(<?= count($preparo['anexos']) ?> anexo<?= count($preparo['anexos']) > 1 ? 's' : '' ?>)</span>
                                <?php endif; ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Área de visualização principal -->
    <div class="lg:col-span-3">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                    <span id="titulo-visualizacao">Agenda do Dia</span>
                </h3>
                <span class="text-sm text-gray-500" id="data-selecionada">
                    <?= $hoje->format('d/m/Y') ?>
                </span>
            </div>
            
            <!-- Container para mensagens (erros, avisos, informações) -->
            <div id="container-mensagens" class="mb-4"></div>

            <!-- Container da visualização principal -->
            <div id="area-visualizacao" class="min-h-[400px]">
                <!-- Conteúdo será carregado dinamicamente via JavaScript -->
                <div class="text-center text-gray-500 py-8">
                    <i class="bi bi-clock-history text-3xl mb-2"></i>
                    <p>Carregando visualização...</p>
                </div>
            </div>
            
            <!-- REMOVIDO: Container do botão de confirmar agendamento -->
            <!-- O modal abrirá diretamente ao clicar no horário -->
        </div>
    </div>
</div>

<script>
// Log de debug para verificação
console.log('carregar_agendamento.php carregado - agenda_id: <?= $agenda_id ?>');
console.log('Modal direto habilitado - sem botão intermediário');
console.log('Dias de funcionamento:', <?= json_encode($dias_funcionamento, JSON_HEX_QUOT | JSON_HEX_APOS) ?>);
console.log('Convênios:', <?= json_encode($convenios, JSON_HEX_QUOT | JSON_HEX_APOS) ?>);

// ✅ FIX ESPECÍFICO PARA NAVEGAÇÃO DO CALENDÁRIO
setTimeout(() => {
    console.log('🛠️ Aplicando fix da navegação do calendário...');
    
    // Função para corrigir navegação
    function corrigirNavegacaoAgora() {
        const navButtons = document.querySelectorAll('.nav-calendario');
        console.log(`🔧 Encontrados ${navButtons.length} botões de navegação para correção`);
        
        navButtons.forEach((btn, index) => {
            const direcao = btn.dataset.direcao;
            console.log(`🔧 Corrigindo botão ${direcao}`);
            
            // Remover listeners antigos
            const novoBotao = btn.cloneNode(true);
            btn.parentNode.replaceChild(novoBotao, btn);
            
            // Adicionar novo listener
            novoBotao.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log(`🔄 NAVEGAÇÃO CORRIGIDA: ${direcao}`);
                
                // Usar a função do agenda.js se disponível
                if (typeof navegarMesCalendario === 'function' && window.agendaIdAtual) {
                    navegarMesCalendario(window.agendaIdAtual, direcao);
                } else {
                    console.warn('⚠️ navegarMesCalendario não disponível, tentando reload...');
                    location.reload(); // Fallback
                }
            });
        });
        
        console.log('✅ Navegação do calendário corrigida!');
    }
    
    // Executar correção
    corrigirNavegacaoAgora();
    
    // Reexecutar quando o calendário for atualizado
    const observer = new MutationObserver(() => {
        setTimeout(corrigirNavegacaoAgora, 100);
    });
    
    const container = document.getElementById('container-calendario');
    if (container?.parentElement) {
        observer.observe(container.parentElement, { childList: true, subtree: true });
    }
    
    // ✅ FUNÇÃO ESPECÍFICA: Forçar dia atual clicável
    window.forcarDiaAtualClicavel = function() {
        const hoje = new Date();
        const dataHoje = hoje.toISOString().split('T')[0];
        const diaHoje = document.querySelector(`[data-data="${dataHoje}"]`);
        
        console.log('🔧 Forçando dia atual clicável:', dataHoje);
        
        if (diaHoje) {
            console.log('   - Estado antes:', {
                disabled: diaHoje.hasAttribute('disabled'),
                classes: diaHoje.className
            });
            
            // Remover todas as restrições
            diaHoje.removeAttribute('disabled');
            diaHoje.classList.remove('cursor-not-allowed', 'text-gray-400');
            diaHoje.classList.add('cursor-pointer', 'text-gray-700', 'hover:bg-teal-50', 'ring-2', 'ring-teal-200');
            
            console.log('   - Estado após:', {
                disabled: diaHoje.hasAttribute('disabled'),
                classes: diaHoje.className
            });
            
            // Testar clique
            diaHoje.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('✅ DIA ATUAL CLICADO COM SUCESSO!');
                
                // Chamar função de seleção se disponível
                if (typeof selecionarDiaNoCalendario === 'function') {
                    selecionarDiaNoCalendario(this, window.agendaIdAtual, dataHoje);
                }
            });
            
            console.log('✅ Dia atual agora deve estar clicável');
            return true;
        } else {
            console.warn('⚠️ Dia atual não encontrado');
            return false;
        }
    };
    
    // Executar correção após 2 segundos
    setTimeout(() => {
        window.forcarDiaAtualClicavel();
    }, 2000);
    
}, 1000);

// Função para abrir modal com instruções completas do preparo
function abrirModalPreparoDetalhes(titulo, instrucoes, anexos = []) {
    // Criar modal dinâmico
    const modalHTML = `
        <div id="modal-preparo-detalhes" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[80vh] overflow-hidden">
                <div class="bg-gray-600 text-white p-4 flex justify-between items-center">
                    <h3 class="text-lg font-semibold flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        ${titulo}
                    </h3>
                    <button 
                        type="button" 
                        onclick="fecharModalPreparoDetalhes()"
                        class="text-white hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 rounded p-1"
                        title="Fechar"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <div class="bg-gray-50 dark:bg-gray-900/20 border-l-4 border-gray-400 p-4 rounded-r-lg">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">Instruções:</h4>
                            <button 
                                type="button" 
                                onclick="copiarTextoModal()"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 flex items-center gap-1"
                                title="Copiar texto"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                Copiar
                            </button>
                        </div>
                        <p id="texto-preparo-modal" class="text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">${instrucoes}</p>
                    </div>
                    
                    ${anexos && anexos.length > 0 ? `
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                        <h5 class="font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                                <path d="M14 2v6h6"/>
                            </svg>
                            Anexos (${anexos.length})
                        </h5>
                        <div class="space-y-2">
                            ${anexos.map(anexo => `
                                <div class="flex items-center justify-between p-2 bg-gray-100 dark:bg-gray-700 rounded">
                                    <div class="flex items-center space-x-2">
                                        ${getIconeArquivoModal(anexo.tipo)}
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">${anexo.nome}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">${formatarTamanho(anexo.tamanho)}</p>
                                        </div>
                                    </div>
                                    <button 
                                        type="button" 
                                        onclick="baixarAnexo(${anexo.id})"
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm flex items-center gap-1"
                                        title="Baixar arquivo"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                        </svg>
                                        Baixar
                                    </button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-end">
                    <button 
                        type="button" 
                        onclick="fecharModalPreparoDetalhes()"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-gray-300"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal existente se houver
    const modalExistente = document.getElementById('modal-preparo-detalhes');
    if (modalExistente) {
        modalExistente.remove();
    }
    
    // Adicionar modal ao body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Adicionar listeners para fechar
    const modal = document.getElementById('modal-preparo-detalhes');
    
    // Fechar com ESC
    const handleEscape = (event) => {
        if (event.key === 'Escape') {
            fecharModalPreparoDetalhes();
        }
    };
    
    modal.handleEscape = handleEscape;
    document.addEventListener('keydown', handleEscape);
    
    // Fechar ao clicar fora do modal
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            fecharModalPreparoDetalhes();
        }
    });
    
    console.log('✅ Modal de preparo aberto:', titulo);
}

// Função para obter ícone de arquivo no modal
function getIconeArquivoModal(tipo) {
    const icones = {
        'pdf': '<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/></svg>',
        'doc': '<svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/></svg>',
        'docx': '<svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/></svg>',
        'jpg': '<svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/><circle cx="10" cy="13" r="2"/></svg>',
        'jpeg': '<svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/><circle cx="10" cy="13" r="2"/></svg>',
        'png': '<svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/><circle cx="10" cy="13" r="2"/></svg>',
        'txt': '<svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/></svg>'
    };
    return icones[tipo] || icones['txt'];
}

// Função para formatar tamanho do arquivo
function formatarTamanho(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Função para baixar anexo
function baixarAnexo(anexoId) {
    window.open('download_anexo.php?id=' + anexoId, '_blank');
}

// Função para copiar texto do modal
function copiarTextoModal() {
    const textoElement = document.getElementById('texto-preparo-modal');
    if (textoElement) {
        const texto = textoElement.textContent || textoElement.innerText;
        
        // Tentar usar a API moderna do clipboard
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(texto).then(() => {
                mostrarNotificacaoCopiado();
            }).catch(() => {
                // Fallback para método antigo
                copiarTextoFallback(texto);
            });
        } else {
            // Fallback para método antigo
            copiarTextoFallback(texto);
        }
    }
}

// Função fallback para copiar texto
function copiarTextoFallback(texto) {
    const textArea = document.createElement('textarea');
    textArea.value = texto;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        mostrarNotificacaoCopiado();
    } catch (err) {
        console.error('Erro ao copiar texto:', err);
        alert('Não foi possível copiar o texto. Tente selecionar manualmente.');
    }
    
    document.body.removeChild(textArea);
}

// Função para mostrar notificação de texto copiado
function mostrarNotificacaoCopiado() {
    const botaoCopiar = document.querySelector('[onclick="copiarTextoModal()"]');
    if (botaoCopiar) {
        const textoOriginal = botaoCopiar.innerHTML;
        botaoCopiar.innerHTML = `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Copiado!
        `;
        botaoCopiar.classList.remove('bg-gray-500', 'hover:bg-gray-600');
        botaoCopiar.classList.add('bg-green-500', 'hover:bg-green-600');
        
        setTimeout(() => {
            botaoCopiar.innerHTML = textoOriginal;
            botaoCopiar.classList.remove('bg-green-500', 'hover:bg-green-600');
            botaoCopiar.classList.add('bg-gray-500', 'hover:bg-gray-600');
        }, 2000);
    }
}

// Função para fechar modal de preparo
function fecharModalPreparoDetalhes() {
    const modal = document.getElementById('modal-preparo-detalhes');
    if (modal) {
        // Remover listeners de teclado
        if (modal.handleEscape) {
            document.removeEventListener('keydown', modal.handleEscape);
        }
        
        // Remover modal
        modal.remove();
        console.log('✅ Modal de preparo fechado');
    }
}
</script>