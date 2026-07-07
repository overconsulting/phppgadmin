<?php
/**
 * @var string $database
 * @var string $schema
 * @var string $table
 * @var list<array{name:string, type:string, nullable:bool, default:?string}> $columns
 * @var list<string> $primaryKey
 * @var list<array{name:string, definition:string}> $indexes
 * @var list<array{column:string, ref_schema:string, ref_table:string, ref_column:string}> $foreignKeys
 * @var bool $isTable
 * @var string $csrf
 */
$base = sprintf('/db/%s/table/%s/%s', rawurlencode($database), rawurlencode($schema), rawurlencode($table));

// Index des FK par colonne locale, pour annoter le tableau des colonnes.
$fkByColumn = [];
foreach ($foreignKeys as $fk) {
    $fkByColumn[$fk['column']] = $fk;
}
?>
<nav class="breadcrumb">
    <a href="/">Serveur</a> /
    <a href="/db/<?= e(rawurlencode($database)) ?>"><?= e($database) ?></a> /
    <strong><?= e($schema) ?>.<?= e($table) ?></strong>
</nav>

<h1><?= e($schema) ?>.<?= e($table) ?></h1>

<?php $tab = 'structure'; require __DIR__ . '/_tabs.php'; ?>

<div class="data-actions">
    <h2>Colonnes</h2>
    <?php if ($isTable): ?>
        <a class="btn-add" href="<?= e($base) ?>/column/new">+ Ajouter une colonne</a>
    <?php endif; ?>
</div>
<table class="grid">
    <thead>
        <tr>
            <th>#</th><th>Colonne</th><th>Type</th><th>Null</th><th>Défaut</th><th>Clé</th>
            <?php if ($isTable): ?><th class="col-actions">Actions</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($columns as $i => $col): ?>
        <tr>
            <td class="muted"><?= $i + 1 ?></td>
            <td><strong><?= e($col['name']) ?></strong></td>
            <td><code><?= e($col['type']) ?></code></td>
            <td><?= $col['nullable'] ? '<span class="muted">oui</span>' : 'non' ?></td>
            <td><?= $col['default'] !== null ? '<code>' . e($col['default']) . '</code>' : '<span class="muted">—</span>' ?></td>
            <td>
                <?php if (in_array($col['name'], $primaryKey, true)): ?>
                    <span class="badge badge-pk">PK</span>
                <?php endif; ?>
                <?php if (isset($fkByColumn[$col['name']])): ?>
                    <span class="badge badge-fk">FK</span>
                <?php endif; ?>
            </td>
            <?php if ($isTable): ?>
                <td class="col-actions">
                    <a class="row-edit" href="<?= e($base) ?>/column/edit?name=<?= e(rawurlencode($col['name'])) ?>">Éditer</a>
                    <form class="row-delete" method="post" action="<?= e($base) ?>/column/drop"
                          onsubmit="return confirm('Supprimer la colonne « <?= e($col['name']) ?> » et ses données ?');">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="name" value="<?= e($col['name']) ?>">
                        <button type="submit" class="btn-danger">Supprimer</button>
                    </form>
                </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="data-actions">
    <h2>Clés étrangères</h2>
    <?php if ($isTable): ?>
        <a class="btn-add" href="<?= e($base) ?>/fk/new">+ Ajouter une clé étrangère</a>
    <?php endif; ?>
</div>
<?php if ($foreignKeys === []): ?>
    <p class="muted">Aucune clé étrangère.</p>
<?php else: ?>
    <table class="grid">
        <thead>
            <tr><th>Contrainte</th><th>Colonne</th><th>Référence</th><?php if ($isTable): ?><th class="col-actions">Actions</th><?php endif; ?></tr>
        </thead>
        <tbody>
        <?php foreach ($foreignKeys as $fk): ?>
            <?php
            $refLink = sprintf(
                '/db/%s/table/%s/%s',
                rawurlencode($database),
                rawurlencode($fk['ref_schema']),
                rawurlencode($fk['ref_table']),
            );
            ?>
            <tr>
                <td class="muted"><?= e($fk['name']) ?></td>
                <td><strong><?= e($fk['column']) ?></strong></td>
                <td>
                    <a href="<?= e($refLink) ?>">
                        <?= e($fk['ref_schema']) ?>.<?= e($fk['ref_table']) ?></a>(<?= e($fk['ref_column']) ?>)
                </td>
                <?php if ($isTable): ?>
                    <td class="col-actions">
                        <form class="row-delete" method="post" action="<?= e($base) ?>/fk/drop"
                              onsubmit="return confirm('Supprimer la clé étrangère « <?= e($fk['name']) ?> » ?');">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="name" value="<?= e($fk['name']) ?>">
                            <button type="submit" class="btn-danger">Supprimer</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="data-actions">
    <h2>Index</h2>
    <?php if ($isTable): ?>
        <a class="btn-add" href="<?= e($base) ?>/index/new">+ Ajouter un index</a>
    <?php endif; ?>
</div>
<?php if ($indexes === []): ?>
    <p class="muted">Aucun index.</p>
<?php else: ?>
    <table class="grid">
        <thead>
            <tr><th>Nom</th><th>Définition</th><?php if ($isTable): ?><th class="col-actions">Actions</th><?php endif; ?></tr>
        </thead>
        <tbody>
        <?php foreach ($indexes as $idx): ?>
            <tr>
                <td><?= e($idx['name']) ?></td>
                <td><code><?= e($idx['definition']) ?></code></td>
                <?php if ($isTable): ?>
                    <td class="col-actions">
                        <?php
                        $confirmMsg = $idx['is_constraint']
                            ? 'L’index « ' . $idx['name'] . ' » porte une contrainte ('
                              . e($idx['constraint_name'] ?? '') . '). La supprimer retirera aussi la contrainte. Continuer ?'
                            : 'Supprimer l’index « ' . $idx['name'] . ' » ?';
                        ?>
                        <form class="row-delete" method="post" action="<?= e($base) ?>/index/drop"
                              onsubmit="return confirm('<?= e($confirmMsg) ?>');">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="name" value="<?= e($idx['name']) ?>">
                            <button type="submit" class="btn-danger"><?= $idx['is_constraint'] ? 'Supprimer (contrainte)' : 'Supprimer' ?></button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

