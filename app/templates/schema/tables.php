<?php
/**
 * @var string $database
 * @var string $csrf
 * @var array<string, list<array{name:string, type:string}>> $schemas
 */
?>
<nav class="breadcrumb">
    <a href="/">Serveur</a> / <strong><?= e($database) ?></strong>
</nav>

<h1><?= e($database) ?></h1>

<?php if ($schemas === []): ?>
    <div class="alert">Aucun schéma applicatif dans cette base.</div>
<?php endif; ?>

<?php foreach ($schemas as $schema => $tables): ?>
    <section class="schema-block">
        <div class="data-actions">
            <h2>Schéma <code><?= e($schema) ?></code></h2>
            <a class="btn-add" href="/db/<?= e(rawurlencode($database)) ?>/create-table?schema=<?= e(rawurlencode($schema)) ?>">+ Créer une table</a>
        </div>
        <?php if ($tables === []): ?>
            <p class="muted">Aucune table ni vue.</p>
        <?php else: ?>
            <form method="post" action="/db/<?= e(rawurlencode($database)) ?>/tables/drop"
                  onsubmit="return this.querySelectorAll('.row-check:checked').length > 0
                            && confirm('Supprimer DÉFINITIVEMENT les tables sélectionnées et toutes leurs données ?');">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="schema" value="<?= e($schema) ?>">
                <table class="grid">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" aria-label="Tout sélectionner"
                                       onclick="this.closest('form').querySelectorAll('.row-check').forEach(c => c.checked = this.checked);">
                            </th>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tables as $t): ?>
                        <?php
                        $base = sprintf(
                            '/db/%s/table/%s/%s',
                            rawurlencode($database),
                            rawurlencode($schema),
                            rawurlencode($t['name']),
                        );
                        ?>
                        <tr>
                            <td class="col-check">
                                <?php if ($t['type'] === 'table'): ?>
                                    <input type="checkbox" class="row-check" name="tables[]"
                                           value="<?= e($t['name']) ?>" aria-label="Sélectionner <?= e($t['name']) ?>">
                                <?php endif; ?>
                            </td>
                            <td><a href="<?= e($base) ?>"><?= e($t['name']) ?></a></td>
                            <td><span class="badge badge-<?= e($t['type']) ?>"><?= e($t['type']) ?></span></td>
                            <td class="actions">
                                <a href="<?= e($base) ?>/data">Données</a>
                                <a href="<?= e($base) ?>/structure">Structure</a>
                                <?php if ($t['type'] === 'table'): ?>
                                    <a href="<?= e($base) ?>/action">Opérations</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="bulk-actions">
                    <button type="submit" class="btn-danger">Supprimer la sélection</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
