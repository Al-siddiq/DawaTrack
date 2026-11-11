<?php
helper('ui');
$adminEntry = session('authentication_area') === 'platform_admin';
$brand = $adminEntry ? null : dawatrack_auth_brand();

$cspNonce = service('cspNonce')->value();

$primaryColour    = $brand['primary_colour'] ?? '#155D46';
$secondaryColour  = $brand['secondary_colour'] ?? '#17231F';
$accentColour     = $brand['accent_colour'] ?? '#F0A202';
$backgroundColour = $brand['background_colour'] ?? '#F4F7F5';
$loginBackground  = $brand['assets']['LOGIN_BACKGROUND'] ?? null;
$uiPalette = dt_ui_brand_palette($primaryColour, $accentColour);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">

    <meta
        name="theme-color"
        content="<?= esc($brand['theme_colour'] ?? '#155D46') ?>"
    >

    <title>
        <?= esc($brand['application_title'] ?? 'Secure access · DawaTrack') ?>
    </title>

    <?php if (! empty($brand['assets']['FAVICON'])): ?>
        <link
            rel="icon"
            href="<?= esc($brand['assets']['FAVICON']) ?>"
        >
    <?php endif ?>

    <link
        rel="stylesheet"
        href="/assets/css/app.css"
    >

    <style nonce="<?= esc($cspNonce, 'attr') ?>">
        :root {
            --dt-primary: <?= esc($primaryColour) ?>;
            <?php foreach ($uiPalette as $token => $colour): ?>
            --ui-<?= esc($token) ?>: <?= esc($colour) ?>;
            <?php endforeach ?>
            --dt-ink: <?= esc($secondaryColour) ?>;
            --dt-accent: <?= esc($accentColour) ?>;
            --dt-background: <?= esc($backgroundColour) ?>;
        }

        <?php if (! empty($loginBackground)): ?>
        :root {
            --dt-login-background:
                url(<?= json_encode(
                    $loginBackground,
                    JSON_HEX_TAG
                    | JSON_HEX_AMP
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
                ) ?>);
        }
        <?php endif ?>
    </style>
</head>

<body class="auth-page ui-auth-entry ui-scope">
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
                <div class="eyebrow"><?= $adminEntry ? 'Admin workspace' : 'Shop sign-in' ?></div>
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
<script type="module" src="/assets/js/auth/entry.js"></script>
</body>
</html>
