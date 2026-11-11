<?php $this->extend('layouts/shell'); ?>
<?php $this->setVar('entryScript', null) ?>
<?= $this->section('content') ?>
<section class="auth-card" aria-labelledby="auth-heading">
    <div class="eyebrow">Platform security</div>
    <h1 id="auth-heading">Verify again to continue</h1>
    <p>Your recent multi-factor verification has expired. DawaTrack will end this Admin session and ask for your password and Shield email verification code again.</p>
    <form action="<?= esc(service('sameOriginUrl')->to('platform-admin/mfa/restart'), 'attr') ?>" method="post" class="auth-form">
        <?= csrf_field() ?>
        <button class="btn btn-primary btn-lg" type="submit">Re-verify administrator access</button>
    </form>
</section>
<?= $this->endSection() ?>
