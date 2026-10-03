<?= $this->extend('layouts/shell') ?>

<?php $this->setVar('entryScript', null); ?>

<?= $this->section('content') ?>

<section class="auth-card" aria-labelledby="auth-heading">
    <div class="eyebrow">Secure access</div>

    <h1 id="auth-heading">
        <?= esc($brand['brand_display_name'] ?? 'DawaTrack') ?>
    </h1>

    <?php if (! empty($brand['tagline'])): ?>
        <p><?= esc($brand['tagline']) ?></p>
    <?php endif ?>

    <p>
        Sign in with your Shop account.
        Platform Administrators will be asked for multi-factor verification.
    </p>

    <div class="button-stack">
        <a
            class="btn btn-primary btn-lg"
            href="<?= esc(dt_url('login'), 'attr') ?>"
        >
            Sign in
        </a>

        <a
            class="btn btn-link"
            href="<?= esc(dt_url('login/magic-link'), 'attr') ?>"
        >
            Recover access
        </a>
    </div>
</section>

<?= $this->endSection() ?>