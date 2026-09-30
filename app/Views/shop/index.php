<?= $this->extend('layouts/shell') ?>
<?= $this->setVar('entryScript', 'assets/js/shop/app.js') ?>
<?= $this->section('content') ?>
<div id="shop-app" v-cloak>
  <div v-if="loading" class="status-panel" role="status">Loading your Shop…</div>
  <div v-else>
    <div class="alert" :class="{'alert-success':access.mode==='FULL','alert-warning':access.mode==='READ_ONLY','alert-danger':['BLOCKED','CLOSED'].includes(access.mode)}" role="status">
      <strong>{{access.mode}}</strong> — {{access.message}}
    </div>
    <nav class="phase-nav" aria-label="Shop account">
      <button v-for="item in navigation" :key="item.id" class="btn" :class="page===item.id?'btn-primary':'btn-outline-primary'" @click="page=item.id">{{item.label}}</button>
      <a class="btn btn-outline-secondary" href="/logout">Sign out</a>
    </nav>

    <section v-if="page==='home'" class="welcome-panel">
      <div class="eyebrow">{{context.role==='PRIMARY_OWNER'?'Primary Owner':'Staff'}}</div>
      <h1>{{context.shop?.name||'Account home'}}</h1>
      <p v-if="context.role==='STAFF'">Your Shop access is active. Operational modules will appear only when implemented and explicitly permitted.</p>
      <p v-else>Manage the identity, access and Shop configuration available in Phase 1.</p>
      <div v-if="context.permissions?.length" class="permission-summary"><strong>Effective permissions</strong><div class="badge-list"><span v-for="code in context.permissions" :key="code" class="badge text-bg-light">{{code}}</span></div></div>
    </section>

    <section v-if="page==='staff' && context.capabilities.manage_staff" class="workspace">
      <h1>Staff</h1>
      <form class="responsive-form" @submit.prevent="createStaff" novalidate>
        <label>Name<input v-model="staffForm.name" required class="form-control" :class="{'is-invalid':fieldErrors.name}"><span v-if="fieldErrors.name" class="invalid-feedback">{{fieldErrors.name[0]}}</span></label>
        <label>Email<input v-model="staffForm.email" type="email" required class="form-control" :class="{'is-invalid':fieldErrors.email}"><span v-if="fieldErrors.email" class="invalid-feedback">{{fieldErrors.email[0]}}</span></label>
        <label>Temporary password<input v-model="staffForm.password" type="password" autocomplete="new-password" minlength="12" required class="form-control" :class="{'is-invalid':fieldErrors.password}"><span v-if="fieldErrors.password" class="invalid-feedback">{{fieldErrors.password[0]}}</span></label>
        <label>Creation reason<input v-model="staffForm.reason" required class="form-control" :class="{'is-invalid':fieldErrors.reason}"><span v-if="fieldErrors.reason" class="invalid-feedback">{{fieldErrors.reason[0]}}</span></label>
        <button class="btn btn-primary" :disabled="busy||readOnly">{{busy?'Creating…':'Create Staff'}}</button>
      </form>
      <div v-if="!staff.length" class="empty-panel">No Staff accounts yet.</div>
      <div class="record-grid">
        <article v-for="member in staff" :key="member.id" class="record-card">
          <div><strong>{{member.username}}</strong><p>{{member.email||'No email shown'}} · {{member.role}} · {{member.status}}</p></div>
          <div v-if="member.role==='STAFF'" class="card-actions">
            <button class="btn btn-outline-primary" :disabled="busy||readOnly" @click="openDialog('status',member)">{{member.status==='ACTIVE'?'Deactivate':'Reactivate'}}</button>
            <button class="btn btn-outline-secondary" :disabled="busy||readOnly" @click="openDialog('reset',member)">Reset credentials</button>
          </div>
          <details v-if="member.role==='STAFF'" @toggle="$event.target.open && !permissionStates[member.id] && loadPermissions(member)">
            <summary>Permissions and history</summary>
            <div v-if="permissionStates[member.id]?.loading" class="status-panel" role="status">Loading permissions…</div>
            <div v-else-if="permissionStates[member.id]?.error" class="alert alert-danger">{{permissionStates[member.id].error}}</div>
            <div v-else-if="permissionStates[member.id]" class="permission-workspace">
              <label>Search permissions<input v-model="permissionSearch[member.id]" type="search" class="form-control" placeholder="Search by feature or action"></label>
              <section v-for="group in permissionGroups(member)" :key="group.name" class="permission-group"><h3>{{group.name}}</h3><div class="permission-grid"><label v-for="permission in group.permissions" :key="permission.code" class="permission-option"><input type="checkbox" :checked="permissionStates[member.id].active.includes(permission.code)" :disabled="busy||readOnly||member.status!=='ACTIVE'" @click.prevent="openDialog('permission',member,permission)"> <span>{{permission.label}}<small>{{permission.code}}</small></span></label></div></section>
              <details class="permission-history"><summary>Grant history</summary><div v-if="!permissionStates[member.id].history.length" class="empty-panel">No permission history.</div><ol><li v-for="entry in permissionStates[member.id].history" :key="entry.id"><code>{{entry.permission_code}}</code> granted {{entry.granted_at}} <span v-if="entry.revoked_at">· revoked {{entry.revoked_at}}</span><span v-else>· active</span></li></ol></details>
            </div>
          </details>
        </article>
      </div>
    </section>

    <section v-if="page==='settings' && context.capabilities.manage_shop_settings" class="workspace">
      <h1>Shop settings</h1>
      <form class="responsive-form" @submit.prevent="saveSettings" novalidate><label v-for="field in settingFields" :key="field.key">{{field.label}}<input v-model="settings[field.key]" class="form-control" :class="{'is-invalid':fieldErrors[field.key]}" :type="field.type||'text'" :required="field.required"><span v-if="fieldErrors[field.key]" class="invalid-feedback">{{fieldErrors[field.key][0]}}</span></label><button class="btn btn-primary" :disabled="busy||readOnly">Save settings</button></form>
    </section>

    <section v-if="page==='branding' && context.capabilities.manage_branding" class="workspace"><h1>Branding</h1><div class="brand-preview" :style="previewStyle"><strong>{{branding.brand_display_name}}</strong><span>{{branding.tagline}}</span></div><form class="responsive-form" @submit.prevent="saveBranding"><label v-for="field in brandFields" :key="field.key">{{field.label}}<input v-model="branding[field.key]" class="form-control" :type="field.colour?'color':'text'" required></label><button class="btn btn-primary" :disabled="busy||readOnly">Save branding</button></form><h2>Brand assets</h2><form @submit.prevent="uploadAsset"><label>Asset type<select v-model="assetType" class="form-select"><option v-for="type in assetTypes">{{type}}</option></select></label><label>Image<input ref="asset" type="file" accept="image/png,image/jpeg,image/webp,image/x-icon" class="form-control" required></label><button class="btn btn-primary" :disabled="busy||readOnly">Upload or replace</button></form><div class="record-grid"><article v-for="asset in assets" class="record-card"><strong>{{asset.asset_type}}</strong><span>{{asset.width_px}}×{{asset.height_px}}</span></article></div></section>
    <section v-if="page==='domains' && context.capabilities.manage_domains" class="workspace"><h1>Domains</h1><article v-for="domain in domains" class="record-card"><div><strong>{{domain.hostname}}</strong><p>{{domain.domain_type}} · {{domain.verification_status}} · TLS {{domain.certificate_status||'pending'}}</p></div><span v-if="domain.is_primary" class="badge text-bg-success">Primary</span></article><form class="responsive-form" @submit.prevent="requestDomain"><label>Custom hostname<input v-model="hostname" class="form-control" placeholder="shop.example.com" required></label><button class="btn btn-primary" :disabled="busy||readOnly">Request verification</button></form><div v-if="dnsToken" class="alert alert-info">Create a DNS TXT record containing <code>{{dnsToken}}</code>.</div></section>
    <section v-if="page==='subscription' && context.capabilities.view_subscription" class="workspace"><h1>Subscription and access</h1><dl class="detail-grid"><div><dt>Plan</dt><dd>{{subscription.entitlement?.plan_code_snapshot||'None'}}</dd></div><div><dt>Access</dt><dd>{{subscription.access?.mode}}</dd></div><div><dt>Starts</dt><dd>{{subscription.entitlement?.starts_at||'—'}}</dd></div><div><dt>Ends</dt><dd>{{subscription.entitlement?.ends_at||'—'}}</dd></div><div><dt>Staff allowance</dt><dd>{{subscription.active_staff}} / {{subscription.entitlement?.max_active_staff_snapshot||0}}</dd></div></dl></section>

    <div v-if="notice" class="toast-notice" role="status">{{notice}}</div><div v-if="error" class="alert alert-danger" role="alert">{{error}}</div>

    <div v-if="dialog.open" class="dialog-backdrop" @click.self="closeDialog">
      <section class="reason-dialog" role="dialog" aria-modal="true" aria-labelledby="dialog-title" @keydown.esc="closeDialog">
        <h2 id="dialog-title">{{dialog.type==='reset'?'Reset Staff credentials':dialog.type==='status'?(dialog.member.status==='ACTIVE'?'Deactivate Staff':'Reactivate Staff'):(permissionStates[dialog.member.id]?.active.includes(dialog.permission.code)?'Revoke permission':'Grant permission')}}</h2>
        <p v-if="dialog.type==='reset'">A temporary password will be shown once. Existing remembered and active Shop sessions will be invalidated.</p>
        <p v-if="dialog.type==='permission'"><code>{{dialog.permission.code}}</code></p>
        <label for="action-reason">Reason</label><textarea id="action-reason" ref="dialogReason" v-model="dialog.reason" class="form-control" rows="3" required></textarea>
        <div v-if="dialog.error" class="alert alert-danger" role="alert">{{dialog.error}}</div>
        <div v-if="temporaryCredential" class="alert alert-warning" role="status"><strong>Temporary password — copy it now:</strong><code class="credential-value">{{temporaryCredential}}</code><p>It will not be shown again.</p></div>
        <div class="button-row"><button v-if="!temporaryCredential" class="btn btn-primary" :disabled="busy" @click="confirmDialog">Confirm</button><button class="btn btn-outline-secondary" @click="closeDialog">{{temporaryCredential?'Done':'Cancel'}}</button></div>
      </section>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
