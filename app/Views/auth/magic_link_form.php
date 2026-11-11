<?php helper('ui'); ?>
<?= $this->extend(config('Auth')->views['layout']) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading" tabindex="-1">Get help signing in</h1>
<p>Enter your account email. If sign-in help is available for that address, we will email you a link. For privacy, we do not say whether the address has an account.</p>
<form action="<?= esc(service('sameOriginUrl')->to('login/magic-link'), 'attr') ?>" method="post" class="auth-form" data-busy-label="Requesting sign-in help…">
    <?= csrf_field() ?>
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" autocomplete="email" inputmode="email" class="form-control" value="<?= esc(old('email')) ?>" required autofocus>
    <button type="submit" class="ui-button"><?= dt_ui_icon('next') ?> Send recovery instructions</button>
</form>
<a class="btn btn-link" href="<?= esc(service('sameOriginUrl')->to('login'), 'attr') ?>">Return to sign in</a>
<?= $this->endSection() ?>
