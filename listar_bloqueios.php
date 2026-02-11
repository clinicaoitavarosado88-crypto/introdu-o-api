<?php
// listar_bloqueios.php - Lista bloqueios ativos
include 'includes/connection.php';
include 'includes/verificar_permissao.php';

// Verificar permissão para administrar agendas
if (!verificarPermissaoAdminAgenda($conn, 'listar bloqueios')) {
    exit;
}

$cidade_id = (int) ($_GET['cidade'] ?? 1);
$agenda_id = isset($_GET['agenda_id']) ? (int) $_GET['agenda_id'] : null;

$condicao_agenda = $agenda_id ? "AND b.AGENDA_ID = $agenda_id" : "";

$sql = "
    SELECT 
        b.ID,
        b.AGENDA_ID,
        b.TIPO_BLOQUEIO,
        b.DATA_BLOQUEIO,
        b.DATA_INICIO,
        b.DATA_FIM,
        b.HORARIO_INICIO,
        b.HORARIO_FIM,
        b.MOTIVO,
        b.USUARIO_BLOQUEIO,
        b.DATA_CRIACAO,
        COALESCE(m.NOME, p.NOME) AS NOME_AGENDA,
        a.TIPO AS TIPO_AGENDA
    FROM AGENDA_BLOQUEIOS b
    JOIN AGENDAS a ON a.ID = b.AGENDA_ID
    LEFT JOIN LAB_MEDICOS_PRES m ON m.ID = a.MEDICO_ID
    LEFT JOIN GRUPO_EXAMES p ON p.ID = a.PROCEDIMENTO_ID
    WHERE b.ATIVO = 1 
    AND a.UNIDADE_ID = $cidade_id
    $condicao_agenda
    ORDER BY b.DATA_CRIACAO DESC
";

$result = ibase_query($conn, $sql);
?>

<div class="p-6">
    <div class="flex justify-between items-start flex-wrap gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-shield-x"></i> Bloqueios Ativos
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">Gerencie bloqueios de agendas, dias e horários</p>
        </div>
        <button onclick="window.history.back()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            <i class="bi bi-arrow-left"></i> Voltar
        </button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                        <th class="px-4 py-3 text-left">Agenda</th>
                        <th class="px-4 py-3 text-left">Tipo</th>
                        <th class="px-4 py-3 text-left">Período/Data</th>
                        <th class="px-4 py-3 text-left">Horário</th>
                        <th class="px-4 py-3 text-left">Motivo</th>
                        <th class="px-4 py-3 text-left">Criado por</th>
                        <th class="px-4 py-3 text-left">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$result || !ibase_fetch_assoc($result)): ?>
                        <?php ibase_query($conn, $sql); $result = ibase_query($conn, $sql); ?>
                    <?php endif; ?>
                    
                    <?php while ($row = ibase_fetch_assoc($result)): ?>
                        <tr class="border-t hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-3">
                                <div class="font-medium"><?= htmlspecialchars(mb_convert_encoding($row['NOME_AGENDA'], 'UTF-8', 'Windows-1252')) ?></div>
                                <div class="text-xs text-gray-500">ID: <?= $row['AGENDA_ID'] ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <?php
                                switch ($row['TIPO_BLOQUEIO']) {
                                    case 'DIA':
                                        echo '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs">Dia</span>';
                                        break;
                                    case 'HORARIO':
                                        echo '<span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs">Horário</span>';
                                        break;
                                    case 'AGENDA_PERMANENTE':
                                        echo '<span class="bg-red-200 text-red-900 px-2 py-1 rounded text-xs">Agenda (Permanente)</span>';
                                        break;
                                    case 'AGENDA_TEMPORARIO':
                                        echo '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">Agenda (Temporário)</span>';
                                        break;
                                }
                                ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($row['DATA_BLOQUEIO']): ?>
                                    <?= date('d/m/Y', strtotime($row['DATA_BLOQUEIO'])) ?>
                                <?php elseif ($row['DATA_INICIO'] && $row['DATA_FIM']): ?>
                                    <?= date('d/m/Y', strtotime($row['DATA_INICIO'])) ?> a <?= date('d/m/Y', strtotime($row['DATA_FIM'])) ?>
                                <?php elseif ($row['DATA_INICIO']): ?>
                                    A partir de <?= date('d/m/Y', strtotime($row['DATA_INICIO'])) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($row['HORARIO_INICIO'] && $row['HORARIO_FIM']): ?>
                                    <?= date('H:i', strtotime($row['HORARIO_INICIO'])) ?> às <?= date('H:i', strtotime($row['HORARIO_FIM'])) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="max-w-xs truncate" title="<?= htmlspecialchars($row['MOTIVO']) ?>">
                                    <?= htmlspecialchars($row['MOTIVO']) ?: 'Sem motivo informado' ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div><?= htmlspecialchars($row['USUARIO_BLOQUEIO']) ?></div>
                                <div class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($row['DATA_CRIACAO'])) ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <button onclick="desbloquear(<?= $row['ID'] ?>, '<?= addslashes($row['TIPO_BLOQUEIO']) ?>')" 
                                        class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700">
                                    <i class="bi bi-unlock"></i> Desbloquear
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php 
                    // Resetar resultado para verificar se está vazio
                    $result_count = ibase_query($conn, str_replace("SELECT", "SELECT COUNT(*) as TOTAL", $sql));
                    $count_row = ibase_fetch_assoc($result_count);
                    if ($count_row['TOTAL'] == 0): 
                    ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                <i class="bi bi-shield-check text-4xl mb-2"></i>
                                <div>Nenhum bloqueio ativo encontrado</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function desbloquear(bloqueioId, tipoBloqueio) {
    if (!confirm(`Tem certeza que deseja remover este bloqueio (${tipoBloqueio})?`)) {
        return;
    }
    
    fetch('processar_bloqueio.php?acao=desbloquear', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `bloqueio_id=${bloqueioId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'sucesso') {
            location.reload(); // Recarregar para atualizar lista
        } else {
            alert(data.mensagem || 'Erro ao desbloquear');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar desbloqueio');
    });
}
</script>