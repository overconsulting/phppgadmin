<?php
/**
 * Onglet « Opérations » : opérations sur la table (renommer, supprimer).
 *
 * @var string $database
 * @var string $schema
 * @var string $table
 * @var string $csrf
 */
$base    = sprintf('/db/%s/table/%s/%s', rawurlencode($database), rawurlencode($schema), rawurlencode($table));
$isTable = true;
$tab     = 'action';
?>
<nav class="breadcrumb">
    <a href="/">Serveur</a> /
    <a href="/db/<?= e(rawurlencode($database)) ?>"><?= e($database) ?></a> /
    <strong><?= e($schema) ?>.<?= e($table) ?></strong>
</nav>

<h1><?= e($schema) ?>.<?= e($table) ?></h1>

<?php require __DIR__ . '/_tabs.php'; ?>

<div class="action-blocks">
    <section class="action-card">
        <h2>Renommer la table</h2>
        <p class="muted">Change le nom de la table dans le schéma <code><?= e($schema) ?></code>.</p>
        <form method="post" action="<?= e($base) ?>/rename" class="inline-form">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="text" name="name" value="<?= e($table) ?>" autocomplete="off" aria-label="Nouveau nom">
            <button type="submit" class="btn-secondary">Renommer</button>
        </form>
    </section>

    <section class="action-card action-danger">
        <h2>Supprimer la table</h2>
        <p class="muted">Action <strong>irréversible</strong> : la table et toutes ses données seront perdues.</p>
        <form method="post" action="<?= e($base) ?>/drop"
              onsubmit="return confirm('Supprimer DÉFINITIVEMENT la table « <?= e($schema) ?>.<?= e($table) ?> » et toutes ses données ?');">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="btn-danger">Supprimer la table</button>
        </form>
    </section>
</div>
