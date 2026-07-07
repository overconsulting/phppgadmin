<?php
/**
 * @var list<string> $databases
 * @var string       $csrf
 */
?>
<h1>Bases de données</h1>
<p class="muted"><?= count($databases) ?> base(s) sur le serveur.</p>

<form method="post" action="/create-database" class="toolbar">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>
        Nouvelle base
        <input type="text" name="name" placeholder="nom_de_la_base"
               pattern="[A-Za-z_][A-Za-z0-9_]*" title="Lettres, chiffres et _ (ne commence pas par un chiffre)" required>
    </label>
    <button type="submit">Créer la base</button>
</form>

<?php if ($databases === []): ?>
    <div class="alert">Aucune base de données accessible avec les identifiants courants.</div>
<?php else: ?>
    <ul class="cards">
        <?php foreach ($databases as $name): ?>
            <li class="card">
                <a href="/db/<?= e(rawurlencode($name)) ?>">
                    <span class="card-icon">🗄️</span>
                    <span class="card-title"><?= e($name) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
