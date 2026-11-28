<?php helper('ui'); ?>
<?= $this->extend('auth/layout') ?>
<?= $this->section('main') ?>
<main class="auth-card" aria-labelledby="owner-activate-heading">
    <p class="auth-eyebrow">DawaTrack · Owner setup</p>
    <h1 id="owner-activate-heading" tabindex="-1">Activate your business account</h1>
    <p>You have been invited to become the Primary Owner of <strong><?= esc($businessName) ?></strong>.</p>
    <p>Owner email: <strong><?= esc($ownerEmail) ?></strong></p>

    <?php if (! empty($error)): ?>
        <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <?php if ($requiresPasswordReset): ?>
        <p>Choose your own password to finish setting up your account. No temporary password is needed.</p>
        <form action="<?= esc(site_url('owner/activate'), 'attr') ?>" method="post" class="auth-form">
            <?= csrf_field() ?>
            <label for="password">Create a password</label>
            <input id="password" name="password" type="password" class="form-control" minlength="12" autocomplete="new-password" required>
            <?php if (! empty($errors['password'])): ?><small class="text-danger"><?= esc(implode(' ', $errors['password'])) ?></small><?php endif; ?>
            <label for="password_confirm">Confirm your password</label>
            <input id="password_confirm" name="password_confirm" type="password" class="form-control" minlength="12" autocomplete="new-password" required>
            <?php if (! empty($errors['password_confirm'])): ?><small class="text-danger"><?= esc(implode(' ', $errors['password_confirm'])) ?></small><?php endif; ?>
            <button type="submit" class="ui-button">Set password and activate account</button>
        </form>
    <?php else: ?>
        <p>Confirm to activate your Owner access. Your business account and its Shop will become available after this step is completed.</p>
        <form action="<?= esc(site_url('owner/activate'), 'attr') ?>" method="post" class="auth-form">
            <?= csrf_field() ?>
            <button type="submit" class="ui-button">Activate my Owner access</button>
        </form>
    <?php endif; ?>
</main>
<?= $this->endSection() ?>
