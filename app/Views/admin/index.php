<?= $this->extend('layouts/shell') ?>

<?php $this->setVar('entryScript', 'assets/js/admin/app.js'); ?>

<?= $this->section('content') ?>

<div id="admin-app"></div>

<?= $this->endSection() ?>