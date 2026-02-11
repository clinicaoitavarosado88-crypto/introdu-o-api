<?php
function renderAgendaCard(array $a, $conn, string $tipo): string {
    $id_agenda = $a['ID'];

    // Convênios
    $res_conv = ibase_query($conn, "SELECT c.NOME FROM AGENDA_CONVENIOS ac JOIN CONVENIOS c ON c.ID = ac.CONVENIO_ID WHERE ac.AGENDA_ID = $id_agenda");
    $convenios = [];
    while ($c = ibase_fetch_assoc($res_conv)) {
        $convenios[] = mb_convert_encoding($c['NOME'], 'UTF-8', 'Windows-1252');
    }

    // Horários
    $res_horarios = ibase_query($conn, "SELECT * FROM AGENDA_HORARIOS WHERE AGENDA_ID = $id_agenda");

    // Blobs
    $lerBlob = function($id) use ($conn) {
        if (!is_string($id)) return '';
        $blob = ibase_blob_open($conn, $id);
        $conteudo = '';
        while ($segmento = ibase_blob_get($blob, 4096)) {
            $conteudo .= $segmento;
        }
        ibase_blob_close($blob);
        return trim($conteudo);
    };

    $obs = mb_convert_encoding($lerBlob($a['OBSERVACOES'] ?? ''), 'UTF-8', 'Windows-1252');
    $info = mb_convert_encoding($lerBlob($a['INFORMACOES_FIXAS'] ?? ''), 'UTF-8', 'Windows-1252');
    $orient = mb_convert_encoding($lerBlob($a['ORIENTACOES'] ?? ''), 'UTF-8', 'Windows-1252');

    // HTML do card
    ob_start();
    ?>
<!-- Card da agenda com link para agendamento -->
<div onclick="carregarAgendamento(<?= $id_agenda ?><?= isset($a['ESPECIALIDADE_ID_FILTRADA']) ? ', ' . $a['ESPECIALIDADE_ID_FILTRADA'] : '' ?>)" 
     title="Clique para agendar"
     data-especialidade-id="<?= isset($a['ESPECIALIDADE_ID_FILTRADA']) ? $a['ESPECIALIDADE_ID_FILTRADA'] : '' ?>"
     class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-md p-5 
            hover:bg-blue-50 dark:hover:bg-gray-700 hover:shadow-lg hover:scale-[1.02] transition-all duration-200 cursor-pointer">

  <!-- Nome -->
  <h3 class="text-base font-bold text-[#0C9C99] mb-4 leading-tight text-center">
    <?php
    // Exibir nome baseado no tipo de agenda
    if (isset($a['MEDICO']) && !empty($a['MEDICO'])) {
        $medico = mb_convert_encoding($a['MEDICO'], 'UTF-8', 'Windows-1252');
        echo "Dr(a). " . htmlspecialchars($medico);
        
        // Adicionar especialidade se for consulta
        if (isset($a['ESPECIALIDADE']) && !empty($a['ESPECIALIDADE'])) {
            $especialidade = mb_convert_encoding($a['ESPECIALIDADE'], 'UTF-8', 'Windows-1252');
            echo " – " . htmlspecialchars($especialidade);
        }
    } elseif (isset($a['PROCEDIMENTO']) && !empty($a['PROCEDIMENTO'])) {
        $procedimento = mb_convert_encoding($a['PROCEDIMENTO'], 'UTF-8', 'Windows-1252');
        echo htmlspecialchars($procedimento);
    } else {
        echo "Agenda não identificada";
    }
    ?>
  </h3>

  <!-- Localização & Contato -->
  <div class="mb-4">
    <div class="flex items-center mb-2">
      <i class="bi bi-geo-alt text-blue-600 mr-2"></i>
      <h4 class="text-sm font-semibold text-gray-700">Localização & Contato</h4>
    </div>
    <div class="flex flex-wrap gap-2">
      <?php if (isset($a['UNIDADE']) && !empty($a['UNIDADE'])): ?>
        <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full border">
          <?= htmlspecialchars(mb_convert_encoding($a['UNIDADE'], 'UTF-8', 'Windows-1252')) ?>
        </span>
      <?php endif; ?>
      
      <?php if (isset($a['SALA']) && !empty($a['SALA'])): ?>
        <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full border">
          Sala <?= htmlspecialchars($a['SALA']) ?>
        </span>
      <?php endif; ?>
      
      <?php if (isset($a['TELEFONE']) && !empty($a['TELEFONE'])): ?>
        <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full border">
          <?= htmlspecialchars($a['TELEFONE']) ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Configurações & Recursos -->
  <div class="mb-4">
    <div class="flex items-center mb-2">
      <i class="bi bi-gear text-green-600 mr-2"></i>
      <h4 class="text-sm font-semibold text-gray-700">Configurações & Recursos</h4>
    </div>
    <div class="flex flex-wrap gap-2">
      <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full border">Horário Marcado</span>
      
      <?php if (isset($a['TEMPO_ESTIMADO_MINUTOS']) && !empty($a['TEMPO_ESTIMADO_MINUTOS'])): ?>
        <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full border">
          <?= $a['TEMPO_ESTIMADO_MINUTOS'] ?> min
        </span>
      <?php endif; ?>
      
      <?php if (isset($a['IDADE_MINIMA']) && !empty($a['IDADE_MINIMA'])): ?>
        <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full border">
          Idade mín: <?= $a['IDADE_MINIMA'] ?> anos
        </span>
      <?php endif; ?>
      
      <!-- Retornos -->
      <?php if (isset($a['LIMITE_RETORNOS_DIA'])): ?>
        <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full border">
          Retorno: <?= ($a['LIMITE_RETORNOS_DIA'] > 0) ? $a['LIMITE_RETORNOS_DIA'] . '/dia' : 'Não' ?>
        </span>
      <?php elseif (isset($a['POSSUI_RETORNO'])): ?>
        <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full border">
          Retorno: <?= ($a['POSSUI_RETORNO'] === 'S' || $a['POSSUI_RETORNO'] === 1) ? 'Sim' : 'Não' ?>
        </span>
      <?php endif; ?>
      
      <!-- Encaixes -->
      <?php if (isset($a['LIMITE_ENCAIXES_DIA']) && $a['LIMITE_ENCAIXES_DIA'] > 0): ?>
        <span class="bg-yellow-100 text-yellow-700 text-xs px-2 py-1 rounded-full border border-yellow-300">
          Encaixe: <?= $a['LIMITE_ENCAIXES_DIA'] ?>/dia
        </span>
      <?php endif; ?>
      
      
      <?php if (isset($a['ATENDE_COMORBIDADE'])): ?>
        <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full border">
          Comorbidade: <?= ($a['ATENDE_COMORBIDADE'] === 'S' || $a['ATENDE_COMORBIDADE'] === 1) ? 'Sim' : 'Não' ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Dias e horários (mesclados) -->
  <div class="text-sm text-gray-700 text-left dark:text-gray-300 mb-2">
    <strong>Dias de atendimento:</strong>
    <ul class="pl-4 mt-1 space-y-1">
      <?php while ($h = ibase_fetch_assoc($res_horarios)): ?>
        <li>
          <strong><?= htmlspecialchars(mb_convert_encoding($h['DIA_SEMANA'], 'UTF-8', 'Windows-1252')) ?>:</strong>
          <?php
          $horarios = [];

          // ✅ CASO ESPECIAL: Funcionamento contínuo (início manhã + fim tarde)
          $funcionamento_continuo = !empty($h['HORARIO_INICIO_MANHA']) && 
                                    empty($h['HORARIO_FIM_MANHA']) &&
                                    empty($h['HORARIO_INICIO_TARDE']) &&
                                    !empty($h['HORARIO_FIM_TARDE']);
          
          if ($funcionamento_continuo) {
              // Mostra como funcionamento contínuo
              $horarios[] = date('H:i', strtotime($h['HORARIO_INICIO_MANHA'])) . " às " . date('H:i', strtotime($h['HORARIO_FIM_TARDE']));
          } else {
              // Lógica normal: só mostra se AMBOS os horários estão preenchidos
              if (!empty($h['HORARIO_INICIO_MANHA']) && !empty($h['HORARIO_FIM_MANHA'])) {
                  $horarios[] = date('H:i', strtotime($h['HORARIO_INICIO_MANHA'])) . " às " . date('H:i', strtotime($h['HORARIO_FIM_MANHA']));
              }

              if (!empty($h['HORARIO_INICIO_TARDE']) && !empty($h['HORARIO_FIM_TARDE'])) {
                  $horarios[] = date('H:i', strtotime($h['HORARIO_INICIO_TARDE'])) . " às " . date('H:i', strtotime($h['HORARIO_FIM_TARDE']));
              }
          }

          echo implode(' - ', $horarios);
          ?>
        </li>
      <?php endwhile; ?>
    </ul>
  </div>

  <!-- Vagas por Dia da Semana -->
  <?php 
  // Reset o cursor dos horários para ler as vagas
  $res_horarios_vagas = ibase_query($conn, "SELECT * FROM AGENDA_HORARIOS WHERE AGENDA_ID = $id_agenda");
  $temVagas = false;
  $vagasPorDia = [];
  
  // Coletar informações de vagas
  while ($h = ibase_fetch_assoc($res_horarios_vagas)) {
      if (isset($h['VAGAS_DIA']) && $h['VAGAS_DIA'] > 0) {
          $temVagas = true;
          $dia = mb_convert_encoding($h['DIA_SEMANA'], 'UTF-8', 'Windows-1252');
          $vagasPorDia[$dia] = $h['VAGAS_DIA'];
      }
  }
  ?>
  
  <?php if ($temVagas): ?>
  <div class="text-sm text-gray-700 text-left dark:text-gray-300 mb-2">
    <strong>Vagas disponíveis por dia:</strong>
    <div class="flex flex-wrap gap-2 mt-2">
      <?php foreach ($vagasPorDia as $dia => $vagas): ?>
        <span class="bg-blue-100 text-blue-700 text-xs px-3 py-1 rounded-full border border-blue-300 font-medium">
          <?= htmlspecialchars($dia) ?>: <?= $vagas ?> vagas
        </span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Convênios -->
  <?php if ($convenios): ?>
    <div class="mt-3">
      <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 text-left  mb-1">Convênios:</p>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($convenios as $c): ?>
          <?php
          $isDestaque = stripos($c, 'particular') !== false || stripos($c, 'cartão') !== false;
          $badgeClass = $isDestaque
              ? 'bg-green-100 text-green-800 border border-green-300'
              : 'bg-gray-100 text-gray-700 border border-gray-300';
          ?>
          <span class="text-xs px-2 py-1 rounded-full <?= $badgeClass ?>"><?= htmlspecialchars($c) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Observações -->
  <div class="mt-4 text-sm text-gray-700 text-left  dark:text-gray-300 space-y-1 leading-snug">
    <?php if ($obs): ?>
      <p><strong class="text-yellow-700">Obs:</strong> <?= $obs ?></p>
    <?php endif; ?>
    <?php if ($info): ?>
      <p><strong class="text-yellow-700">Fixas:</strong> <?= $info ?></p>
    <?php endif; ?>
    <?php if ($orient): ?>
      <p><strong class="text-yellow-700">Orientações:</strong> <?= $orient ?></p>
    <?php endif; ?>
  </div>
  
  <!-- Indicador visual de que é clicável -->
  <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600">
    <div class="flex items-center justify-center text-teal-600 dark:text-teal-400">
      <i class="bi bi-calendar-plus mr-2"></i>
      <span class="text-sm font-medium">Clique para agendar</span>
      <i class="bi bi-arrow-right ml-2"></i>
    </div>
  </div>
</div>

    <?php
    return ob_get_clean();
}
?>