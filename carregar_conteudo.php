<?php
$tipo = $_GET['tipo'] ?? '';
$nome = $_GET['nome'] ?? '';

// ⚙️ Verifica se é a tela de configurações
if ($tipo === 'configuracoes') {
    include 'configuracoes.php';
    exit;
}

$tipoLabel = $tipo === 'consulta' ? "Especialidade" : "Grupo de Procedimento";
$descricao = $tipo === 'consulta' ? "a especialidade" : "o grupo de procedimento";

// 💬 Cabeçalho
echo "<section class='bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md text-center mb-6'>";
echo "<h2 class='text-2xl font-bold text-[#0C9C99] mb-1'>{$tipoLabel}: <span class='text-gray-800 dark:text-white'>" . htmlspecialchars($nome) . "</span></h2>";
echo "<p class='text-sm text-gray-600 dark:text-gray-300'>Você selecionou {$descricao} <strong>" . htmlspecialchars($nome) . "</strong>.</p>";
echo "</section>";

// 📆 Botões de dias da semana
$dias = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
echo "<div class='text-center mb-4'>";
echo "<p id='dia-selecionado' class='text-sm text-gray-600 dark:text-gray-400 italic mb-2'>Dia selecionado: <span class='font-medium text-[#0C9C99]'>Todos os dias</span></p>";
echo "<div class='flex flex-wrap justify-center gap-2' id='filtros-dias'>";
echo "<button onclick=\"selecionarDia(this, '$tipo', '$nome', '')\" data-dia='' class='px-4 py-2 text-sm rounded border border-gray-300 bg-white hover:bg-[#0C9C99] hover:text-white transition'>Todos os dias</button>";
foreach ($dias as $dia) {
    echo "<button onclick=\"selecionarDia(this, '$tipo', '$nome', '$dia')\" data-dia='$dia' class='px-4 py-2 text-sm rounded border border-gray-300 bg-white hover:bg-[#0C9C99] hover:text-white transition'>$dia</button>";
}
echo "</div></div>";

// 🔄 Contêineres para resposta dinâmica
echo "<div id='dias-disponiveis' class='text-center mb-4'></div>";
echo "<div id='medicos-por-dia' class='mt-6'></div>";

// 📜 Scripts
echo "<script>
  carregarDiasDisponiveis('$tipo', '$nome');
  carregarListagemAgendas('$tipo', '$nome');
</script>";
