<?php helper('ui'); ?>
<?= $this->extend('auth/layout') ?>
<?= $this->section('main') ?>
<h1 id="auth-heading" tabindex="-1">Please wait before trying again</h1>
<p>There have been too many attempts. Wait <?= esc((string) $retryAfter) ?> seconds before trying again.</p>
<a class="ui-button" href="<?= esc(service('sameOriginUrl')->to('auth'), 'attr') ?>">Return to sign in</a>
<?= $this->endSection() ?>
