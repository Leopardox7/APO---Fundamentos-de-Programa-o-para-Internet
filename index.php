<?php
require __DIR__ . '/config.php';

$page = $_GET['page'] ?? 'inicio';
$allowed = ['inicio', 'produtos', 'enderecos', 'transportadores', 'fretes', 'relatorios'];
if (!in_array($page, $allowed, true))
    $page = 'inicio';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'delete') {
            $table = $_POST['table'] ?? '';
            $id = (int) ($_POST['id'] ?? 0);
            $tables = ['produtos' => 'produtos', 'enderecos' => 'enderecos', 'transportadores' => 'transportadores'];
            if (!isset($tables[$table]))
                throw new RuntimeException('Operação não permitida.');
            db()->prepare("DELETE FROM {$tables[$table]} WHERE id=?")->execute([$id]);
            flash('Registro excluído com sucesso.');
            redirect($table);
        }

        if ($action === 'save_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            if ($nome === '')
                throw new RuntimeException('Informe o nome do produto.');
            $data = [$nome, trim($_POST['descricao'] ?? ''), trim($_POST['categoria'] ?? ''), trim($_POST['unidade'] ?? 'un'), (float) ($_POST['peso'] ?? 0)];
            if ($id) {
                $data[] = $id;
                db()->prepare('UPDATE produtos SET nome=?,descricao=?,categoria=?,unidade=?,peso=? WHERE id=?')->execute($data);
            } else
                db()->prepare('INSERT INTO produtos(nome,descricao,categoria,unidade,peso) VALUES(?,?,?,?,?)')->execute($data);
            flash('Produto salvo com sucesso.');
            redirect('produtos');
        }

        if ($action === 'save_address') {
            $id = (int) ($_POST['id'] ?? 0);
            $ident = trim($_POST['identificacao'] ?? '');
            if ($ident === '' || trim($_POST['logradouro'] ?? '') === '' || trim($_POST['cidade'] ?? '') === '' || trim($_POST['estado'] ?? '') === '')
                throw new RuntimeException('Preencha identificação, logradouro, cidade e estado.');
            $data = [$ident, $_POST['tipo'] ?? 'ambos', trim($_POST['logradouro'] ?? ''), trim($_POST['numero'] ?? ''), trim($_POST['complemento'] ?? ''), trim($_POST['bairro'] ?? ''), trim($_POST['cidade'] ?? ''), strtoupper(substr(trim($_POST['estado'] ?? ''), 0, 2)), trim($_POST['cep'] ?? '')];
            if ($id) {
                $data[] = $id;
                db()->prepare('UPDATE enderecos SET identificacao=?,tipo=?,logradouro=?,numero=?,complemento=?,bairro=?,cidade=?,estado=?,cep=? WHERE id=?')->execute($data);
            } else
                db()->prepare('INSERT INTO enderecos(identificacao,tipo,logradouro,numero,complemento,bairro,cidade,estado,cep) VALUES(?,?,?,?,?,?,?,?,?)')->execute($data);
            flash('Endereço salvo com sucesso.');
            redirect('enderecos');
        }

        if ($action === 'save_carrier') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            if ($nome === '')
                throw new RuntimeException('Informe o nome do transportador.');
            $data = [$nome, trim($_POST['documento'] ?? ''), trim($_POST['telefone'] ?? ''), trim($_POST['email'] ?? '')];
            if ($id) {
                $data[] = $id;
                db()->prepare('UPDATE transportadores SET nome=?,documento=?,telefone=?,email=? WHERE id=?')->execute($data);
            } else
                db()->prepare('INSERT INTO transportadores(nome,documento,telefone,email) VALUES(?,?,?,?)')->execute($data);
            flash('Transportador salvo com sucesso.');
            redirect('transportadores');
        }

        if ($action === 'save_freight') {
            $carrier = (int) ($_POST['transportador_id'] ?? 0);
            $origin = (int) ($_POST['origem_id'] ?? 0);
            $dest = (int) ($_POST['destino_id'] ?? 0);
            $products = $_POST['produtos'] ?? [];
            $quantities = $_POST['quantidades'] ?? [];
            if (!$carrier || !$origin || !$dest || !$products)
                throw new RuntimeException('Selecione transportador, origem, destino e ao menos um produto.');
            if ($origin === $dest)
                throw new RuntimeException('Origem e destino devem ser diferentes.');
            $pdo = db();
            $pdo->beginTransaction();
            $code = 'FR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $stmt = $pdo->prepare('INSERT INTO fretes(codigo,transportador_id,origem_id,destino_id,data_frete,previsao_entrega,status,observacoes) VALUES(?,?,?,?,?,?,?,?)');
            $stmt->execute([$code, $carrier, $origin, $dest, $_POST['data_frete'] ?? date('Y-m-d'), ($_POST['previsao_entrega'] ?? '') ?: null, $_POST['status'] ?? 'Pendente', trim($_POST['observacoes'] ?? '')]);
            $freightId = (int) $pdo->lastInsertId();
            $insert = $pdo->prepare('INSERT INTO frete_produtos(frete_id,produto_id,quantidade) VALUES(?,?,?)');
            foreach ($products as $productId) {
                $qty = max(1, (int) ($quantities[$productId] ?? 1));
                $insert->execute([$freightId, (int) $productId, $qty]);
            }
            $pdo->commit();
            flash("Frete {$code} gerado com sucesso.");
            redirect('fretes');
        }

        if ($action === 'update_status') {
            db()->prepare('UPDATE fretes SET status=? WHERE id=?')->execute([$_POST['status'] ?? 'Pendente', (int) ($_POST['id'] ?? 0)]);
            flash('Status do frete atualizado.');
            redirect('fretes');
        }
    } catch (Throwable $ex) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction())
            $pdo->rollBack();
        flash('Não foi possível concluir: ' . $ex->getMessage(), 'error');
        redirect($page);
    }
}

