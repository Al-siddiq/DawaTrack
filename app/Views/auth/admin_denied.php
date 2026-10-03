<?= $this->extend('layouts/shell') ?>
<?= $this->setVar('entryScript', 'assets/js/auth/app.js') ?>
<?= $this->section('content') ?>
<section class="auth-card" aria-labelledby="auth-heading">
    <div class="eyebrow">Platform security</div>
    <h1 id="auth-heading">Administrator access required</h1>
    <p>This signed-in identity does not have DawaTrack Platform Administrator authority. Tenant membership never grants platform access.</p>
    <div class="button-stack">
        <a class="btn btn-primary" href="<?= esc(service('sameOriginUrl')->to('/'), 'attr') ?>">Return to your Shop</a>
        <a class="btn btn-outline-secondary" href="<?= esc(service('sameOriginUrl')->to('logout'), 'attr') ?>">Sign out</a>
    </div>
</section>
<?= $this->endSection() ?>
