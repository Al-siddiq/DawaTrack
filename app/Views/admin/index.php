<?= $this->extend('layouts/shell') ?>
<?= $this->setVar('entryScript', 'assets/js/admin/app.js') ?>
<?= $this->section('content') ?>
<div id="admin-app" class="workspace" v-cloak>
  <header><div class="eyebrow">Platform administration</div><h1>Onboard a Shop</h1><p>Create the Owner, Tenant, Shop, brand, platform address and Trial in one audited transaction.</p></header>
  <ol class="step-indicator" aria-label="Onboarding progress"><li :class="{active:step===1}">Details</li><li :class="{active:step===2}">Review</li><li :class="{active:step===3}">Complete</li></ol>
  <div v-if="error" class="alert alert-danger" role="alert">{{error}} No partial account was retained.</div>
  <form v-if="step===1" class="responsive-form" @submit.prevent="clearErrors();step=2" novalidate>
    <label v-for="field in fields" :key="field.key">{{field.label}}
      <input v-model="form[field.key]" class="form-control" :class="{'is-invalid':fieldErrors[field.key]}" :type="field.type||'text'" :autocomplete="field.type==='password'?'new-password':undefined" :required="field.required" :minlength="field.type==='password'?12:undefined">
      <span v-if="fieldErrors[field.key]" class="invalid-feedback">{{fieldErrors[field.key][0]}}</span>
    </label>
    <button class="btn btn-primary">Review onboarding</button>
  </form>
  <section v-if="step===2" aria-labelledby="review-heading"><h2 id="review-heading">Confirm details</h2><p>The temporary password is never redisplayed after submission.</p><dl class="detail-grid"><div v-for="field in fields" :key="field.key"><dt>{{field.label}}</dt><dd>{{field.type==='password'?'••••••••••••':form[field.key]}}</dd></div></dl><div class="button-row"><button class="btn btn-outline-secondary" @click="step=1">Back</button><button class="btn btn-primary" :disabled="busy" @click="submit">{{busy?'Creating…':'Create Tenant and Shop'}}</button></div></section>
  <section v-if="step===3" class="success-panel" role="status"><h2>Shop onboarding complete</h2><dl class="detail-grid"><div><dt>Tenant</dt><dd>{{result.tenant_public_id}}</dd></div><div><dt>Shop</dt><dd>{{result.shop_public_id}}</dd></div><div><dt>Owner</dt><dd>{{result.owner_email}}</dd></div><div><dt>Platform address</dt><dd>{{result.hostname}}</dd></div><div><dt>Trial</dt><dd>30 days · Active</dd></div></dl></section>
</div>
<?= $this->endSection() ?>
