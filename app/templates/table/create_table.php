<?php
/**
 * @var string $database
 * @var string $schema
 * @var list<string> $types
 * @var string $csrf
 */
$rows = 6; // lignes de colonnes vides au départ (les vides sont ignorées au submit)
?>
<nav class="breadcrumb">
    <a href="/">Serveur</a> /
    <a href="/db/<?= e(rawurlencode($database)) ?>"><?= e($database) ?></a> /
    <strong>Créer une table</strong>
</nav>

<h1>Créer une table</h1>
<p class="muted">Schéma <code><?= e($schema) ?></code></p>

<form method="post" action="/db/<?= e(rawurlencode($database)) ?>/create-table" class="ddl-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="schema" value="<?= e($schema) ?>">

    <div class="field">
        <label>Nom de la table</label>
        <input type="text" name="name" autocomplete="off" required placeholder="ex. produits">
    </div>

    <h2>Colonnes</h2>
    <table class="grid" id="cols-table">
        <thead>
            <tr><th>Nom</th><th>Type</th><th class="center">NOT NULL</th><th>Défaut</th><th class="center">PK</th></tr>
        </thead>
        <tbody>
        <?php for ($i = 0; $i < $rows; $i++): ?>
            <tr>
                <td><input type="text" name="cols[<?= $i ?>][name]" autocomplete="off"></td>
                <td><input type="text" name="cols[<?= $i ?>][type]" list="pg-types" autocomplete="off" placeholder="ex. serial, text…"></td>
                <td class="center"><input type="checkbox" name="cols[<?= $i ?>][notnull]" value="1"></td>
                <td><input type="text" name="cols[<?= $i ?>][default]" autocomplete="off" placeholder="ex. now()"></td>
                <td class="center"><input type="checkbox" name="cols[<?= $i ?>][pk]" value="1"></td>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>

    <div class="form-actions">
        <button type="button" id="add-col" class="btn-secondary">+ Ligne</button>
        <button type="submit">Créer la table</button>
        <a class="btn-link" href="/db/<?= e(rawurlencode($database)) ?>">Annuler</a>
    </div>
</form>

<?php require __DIR__ . '/_types_datalist.php'; ?>

<script>
// Ajoute une ligne de colonne (clone de la dernière, index incrémenté).
document.getElementById('add-col').addEventListener('click', function () {
    var body = document.querySelector('#cols-table tbody');
    var idx = body.querySelectorAll('tr').length;
    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td><input type="text" name="cols[' + idx + '][name]" autocomplete="off"></td>' +
        '<td><input type="text" name="cols[' + idx + '][type]" list="pg-types" autocomplete="off"></td>' +
        '<td class="center"><input type="checkbox" name="cols[' + idx + '][notnull]" value="1"></td>' +
        '<td><input type="text" name="cols[' + idx + '][default]" autocomplete="off"></td>' +
        '<td class="center"><input type="checkbox" name="cols[' + idx + '][pk]" value="1"></td>';
    body.appendChild(tr);
});
</script>
