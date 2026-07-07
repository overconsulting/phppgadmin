<?php
/**
 * Liste d'autocomplétion des types PostgreSQL courants (champ libre + suggestions).
 *
 * @var list<string> $types
 */
?>
<datalist id="pg-types">
    <?php foreach (($types ?? []) as $type): ?>
        <option value="<?= e($type) ?>"></option>
    <?php endforeach; ?>
</datalist>
