<?= $this->extend(config('Auth')->views['layout']) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading">Check your email</h1>
<p>If the address is eligible, DawaTrack has sent a time-limited sign-in link. For security, this page does not confirm whether an account exists.</p>
<a class="btn btn-primary" href="<?= esc(service('sameOriginUrl')->to('login'), 'attr') ?>">Return to sign in</a>
<?= $this->endSection() ?>
