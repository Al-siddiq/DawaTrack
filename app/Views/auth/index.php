<?= $this->extend('layouts/shell') ?>
<?= $this->setVar('entryScript', 'assets/js/auth/app.js') ?>
<?= $this->section('content') ?>
<section id="auth-app" class="welcome-panel" aria-labelledby="auth-heading">
    <div class="eyebrow">Authentication</div>
    <h1 id="auth-heading">Secure access foundation</h1>
    <p>Authentication workflows are intentionally unavailable until Phase 1. This shell confirms the approved server-rendered entry boundary.</p>
    <status-panel></status-panel>
</section>
<?= $this->endSection() ?>
