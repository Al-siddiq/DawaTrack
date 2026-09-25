<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#155d46">
    <meta name="csrf-header" content="<?= esc(config('Security')->headerName) ?>">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <title><?= esc($pageTitle) ?> · DawaTrack</title>
    <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="app-header">
    <a class="brand" href="<?= site_url('/') ?>" aria-label="DawaTrack home">
        <span class="brand-mark" aria-hidden="true">D</span>
        <span>DawaTrack</span>
    </a>
    <span class="area-label"><?= esc($pageTitle) ?></span>
</header>
<main id="main-content" class="shell-container" tabindex="-1">
    <?= $this->renderSection('content') ?>
</main>
<footer class="app-footer">Secure business operations foundation</footer>
<script src="<?= base_url('assets/vendor/vue/vue.global.prod.js') ?>" defer></script>
<script type="module" src="<?= base_url($entryScript) ?>"></script>
<script type="module" src="<?= base_url('assets/js/shared/register-service-worker.js') ?>"></script>
</body>
</html>
