<?php
// visualizacao_semana.php
date_default_timezone_set('America/Fortaleza');

// Início da semana atual (segunda-feira)
$hoje = new DateTime();
$inicioSemana = clone $hoje;
$inicioSemana->modify('monday');

// Dias da semana (segunda a sábado)
$dias = [];
for ($i = 0; $i < 6; $i++) {
  $dia = clone $inicioSemana;
  $dia->modify("+$i days");
  $dias[] = $dia;
}

// Simulação de horários disponíveis por dia
$horarios = ["08:00", "08:30", "09:00", "10:00", "10:30", "14:00", "15:30"];
?>

<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
  <?php foreach ($dias as $dia): ?>
    <div class="bg-white rounded shadow p-3">
      <h3 class="text-sm font-semibold text-gray-700 mb-2 text-center">
        <?= ucfirst(strftime('%A', $dia->getTimestamp())) ?><br>
        <span class="text-xs text-gray-500"><?= $dia->format('d/m') ?></span>
      </h3>
      <div class="space-y-1">
        <?php foreach ($horarios as $hora): ?>
          <div onclick="alert('Agendar <?= $dia->format('Y-m-d') ?> <?= $hora ?>')" 
               class="cursor-pointer text-sm text-center border rounded py-1 px-2 hover:bg-teal-50 hover:border-teal-500">
            <?= $hora ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
