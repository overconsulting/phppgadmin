<?php
/**
 * @var string $database
 * @var string $schema
 * @var string $table
 * @var list<string> $columns           colonnes locales
 * @var array<string, list<string>> $refMap  table → colonnes (cibles possibles)
 * @var list<string> $onDelete          actions ON DELETE autorisées
 * @var string $csrf
 */
$base      = sprintf('/db/%s/table/%s/%s', rawurlencode($database), rawurlencode($schema), rawurlencode($table));
$structure = $base . '/structure';
$refTables = array_keys($refMap);
?>
<nav class="breadcrumb">
    <a href="/">Serveur</a> /
    <a href="/db/<?= e(rawurlencode($database)) ?>"><?= e($database) ?></a> /
    <a href="<?= e($structure) ?>"><?= e($schema) ?>.<?= e($table) ?></a> /
    <strong>Ajouter une clé étrangère</strong>
</nav>

<h1>Ajouter une clé étrangère</h1>
<p class="muted"><?= e($schema) ?>.<?= e($table) ?> — schéma <code><?= e($schema) ?></code></p>

<form method="post" action="<?= e($base) ?>/fk" class="ddl-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="field">
        <label>Nom de la contrainte <span class="muted">(optionnel)</span></label>
        <input type="text" name="name" autocomplete="off" placeholder="ex. fk_orders_customer">
    </div>
    <div class="field">
        <label>Colonne locale</label>
        <select name="column">
            <?php foreach ($columns as $col): ?>
                <option value="<?= e($col) ?>"><?= e($col) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Table référencée</label>
        <select name="ref_table" id="fk-ref-table">
            <?php foreach ($refTables as $t): ?>
                <option value="<?= e($t) ?>"><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Colonne référencée</label>
        <select name="ref_column" id="fk-ref-column"></select>
    </div>
    <div class="field">
        <label>ON DELETE</label>
        <select name="on_delete">
            <option value="">— (par défaut : NO ACTION)</option>
            <?php foreach ($onDelete as $action): ?>
                <option value="<?= e($action) ?>"><?= e($action) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-actions">
        <button type="submit">Ajouter la clé étrangère</button>
        <a class="btn-link" href="<?= e($structure) ?>">Annuler</a>
    </div>
</form>

<script>
// Peuple « colonne référencée » selon la table choisie (pas d'AJAX).
var FK_MAP = <?= json_encode($refMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
(function () {
    var tableSel = document.getElementById('fk-ref-table');
    var colSel   = document.getElementById('fk-ref-column');
    function fill() {
        var cols = FK_MAP[tableSel.value] || [];
        colSel.innerHTML = '';
        cols.forEach(function (c) {
            var o = document.createElement('option');
            o.value = c; o.textContent = c;
            colSel.appendChild(o);
        });
    }
    tableSel.addEventListener('change', fill);
    fill();
})();
</script>
