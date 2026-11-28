<?php helper('ui'); ?>
<?= $this->extend('auth/layout') ?>

<?= $this->section('main') ?>

<header class="mb-4">
    <div class="eyebrow">
        Security requirement
    </div>

    <h1 id="auth-heading" tabindex="-1">
        Choose a new password
    </h1>

    <p>
        <?= ! empty($magicLinkSession)
            ? 'You signed in using a secure one-time link. Choose a new password to secure your account.'
            : 'Your temporary password must be replaced before you can continue.' ?>
    </p>
</header>

<form
    method="post"
    action="<?= esc(service('sameOriginUrl')->to('change-password'), 'attr') ?>"
    class="auth-form"
    data-busy-label="Saving new password…"
>
    <?= csrf_field() ?>

    <?php if (empty($magicLinkSession)): ?>
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
    <?php endif; ?>

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
        class="ui-button"
    >
        Change password
    </button>
</form>

<?php if (empty($magicLinkSession)): ?>
    <p class="auth-help">
        If you do not know your current password,
        <a href="<?= esc(site_url('logout'), 'attr') ?>">sign out and request a secure sign-in link</a>.
    </p>
<?php endif; ?>

<?= $this->endSection() ?>