<?= $this->extend('layouts/shell') ?>

<?php
$this->setVar(
    'entryScript',
    'assets/js/shop/app.js'
);

$cspNonce = service('cspNonce')->value();
?>

<?= $this->section('content') ?>

<style
    id="shop-preview-style"
    nonce="<?= esc($cspNonce, 'attr') ?>"
></style>

<?php
$user = auth()->user();
$platformPermissions = [];
?>
<div id="shop-app"
    data-brand-name="<?= esc($brand['brand_display_name'] ?? 'DawaTrack', 'attr') ?>"
    data-brand-logo="<?= esc($brand['assets']['LOGO'] ?? '', 'attr') ?>"
    data-user-name="<?= esc((string) ($user?->username ?? 'Signed-in user'), 'attr') ?>"
    data-permissions="<?= esc(json_encode($platformPermissions), 'attr') ?>"
></div>

<?= $this->endSection() ?>