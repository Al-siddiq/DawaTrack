<?php helper('ui'); ?>
<?= $this->extend(config('Auth')->views['layout']) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading" tabindex="-1">Confirm your email</h1>
<p>Enter the code sent to your email to activate your account.</p>
<form action="<?= esc(url_to('auth-action-verify'), 'attr') ?>" method="post" class="auth-form" data-busy-label="Checking email code…">
    <?= csrf_field() ?>
    <label for="auth-token">Email confirmation code</label>
    <input id="auth-token" name="token" type="text" class="form-control auth-code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" value="<?= esc(old('token'), 'attr') ?>" required>
    <button type="submit" class="ui-button"><?= dt_ui_icon('success') ?> Confirm email</button>
</form>
<?= $this->endSection() ?>
