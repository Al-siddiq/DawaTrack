<?php helper('ui'); ?>
<?= $this->extend(config('Auth')->views['layout']) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading" tabindex="-1">Security check</h1>
<p>Confirm your account email to receive a sign-in code.</p>
<form action="<?= esc(url_to('auth-action-handle'), 'attr') ?>" method="post" class="auth-form" data-busy-label="Sending code…">
    <?= csrf_field() ?>
    <label for="email">Account email address</label>
    <input id="email" name="email" type="email" class="form-control" inputmode="email" autocomplete="email" value="<?= esc(old('email', $user->email), 'attr') ?>" required>
    <button type="submit" class="ui-button"><?= dt_ui_icon('next') ?> Send sign-in code</button>
</form>
<?= $this->endSection() ?>
