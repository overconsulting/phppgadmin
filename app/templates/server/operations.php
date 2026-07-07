<?php
/**
 * Page « Opérations » d'une base : renommer, supprimer.
 *
 * @var string $database
 * @var string $csrf
 */
$base = '/db/' . rawurlencode($database);
?>
<nav class="breadcrumb">
    <a href="/">Serveur</a> /
    <strong><?= e($database) ?></strong>
</nav>

<h1>Opérations — <?= e($database) ?></h1>

<div class="action-blocks">
    <section class="action-card">
        <h2>Renommer la base</h2>
        <p class="muted">Change le nom de la base <code><?= e($database) ?></code>.</p>
        <form method="post" action="<?= e($base) ?>/rename" class="inline-form">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="text" name="name" value="<?= e($database) ?>" autocomplete="off"
                   pattern="[A-Za-z_][A-Za-z0-9_]*"
                   title="Lettres, chiffres et _ (ne commence pas par un chiffre)" aria-label="Nouveau nom">
            <button type="submit" class="btn-secondary">Renommer</button>
        </form>
    </section>

    <section class="action-card action-danger">
        <h2>Supprimer la base</h2>
        <p class="muted">Action <strong>irréversible</strong> : la base et toutes ses données seront perdues.</p>
        <form method="post" action="<?= e($base) ?>/drop"
              onsubmit="return confirm('Supprimer DÉFINITIVEMENT la base « <?= e($database) ?> » et toutes ses données ?');">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="btn-danger">Supprimer la base</button>
        </form>
    </section>
</div>
