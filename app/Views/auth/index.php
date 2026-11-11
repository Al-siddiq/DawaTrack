<?php helper('ui'); ?>
<?= $this->extend('auth/layout') ?>
<?= $this->section('main') ?>
<h1 id="auth-heading" tabindex="-1">Welcome</h1>
<p><?= session('authentication_area') === 'platform_admin' ? 'Sign in to the Admin workspace.' : 'Sign in to continue to your shop.' ?></p>
<div class="button-stack">
    <a class="ui-button" href="<?= esc(dt_url('login'), 'attr') ?>"><?= dt_ui_icon('next') ?> Sign in</a>
    <?php if (setting('Auth.allowMagicLinkLogins')): ?>
        <a class="ui-button ui-button--secondary" href="<?= esc(dt_url('login/magic-link'), 'attr') ?>">Get help signing in</a>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
