<?php
/**
 * @var string      $title
 * @var string      $content
 * @var list<string> $databases
 * @var ?string     $currentDb
 * @var array<string, list<array{name:string, type:string}>> $navTables
 * @var list<array{type:string, message:string}> $flashes
 * @var ?string $lastSql
 */
$databases = $databases ?? [];
$currentDb = $currentDb ?? null;
$navTables = $navTables ?? [];
$flashes   = $flashes ?? [];
$lastSql   = $lastSql ?? null;
// Table active (si on est sur une page table) → pour la surligner dans la sidebar.
$activeSchema = $schema ?? null;
$activeTable  = $table ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'phpPgAdmin') ?> — phpPgAdmin</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/">🐘 phpPgAdmin</a>
    <?php if ($currentDb !== null): ?>
        <nav class="topbar-actions">
            <a href="/db/<?= e(rawurlencode($currentDb)) ?>">Schémas</a>
            <a href="/db/<?= e(rawurlencode($currentDb)) ?>/query">Console SQL</a>
            <a href="/db/<?= e(rawurlencode($currentDb)) ?>/operations">Opérations</a>
        </nav>
    <?php endif; ?>
    <?php if (\App\Core\Auth::check()): ?>
        <a href="/logout" class="topbar-logout">Déconnexion</a>
    <?php endif; ?>
</header>

<div class="layout">
    <aside class="sidebar">
        <h2>Bases</h2>
        <ul class="db-list">
            <?php foreach ($databases as $name): ?>
                <li>
                    <?php if ($name === $currentDb): ?>
                        <details class="db-item" open>
                            <summary class="active"><?= e($name) ?></summary>
                            <div class="nav-tables">
                                <?php if ($navTables === []): ?>
                                    <p class="muted nav-empty">Aucune table.</p>
                                <?php endif; ?>
                                <?php foreach ($navTables as $sch => $tables): ?>
                                    <div class="nav-schema"><?= e($sch) ?></div>
                                    <ul class="nav-table-list">
                                        <?php foreach ($tables as $t): ?>
                                            <?php
                                            $href = sprintf(
                                                '/db/%s/table/%s/%s',
                                                rawurlencode($currentDb),
                                                rawurlencode($sch),
                                                rawurlencode($t['name']),
                                            );
                                            $isActive = $activeSchema === $sch && $activeTable === $t['name'];
                                            ?>
                                            <li>
                                                <a class="nav-table <?= $isActive ? 'active-table' : '' ?>"
                                                   href="<?= e($href) ?>">
                                                    <span class="nav-table-icon"><?= $t['type'] === 'vue' ? '◫' : '▦' ?></span>
                                                    <?= e($t['name']) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php else: ?>
                        <a class="db-link" href="/db/<?= e(rawurlencode($name)) ?>"><?= e($name) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php if ($databases === []): ?>
                <li class="muted">Aucune base accessible</li>
            <?php endif; ?>
        </ul>
    </aside>

    <main class="content">
        <?php foreach ($flashes as $flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
        <?php if (!empty($lastSql)): ?>
            <div class="sql-box sql-box-write">
                <div class="sql-box-head"><span>Dernière écriture exécutée</span></div>
                <pre><code><?= e($lastSql) ?></code></pre>
            </div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </main>
</div>
</body>
</html>
