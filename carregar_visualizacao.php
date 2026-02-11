<?php
include 'includes/connection.php';

$agendaId = (int) ($_GET['agenda_id'] ?? 0);
$tipo     = $_GET['tipo'] ?? 'dia';

if ($tipo !== 'semana') {
  echo "<div class='p-4 text-red-500'>Visualização ainda não implementada.</div>";
  exit;
}

// Calcula a segunda-feira da semana atual
$hoje = new DateTime();
$diaSemana = (int) $hoje->format('N'); // 1 (segunda) a 7 (domingo)
$inicioSemana = clone $hoje;
$inicioSemana->modify('-' . ($diaSemana - 1) . ' days');

// Gera os 7 dias da semana
$datasSemana = [];
for ($i = 0; $i < 7; $i++) {
  $data = clone $inicioSemana;
  $data->modify("+$i days");
  $datasSemana[] = $data->format('Y-m-d');
}

// Monta a estrutura HTML (ex: header com dias da semana)
echo "<div class='overflow-x-auto'>";
echo "<table class='min-w-full border text-sm'>";
echo "<thead><tr>";
foreach ($datasSemana as $data) {
  $diaFormatado = date('d/m', strtotime($data));
  $nomeDia = ucfirst(strftime('%A', strtotime($data)));
  echo "<th class='border px-3 py-2 text-center bg-gray-100'>$nomeDia<br><span class='text-xs text-gray-500'>$diaFormatado</span></th>";
}
echo "</tr></thead><tbody><tr>";

// Para cada dia, buscar os horários disponíveis
foreach ($datasSemana as $data) {
  $sql = "SELECT HORA, DISPONIVEL FROM horarios_agenda 
          WHERE agenda_id = $agendaId AND data = '$data'
          ORDER BY HORA";
  $result = exeBD($sql);

  echo "<td class='align-top border px-2 py-2'>";
  while ($row = ibase_fetch_assoc($result)) {
    $hora = $row['HORA'];
    $disponivel = $row['DISPONIVEL'];

    $classe = $disponivel
      ? 'bg-white hover:bg-teal-100 border text-gray-700 cursor-pointer'
      : 'bg-gray-100 text-gray-400 border';

    $onclick = $disponivel
      ? "onclick=\"window.location.href='finalizar_agendamento.php?agenda_id=$agendaId&data=$data&horario=$hora'\""
      : '';

    echo "<div class='p-2 my-1 rounded text-center $classe' $onclick>$hora</div>";
  }
  echo "</td>";
}
echo "</tr></tbody></table></div>";
