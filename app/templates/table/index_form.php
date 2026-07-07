<?php
/**
 * @var string $database
 * @var string $schema
 * @var string $table
 * @var list<string> $columns
 * @var string $csrf
 */
$base      = sprintf('/db/%s/table/%s/%s', rawurlencode($database), rawurlencode($schema), rawurlencode($table));
$structure = $base . '/structure';
?>
<nav class="breadcrumb">
    <a href="/">Serveur</a> /
    <a href="/db/<?= e(rawurlencode($database)) ?>"><?= e($database) ?></a> /
    <a href="<?= e($structure) ?>"><?= e($schema) ?>.<?= e($table) ?></a> /
    <strong>Ajouter un index</strong>
</nav>

<h1>Ajouter un index</h1>
<p class="muted"><?= e($schema) ?>.<?= e($table) ?></p>

<form method="post" action="<?= e($base) ?>/index" class="ddl-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="field">
        <label>Nom de l'index <span class="muted">(optionnel — auto si vide)</span></label>
        <input type="text" name="name" autocomplete="off" placeholder="ex. idx_customers_email">
    </div>
    <div class="field">
        <label>Colonnes</label>
        <?php foreach ($columns as $col): ?>
            <label class="inline">
                <input type="checkbox" name="columns[]" value="<?= e($col) ?>"> <?= e($col) ?>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="field">
        <label class="inline">
            <input type="checkbox" name="unique" value="1"> Index UNIQUE
        </label>
    </div>

    <div class="form-actions">
        <button type="submit">Créer l'index</button>
        <a class="btn-link" href="<?= e($structure) ?>">Annuler</a>
    </div>
</form>
