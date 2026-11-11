<?php helper('ui'); ?>
<?= $this->extend(config('Auth')->views['layout']) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading" tabindex="-1">Check your email</h1>
<p>If sign-in help is available for that address, an email link has been sent. The link expires. This message does not confirm whether the address has an account.</p>
<a class="ui-button" href="<?= esc(service('sameOriginUrl')->to('login'), 'attr') ?>">Return to sign in</a>
<?= $this->endSection() ?>
