<?php
$medico   = $_GET['medico']   ?? '';
$unidade  = $_GET['unidade']  ?? '';
$tipo     = $_GET['tipo']     ?? '';
$nome     = $_GET['nome']     ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Agenda de <?php echo htmlspecialchars($medico); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { darkMode: 'class' };
  </script>
</head>
<body class="bg-gray-100 text-gray-800">
  <div id="loader" class="hidden text-center py-4 text-sm text-gray-500">Carregando agenda...</div>

  <main class="max-w-7xl mx-auto p-6" id="conteudo-dinamico">
    <div class="flex flex-wrap gap-6">

      <!-- Painel lateral -->
      <aside class="w-full md:w-72 space-y-4">
        <div class="bg-white shadow rounded p-4 space-y-2">
          <p class="text-sm font-medium text-gray-700">Observações</p>
          <p class="text-sm text-gray-500">Informações fixas</p>
          <p class="text-sm text-gray-500">Convênios atendidos</p>
        </div>

        <div class="bg-white shadow rounded p-4">
          <div class="flex justify-between items-center mb-2">
            <button class="text-sm">&lt;</button>
            <p class="text-sm font-semibold">Julho 2025</p>
            <button class="text-sm">&gt;</button>
          </div>
          <div class="flex justify-around text-xs text-gray-500 mb-2">
            <span>SEG</span><span>TER</span><span>QUA</span><span>QUI</span><span>SEX</span><span>SAB</span><span>DOM</span>
          </div>
          <div class="grid grid-cols-7 gap-1 text-center text-xs text-gray-700">
            <?php
            for ($i = 30; $i <= 31; $i++) echo "<div>$i</div>";
            for ($i = 1; $i <= 27; $i++) echo "<div" . ($i === 27 ? " class='bg-teal-100 rounded'" : "") . ">$i</div>";
            ?>
          </div>
        </div>
      </aside>

      <!-- Conteúdo principal -->
      <section class="flex-1 w-full space-y-6">
        <!-- Filtros -->
        <div class="bg-white shadow rounded p-4 grid grid-cols-1 md:grid-cols-4 gap-4">
          <input type="text" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="Unidade" value="<?php echo htmlspecialchars($unidade); ?>" readonly>
          <input type="text" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="Procedimento" value="<?php echo htmlspecialchars($nome); ?>" readonly>
          <input type="text" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="<?php echo $tipo === 'consulta' ? 'Especialidade' : 'Grupo'; ?>" value="<?php echo htmlspecialchars($nome); ?>" readonly>
          <input type="text" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="Médico" value="<?php echo htmlspecialchars($medico); ?>" readonly>
        </div>

        <!-- Grade da agenda -->
        <div class="overflow-x-auto bg-white shadow rounded">
          <div class="grid grid-cols-7 border-t text-sm text-gray-600 text-center font-semibold bg-gray-50">
            <?php
              $dias = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'];
              foreach ($dias as $dia) {
                echo "<div class='py-3 border-r'>$dia</div>";
              }
            ?>
          </div>
          <div id="agenda-semanal" class="grid grid-cols-7 min-h-[300px] text-xs text-gray-500">
            <?php
            // Simula slots vazios
            for ($linha = 0; $linha < 8; $linha++) {
              for ($col = 0; $col < 7; $col++) {
                echo "<div class='border-r border-t h-12'></div>";
              }
            }
            ?>
          </div>
        </div>
      </section>
    </div>
  </main>

  <script>
    const loader = document.getElementById('loader');
    const conteudo = document.getElementById('agenda-semanal');

    loader.classList.remove('hidden');

    fetch('carregar_conteudo.php?' + new URLSearchParams({
      medico: <?php echo json_encode($medico); ?>,
      unidade: <?php echo json_encode($unidade); ?>,
      tipo: <?php echo json_encode($tipo); ?>,
      nome: <?php echo json_encode($nome); ?>
    }))
    .then(r => r.text())
    .then(html => {
      conteudo.innerHTML = html;
    })
    .catch(() => {
      conteudo.innerHTML = "<div class='col-span-7 text-center py-6 text-red-600'>Erro ao carregar agenda.</div>";
    })
    .finally(() => loader.classList.add('hidden'));
  </script>
</body>
</html>
