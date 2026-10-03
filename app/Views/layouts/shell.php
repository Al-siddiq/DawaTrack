<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= esc($brand['theme_colour'] ?? '#155D46') ?>">
    <meta name="csrf-header" content="<?= esc(config('Security')->headerName) ?>">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <title><?= esc($brand['application_title'] ?? $pageTitle . ' · DawaTrack') ?></title>
    <?php if (! empty($brand['assets']['FAVICON'])): ?><link rel="icon" href="<?= esc($brand['assets']['FAVICON']) ?>"><?php endif ?>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" href="/assets/css/app.css">

    <?php
    $cspNonce = service('cspNonce')->value();

    $primaryColour    = $brand['primary_colour'] ?? '#155D46';
    $secondaryColour  = $brand['secondary_colour'] ?? '#17231F';
    $accentColour     = $brand['accent_colour'] ?? '#F0A202';
    $backgroundColour = $brand['background_colour'] ?? '#F4F7F5';
    $loginBackground  = $brand['assets']['LOGIN_BACKGROUND'] ?? null;
    ?>

    <style nonce="<?= esc($cspNonce, 'attr') ?>">
        :root {
            /*
             * DawaTrack / Shop branding
             *
             * Values are resolved by the server for the current Shop.
             * The shared stylesheet consumes these CSS custom properties.
             */
            --dt-primary: <?= esc($primaryColour) ?>;
            --dt-ink: <?= esc($secondaryColour) ?>;
            --dt-accent: <?= esc($accentColour) ?>;
            --dt-background: <?= esc($backgroundColour) ?>;

            /*
             * Platform administration tokens
             */
            --admin-bg: #f3f4f6;
            --admin-surface: #ffffff;
            --admin-sidebar: #e9ebef;
            --admin-text: #1f2937;
            --admin-muted: #6b7280;
            --admin-border: #d5d9e0;
            --admin-accent: #4f46e5;
            --admin-accent-soft: rgba(79, 70, 229, 0.1);
            --sidebar-width: 16rem;

            /*
             * Additional shared colour tokens
             */
            --ja: #db4a39;
            --fari: #ffffff;
            --lemu: #f39c12;
            --yellow: #ffc107;
            --kammala: #ebf8a4;
            --kore: #32cd32;
            --gargadi: #c4e3f3;
            --tangarda: #ff3860;
            --haske: #f8f9fa;
            --hayaki: #f8f8f8;
            --toka: #ecece9;
        }

        <?php if (! empty($loginBackground)): ?>
        :root {
            --dt-login-background: url("<?= esc($loginBackground, 'attr') ?>");
        }
        <?php endif ?>

        /*
         * Custom scrollbar
         */

        * {
            scrollbar-width: thin;
            scrollbar-color: var(--toka) #f3f4f6;
        }

        *::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        *::-webkit-scrollbar-track {
            background: #f3f4f6;
            border-radius: 999px;
        }

        *::-webkit-scrollbar-thumb {
            background: #1d4ed8;
            border-radius: 999px;
            border: 2px solid #f3f4f6;
        }

        *::-webkit-scrollbar-thumb:hover {
            background: #0f172a;
        }
    </style>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="app-header">
    <a class="brand" href="<?= site_url('/') ?>" aria-label="DawaTrack home">
        <?php if (! empty($brand['assets']['LOGO'])): ?><img class="brand-logo" src="<?= esc($brand['assets']['LOGO']) ?>" alt=""><?php else: ?><span class="brand-mark" aria-hidden="true"><?= esc(mb_substr($brand['brand_display_name'] ?? 'D', 0, 1)) ?></span><?php endif ?>
        <span><?= esc($brand['brand_display_name'] ?? 'DawaTrack') ?></span>
    </a>
    <span class="area-label"><?= esc($pageTitle) ?></span>
</header>
<main id="main-content" class="shell-container" tabindex="-1">
    <?= $this->renderSection('content') ?>
</main>
<footer class="app-footer">Secure business operations foundation</footer>

<?php if (! empty($entryScript)): ?>
    <script src="/assets/vendor/vue/vue.runtime.global.prod.js" defer></script>
    <script type="module" src="/<?= esc(ltrim($entryScript, '/'), 'attr') ?>" ></script>
<?php endif ?>
<script
    type="module"
    src="/assets/js/shared/register-service-worker.js"
></script>
</body>
</html>
