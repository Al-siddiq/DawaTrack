<?= $this->extend('layouts/shell') ?>
<?= $this->setVar('entryScript', 'assets/js/admin/app.js') ?>
<?= $this->section('content') ?>
<section id="admin-app" class="welcome-panel" aria-labelledby="admin-heading">
    <div class="eyebrow">Platform administration</div>
    <h1 id="admin-heading">Administration foundation</h1>
    <p>Administrative capabilities are not enabled in Phase 0. This separate shell is ready for its future protected route boundary.</p>
    <status-panel></status-panel>
</section>
<?= $this->endSection() ?>
