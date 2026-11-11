<?php helper('ui'); ?>
<?= $this->extend('auth/layout') ?>
<?php $this->setVar('adminEntryOverride', true) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading" tabindex="-1">Admin access is needed</h1>
<p>This account cannot open the Admin workspace. A shop account does not give Admin access.</p>
<div class="button-stack">
    <a class="ui-button" href="<?= esc(service('sameOriginUrl')->to('/'), 'attr') ?>">Return to your shop</a>
    <a class="ui-button ui-button--secondary" href="<?= esc(service('sameOriginUrl')->to('logout'), 'attr') ?>">Sign out</a>
</div>
<?= $this->endSection() ?>
