<?php $brand = dawatrack_auth_brand(); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= esc($brand['theme_colour'] ?? '#155D46') ?>">
    <title><?= esc($brand['application_title'] ?? 'Secure access · DawaTrack') ?></title>
    <?php if (! empty($brand['assets']['FAVICON'])): ?>
        <link rel="icon" href="<?= esc($brand['assets']['FAVICON']) ?>">
    <?php endif ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body class="auth-page" style="--dt-primary: <?= esc($brand['primary_colour'] ?? '#155D46') ?>; --dt-ink: <?= esc($brand['secondary_colour'] ?? '#17231F') ?>; --dt-accent: <?= esc($brand['accent_colour'] ?? '#F0A202') ?>; --dt-background: <?= esc($brand['background_colour'] ?? '#F4F7F5') ?>;<?php if (! empty($brand['assets']['LOGIN_BACKGROUND'])): ?> --dt-login-background: url('<?= esc($brand['assets']['LOGIN_BACKGROUND']) ?>');<?php endif ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>
<main id="main-content" class="auth-shell" tabindex="-1">
    <section class="auth-card" aria-labelledby="auth-heading">
        <header class="auth-brand">
            <?php if (! empty($brand['assets']['LOGO'])): ?>
                <img class="brand-logo" src="<?= esc($brand['assets']['LOGO']) ?>" alt="">
            <?php else: ?>
                <span class="brand-mark" aria-hidden="true"><?= esc(mb_substr($brand['brand_display_name'] ?? 'D', 0, 1)) ?></span>
            <?php endif ?>
            <div>
                <div class="eyebrow">Secure access</div>
                <strong><?= esc($brand['brand_display_name'] ?? 'DawaTrack') ?></strong>
            </div>
        </header>

        <?php if (session('error') !== null): ?>
            <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
        <?php endif ?>
        <?php if (session('message') !== null): ?>
            <div class="alert alert-success" role="status"><?= esc(session('message')) ?></div>
        <?php endif ?>
        <?php if (session('errors') !== null): ?>
            <div class="alert alert-danger" role="alert">
                <p class="mb-1">Please correct the following:</p>
                <ul class="mb-0">
                    <?php foreach ((array) session('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>

        <?= $this->renderSection('main') ?>
    </section>
</main>
</body>
</html>
