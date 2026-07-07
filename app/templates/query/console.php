<?php
/**
 * @var string $database
 * @var string $sql
 * @var ?string $error
 * @var list<string> $headers
 * @var list<array<string, mixed>> $rows
 * @var ?int $count
 * @var int $maxRows
 * @var ?float $elapsedMs
 * @var bool $writeMode
 * @var ?int $affected
 * @var string $csrf
 */
$action = '/db/' . rawurlencode($database) . '/query';
?>
<nav class="breadcrumb">
    <a href="/">Serveur</a> /
    <a href="/db/<?= e(rawurlencode($database)) ?>"><?= e($database) ?></a> /
    <strong>Console SQL</strong>
</nav>

<h1>Console SQL — <?= e($database) ?></h1>
<p class="muted">
    Par défaut en lecture seule (<code>SELECT</code>, <code>WITH</code>, <code>EXPLAIN</code>).
    Coche <strong>Mode écriture</strong> pour exécuter <code>INSERT</code>/<code>UPDATE</code>/<code>DELETE</code>/DDL.
</p>

<form method="post" action="<?= e($action) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <textarea name="sql" rows="6" spellcheck="false" placeholder="SELECT * FROM public.customers;"><?= e($sql) ?></textarea>
    <div class="form-actions">
        <button type="submit">Exécuter</button>
        <button type="submit" name="explain" value="1" class="btn-secondary">EXPLAIN</button>
        <button type="submit" formaction="<?= e($action) ?>/export" class="btn-secondary">⬇ Export CSV</button>
        <label class="write-toggle">
            <input type="checkbox" name="write" value="1" <?= $writeMode ? 'checked' : '' ?>>
            Mode écriture
        </label>
    </div>
</form>

<?php if ($error !== null): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($affected !== null && $error === null): ?>
    <div class="flash flash-success">
        <?= $affected ?> ligne(s) affectée(s)<?php if ($elapsedMs !== null): ?>
            — exécuté en <?= e(number_format($elapsedMs, 1, ',', ' ')) ?> ms<?php endif; ?>.
    </div>
<?php endif; ?>

<?php if ($count !== null && $error === null): ?>
    <p class="muted">
        <?= $count ?> ligne(s)<?= $count >= $maxRows ? ' (affichage limité à ' . $maxRows . ')' : '' ?>
        <?php if ($elapsedMs !== null): ?> — exécuté en <?= e(number_format($elapsedMs, 1, ',', ' ')) ?> ms<?php endif; ?>.
    </p>
    <?php if ($count > 0): ?>
        <div class="table-scroll">
            <table class="grid">
                <thead>
                    <tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($headers as $h): ?>
                            <?php $v = $row[$h] ?? null; ?>
                            <td>
                                <?php if ($v === null): ?>
                                    <span class="muted null">NULL</span>
                                <?php else: ?>
                                    <?= e(is_scalar($v) ? (string) $v : (string) json_encode($v)) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
