<?php helper('ui'); ?>
<?= $this->extend('auth/layout') ?>
<?php $this->setVar('adminEntryOverride', true) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading" tabindex="-1">Confirm Admin access again</h1>
<p>Your security check has expired. Continuing will end this Admin session and ask you to sign in again with your password and email code.</p>
<form action="<?= esc(service('sameOriginUrl')->to('platform-admin/mfa/restart'), 'attr') ?>" method="post" class="auth-form" data-busy-label="Restarting secure sign-in…">
    <?= csrf_field() ?>
    <button class="ui-button" type="submit"><?= dt_ui_icon('refresh') ?> Start the security check</button>
</form>
<?= $this->endSection() ?>
