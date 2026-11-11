<?php helper('ui'); ?>
<?= $this->extend(config('Auth')->views['layout']) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading" tabindex="-1">Ask for an account</h1>
<p>A shop owner or an Admin must create your account. You cannot register yourself here.</p>
<a class="ui-button" href="<?= esc(service('sameOriginUrl')->to('login'), 'attr') ?>">Sign in</a>
<?= $this->endSection() ?>