$flash = take_flash();
function rows(string $sql, array $params = []): array
{
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
}
function one(string $sql, array $params = []): ?array
{
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s->fetch() ?: null;
}

if ($page === 'relatorios' && isset($_GET['csv'])) {
    $date = $_GET['data'] ?? date('Y-m-d');
    $data = rows("SELECT f.codigo,f.data_frete,f.previsao_entrega,f.status,t.nome transportador,eo.identificacao origem,ed.identificacao destino,GROUP_CONCAT(CONCAT(p.nome,' (',fp.quantidade,')') SEPARATOR '; ') produtos FROM fretes f JOIN transportadores t ON t.id=f.transportador_id JOIN enderecos eo ON eo.id=f.origem_id JOIN enderecos ed ON ed.id=f.destino_id LEFT JOIN frete_produtos fp ON fp.frete_id=f.id LEFT JOIN produtos p ON p.id=fp.produto_id WHERE f.data_frete=? GROUP BY f.id ORDER BY f.id DESC", [$date]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio-fretes-' . $date . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Código', 'Data', 'Previsão', 'Status', 'Transportador', 'Origem', 'Destino', 'Produtos'], ';');
    foreach ($data as $r)
        fputcsv($out, array_values($r), ';');
    fclose($out);
    exit;
}

