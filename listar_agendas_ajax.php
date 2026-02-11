<?php
// Arquivo para listagem de agendas em HTML (tabela de gerenciamento)
include 'includes/connection.php';
include 'includes/verificar_permissao.php';

// Verificar permissão para administrar agendas
if (!verificarPermissaoAdminAgenda($conn, 'listar agendas')) {
    echo '<tr><td colspan="6" class="text-center text-red-600 py-4">Permissão negada</td></tr>';
    exit;
}

$cidade_id = (int) ($_GET['cidade'] ?? 1);
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$limite = (int) ($_GET['limite'] ?? 10);
$offset = ($pagina - 1) * $limite;

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
  SELECT FIRST $limite SKIP $offset
    a.ID,
    a.TIPO,
    a.SITUACAO,
    m.NOME AS NOME_MEDICO,
    p.NOME AS PROCEDIMENTO,
    -- Concatena todas as especialidades distintas do médico.
    -- Se não houver especialidades (ex: para agendas de procedimento sem médico ligado a especialidade), será NULL.
    CAST(LIST(DISTINCT e.NOME, ', ') AS VARCHAR(500)) AS ESPECIALIDADE,
    -- Para a coluna 'Especialidade / Exames':
    -- Se for agenda de consulta, mostra a lista de especialidades do médico.
    -- Se for agenda de procedimento, mostra o nome do procedimento (p.NOME).
    COALESCE(CAST(LIST(DISTINCT e.NOME, ', ') AS VARCHAR(500)), p.NOME) AS NOME_AGENDA,
    (
      SELECT CAST(LIST(TRIM(d.DIA_SEMANA), ', ') AS VARCHAR(255))
      FROM AGENDA_HORARIOS d
      WHERE d.AGENDA_ID = a.ID
    ) AS DIAS
  FROM AGENDAS a
  LEFT JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
  LEFT JOIN LAB_MEDICOS_ESPECIALIDADES me ON me.MEDICO_ID = m.ID
  LEFT JOIN ESPECIALIDADES e ON e.ID = me.ESPECIALIDADE_ID
  LEFT JOIN GRUPO_EXAMES p ON p.ID = a.PROCEDIMENTO_ID
  WHERE a.UNIDADE_ID = $cidade_id $condBusca
  -- Agrupa os resultados pela ID da agenda e outros campos não agregados para evitar duplicação
  GROUP BY a.ID, a.TIPO, a.SITUACAO, m.NOME, p.NOME
  ORDER BY a.TIPO, NOME_AGENDA
";

$res = ibase_query($conn, $sql);

// Verificar permissão específica para excluir agendas (ID 97)
$usuario_atual = getUsuarioAtual();
$temPermissaoExcluir = $usuario_atual ? verificarPermissaoUsuario($conn, $usuario_atual, 97) : false;

$count = 0;
while ($row = ibase_fetch_assoc($res)) {
  $count++;

  // Determinar nome correto da agenda
  $nome_agenda_display = '';
  if ($row['TIPO'] === 'consulta') {
    $nome_agenda_display = $row['NOME_MEDICO'];
  } else {
    $nome_agenda_display = !empty($row['NOME_MEDICO']) ? $row['NOME_MEDICO'] : $row['PROCEDIMENTO'];
  }

  $nome_medico = $row['NOME_MEDICO'] ? mb_convert_encoding(trim($row['NOME_MEDICO']), 'UTF-8', 'Windows-1252') : '';
  $procedimento = $row['PROCEDIMENTO'] ? mb_convert_encoding(trim($row['PROCEDIMENTO']), 'UTF-8', 'Windows-1252') : '';
  $especialidade = $row['ESPECIALIDADE'] ? mb_convert_encoding(trim($row['ESPECIALIDADE']), 'UTF-8', 'Windows-1252') : '';
  $nome_agenda = mb_convert_encoding(trim($row['NOME_AGENDA'] ?? ''), 'UTF-8', 'Windows-1252');
  $nome_agenda_display = mb_convert_encoding($nome_agenda_display, 'UTF-8', 'Windows-1252');
  $dias = mb_convert_encoding(trim($row['DIAS'] ?? ''), 'UTF-8', 'Windows-1252');

  $id = (int)$row['ID'];
  $tipo = trim($row['TIPO']);
  $situacao = (int)$row['SITUACAO'];
  $situacao_texto = ($situacao == 1) ? 'Ativa' : 'Inativa';
  $situacao_class = ($situacao == 1) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';

  ?>
  <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
    <td class="px-4 py-3 text-center"><?= $id ?></td>
    <td class="px-4 py-3"><?= htmlspecialchars($nome_agenda_display) ?></td>
    <td class="px-4 py-3"><?= htmlspecialchars($nome_agenda) ?></td>
    <td class="px-4 py-3"><?= htmlspecialchars($dias) ?></td>
    <td class="px-4 py-3 text-center">
      <span class="px-2 py-1 rounded-full text-xs font-medium <?= $tipo === 'consulta' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' ?>">
        <?= ucfirst($tipo) ?>
      </span>
    </td>
    <td class="px-4 py-3 text-center">
      <span class="px-2 py-1 rounded-full text-xs font-medium <?= $situacao_class ?>">
        <?= $situacao_texto ?>
      </span>
    </td>
    <td class="px-4 py-3 text-center">
      <div class="flex items-center justify-center gap-2">
        <button onclick="carregarFormEdicao(<?= $id ?>)"
                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                title="Editar">
          <i class="bi bi-pencil-square text-lg"></i>
        </button>

        <?php if ($situacao == 1): ?>
          <button onclick="alterarSituacao(this, <?= $id ?>, 0)"
                  class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300"
                  title="Desativar">
            <i class="bi bi-pause-circle text-lg"></i>
          </button>
        <?php else: ?>
          <button onclick="alterarSituacao(this, <?= $id ?>, 1)"
                  class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300"
                  title="Ativar">
            <i class="bi bi-play-circle text-lg"></i>
          </button>
        <?php endif; ?>

        <?php if ($temPermissaoExcluir): ?>
          <button onclick="if(confirm('Tem certeza que deseja excluir esta agenda?')) excluirAgenda(<?= $id ?>)"
                  class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                  title="Excluir">
            <i class="bi bi-trash text-lg"></i>
          </button>
        <?php endif; ?>
      </div>
    </td>
  </tr>
  <?php
}

// Se não houver agendas
if ($count === 0) {
  echo '<tr><td colspan="7" class="text-center py-8 text-gray-500">Nenhuma agenda encontrada</td></tr>';
}
?>