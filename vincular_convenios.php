<?php
include 'includes/connection.php';

$host = 'localhost:/opt/banco/oitava_db.fdb';
$conn_iso = ibase_connect($host, 'SYSDBA', 'masterkey');

$cidades = [1 => 'Mossoró', 2 => 'Natal/Zona Norte', 3 => 'Parnamirim', 4 => 'Baraúna', 5 => 'Assú', 8 => 'Santo Antônio', 13 => 'Alto do Rodrigues', 14 => 'Extremoz'];

// Filtros
$convenio_id = isset($_GET['convenio_id']) && $_GET['convenio_id'] !== '' ? intval($_GET['convenio_id']) : null;
$idlocal = isset($_GET['idlocal']) && $_GET['idlocal'] !== '' ? intval($_GET['idlocal']) : null;
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// Buscar categorias (CONVENIOS - 35 registros)
$categorias = [];
$q_cat = ibase_query($conn, "SELECT ID, NOME FROM CONVENIOS ORDER BY NOME");
while ($r = ibase_fetch_assoc($q_cat)) {
    $categorias[] = ['id' => (int)$r['ID'], 'nome' => mb_convert_encoding(trim($r['NOME']), 'UTF-8', 'Windows-1252')];
}

// Processar POST
$mensagem = '';
$tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $post_convenio_id = (int)($_POST['convenio_id'] ?? 0);

    if ($acao === 'vincular' && $post_convenio_id > 0) {
        $lab_ids = $_POST['lab_convenios'] ?? [];
        $adicionados = 0;
        $ja_existentes = 0;

        foreach ($lab_ids as $lab_id) {
            $lab_id = (int)$lab_id;
            // Verificar duplicata
            $q_check = ibase_prepare($conn, "SELECT COUNT(*) as EXISTE FROM CONVENIO_LAB_CONVENIOS WHERE CONVENIO_ID = ? AND LAB_CONVENIO_ID = ?");
            $r_check = ibase_execute($q_check, $post_convenio_id, $lab_id);
            $row = ibase_fetch_assoc($r_check);

            if ($row['EXISTE'] == 0) {
                $q_id = ibase_query($conn, "SELECT GEN_ID(GEN_CONVENIO_LAB_CONVENIOS_ID, 1) as NOVO_ID FROM RDB\$DATABASE");
                $r_id = ibase_fetch_assoc($q_id);
                $novo_id = (int)$r_id['NOVO_ID'];

                $q_ins = ibase_prepare($conn, "INSERT INTO CONVENIO_LAB_CONVENIOS (ID, CONVENIO_ID, LAB_CONVENIO_ID) VALUES (?, ?, ?)");
                ibase_execute($q_ins, $novo_id, $post_convenio_id, $lab_id);
                $adicionados++;
            } else {
                $ja_existentes++;
            }
        }
        ibase_commit($conn);

        $partes = [];
        if ($adicionados > 0) $partes[] = "$adicionados vinculado(s)";
        if ($ja_existentes > 0) $partes[] = "$ja_existentes ja existente(s)";
        $mensagem = implode(', ', $partes) . '.';
        $tipo_msg = $adicionados > 0 ? 'success' : 'info';

        $convenio_id = $post_convenio_id;
    }

    if ($acao === 'desvincular') {
        $vinculo_id = (int)($_POST['vinculo_id'] ?? 0);
        $post_convenio_id = (int)($_POST['convenio_id'] ?? 0);
        if ($vinculo_id > 0) {
            $q_del = ibase_prepare($conn, "DELETE FROM CONVENIO_LAB_CONVENIOS WHERE ID = ?");
            ibase_execute($q_del, $vinculo_id);
            ibase_commit($conn);
            $mensagem = 'Desvinculado com sucesso.';
            $tipo_msg = 'success';
        }
        $convenio_id = $post_convenio_id;
    }

    if ($acao === 'desvincular_todos') {
        $post_convenio_id = (int)($_POST['convenio_id'] ?? 0);
        if ($post_convenio_id > 0) {
            $q_del_all = ibase_prepare($conn, "DELETE FROM CONVENIO_LAB_CONVENIOS WHERE CONVENIO_ID = ?");
            ibase_execute($q_del_all, $post_convenio_id);
            ibase_commit($conn);
            $mensagem = 'Todos os vinculos removidos.';
            $tipo_msg = 'success';
        }
        $convenio_id = $post_convenio_id;
    }
}

