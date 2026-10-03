<?= $this->extend(config('Auth')->views['layout']) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading">Registration is managed</h1>
<p>DawaTrack accounts are created by an authorized Platform Administrator or Shop Owner. Public registration is not available.</p>
<a class="btn btn-primary" href="<?= esc(service('sameOriginUrl')->to('login'), 'attr') ?>">Sign in</a>
<?= $this->endSection() ?>
