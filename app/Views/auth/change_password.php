<?= $this->extend('auth/layout') ?>

<?= $this->section('main') ?>

<header class="mb-4">
    <div class="eyebrow">
        Security requirement
    </div>

    <h1 id="auth-heading">
        Choose a new password
    </h1>

    <p>
        Your temporary password must be replaced before you can continue.
    </p>
</header>

<form
    method="post"
    action="<?= esc(service('sameOriginUrl')->to('change-password'), 'attr') ?>"
    class="auth-form"
    novalidate
>
    <?= csrf_field() ?>

    <label for="current-password">
        Current temporary password
    </label>

    <input
        id="current-password"
        name="current_password"
        type="password"
        class="form-control"
        autocomplete="current-password"
        required
    >

    <label for="new-password">
        New password
    </label>

    <input
        id="new-password"
        name="password"
        type="password"
        class="form-control"
        autocomplete="new-password"
        minlength="12"
        required
    >

    <label for="confirm-password">
        Confirm new password
    </label>

    <input
        id="confirm-password"
        name="password_confirm"
        type="password"
        class="form-control"
        autocomplete="new-password"
        minlength="12"
        required
    >

    <button
        type="submit"
        class="btn btn-primary"
    >
        Change password
    </button>
</form>

<?= $this->endSection() ?>