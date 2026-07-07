<?php
/**
 * Barre d'onglets partagée d'une table/vue.
 *
 * @var string $base    URL de base /db/{db}/table/{schema}/{table}
 * @var string $tab     onglet actif : 'data' | 'structure' | 'action'
 * @var bool   $isTable l'onglet Opérations n'est proposé que pour une table de base
 */
$tab = $tab ?? '';
?>
<nav class="tabs">
    <a class="<?= $tab === 'data' ? 'active' : '' ?>" href="<?= e($base) ?>/data">Données</a>
    <a class="<?= $tab === 'structure' ? 'active' : '' ?>" href="<?= e($base) ?>/structure">Structure</a>
    <?php if ($isTable ?? false): ?>
        <a class="<?= $tab === 'action' ? 'active' : '' ?>" href="<?= e($base) ?>/action">Opérations</a>
    <?php endif; ?>
</nav>
