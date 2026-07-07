<?php
/**
 * @var string $database
 * @var string $schema
 * @var string $table
 * @var string $mode  'create' | 'edit'
 * @var list<array{name:string, type:string, nullable:bool, default:?string}> $columns
 * @var array<string, mixed> $values
 * @var list<string> $primaryKey
 * @var array<string, mixed> $pk
 * @var string $csrf
 */
$base    = sprintf('/db/%s/table/%s/%s', rawurlencode($database), rawurlencode($schema), rawurlencode($table));
$dataUrl = $base . '/data';
$isEdit  = $mode === 'edit';
$action  = $isEdit ? $base . '/row/update' : $base . '/row';
?>
<nav class="breadcrumb">
    <a href="/">Serveur</a> /
    <a href="/db/<?= e(rawurlencode($database)) ?>"><?= e($database) ?></a> /
    <a href="<?= e($dataUrl) ?>"><?= e($schema) ?>.<?= e($table) ?></a> /
    <strong><?= $isEdit ? 'Éditer une ligne' : 'Ajouter une ligne' ?></strong>
</nav>

<h1><?= $isEdit ? 'Éditer une ligne' : 'Ajouter une ligne' ?></h1>
<p class="muted"><?= e($schema) ?>.<?= e($table) ?></p>

<form method="post" action="<?= e($action) ?>" class="row-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <?php if ($isEdit): ?>
        <?php foreach ($pk as $col => $val): ?>
            <input type="hidden" name="pk[<?= e((string) $col) ?>]" value="<?= e((string) $val) ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <table class="grid form-grid">
        <thead>
            <tr>
                <th>Colonne</th><th>Type</th><th>Valeur</th><th class="center">NULL</th>
                <?php if (!$isEdit): ?><th class="center">Défaut</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($columns as $col): ?>
            <?php
            $name      = $col['name'];
            $isPk      = in_array($name, $primaryKey, true);
            $hasValue  = array_key_exists($name, $values);
            $current   = $hasValue ? $values[$name] : null;
            $isNullVal = $hasValue && $current === null;
            ?>
            <tr>
                <td>
                    <strong><?= e($name) ?></strong>
                    <?php if ($isPk): ?><span class="badge badge-pk">PK</span><?php endif; ?>
                </td>
                <td><code><?= e($col['type']) ?></code></td>

                <?php if ($isEdit && $isPk): ?>
                    <td colspan="2" class="muted">
                        <?= $current === null ? 'NULL' : e((string) $current) ?>
                        <span class="muted"> (clé — non modifiable)</span>
                    </td>
                <?php else: ?>
                    <td>
                        <input type="text" name="fields[<?= e($name) ?>]"
                               value="<?= $isNullVal ? '' : e(is_scalar($current) ? (string) $current : (string) json_encode($current)) ?>">
                    </td>
                    <td class="center">
                        <?php if ($col['nullable']): ?>
                            <input type="checkbox" name="null[<?= e($name) ?>]" value="1" <?= $isNullVal ? 'checked' : '' ?>>
                        <?php else: ?>
                            <span class="muted" title="Colonne NOT NULL">—</span>
                        <?php endif; ?>
                    </td>
                    <?php if (!$isEdit): ?>
                        <td class="center">
                            <?php if ($col['default'] !== null || $isPk): ?>
                                <input type="checkbox" name="default[<?= e($name) ?>]" value="1"
                                       <?= $col['default'] !== null ? 'checked' : '' ?>>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="form-actions">
        <button type="submit"><?= $isEdit ? 'Enregistrer' : 'Ajouter' ?></button>
        <a class="btn-link" href="<?= e($dataUrl) ?>">Annuler</a>
    </div>
</form>

<script>
// Cocher NULL (ou Défaut) vide et désactive le champ valeur ; décocher le restaure.
document.querySelectorAll('.row-form tbody tr').forEach(function (tr) {
    var input = tr.querySelector('input[type=text]');
    var boxes = tr.querySelectorAll('input[type=checkbox]');
    if (!input || boxes.length === 0) return;
    var apply = function () {
        var checked = Array.from(boxes).some(function (c) { return c.checked; });
        if (checked) {
            if (!input.disabled) { input.dataset.prev = input.value; input.value = ''; }
            input.disabled = true;
        } else {
            input.disabled = false;
            if (input.dataset.prev !== undefined) { input.value = input.dataset.prev; }
        }
    };
    boxes.forEach(function (cb) { cb.addEventListener('change', apply); });
    apply();
});
</script>
