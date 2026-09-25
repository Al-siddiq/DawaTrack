<?= $this->extend('layouts/shell') ?>
<?= $this->setVar('entryScript', 'assets/js/shop/app.js') ?>
<?= $this->section('content') ?>
<section id="shop-app" class="welcome-panel" aria-labelledby="shop-heading">
    <div class="eyebrow">Shop workspace</div>
    <h1 id="shop-heading">Your DawaTrack workspace is ready.</h1>
    <p>This foundation is prepared for secure shop operations. Business features will appear only after they are implemented and approved.</p>
    <status-panel></status-panel>
</section>
<?= $this->endSection() ?>
