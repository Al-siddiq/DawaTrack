<?= $this->extend(config('Auth')->views['layout']) ?>
<?= $this->section('main') ?>
<h1 id="auth-heading">Recover access</h1>
<p>Enter your account email. If it is eligible, recovery instructions will be sent. This response never confirms whether an account exists.</p>
<form action="<?= esc(service('sameOriginUrl')->to('login/magic-link'), 'attr') ?>" method="post" class="auth-form">
    <?= csrf_field() ?>
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" autocomplete="email" inputmode="email" class="form-control" value="<?= esc(old('email')) ?>" required autofocus>
    <button type="submit" class="btn btn-primary btn-lg">Send recovery instructions</button>
</form>
<a class="btn btn-link" href="<?= esc(service('sameOriginUrl')->to('login'), 'attr') ?>">Return to sign in</a>
<?= $this->endSection() ?>