// Buscar vinculos existentes da categoria selecionada
$vinculos = [];
if ($convenio_id > 0) {
    $q_vinc = ibase_prepare($conn_iso, "SELECT clc.ID as VINCULO_ID, clc.LAB_CONVENIO_ID, lc.CONVENIO, lc.IDLOCAL, lc.PARTICULAR, lc.PIX, lc.E_CARTAO, lc.CARTAO_BENEFICIO, lc.SOCIO
        FROM CONVENIO_LAB_CONVENIOS clc
        JOIN LAB_CONVENIOS lc ON clc.LAB_CONVENIO_ID = lc.IDCONVENIO
        WHERE clc.CONVENIO_ID = ?
        ORDER BY lc.CONVENIO");
    $r_vinc = ibase_execute($q_vinc, $convenio_id);
    while ($row = ibase_fetch_assoc($r_vinc)) {
        $vinculos[] = [
            'vinculo_id' => (int)$row['VINCULO_ID'],
            'lab_id' => (int)$row['LAB_CONVENIO_ID'],
            'nome' => mb_convert_encoding(trim($row['CONVENIO']), 'UTF-8', 'ISO-8859-1'),
            'idlocal' => $row['IDLOCAL'],
            'particular' => trim($row['PARTICULAR'] ?? ''),
            'pix' => trim($row['PIX'] ?? ''),
            'cartao' => trim($row['E_CARTAO'] ?? ''),
            'beneficio' => trim($row['CARTAO_BENEFICIO'] ?? ''),
            'socio' => trim($row['SOCIO'] ?? '')
        ];
    }
}
$ids_vinculados = array_column($vinculos, 'lab_id');

// Buscar LAB_CONVENIOS para listagem (filtrado)
$lab_convenios = [];
$executar = ($convenio_id > 0);
if ($executar) {
    $sql_lab = "SELECT lc.IDCONVENIO, lc.CONVENIO, lc.IDLOCAL, lc.PARTICULAR, lc.PIX, lc.E_CARTAO, lc.CARTAO_BENEFICIO, lc.SOCIO, lc.SUSPENSO
        FROM LAB_CONVENIOS lc
        WHERE (lc.SUSPENSO IS NULL OR lc.SUSPENSO <> 'S')";

    if ($idlocal) {
        $sql_lab .= " AND lc.IDLOCAL = $idlocal";
    }

    $busca_query = '';
    if ($busca !== '') {
        $busca_query = str_replace("'", "''", iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $busca));
        $sql_lab .= " AND lc.CONVENIO CONTAINING '$busca_query'";
    }

    $sql_lab .= " ORDER BY lc.CONVENIO";

    $q_lab = ibase_query($conn_iso, $sql_lab);
    if ($q_lab) {
        while ($row = ibase_fetch_assoc($q_lab)) {
            $lab_convenios[] = [
                'id' => (int)$row['IDCONVENIO'],
                'nome' => mb_convert_encoding(trim($row['CONVENIO']), 'UTF-8', 'ISO-8859-1'),
                'idlocal' => $row['IDLOCAL'],
                'particular' => trim($row['PARTICULAR'] ?? ''),
                'pix' => trim($row['PIX'] ?? ''),
                'cartao' => trim($row['E_CARTAO'] ?? ''),
                'beneficio' => trim($row['CARTAO_BENEFICIO'] ?? ''),
                'socio' => trim($row['SOCIO'] ?? '')
            ];
        }
    }
}

