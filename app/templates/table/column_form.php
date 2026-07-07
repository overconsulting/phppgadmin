<?php
/**
 * @var string $database
 * @var string $schema
 * @var string $table
 * @var string $mode  'add' | 'edit'
 * @var array{name:string, type:string, nullable:bool, default:?string} $column
 * @var list<string> $types
 * @var string $csrf
 */
$base      = sprintf('/db/%s/table/%s/%s', rawurlencode($database), rawurlencode($schema), rawurlencode($table));
$structure = $base . '/structure';
$isEdit    = $mode === 'edit';
$action    = $isEdit ? $base . '/column/update' : $base . '/column';
?>
<nav class="breadcrumb">
    <a href="/">Serveur</a> /
    <a href="/db/<?= e(rawurlencode($database)) ?>"><?= e($database) ?></a> /
    <a href="<?= e($structure) ?>"><?= e($schema) ?>.<?= e($table) ?></a> /
    <strong><?= $isEdit ? 'Éditer la colonne' : 'Ajouter une colonne' ?></strong>
</nav>

<h1><?= $isEdit ? 'Éditer la colonne ' . e($column['name']) : 'Ajouter une colonne' ?></h1>
<p class="muted"><?= e($schema) ?>.<?= e($table) ?></p>

<form method="post" action="<?= e($action) ?>" class="ddl-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="orig" value="<?= e($column['name']) ?>">
    <?php endif; ?>

    <div class="field">
        <label>Nom</label>
        <input type="text" name="name" value="<?= e($column['name']) ?>" autocomplete="off" required>
    </div>
    <div class="field">
        <label>Type</label>
        <input type="text" name="type" value="<?= e($column['type']) ?>" list="pg-types" autocomplete="off" required
               placeholder="ex. integer, varchar(255), timestamptz…">
    </div>
    <div class="field">
        <label class="inline">
            <input type="checkbox" name="nullable" value="1" <?= $column['nullable'] ? 'checked' : '' ?>>
            Autoriser NULL
        </label>
    </div>
    <div class="field">
        <label>Valeur par défaut <span class="muted">(expression SQL : <code>now()</code>, <code>0</code>, <code>'texte'</code>)</span></label>
        <input type="text" name="default" value="<?= e($column['default'] ?? '') ?>" autocomplete="off"
               placeholder="laisser vide = aucune">
    </div>

    <div class="form-actions">
        <button type="submit"><?= $isEdit ? 'Enregistrer' : 'Ajouter la colonne' ?></button>
        <a class="btn-link" href="<?= e($structure) ?>">Annuler</a>
    </div>
</form>

<?php require __DIR__ . '/_types_datalist.php'; ?>
