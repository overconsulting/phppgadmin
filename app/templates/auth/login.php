<?php
/**
 * Page de connexion (autonome, sans layout : aucune requête base tant qu'on n'est pas connecté).
 *
 * @var string  $csrf        Jeton CSRF
 * @var ?string $user        Valeur pré-remplie du champ utilisateur
 * @var ?string $error       Message d'erreur éventuel
 * @var ?bool   $configError Vrai si AUTH_ENABLED=true mais APP_PASSWORD non défini
 */
$configError = $configError ?? false;
$error       = $error ?? null;
$user        = $user ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — phpPgAdmin</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="login-body">
<main class="login-card">
    <div class="login-brand">🐘 phpPgAdmin</div>

    <?php if ($configError): ?>
        <div class="flash flash-error">
            L'authentification est activée mais <code>APP_PASSWORD</code> n'est pas défini.<br>
            Définissez <code>APP_PASSWORD</code>, ou passez <code>AUTH_ENABLED=false</code> pour un usage local.
        </div>
    <?php else: ?>
        <?php if ($error !== null): ?>
            <div class="flash flash-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/login" class="login-form">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="login-field">
                <span>Utilisateur</span>
                <input type="text" name="user" value="<?= e($user) ?>"
                       autocomplete="username" autofocus>
            </label>
            <label class="login-field">
                <span>Mot de passe</span>
                <input type="password" name="password" autocomplete="current-password">
            </label>
            <button type="submit">Se connecter</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
