<?php
// Arquivo para paginação de agendas em HTML
include 'includes/connection.php';
include 'includes/verificar_permissao.php';

// Verificar permissão para administrar agendas
if (!verificarPermissaoAdminAgenda($conn, 'paginar agendas')) {
    echo '<div class="text-center text-red-600 py-4">Permissão negada</div>';
    exit;
}

$cidade_id = (int) ($_GET['cidade'] ?? 1);
$limite = (int) ($_GET['limite'] ?? 10);
$paginaAtual = (int) ($_GET['paginaAtual'] ?? 1);

$busca = isset($_GET['busca']) ? strtoupper(trim($_GET['busca'])) : '';
$condBusca = '';

if ($busca !== '') {
  $buscaEsc = str_replace("'", "''", $busca);
  $condBusca = "
    AND (
      UPPER(m.NOME) LIKE '%$buscaEsc%' OR
      UPPER(e.NOME) LIKE '%$buscaEsc%' OR
      UPPER(p.NOME) LIKE '%$buscaEsc%'
    )
  ";
}

$sql = "
  SELECT COUNT(*) AS TOTAL
  FROM AGENDAS a
  LEFT JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
  LEFT JOIN LAB_MEDICOS_ESPECIALIDADES me ON me.MEDICO_ID = m.ID
  LEFT JOIN ESPECIALIDADES e ON e.ID = me.ESPECIALIDADE_ID
  LEFT JOIN GRUPO_EXAMES p ON p.ID = a.PROCEDIMENTO_ID
  WHERE a.SITUACAO = 1 AND a.UNIDADE_ID = $cidade_id $condBusca
";
$res = ibase_query($conn, $sql);
$total = (int) ibase_fetch_assoc($res)['TOTAL'];
$totalPaginas = ceil($total / $limite);

// Renderizar HTML da paginação
?>
<div class="flex items-center justify-between">
  <!-- Botão Anterior -->
  <?php if ($paginaAtual > 1): ?>
    <button onclick="carregarPagina(<?= $paginaAtual - 1 ?>)"
            class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700">
      Anterior
    </button>
  <?php else: ?>
    <button disabled
            class="px-4 py-2 bg-gray-300 text-gray-500 rounded cursor-not-allowed">
      Anterior
    </button>
  <?php endif; ?>

  <!-- Números de páginas -->
  <div class="flex gap-2">
    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
      <?php if ($i == $paginaAtual): ?>
        <button class="px-3 py-1 bg-teal-600 text-white rounded font-bold">
          <?= $i ?>
        </button>
      <?php else: ?>
        <button onclick="carregarPagina(<?= $i ?>)"
                class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
          <?= $i ?>
        </button>
      <?php endif; ?>
    <?php endfor; ?>
  </div>

  <!-- Botão Próximo -->
  <?php if ($paginaAtual < $totalPaginas): ?>
    <button onclick="carregarPagina(<?= $paginaAtual + 1 ?>)"
            class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700">
      Próximo
    </button>
  <?php else: ?>
    <button disabled
            class="px-4 py-2 bg-gray-300 text-gray-500 rounded cursor-not-allowed">
      Próximo
    </button>
  <?php endif; ?>
</div>

<div class="text-center text-sm text-gray-600 dark:text-gray-400 mt-2">
  Mostrando página <?= $paginaAtual ?> de <?= $totalPaginas ?> (<?= $total ?> registro<?= $total != 1 ? 's' : '' ?> no total)
</div>
<?php