$products = rows('SELECT * FROM produtos ORDER BY nome');
$addresses = rows('SELECT * FROM enderecos ORDER BY identificacao');
$carriers = rows('SELECT * FROM transportadores ORDER BY nome');
$freights = rows('SELECT f.*,t.nome transportador,eo.identificacao origem,ed.identificacao destino FROM fretes f JOIN transportadores t ON t.id=f.transportador_id JOIN enderecos eo ON eo.id=f.origem_id JOIN enderecos ed ON ed.id=f.destino_id ORDER BY f.id DESC');
$counts = ['produtos' => count($products), 'enderecos' => count($addresses), 'transportadores' => count($carriers), 'fretes' => count($freights)];
$edit = null;
if (isset($_GET['edit'])) {
    $table = ['produtos' => 'produtos', 'enderecos' => 'enderecos', 'transportadores' => 'transportadores'][$page] ?? null;
    if ($table)
        $edit = one("SELECT * FROM {$table} WHERE id=? ", [(int) $_GET['edit']]);
}
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>LogiControl | Gestão logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark topbar">
        <div class="container-fluid"><a class="navbar-brand fw-bold" href="index.php">LogiControl</a><span
                class="text-white-50 small">Gestão logística simplificada</span></div>
    </nav>
    <div class="container-fluid">
        <div class="row">
            <aside class="col-lg-2 sidebar p-3">
                <div class="small text-uppercase text-secondary fw-bold mb-2">Menu principal</div>
                <?php foreach (['inicio' => 'Visão geral', 'produtos' => 'Produtos', 'enderecos' => 'Endereços', 'transportadores' => 'Transportadores', 'fretes' => 'Fretes', 'relatorios' => 'Relatórios'] as $key => $label): ?><a
                        class="nav-link <?= $page === $key ? 'active' : '' ?>"
                        href="index.php?page=<?= $key ?>"><?= e($label) ?></a><?php endforeach; ?>
                <div class="mt-4 small text-secondary">Versão 1.0 · Xampp</div>
            </aside>
            <main class="col-lg-10 p-4">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash[1] === 'error' ? 'danger' : 'success' ?>"><?= e($flash[0]) ?></div>
                <?php endif; ?>
                <?php if ($page === 'inicio'): ?>
                    <div class="mb-4">
                        <h1 class="h3 fw-bold">Visão geral</h1>
                        <p class="text-secondary">Acompanhe os principais registros da operação.</p>
                    </div>
                    <div class="row g-3">
                        <?php foreach (['produtos' => 'Produtos', 'enderecos' => 'Endereços', 'transportadores' => 'Transportadores', 'fretes' => 'Fretes'] as $k => $v): ?>
                            <div class="col-md-6 col-xl-3">
                                <div class="stat-card">
                                    <div class="text-secondary"><?= $v ?></div>
                                    <div class="display-6 fw-semibold"><?= $counts[$k] ?></div>
                                </div>
                            </div><?php endforeach; ?>
                    </div>
                    <div class="card mt-4">
                        <div class="card-body">
                            <h2 class="h5">Comece por aqui</h2>
                            <p class="mb-0 text-secondary">Cadastre produtos, endereços e transportadores. Depois, gere um
                                frete vinculando esses registros.</p>
                        </div>
                    </div>
                <?php elseif ($page === 'produtos'): ?>
                    <h1 class="h3 fw-bold">Produtos</h1>
                    <div class="row g-4">
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-body">
                                    <h2 class="h5"><?= $edit ? 'Editar' : 'Novo' ?> produto</h2>
                                    <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input
                                            type="hidden" name="action" value="save_product"><input type="hidden" name="id"
                                            value="<?= e($edit['id'] ?? '') ?>"><label class="form-label">Nome *</label><input
                                            class="form-control mb-3" name="nome" required
                                            value="<?= e($edit['nome'] ?? '') ?>"><label
                                            class="form-label">Descrição</label><textarea class="form-control mb-3"
                                            name="descricao"><?= e($edit['descricao'] ?? '') ?></textarea><label
                                            class="form-label">Categoria</label><input class="form-control mb-3"
                                            name="categoria" value="<?= e($edit['categoria'] ?? '') ?>">
                                        <div class="row">
                                            <div class="col"><label class="form-label">Unidade</label><input
                                                    class="form-control mb-3" name="unidade"
                                                    value="<?= e($edit['unidade'] ?? 'un') ?>"></div>
                                            <div class="col"><label class="form-label">Peso (kg)</label><input
                                                    class="form-control mb-3" type="number" min="0" step=".001" name="peso"
                                                    value="<?= e($edit['peso'] ?? '0') ?>"></div>
                                        </div><button class="btn btn-primary">Salvar produto</button><?php if ($edit): ?> <a
                                                class="btn btn-light" href="index.php?page=produtos">Cancelar</a><?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-8">
                            <div class="card">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Categoria</th>
                                                <th>Peso</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody><?php foreach ($products as $p): ?>
                                                <tr>
                                                    <td><b><?= e($p['nome']) ?></b>
                                                        <div class="small text-secondary"><?= e($p['descricao']) ?></div>
                                                    </td>
                                                    <td><?= e($p['categoria']) ?></td>
                                                    <td><?= e($p['peso']) ?> kg</td>
                                                    <td><a class="btn btn-sm btn-outline-primary"
                                                            href="?page=produtos&edit=<?= $p['id'] ?>">Editar</a>
                                                        <form class="d-inline" method="post"
                                                            onsubmit="return confirm('Excluir este produto?')"><input
                                                                type="hidden" name="csrf" value="<?= csrf() ?>"><input
                                                                type="hidden" name="action" value="delete"><input type="hidden"
                                                                name="table" value="produtos"><input type="hidden" name="id"
                                                                value="<?= $p['id'] ?>"><button
                                                                class="btn btn-sm btn-outline-danger">Excluir</button></form>
                                                    </td>
                                                </tr><?php endforeach; ?><?php if (!$products): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center text-secondary p-4">Nenhum produto
                                                        cadastrado.</td>
                                                </tr><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($page === 'enderecos'): ?>
                    <h1 class="h3 fw-bold">Endereços</h1>
                    <div class="row g-4">
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-body">
                                    <h2 class="h5"><?= $edit ? 'Editar' : 'Novo' ?> endereço</h2>
                                    <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input
                                            type="hidden" name="action" value="save_address"><input type="hidden" name="id"
                                            value="<?= e($edit['id'] ?? '') ?>"><?php $fields = ['identificacao' => 'Identificação', 'logradouro' => 'Logradouro', 'numero' => 'Número', 'complemento' => 'Complemento', 'bairro' => 'Bairro', 'cidade' => 'Cidade', 'estado' => 'Estado', 'cep' => 'CEP'];
                                            foreach ($fields as $k => $v): ?><label
                                                class="form-label"><?= $v ?><?= in_array($k, ['identificacao', 'logradouro', 'cidade', 'estado']) ? ' *' : '' ?></label><input
                                                class="form-control mb-2" name="<?= $k ?>"
                                                <?= in_array($k, ['identificacao', 'logradouro', 'cidade', 'estado']) ? 'required' : '' ?>
                                                value="<?= e($edit[$k] ?? '') ?>"><?php endforeach; ?><label
                                            class="form-label">Tipo</label><select class="form-select mb-3"
                                            name="tipo"><?php foreach (['origem' => 'Origem', 'destino' => 'Destino', 'ambos' => 'Ambos'] as $k => $v): ?>
                                                <option value="<?= $k ?>" <?= ($edit['tipo'] ?? 'ambos') === $k ? 'selected' : '' ?>><?= $v ?>
                                                </option><?php endforeach; ?>
                                        </select><button class="btn btn-primary">Salvar endereço</button></form>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-8">
                            <div class="card table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Identificação</th>
                                            <th>Endereço</th>
                                            <th>Tipo</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($addresses as $a): ?>
                                            <tr>
                                                <td><?= e($a['identificacao']) ?></td>
                                                <td><?= e($a['logradouro']) ?>, <?= e($a['numero']) ?> —
                                                    <?= e($a['cidade']) ?>/<?= e($a['estado']) ?></td>
                                                <td><?= e($a['tipo']) ?></td>
                                                <td><a class="btn btn-sm btn-outline-primary"
                                                        href="?page=enderecos&edit=<?= $a['id'] ?>">Editar</a>
                                                    <form class="d-inline" method="post"
                                                        onsubmit="return confirm('Excluir endereço?')"><input type="hidden"
                                                            name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action"
                                                            value="delete"><input type="hidden" name="table"
                                                            value="enderecos"><input type="hidden" name="id"
                                                            value="<?= $a['id'] ?>"><button
                                                            class="btn btn-sm btn-outline-danger">Excluir</button></form>
                                                </td>
                                            </tr><?php endforeach; ?><?php if (!$addresses): ?>
                                            <tr>
                                                <td colspan="4" class="p-4 text-center text-secondary">Nenhum endereço
                                                    cadastrado.</td>
                                            </tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php elseif ($page === 'transportadores'): ?>
                    <h1 class="h3 fw-bold">Transportadores</h1>
                    <div class="row g-4">
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-body">
                                    <h2 class="h5"><?= $edit ? 'Editar' : 'Novo' ?> transportador</h2>
                                    <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input
                                            type="hidden" name="action" value="save_carrier"><input type="hidden" name="id"
                                            value="<?= e($edit['id'] ?? '') ?>"><?php foreach (['nome' => 'Nome', 'documento' => 'CPF/CNPJ', 'telefone' => 'Telefone', 'email' => 'E-mail'] as $k => $v): ?><label
                                                class="form-label"><?= $v ?><?= $k === 'nome' ? ' *' : '' ?></label><input
                                                class="form-control mb-3" name="<?= $k ?>" <?= $k === 'nome' ? 'required' : '' ?>
                                                value="<?= e($edit[$k] ?? '') ?>"><?php endforeach; ?><button
                                            class="btn btn-primary">Salvar transportador</button></form>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-8">
                            <div class="card table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Documento</th>
                                            <th>Contato</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($carriers as $c): ?>
                                            <tr>
                                                <td><?= e($c['nome']) ?></td>
                                                <td><?= e($c['documento']) ?></td>
                                                <td><?= e($c['telefone']) ?>
                                                    <div class="small text-secondary"><?= e($c['email']) ?></div>
                                                </td>
                                                <td><a class="btn btn-sm btn-outline-primary"
                                                        href="?page=transportadores&edit=<?= $c['id'] ?>">Editar</a>
                                                    <form class="d-inline" method="post"
                                                        onsubmit="return confirm('Excluir transportador?')"><input type="hidden"
                                                            name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action"
                                                            value="delete"><input type="hidden" name="table"
                                                            value="transportadores"><input type="hidden" name="id"
                                                            value="<?= $c['id'] ?>"><button
                                                            class="btn btn-sm btn-outline-danger">Excluir</button></form>
                                                </td>
                                            </tr><?php endforeach; ?><?php if (!$carriers): ?>
                                            <tr>
                                                <td colspan="4" class="p-4 text-center text-secondary">Nenhum transportador
                                                    cadastrado.</td>
                                            </tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php elseif ($page === 'fretes'): ?>
                    <h1 class="h3 fw-bold">Fretes</h1>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h2 class="h5">Gerar novo frete</h2>
                            <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden"
                                    name="action" value="save_freight">
                                <div class="row g-3">
                                    <div class="col-md-4"><label class="form-label">Transportador *</label><select
                                            class="form-select" name="transportador_id" required>
                                            <option value="">Selecione</option><?php foreach ($carriers as $c): ?>
                                                <option value="<?= $c['id'] ?>"><?= e($c['nome']) ?></option><?php endforeach; ?>
                                        </select></div>
                                    <div class="col-md-4"><label class="form-label">Origem *</label><select
                                            class="form-select" name="origem_id" required>
                                            <option value="">Selecione</option>
                                            <?php foreach ($addresses as $a):
                                                if ($a['tipo'] !== 'destino'): ?>
                                                    <option value="<?= $a['id'] ?>"><?= e($a['identificacao']) ?></option>
                                                <?php endif; endforeach; ?>
                                        </select></div>
                                    <div class="col-md-4"><label class="form-label">Destino *</label><select
                                            class="form-select" name="destino_id" required>
                                            <option value="">Selecione</option>
                                            <?php foreach ($addresses as $a):
                                                if ($a['tipo'] !== 'origem'): ?>
                                                    <option value="<?= $a['id'] ?>"><?= e($a['identificacao']) ?></option>
                                                <?php endif; endforeach; ?>
                                        </select></div>
                                    <div class="col-md-3"><label class="form-label">Data do frete *</label><input
                                            class="form-control" type="date" name="data_frete" value="<?= date('Y-m-d') ?>"
                                            required></div>
                                    <div class="col-md-3"><label class="form-label">Previsão de entrega</label><input
                                            class="form-control" type="date" name="previsao_entrega"></div>
                                    <div class="col-md-3"><label class="form-label">Status</label><select
                                            class="form-select"
                                            name="status"><?php foreach (['Pendente', 'Em trânsito', 'Entregue', 'Cancelado'] as $s): ?>
                                                <option><?= e($s) ?></option><?php endforeach; ?>
                                        </select></div>
                                    <div class="col-md-12"><label class="form-label">Produtos e quantidades *</label>
                                        <div class="row"><?php foreach ($products as $p): ?>
                                                <div class="col-md-6 col-xl-4">
                                                    <div class="product-pick"><label class="form-check-label"><input
                                                                class="form-check-input me-2" type="checkbox" name="produtos[]"
                                                                value="<?= $p['id'] ?>"><?= e($p['nome']) ?></label><input
                                                            class="form-control form-control-sm mt-2" type="number" min="1"
                                                            name="quantidades[<?= $p['id'] ?>]" value="1"
                                                            aria-label="Quantidade de <?= e($p['nome']) ?>"></div>
                                                </div><?php endforeach; ?>
                                        </div><?php if (!$products): ?>
                                            <p class="text-danger small">Cadastre ao menos um produto antes de gerar fretes.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-12"><label class="form-label">Observações</label><textarea
                                            class="form-control" name="observacoes"></textarea></div>
                                    <div class="col-12"><button class="btn btn-primary"
                                            <?= (!$products || !$carriers || !$addresses) ? 'disabled' : '' ?>>Gerar frete</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Data</th>
                                    <th>Transportador</th>
                                    <th>Trajeto</th>
                                    <th>Status</th>
                                    <th>Atualizar</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($freights as $f): ?>
                                    <tr>
                                        <td><b><?= e($f['codigo']) ?></b></td>
                                        <td><?= e($f['data_frete']) ?></td>
                                        <td><?= e($f['transportador']) ?></td>
                                        <td><?= e($f['origem']) ?> → <?= e($f['destino']) ?></td>
                                        <td><?= e($f['status']) ?></td>
                                        <td>
                                            <form method="post" class="d-flex gap-2"><input type="hidden" name="csrf"
                                                    value="<?= csrf() ?>"><input type="hidden" name="action"
                                                    value="update_status"><input type="hidden" name="id"
                                                    value="<?= $f['id'] ?>"><select class="form-select form-select-sm"
                                                    name="status"><?php foreach (['Pendente', 'Em trânsito', 'Entregue', 'Cancelado'] as $s): ?>
                                                        <option <?= $f['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                                    <?php endforeach; ?>
                                                </select><button class="btn btn-sm btn-outline-primary">Salvar</button></form>
                                        </td>
                                    </tr><?php endforeach; ?><?php if (!$freights): ?>
                                    <tr>
                                        <td colspan="6" class="p-4 text-center text-secondary">Nenhum frete gerado.</td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($page === 'relatorios'): ?>
                    <h1 class="h3 fw-bold">Relatórios</h1>
                    <div class="card">
                        <div class="card-body">
                            <h2 class="h5">Fretes por data</h2>
                            <form class="row g-3 align-items-end" method="get"><input type="hidden" name="page"
                                    value="relatorios">
                                <div class="col-md-4"><label class="form-label">Data do frete</label><input
                                        class="form-control" type="date" name="data"
                                        value="<?= e($_GET['data'] ?? date('Y-m-d')) ?>"></div>
                                <div class="col-auto"><button class="btn btn-primary" name="csv" value="1">Baixar relatório
                                        CSV</button></div>
                            </form>
                            <p class="small text-secondary mt-3 mb-0">O arquivo pode ser aberto no Excel ou LibreOffice.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>