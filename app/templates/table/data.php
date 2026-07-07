<?php
/**
 * @var string $database
 * @var string $schema
 * @var string $table
 * @var list<array<string, mixed>> $rows
 * @var list<string> $headers
 * @var int $page
 * @var int $totalPages
 * @var int $total
 * @var ?string $sort
 * @var string $dir
 * @var ?string $filterCol
 * @var ?string $filterVal
 * @var string $filterOp
 * @var array<string, string> $operators
 * @var string $executedSql
 * @var list<string> $primaryKey
 * @var bool $isTable
 * @var string $csrf
 * @var ?string $lastSql
 */
$base    = sprintf('/db/%s/table/%s/%s', rawurlencode($database), rawurlencode($schema), rawurlencode($table));
$dataUrl = $base . '/data';
$canEditRows = ($primaryKey ?? []) !== [];

// Paramètres courants (filtre + tri) à propager dans les liens.
$current = [];
if ($filterCol !== null && $filterVal !== null && $filterVal !== '') {
    $current['filter_col'] = $filterCol;
    $current['filter_val'] = $filterVal;
    $current['filter_op']  = $filterOp;
}
if ($sort !== null) {
    $current['sort'] = $sort;
    $current['dir']  = $dir;
}

/** Construit une URL en fusionnant des paramètres avec les paramètres courants. */
$url = static function (array $extra) use ($dataUrl, $current): string {
    $params = array_merge($current, $extra);

    return $params === [] ? $dataUrl : $dataUrl . '?' . http_build_query($params);
};

// Lien de tri pour un en-tête : bascule asc/desc si déjà trié dessus.
$sortUrl = static function (string $col) use ($url, $sort, $dir): string {
    $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';

    return $url(['sort' => $col, 'dir' => $nextDir, 'page' => null]);
};

$exportUrl = $base . '/export' . ($current === [] ? '' : '?' . http_build_query($current));
?>
<nav class="breadcrumb">
    <a href="/">Serveur</a> /
    <a href="/db/<?= e(rawurlencode($database)) ?>"><?= e($database) ?></a> /
    <strong><?= e($schema) ?>.<?= e($table) ?></strong>
</nav>

<h1><?= e($schema) ?>.<?= e($table) ?></h1>

<?php $tab = 'data'; require __DIR__ . '/_tabs.php'; ?>

<?php $consoleUrl = '/db/' . rawurlencode($database) . '/query?sql=' . rawurlencode($executedSql); ?>
<div class="sql-box">
    <div class="sql-box-head">
        <span>Requête exécutée</span>
        <a class="btn-link" href="<?= e($consoleUrl) ?>">✎ Modifier dans la console</a>
    </div>
    <pre><code><?= e($executedSql) ?></code></pre>
</div>

<form class="toolbar" method="get" action="<?= e($dataUrl) ?>">
    <label>Filtrer
        <select name="filter_col">
            <option value="">— colonne —</option>
            <?php foreach ($headers as $h): ?>
                <option value="<?= e($h) ?>" <?= $filterCol === $h ? 'selected' : '' ?>><?= e($h) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <select name="filter_op">
        <?php foreach ($operators as $opKey => $opLabel): ?>
            <option value="<?= e($opKey) ?>" <?= $filterOp === $opKey ? 'selected' : '' ?>><?= e($opLabel) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="filter_val" value="<?= e($filterVal ?? '') ?>" placeholder="valeur…">
    <?php if ($sort !== null): ?>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <input type="hidden" name="dir" value="<?= e($dir) ?>">
    <?php endif; ?>
    <button type="submit">Filtrer</button>
    <?php if ($filterCol !== null): ?>
        <a class="btn-link" href="<?= e($url(['filter_col' => null, 'filter_val' => null, 'filter_op' => null])) ?>">Réinitialiser</a>
    <?php endif; ?>
    <a class="btn-link" href="<?= e($exportUrl) ?>">⬇ Export CSV</a>
</form>

<div class="data-actions">
    <p class="muted"><?= $total ?> ligne(s) — page <?= $page ?> / <?= $totalPages ?></p>
    <?php if ($isTable): ?>
        <a class="btn-add" href="<?= e($base) ?>/row/new">+ Ajouter une ligne</a>
    <?php endif; ?>
</div>

<?php if (!$canEditRows && $isTable): ?>
    <p class="muted">Sans clé primaire, l'édition et la suppression ligne à ligne sont indisponibles.</p>
<?php endif; ?>

<?php if ($rows === []): ?>
    <div class="alert"><?= $filterCol !== null ? 'Aucune ligne ne correspond au filtre.' : 'Table vide.' ?></div>
<?php else: ?>
    <div class="table-scroll">
        <table class="grid">
            <thead>
                <tr>
                    <?php foreach ($headers as $h): ?>
                        <th>
                            <a class="sort-link" href="<?= e($sortUrl($h)) ?>">
                                <?= e($h) ?><?php if ($sort === $h): ?> <?= $dir === 'asc' ? '▲' : '▼' ?><?php endif; ?>
                            </a>
                        </th>
                    <?php endforeach; ?>
                    <?php if ($canEditRows): ?><th class="col-actions">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($headers as $h): ?>
                        <?php $v = $row[$h] ?? null; ?>
                        <td>
                            <?php if ($v === null): ?>
                                <span class="muted null">NULL</span>
                            <?php else: ?>
                                <?= e(is_scalar($v) ? (string) $v : (string) json_encode($v)) ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <?php if ($canEditRows): ?>
                        <?php
                        $pkParams = [];
                        foreach ($primaryKey as $pkCol) {
                            $pkParams[$pkCol] = $row[$pkCol] ?? '';
                        }
                        $editUrl = $base . '/row/edit?' . http_build_query(['pk' => $pkParams]);
                        ?>
                        <td class="col-actions">
                            <a class="row-edit" href="<?= e($editUrl) ?>">Éditer</a>
                            <form class="row-delete" method="post" action="<?= e($base) ?>/row/delete"
                                  onsubmit="return confirm('Supprimer définitivement cette ligne ?');">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <?php foreach ($pkParams as $pkCol => $pkVal): ?>
                                    <input type="hidden" name="pk[<?= e((string) $pkCol) ?>]" value="<?= e((string) $pkVal) ?>">
                                <?php endforeach; ?>
                                <button type="submit" class="btn-danger">Supprimer</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <nav class="pager">
        <?php if ($page > 1): ?>
            <a href="<?= e($url(['page' => $page - 1])) ?>">← Précédent</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?= e($url(['page' => $page + 1])) ?>">Suivant →</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
