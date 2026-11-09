<?= $this->extend('layouts/shell') ?>

<?php $this->setVar('entryScript', 'assets/js/admin/app.js'); ?>

<?= $this->section('content') ?>

<?php
$user = auth()->user();
$platformPermissions = [];
foreach (array_keys(config('AuthGroups')->permissions) as $permission) {
    if (str_starts_with($permission, 'platform.') && $user?->can($permission)) {
        $platformPermissions[] = $permission;
    }
}
?>
<div id="admin-app"
    data-brand-name="<?= esc('DawaTrack', 'attr') ?>"
    data-brand-logo="<?= esc('', 'attr') ?>"
    data-user-name="<?= esc((string) ($user?->username ?? 'Signed-in user'), 'attr') ?>"
    data-permissions="<?= esc(json_encode($platformPermissions), 'attr') ?>"
></div>

<?= $this->endSection() ?>