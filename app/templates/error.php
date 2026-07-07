<?php
/**
 * @var string $title
 * @var string $message
 */
?>
<h1><?= e($title ?? 'Erreur') ?></h1>
<div class="alert alert-error"><?= e($message ?? 'Une erreur est survenue.') ?></div>
<p><a href="/">← Retour à l'accueil</a></p>
