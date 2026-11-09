<?php helper('ui'); ?>
<?= $this->extend(config('Auth')->views['layout']) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading">Sign in</h1>
<p>Use your account email address and password.</p>
<form action="<?= esc(dt_url('login'), 'attr') ?>" method="post" class="auth-form" data-busy-label="Signing in…">
    <?= csrf_field() ?>
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" autocomplete="username" inputmode="email" class="form-control" value="<?= esc(old('email')) ?>" required autofocus>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" class="form-control" required>
    <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
        <label class="form-check-label auth-check"><input class="form-check-input" type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
    <?php endif ?>
    <button type="submit" class="ui-button"><?= dt_ui_icon('next') ?> Sign in</button>
</form>
<?php if (setting('Auth.allowMagicLinkLogins')): ?>
    <a class="btn btn-link" href="<?= esc(service('sameOriginUrl')->to('login/magic-link'), 'attr') ?>">Get help signing in</a>
<?php endif ?>
<?= $this->endSection() ?>
