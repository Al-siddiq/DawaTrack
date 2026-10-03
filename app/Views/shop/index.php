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

<div id="shop-app"></div>

<?= $this->endSection() ?>