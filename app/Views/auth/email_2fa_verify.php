<?php helper('ui'); ?>
<?= $this->extend(config('Auth')->views['layout']) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading" tabindex="-1">Enter your sign-in code</h1>
<p>Check your email and enter the code to finish the security check. Keep this code private.</p>
<form action="<?= esc(url_to('auth-action-verify'), 'attr') ?>" method="post" class="auth-form" data-busy-label="Checking code…">
    <?= csrf_field() ?>
    <label for="auth-token">Code from your email</label>
    <input id="auth-token" name="token" type="text" class="form-control auth-code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" required autofocus>
    <button type="submit" class="ui-button"><?= dt_ui_icon('success') ?> Confirm sign-in code</button>
</form>
<?= $this->endSection() ?>