// Nome da categoria selecionada
$nome_categoria = '';
if ($convenio_id > 0) {
    foreach ($categorias as $cat) {
        if ($cat['id'] == $convenio_id) {
            $nome_categoria = $cat['nome'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vincular Convenios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .table-responsive { max-height: 500px; overflow-y: auto; }
        .table-hover tbody tr:hover { background-color: #0d6efd !important; color: #fff !important; }
        .table-hover tbody tr:hover td, .table-hover tbody tr:hover th { background-color: #0d6efd !important; color: #fff !important; }
        .badge-tipo { font-size: 0.7rem; }
        .ja-vinculado { background-color: #d1e7dd !important; }
        .table-hover tbody tr.ja-vinculado:hover td { background-color: #0d6efd !important; }
    </style>
</head>
<body>
<div class="container-fluid px-4 py-4">
    <div class="mb-4 text-center">
        <h2 class="fw-bold">Vincular Convenios <?= $nome_categoria ? "- $nome_categoria" : '' ?></h2>
    </div>

    <?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo_msg ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($mensagem) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <form method="get" class="card p-4 shadow-sm mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Categoria (CONVENIOS)</label>
                <select name="convenio_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Selecione a categoria</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $convenio_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">Cidade</label>
                <select name="idlocal" class="form-select" onchange="this.form.submit()">
                    <option value="">Todas as cidades</option>
                    <?php foreach ($cidades as $id => $nome): ?>
                    <option value="<?= $id ?>" <?= $id == $idlocal ? 'selected' : '' ?>><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">Buscar convenio</label>
                <input type="text" name="busca" class="form-control" placeholder="Ex: PIX, CARTAO..." value="<?= htmlspecialchars($busca) ?>">
            </div>

            <div class="col-md-1 mt-2">
                <label class="form-label invisible">Filtrar</label>
                <button class="btn btn-primary w-100"><i class="bi bi-funnel-fill"></i> Filtrar</button>
            </div>

            <div class="col-md-2 mt-2">
                <label class="form-label invisible">Voltar</label>
                <a href="vincular_convenios.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-counterclockwise"></i> Limpar
                </a>
            </div>
        </div>
    </form>

    <?php if ($convenio_id > 0): ?>
    <div class="row">
        <!-- Vinculados (esquerda) -->
        <div class="col-md-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-check2-square"></i> Vinculados a "<?= htmlspecialchars($nome_categoria) ?>"</span>
                    <span class="badge bg-light text-success"><?= count($vinculos) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($vinculos)): ?>
                    <div class="alert alert-warning m-3 mb-0">Nenhum convenio vinculado.</div>
                    <?php else: ?>
                    <?php
                    // Agrupar vinculos por cidade
                    $vinculos_por_cidade = [];
                    foreach ($vinculos as $v) {
                        $cidade_nome = $cidades[$v['idlocal']] ?? 'Sem cidade';
                        $vinculos_por_cidade[$cidade_nome][] = $v;
                    }
                    ksort($vinculos_por_cidade);
                    ?>
                    <div style="max-height: 500px; overflow-y: auto;">
                        <?php foreach ($vinculos_por_cidade as $cidade_nome => $vinculos_cidade): ?>
                        <div class="border-bottom">
                            <div class="bg-light px-3 py-2 fw-semibold d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-geo-alt-fill text-danger"></i> <?= htmlspecialchars($cidade_nome) ?></span>
                                <span class="badge bg-secondary"><?= count($vinculos_cidade) ?></span>
                            </div>
                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                    <?php foreach ($vinculos_cidade as $v): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <?= htmlspecialchars($v['nome']) ?>
                                            <small class="text-muted">(<?= $v['lab_id'] ?>)</small>
                                        </td>
                                        <td class="text-center" style="width:90px">
                                            <?php if ($v['particular'] === 'S'): ?><span class="badge bg-primary badge-tipo">PART</span><?php endif; ?>
                                            <?php if ($v['pix'] === 'S'): ?><span class="badge bg-info badge-tipo">PIX</span><?php endif; ?>
                                            <?php if ($v['cartao'] === 'S'): ?><span class="badge bg-warning text-dark badge-tipo">CART</span><?php endif; ?>
                                            <?php if ($v['beneficio'] === 'S'): ?><span class="badge bg-danger badge-tipo">DESC</span><?php endif; ?>
                                            <?php if ($v['socio'] === 'S'): ?><span class="badge bg-secondary badge-tipo">SOC</span><?php endif; ?>
                                        </td>
                                        <td class="text-center" style="width:40px">
                                            <form method="post" class="d-inline" onsubmit="return confirm('Desvincular?')">
                                                <input type="hidden" name="acao" value="desvincular">
                                                <input type="hidden" name="convenio_id" value="<?= $convenio_id ?>">
                                                <input type="hidden" name="vinculo_id" value="<?= $v['vinculo_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger p-0 px-1" title="Desvincular">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="p-2 text-end">
                        <form method="post" class="d-inline" onsubmit="return confirm('Remover TODOS os vinculos de <?= htmlspecialchars($nome_categoria, ENT_QUOTES) ?>?')">
                            <input type="hidden" name="acao" value="desvincular_todos">
                            <input type="hidden" name="convenio_id" value="<?= $convenio_id ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i> Remover todos
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- LAB_CONVENIOS para vincular (direita) -->
        <div class="col-md-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul"></i> Convenios Disponiveis (LAB_CONVENIOS)</span>
                    <span class="badge bg-light text-primary"><?= count($lab_convenios) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($lab_convenios)): ?>
                    <div class="alert alert-info m-3 mb-0">Selecione uma cidade ou busque para listar convenios.</div>
                    <?php else: ?>
                    <form method="post" id="formVincular">
                        <input type="hidden" name="acao" value="vincular">
                        <input type="hidden" name="convenio_id" value="<?= $convenio_id ?>">

                        <div class="p-2 d-flex gap-2 align-items-center border-bottom">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSelTodos">
                                <i class="bi bi-check-all"></i> Todos
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSelNenhum">
                                <i class="bi bi-x-circle"></i> Nenhum
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSelNaoVinculados">
                                <i class="bi bi-plus-circle"></i> Apenas novos
                            </button>
                            <div class="ms-auto">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="bi bi-link-45deg"></i> Vincular selecionados
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm align-middle table-hover mb-0">
                                <thead class="table-primary sticky-top">
                                    <tr>
                                        <th class="text-center" style="width:40px">
                                            <input type="checkbox" id="checkAll" class="form-check-input">
                                        </th>
                                        <th>Convenio</th>
                                        <th class="text-center" style="width:80px">Cidade</th>
                                        <th class="text-center" style="width:120px">Tipo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lab_convenios as $lc): ?>
                                    <?php $ja = in_array($lc['id'], $ids_vinculados); ?>
                                    <tr class="<?= $ja ? 'ja-vinculado' : '' ?>">
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input lab-check"
                                                   name="lab_convenios[]" value="<?= $lc['id'] ?>"
                                                   data-vinculado="<?= $ja ? '1' : '0' ?>"
                                                   <?= $ja ? 'checked disabled' : '' ?>>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($lc['nome']) ?>
                                            <small class="text-muted">(<?= $lc['id'] ?>)</small>
                                            <?php if ($ja): ?><span class="badge bg-success badge-tipo">vinculado</span><?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <small><?= $cidades[$lc['idlocal']] ?? '-' ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($lc['particular'] === 'S'): ?><span class="badge bg-primary badge-tipo">PART</span><?php endif; ?>
                                            <?php if ($lc['pix'] === 'S'): ?><span class="badge bg-info badge-tipo">PIX</span><?php endif; ?>
                                            <?php if ($lc['cartao'] === 'S'): ?><span class="badge bg-warning text-dark badge-tipo">CART</span><?php endif; ?>
                                            <?php if ($lc['beneficio'] === 'S'): ?><span class="badge bg-danger badge-tipo">DESC</span><?php endif; ?>
                                            <?php if ($lc['socio'] === 'S'): ?><span class="badge bg-secondary badge-tipo">SOC</span><?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="p-2 text-end border-top">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-link-45deg"></i> Vincular selecionados
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php elseif (!$convenio_id): ?>
    <div class="alert alert-warning text-center mt-4">Selecione a categoria do convenio para iniciar.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.lab-check:not(:disabled)').forEach(cb => cb.checked = this.checked);
});

document.getElementById('btnSelTodos')?.addEventListener('click', function() {
    document.querySelectorAll('.lab-check:not(:disabled)').forEach(cb => cb.checked = true);
});

document.getElementById('btnSelNenhum')?.addEventListener('click', function() {
    document.querySelectorAll('.lab-check:not(:disabled)').forEach(cb => cb.checked = false);
});

document.getElementById('btnSelNaoVinculados')?.addEventListener('click', function() {
    document.querySelectorAll('.lab-check').forEach(cb => {
        if (cb.dataset.vinculado === '0') cb.checked = true;
        else cb.checked = false;
    });
});
</script>
</body>
</html>
