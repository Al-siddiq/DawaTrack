import { WorkspaceShell } from "../components/ui/workspace-shell.js";
import {
  UiAlert,
  UiLoading,
  UiButton,
  UiEmptyState,
  UiAccordion,
  UiBadge,
  UiStatusPanel,
} from "../components/ui/index.js";
import {
  adminPages,
  readWorkspacePresentation,
} from "../shared/workspace-registry.js";
import { useWorkspaceNavigation } from "../shared/workspace-navigation.js";
import { phaseOneApi } from "../api/phase-one.js";
import { AuditPanel } from "../components/audit-panel.js";
import { field, action, submit, message, pager } from "../components/commercial-ui.js";
import {
  managementLabel,
  facts,
  note,
  selectField,
  fieldSummary,
  useTradingConfirm,
} from "../components/management-ui.js";
const { createApp, reactive, ref, h, computed, watch, onMounted, onUnmounted } =
  window.Vue;
export const AdminApp = {
  setup() {
    const presentation = readWorkspacePresentation(
      document.getElementById("admin-app"),
    );
    const can = (permission) => presentation.permissions.includes(permission);
    const navigation = computed(() =>
      adminPages.filter((item) => can(item.permission)),
    );
    const workspaceNavigation = useWorkspaceNavigation({
      items: navigation,
      fallback: "overview",
    });
    workspaceNavigation.initialize();
    const page = workspaceNavigation.currentId,
      confirmation = useTradingConfirm();
    const pageLoading = ref(false),
      step = ref(1),
      busy = ref(false),
      error = ref(""),
      notice = ref(""),
      online = ref(navigator.onLine),
      search = ref("");
    const errorTarget = ref("");
    const fieldErrors = reactive({}),
      result = reactive({}),
      domains = ref([]),
      domainReason = reactive({}),
      tenants = ref([]),
      dashboard = ref(null),
      directoryPagination = ref({ page: 1, per_page: 25, total: 0, total_pages: 0 }),
      directoryLoading = ref(false),
      operationsPagination = ref({ page: 1, per_page: 50, total: 0, total_pages: 0 }),
      operationsLoading = ref(false),
      directoryFilters = reactive({ tenant_status: "", shop_status: "", entitlement_status: "" }),
      tenantDetail = ref(null),
      tenantDetailLoading = ref(false),
      operationForms = reactive({}),
      ownerActionForm = reactive({ target_email: "", reason: "" });
    const form = reactive({
      business_name: "",
      shop_name: "",
      contact_phone: "",
      contact_email: "",
      address_line_1: "",
      address_line_2: "",
      city_town: "",
      state_region: "",
      platform_subdomain: "",
      owner_name: "",
      owner_email: "",
      reason: "New Tenant onboarding",
    });
    const fields = [
      ["business_name", "Business name", "text", true],
      ["shop_name", "Shop name", "text", true],
      ["contact_phone", "Contact phone", "tel"],
      ["contact_email", "Contact email", "email"],
      ["address_line_1", "Address", "text", true],
      ["address_line_2", "Address line 2", "text"],
      ["city_town", "City or town", "text", true],
      ["state_region", "State or region", "text", true],
      ["platform_subdomain", "DawaTrack address prefix", "text", true],
      ["owner_name", "Shop owner name", "text", true],
      ["owner_email", "Primary Owner email", "email", true],
      ["reason", "Why are you adding this shop?", "text", true],
    ];
    const blocked = computed(
      () => busy.value || pageLoading.value || directoryLoading.value || operationsLoading.value || tenantDetailLoading.value || !online.value,
    );
    const connection = () => {
      online.value = navigator.onLine;
    };
    const set = (object, key) => (value) => {
      object[key] = value;
    };
    function clearErrors() {
      error.value = "";
      notice.value = "";
      Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);
    }
    async function run(work, success = "", target = "") {
      if (busy.value || !online.value) return;
      busy.value = true;
      clearErrors();
      errorTarget.value = target;
      try {
        await work();
        notice.value = success;
      } catch (failure) {
        error.value = message(failure);
        Object.assign(fieldErrors, failure.details || {});
      } finally {
        busy.value = false;
      }
    }
    async function loadDomains() {
      domains.value = (await phaseOneApi.platformDomains()).data;
    }
    function adaptTenant(row) {
      const entitlement = row.entitlement || {};
      return {
        public_id: row.tenant_public_id,
        legal_or_business_name: row.business_name,
        status: row.tenant_status,
        entitlement_status: entitlement.status,
        tenant_created_at: row.tenant_created_at,
        shop_public_id: row.shop?.public_id || "",
        shop_name: row.shop?.name || "Shop record unavailable",
        shop_status: row.shop?.status || "INACTIVE",
        primary_hostname: row.shop?.primary_hostname || "",
        active_staff: row.active_staff_count || 0,
        subscription: {
          current_entitlement: ["TRIAL", "ACTIVE"].includes(entitlement.status) ? {
            plan_name: entitlement.plan_name || entitlement.plan_code || "Current plan",
            starts_at: entitlement.starts_at,
            ends_at: entitlement.ends_at,
          } : null,
          future_count: entitlement.scheduled_count || 0,
          future_periods: [],
          historical_periods: [],
          events: [],
        },
      };
    }
    let operationsGeneration = 0;
    async function loadOperations(pageNumber = 1) {
      const generation = ++operationsGeneration;
      operationsLoading.value = true;
      tenants.value = [];
      clearErrors();
      try {
        const response = await phaseOneApi.platformOperations({
          q: page.value === "plans" ? search.value.trim() : "",
          page: pageNumber,
          per_page: 50,
        });
        if (generation !== operationsGeneration) return;
        tenants.value = (response.data.items || []).map(adaptTenant);
        operationsPagination.value = response.data.pagination;
      } catch (failure) {
        if (generation === operationsGeneration) error.value = message(failure);
      } finally {
        if (generation === operationsGeneration) operationsLoading.value = false;
      }
    }
    async function loadDashboard() {
      dashboard.value = (await phaseOneApi.platformDashboard()).data;
    }
    let directoryGeneration = 0;
    async function loadDirectory(pageNumber = 1) {
      const generation = ++directoryGeneration;
      directoryLoading.value = true;
      tenants.value = [];
      clearErrors();
      try {
        const response = await phaseOneApi.platformOperations({
          q: search.value.trim(),
          tenant_status: directoryFilters.tenant_status,
          shop_status: directoryFilters.shop_status,
          entitlement_status: directoryFilters.entitlement_status,
          page: pageNumber,
          per_page: 25,
        });
        if (generation !== directoryGeneration) return;
        tenants.value = (response.data.items || []).map(adaptTenant);
        directoryPagination.value = response.data.pagination;
        tenantDetail.value = null;
      } catch (failure) {
        if (generation === directoryGeneration) error.value = message(failure);
      } finally {
        if (generation === directoryGeneration) directoryLoading.value = false;
      }
    }
    async function openTenantDetail(tenant) {
      tenantDetailLoading.value = true;
      tenantDetail.value = null;
      clearErrors();
      try {
        tenantDetail.value = (await phaseOneApi.platformTenant(tenant.public_id)).data;
      } catch (failure) {
        error.value = message(failure);
      } finally {
        tenantDetailLoading.value = false;
      }
    }
    function applyDirectoryFilters() {
      loadDirectory(1);
    }
    function clearDirectoryFilters() {
      search.value = "";
      directoryFilters.tenant_status = "";
      directoryFilters.shop_status = "";
      directoryFilters.entitlement_status = "";
      loadDirectory(1);
    }
    async function loadPage(id) {
      const generation = ++loadGeneration;
      pageLoading.value = true;
      clearErrors();
      search.value = "";
      tenantDetail.value = null;
      try {
        if (id === "overview" && can("platform.access")) {
          dashboard.value = null;
          await loadDashboard();
        }
        else if (id === "domains" && can("platform.domains")) await loadDomains();
        else if (id === "shops" && can("platform.access")) await loadDirectory(1);
        else if (id === "plans" && can("platform.access")) await loadOperations(1);
      } catch (failure) {
        if (generation === loadGeneration) error.value = message(failure);
      } finally {
        if (generation === loadGeneration) pageLoading.value = false;
      }
    }
    let loadGeneration = 0;
    function operationForm(tenant) {
      return (operationForms[tenant.public_id] ??= reactive({
        operation: "ACTIVATE",
        plan_code: "STARTER",
        starts_at: "",
        ends_at: "",
        external_reference: "",
        reason: "",
      }));
    }
    async function onboard() {
      if (!can("platform.onboard")) return;
      await run(async () => {
        Object.assign(result, (await phaseOneApi.onboard(form)).data);
        step.value = 3;
      }, "Tenant onboarding created. Owner invitation delivery is shown below.");
      if (error.value) step.value = 1;
    }
    async function domainAction(domain, command, ready) {
      if (!can("platform.domains") || blocked.value) return;
      const reason = (domainReason[domain.public_id] || "").trim();
      if (!reason) {
        error.value = "Enter why you are changing this web address.";
        return;
      }
      if (
        !(await confirmation.ask({
          title: "Change this web address?",
          message: `${domain.hostname}: ${command === "tls" ? (ready ? "mark the secure connection ready" : "mark the secure connection failed") : command === "activate" ? "activate this address" : command === "deactivate" ? "stop using this address" : "make this the main address"}. Existing ownership and access rules still apply.`,
          confirm: "Confirm address change",
          danger: command === "deactivate" || ready === false,
        }))
      )
        return;
      await run(async () => {
        const body = { reason };
        if (ready !== undefined) body.ready = ready;
        await phaseOneApi.domainAction(domain.public_id, command, body);
        await loadDomains();
        domainReason[domain.public_id] = "";
      }, "Web address updated.");
    }
    async function subscriptionAction(tenant) {
      if (!can("platform.subscriptions") || blocked.value) return;
      const data = operationForm(tenant);
      if (
        !(await confirmation.ask({
          title: "Apply this plan change?",
          message: `${tenant.shop_name}: ${data.operation.toLowerCase()} ${data.plan_code.toLowerCase()} from ${data.starts_at} to ${data.ends_at} UTC. This affects shop access and staff places. Existing plan periods stay in history.`,
          confirm: "Apply plan change",
        }))
      )
        return;
      await run(
        async () => {
          await phaseOneApi.subscriptionAction(tenant.public_id, data);
          await loadOperations();
          data.reason = "";
        },
        "Plan change saved.",
        tenant.public_id,
      );
    }
    async function lifecycle(tenant, type, command) {
      if (!can("platform.tenants") || blocked.value) return;
      const data = operationForm(tenant);
      if (!data.reason.trim()) {
        error.value = "Enter a reason before changing account access.";
        return;
      }
      const descriptions = {
        close:
          "Close this business account permanently. Records stay, but shop access will be closed.",
        suspend:
          "Pause this business account. Shop access will be blocked until it is reopened.",
        reactivate:
          "Reopen access, subject to the current account and plan rules.",
        deactivate: "Stop access to this shop. Its past records stay.",
      };
      if (
        !(await confirmation.ask({
          title: `Change access for ${tenant.shop_name}?`,
          message: descriptions[command],
          confirm:
            command === "close"
              ? "Close account permanently"
              : "Confirm access change",
          danger: ["close", "suspend", "deactivate"].includes(command),
        }))
      )
        return;
      await run(async () => {
        if (type === "tenant")
          await phaseOneApi.tenantAction(tenant.public_id, command, {
            reason: data.reason,
          });
        else
          await phaseOneApi.shopAction(tenant.shop_public_id, command, {
            reason: data.reason,
          });
        await loadOperations();
        data.reason = "";
      }, "Account access updated.");
    }
    function formField(object, key, label, options = {}) {
      return field(
        `admin-${object === form ? "onboard" : "operation"}-${key}`,
        label,
        object[key] || "",
        set(object, key),
        { disabled: blocked.value, errors: fieldErrors[key] || [], ...options },
      );
    }
    function timeline(subscription) {
      return h(
        UiAccordion,
        { title: "Entitlement summary" },
        {
          default: () => [
            h("p", "Scheduled future periods: " + (subscription.future_count || 0)),
            note("Open Tenant details to review the next and recent entitlement periods. Activity remains available in the Audit workspace."),
          ],
        },
      );
    }
    function planForm(tenant) {
      if (!can("platform.subscriptions") || page.value !== "plans") return null;
      const data = operationForm(tenant),
        prefix = `plan-${tenant.public_id}`;
      const f = (key, label, options = {}) =>
        field(`${prefix}-${key}`, label, data[key], set(data, key), {
          disabled: blocked.value,
          errors:
            errorTarget.value === tenant.public_id
              ? fieldErrors[key] || []
              : [],
          ...options,
        });
      return h(
        UiAccordion,
        { title: `Change plan for ${tenant.shop_name}` },
        {
          default: () =>
            h(
              "form",
              {
                class: "commercial-form",
                onSubmit: (event) => {
                  event.preventDefault();
                  subscriptionAction(tenant);
                },
              },
              [
                selectField(
                  `${prefix}-operation`,
                  "Change",
                  data.operation,
                  set(data, "operation"),
                  [
                    ["ACTIVATE", "Start plan access"],
                    ["RENEW", "Renew plan"],
                    ["UPGRADE", "Move to a larger plan"],
                    ["DOWNGRADE", "Move to a smaller plan"],
                    ["EXTEND", "Extend the plan period"],
                  ],
                  { disabled: blocked.value },
                ),
                selectField(
                  `${prefix}-plan`,
                  "Plan",
                  data.plan_code,
                  set(data, "plan_code"),
                  [
                    ["STARTER", "Starter"],
                    ["GROWTH", "Growth"],
                    ["TRIAL", "Trial"],
                  ],
                  { disabled: blocked.value },
                ),
                f("starts_at", "Starts (UTC)", {
                  type: "datetime-local",
                  required: true,
                }),
                f("ends_at", "Ends (UTC)", {
                  type: "datetime-local",
                  required: true,
                }),
                note(
                  "These times use UTC. Enter the intended UTC time; this form does not convert from your device’s time zone.",
                ),
                f("external_reference", "External reference (optional)", {
                  maxlength: 120,
                }),
                f("reason", "Why are you changing this plan?", {
                  required: true,
                  maxlength: 500,
                }),
                submit("Review plan change", blocked.value, {
                  loading: busy.value,
                  icon: "plan",
                }),
              ],
            ),
        },
      );
    }
    function tenantCard(tenant) {
      const subscription = tenant.subscription,
        current = subscription.current_entitlement,
        data = operationForm(tenant);
      return h(
        "article",
        { class: "commercial-card", key: tenant.public_id },
        [
          h("div", { class: "trading-task-header" }, [
            h("h3", tenant.shop_name),
            h(UiBadge, { label: managementLabel(tenant.shop_status) }),
          ]),
          h("p", tenant.legal_or_business_name),
          facts([
            ["Business account", managementLabel(tenant.status)],
            ["Plan status", managementLabel(tenant.entitlement_status)],
            ["Current plan", current?.plan_name || "No current plan"],
            [
              "Current plan period",
              current
                ? `${current.starts_at} → ${current.ends_at} UTC`
                : "View only when account access permits",
            ],
            ["Active staff", tenant.active_staff],
            ["Main web address", tenant.primary_hostname || "Not configured"],
            ["Scheduled plan periods", subscription.future_count || 0],
          ]),
          action("View Tenant details", () => openTenantDetail(tenant), blocked.value),
          timeline(subscription),
          planForm(tenant),
          page.value === "shops" &&
            can("platform.tenants") &&
            h(
              UiAccordion,
              {
                title: "Manage account and shop access",
                class: "trading-danger-zone",
              },
              {
                default: () => [
                  field(
                    `lifecycle-${tenant.public_id}`,
                    "Why are you changing access?",
                    data.reason,
                    set(data, "reason"),
                    { required: true, maxlength: 500, disabled: blocked.value },
                  ),
                  h(
                    "div",
                    { class: "ui-actions" },
                    [
                      tenant.status === "ACTIVE" &&
                        action(
                          "Pause business account",
                          () => lifecycle(tenant, "tenant", "suspend"),
                          blocked.value,
                          "btn-outline-danger",
                          { icon: "cancel-record" },
                        ),
                      tenant.status === "SUSPENDED" &&
                        action(
                          "Reopen business account",
                          () => lifecycle(tenant, "tenant", "reactivate"),
                          blocked.value,
                        ),
                      tenant.status !== "CLOSED" &&
                        action(
                          "Close account permanently",
                          () => lifecycle(tenant, "tenant", "close"),
                          blocked.value,
                          "btn-danger",
                          { icon: "cancel-record" },
                        ),
                      tenant.shop_status === "ACTIVE"
                        ? action(
                            "Stop shop access",
                            () => lifecycle(tenant, "shop", "deactivate"),
                            blocked.value,
                            "btn-outline-danger",
                          )
                        : action(
                            "Reopen shop access",
                            () => lifecycle(tenant, "shop", "reactivate"),
                            blocked.value || tenant.status === "CLOSED",
                          ),
                    ].filter(Boolean),
                  ),
                ],
              },
            ),
          h(
            UiAccordion,
            { title: "Reference details" },
            {
              default: () =>
                facts([
                  ["Business account", tenant.public_id],
                  ["Shop", tenant.shop_public_id],
                ]),
            },
          ),
        ].filter(Boolean),
      );
    }
    function domainCard(domain) {
      return h("article", { class: "commercial-card", key: domain.public_id }, [
        h("h3", { class: "break-all" }, domain.hostname),
        h("p", domain.shop_name),
        facts([
          ["Address type", managementLabel(domain.domain_type)],
          ["Ownership", managementLabel(domain.verification_status)],
          [
            "Secure connection",
            managementLabel(domain.certificate_status || "PENDING"),
          ],
          [
            "Use",
            domain.is_primary
              ? "Main address"
              : domain.is_active
                ? "Available"
                : "Not active",
          ],
        ]),
        field(
          `domain-reason-${domain.public_id}`,
          "Why are you changing this address?",
          domainReason[domain.public_id] || "",
          (value) => {
            domainReason[domain.public_id] = value;
          },
          { required: true, disabled: blocked.value },
        ),
        h(
          "div",
          { class: "ui-actions" },
          [
            domain.domain_type === "CUSTOM" &&
              action(
                "Mark secure connection ready",
                () => domainAction(domain, "tls", true),
                blocked.value,
                "btn-outline-primary",
                { icon: "success" },
              ),
            domain.domain_type === "CUSTOM" &&
              action(
                "Mark secure connection failed",
                () => domainAction(domain, "tls", false),
                blocked.value,
                "btn-outline-danger",
                { icon: "error" },
              ),
            domain.domain_type === "CUSTOM" &&
              !domain.is_active &&
              action(
                "Activate address",
                () => domainAction(domain, "activate"),
                blocked.value,
                "btn-primary",
                { icon: "web-address" },
              ),
            domain.is_active &&
              !domain.is_primary &&
              action(
                "Make main address",
                () =>
                  domainAction(
                    domain,
                    domain.domain_type === "PLATFORM_SUBDOMAIN"
                      ? "fallback"
                      : "primary",
                  ),
                blocked.value,
              ),
            domain.domain_type === "CUSTOM" &&
              domain.is_active &&
              !domain.is_primary &&
              action(
                "Stop using address",
                () => domainAction(domain, "deactivate"),
                blocked.value,
                "btn-outline-danger",
              ),
          ].filter(Boolean),
        ),
      ]);
    }
    function metricCard(title, value, detail = "") {
      return h("article", { class: "commercial-card admin-metric-card col" }, [
        h("p", title),
        h("strong", String(value ?? "—")),
        detail && h("small", detail),
      ].filter(Boolean));
    }
    function dashboardView() {
      const data = dashboard.value;
      if (!data) return h(UiEmptyState, { title: "Dashboard unavailable", message: "Reload to try again." });
      const m = data.metrics;
      const deviceText = m.devices.state === "AVAILABLE" ? (m.devices.active + " active · " + m.devices.revoked + " revoked") : "Not available";
      const syncText = m.sync_foundation.state === "AVAILABLE" ? m.sync_foundation.generation_state : "Not available";
      return h("div", { class: "admin-dashboard" }, [
        h("div", { class: "row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3" }, [
          metricCard("Total Tenants", m.tenants.total, m.tenants.active + " active · " + m.tenants.suspended + " paused"),
          metricCard("Onboarding", m.tenants.onboarding, "Accounts being set up"),
          metricCard("Closed Tenants", m.tenants.closed, "Closed business accounts"),
          metricCard("Total Shops", m.shops.total, m.shops.active + " active · " + m.shops.inactive + " inactive"),
          metricCard("Current Trial plans", m.entitlements.trial_current),
          metricCard("Current paid plans", m.entitlements.paid_current),
          metricCard("Expired plans", m.entitlements.expired, "Expiry does not itself suspend a Tenant"),
          metricCard("Scheduled plans", m.entitlements.scheduled),
          metricCard("Platform Administrators", m.platform_administrators.enabled, "Enabled Shield accounts"),
          metricCard("Registered Devices", m.devices.registered ?? "Not available", deviceText),
          metricCard("Sync generation", syncText, m.sync_foundation.active_generation_count === null ? "Readiness not available" : (m.sync_foundation.active_generation_count + " active generation(s)")),
          metricCard("Entitlement data checks", m.entitlements.integrity_issues, "Multiple current periods require investigation"),
        ]),
        h("section", { class: "commercial-card admin-expiry-panel" }, [
          h("h3", "Upcoming subscription expiries"),
          h("p", "Current entitlements ending within the next 30 days."),
          !(data.upcoming_expiries || []).length && h(UiEmptyState, { title: "No upcoming expiries", message: "No current entitlement ends in the next 30 days." }),
          ...(data.upcoming_expiries || []).map(row => h("p", { key: row.tenant_public_id + row.ends_at }, [
            h("strong", row.business_name),
            " · " + (row.shop_name || "Shop") + " · " + row.plan_code + " · ends " + row.ends_at + " UTC",
          ])),
          h("small", "Updated " + data.as_of + " · " + data.freshness.state),
        ]),
      ]);
    }
    async function ownerInvitationAction() {
      const data = tenantDetail.value;
      if (!data || data.primary_owner.membership_status !== "INVITED") return;
      const reason = ownerActionForm.reason.trim();
      if (!reason) { error.value = "Enter why the Owner invitation is being resent."; return; }
      if (!(await confirmation.ask({
        title: "Resend the Primary Owner invitation?",
        message: "A fresh one-time sign-in link will be emailed. Any previous unused invitation link will stop working.",
        confirm: "Resend invitation",
      }))) return;
      let delivery = "FAILED";
      await run(async () => {
        const response = await phaseOneApi.ownerInvitation(data.tenant.public_id, { reason });
        delivery = response.data.delivery_status;
        tenantDetail.value = (await phaseOneApi.platformTenant(data.tenant.public_id)).data;
        ownerActionForm.reason = "";
      }, "Owner invitation request processed.");
      if (!error.value && delivery === "FAILED") notice.value = "The invitation link was prepared, but the email was not delivered. You can safely retry.";
    }
    async function ownerRecoveryAction() {
      const data = tenantDetail.value;
      if (!data || data.primary_owner.membership_status !== "ACTIVE") return;
      const reason = ownerActionForm.reason.trim();
      if (!reason) { error.value = "Enter why Owner access recovery is required."; return; }
      if (!(await confirmation.ask({
        title: "Send Owner access recovery?",
        message: "A fresh one-time sign-in link will be emailed to the current Primary Owner. Any previous unused magic link will stop working.",
        confirm: "Send recovery link",
      }))) return;
      let delivery = "FAILED";
      await run(async () => {
        const response = await phaseOneApi.ownerRecovery(data.tenant.public_id, { reason });
        delivery = response.data.delivery_status;
        tenantDetail.value = (await phaseOneApi.platformTenant(data.tenant.public_id)).data;
        ownerActionForm.reason = "";
      }, "Owner access recovery request processed.");
      if (!error.value && delivery === "FAILED") notice.value = "The access link was prepared, but the email was not delivered. Retry when email service is available.";
    }
    async function ownerReassignAction() {
      const data = tenantDetail.value;
      const owner = data?.primary_owner;
      if (!data || owner?.membership_status !== "ACTIVE") return;
      const reason = ownerActionForm.reason.trim();
      const targetEmail = ownerActionForm.target_email.trim();
      if (!targetEmail || !reason) { error.value = "Enter the new Owner email and the reason for the transfer."; return; }
      if (!(await confirmation.ask({
        title: "Transfer Primary Owner responsibility?",
        message: "Current Owner: " + (owner.owner_name || owner.owner_email) + ". New Owner: " + targetEmail + ". The former Owner's sessions and remembered access will be revoked. This action cannot be undone automatically.",
        confirm: "Confirm Owner transfer",
        danger: true,
      }))) return;
      let delivery = "FAILED";
      await run(async () => {
        const response = await phaseOneApi.ownerReassign(data.tenant.public_id, {
          current_owner_membership_public_id: owner.membership_public_id,
          target_email: targetEmail,
          reason,
          confirmed: true,
        });
        delivery = response.data.new_owner_access_delivery_status;
        tenantDetail.value = (await phaseOneApi.platformTenant(data.tenant.public_id)).data;
        ownerActionForm.target_email = "";
        ownerActionForm.reason = "";
      }, "Primary Owner responsibility transferred.");
      if (!error.value && delivery === "FAILED") notice.value = "Ownership was transferred, but the new Owner access email was not delivered. Use Owner recovery to retry.";
    }
    function tenantDetailView() {
      if (tenantDetailLoading.value) return h(UiLoading, { label: "Loading Tenant details…" });
      const data = tenantDetail.value;
      if (!data) return null;
      const tenant = data.tenant, shop = data.shop, entitlement = data.entitlements;
      const periodText = period => period ? ((period.plan_name || period.plan_code) + " · " + period.starts_at + " → " + period.ends_at + " UTC") : "None";
      return h("article", { class: "commercial-card admin-tenant-detail" }, [
        action("Back to Tenants", () => { tenantDetail.value = null; }, blocked.value, "btn-outline-primary"),
        h("h2", tenant.business_name),
        facts([
          ["Tenant status", managementLabel(tenant.status)],
          ["Created", tenant.created_at || "Not recorded"],
          ["Activated", tenant.activated_at || "Not activated"],
          ["Paused", tenant.suspended_at || "Not paused"],
          ["Closed", tenant.closed_at || "Not closed"],
          ["Status reason", tenant.status_reason || "None recorded"],
          ["Primary Owner membership", managementLabel(data.primary_owner.membership_status)],
          ["Active Staff", data.active_staff_count],
        ]),
        h("h3", "Primary Owner"),
        facts([
          ["Owner name", data.primary_owner.owner_name || "Not recorded"],
          ["Owner email", data.primary_owner.owner_email || "Not available"],
          ["Membership status", managementLabel(data.primary_owner.membership_status)],
          ["Membership reference", data.primary_owner.membership_public_id || "Not available"],
          ["Invited", data.primary_owner.invited_at || "Not invited"],
          ["Activated", data.primary_owner.activated_at || "Not activated"],
        ]),
        can("platform.tenants") && data.primary_owner.membership_status === "INVITED" && h("section", { class: "commercial-card" }, [
          h("p", "The Tenant and Shop are pending. The Owner must accept the invitation before access is activated."),
          field("owner-action-reason", "Reason for resending invitation", ownerActionForm.reason, value => { ownerActionForm.reason = value; }, { required: true, disabled: blocked.value }),
          action("Resend Owner invitation", ownerInvitationAction, blocked.value, "btn-primary"),
        ]),
        can("platform.tenants") && data.primary_owner.membership_status === "ACTIVE" && h("section", { class: "commercial-card" }, [
          h("h4", "Owner access recovery"),
          field("owner-action-reason", "Reason for access recovery or transfer", ownerActionForm.reason, value => { ownerActionForm.reason = value; }, { required: true, disabled: blocked.value }),
          action("Send Owner access recovery link", ownerRecoveryAction, blocked.value, "btn-outline-primary"),
          h("hr"),
          h("h4", "Reassign Primary Owner"),
          field("owner-target-email", "New Owner's existing DawaTrack email", ownerActionForm.target_email, value => { ownerActionForm.target_email = value; }, { type: "email", required: true, disabled: blocked.value }),
          note("The new Owner must already have an active DawaTrack account and must not have active access to another Tenant."),
          action("Review and transfer ownership", ownerReassignAction, blocked.value, "btn-outline-danger"),
        ]),
        h("h3", "Shop details"),
        shop ? facts([
          ["Shop", shop.name],
          ["Shop status", managementLabel(shop.status)],
          ["Contact phone", shop.contact_phone || "Not provided"],
          ["Contact email", shop.contact_email || "Not provided"],
          ["Address", [shop.address_line_1, shop.address_line_2, shop.city_town, shop.state_region, shop.country_code].filter(Boolean).join(", ")],
          ["Time zone", shop.timezone_name],
          ["Currency", shop.currency_code],
        ]) : note("No Shop record is linked to this Tenant.", "warning"),
        h("h3", "Entitlements"),
        facts([
          ["Current entitlement", entitlement.integrity_issue ? "Multiple current periods — review required" : periodText(entitlement.current)],
          ["Next scheduled entitlement", periodText(entitlement.next)],
          ["Current periods detected", entitlement.current_count],
          ["Recent previous periods", (entitlement.historical_periods || []).length],
        ]),
        ...(entitlement.historical_periods || []).map(row => h("p", { key: row.public_id }, periodText(row))),
        h("h3", "Primary web address"),
        data.primary_domain ? facts([
          ["Hostname", data.primary_domain.hostname],
          ["Ownership", managementLabel(data.primary_domain.verification_status)],
          ["Secure connection", managementLabel(data.primary_domain.certificate_status)],
          ["Activated", data.primary_domain.activated_at || "Not recorded"],
        ]) : note("No active primary web address is configured."),
        h("h3", "Devices and sync foundation"),
        facts([
          ["Registered Devices", data.devices.registered ?? "Not available"],
          ["Active Devices", data.devices.active ?? "Not available"],
          ["Revoked Devices", data.devices.revoked ?? "Not available"],
          ["Device data", data.devices.state],
          ["Active sync generations", data.sync_foundation.active_generation_count ?? "Not available"],
          ["Generation readiness", data.sync_foundation.generation_state],
        ]),
        h("small", "Snapshot as of " + data.as_of + " UTC. This view does not expose Shop transactions or financial records."),
      ]);
    }
    function directoryView() {
      if (tenantDetail.value || tenantDetailLoading.value) return tenantDetailView();
      return h("div", { class: "trading-stack" }, [
        h("div", { class: "row g-3" }, [
          h("div", { class: "col-12 col-md-6 col-xl-3" }, [field("admin-search", "Find a Tenant, Shop or web address", search.value, value => { search.value = value; }, { type: "search", disabled: blocked.value })]),
          h("div", { class: "col-12 col-md-6 col-xl-3" }, [selectField("tenant-status-filter", "Tenant status", directoryFilters.tenant_status, value => { directoryFilters.tenant_status = value; }, [
            ["", "All Tenant statuses"], ["ONBOARDING", "Onboarding"], ["ACTIVE", "Active"], ["SUSPENDED", "Paused"], ["CLOSED", "Closed"],
          ], { disabled: blocked.value })]),
          h("div", { class: "col-12 col-md-6 col-xl-3" }, [selectField("shop-status-filter", "Shop status", directoryFilters.shop_status, value => { directoryFilters.shop_status = value; }, [
            ["", "All Shop statuses"], ["ACTIVE", "Active"], ["INACTIVE", "Inactive"],
          ], { disabled: blocked.value })]),
          h("div", { class: "col-12 col-md-6 col-xl-3" }, [selectField("entitlement-status-filter", "Plan status", directoryFilters.entitlement_status, value => { directoryFilters.entitlement_status = value; }, [
            ["", "All plan statuses"], ["TRIAL", "Trial"], ["ACTIVE", "Paid and current"], ["EXPIRED", "Expired"], ["SCHEDULED", "Scheduled"], ["NONE", "No plan"],
          ], { disabled: blocked.value })]),
        ]),
        h("div", { class: "ui-actions" }, [
          action("Search Tenants", applyDirectoryFilters, blocked.value || directoryLoading.value, "btn-primary"),
          action("Clear filters", clearDirectoryFilters, blocked.value || directoryLoading.value),
          action("Reload Tenants", () => loadDirectory(directoryPagination.value.page), blocked.value || directoryLoading.value, "btn-outline-primary"),
        ]),
        directoryLoading.value && h(UiLoading, { label: "Loading Tenants…" }),
        !directoryLoading.value && tenants.value.map(tenantCard),
        !directoryLoading.value && !error.value && !tenants.value.length && h(UiEmptyState, {
          title: directoryPagination.value.total ? "No matching Tenants" : "No Tenants registered",
          message: directoryPagination.value.total ? "Change the search or filters and try again." : "Tenant accounts will appear here after onboarding.",
        }),
        !directoryLoading.value && directoryPagination.value.total_pages > 1 && pager(directoryPagination.value, blocked.value, loadDirectory),
        h("small", directoryPagination.value.total + " Tenant record(s) · page " + directoryPagination.value.page + " of " + directoryPagination.value.total_pages),
      ].filter(Boolean));
    }
    function onboarding() {
      return [
        h("h2", "Register a Tenant"),
        h(
          "ol",
          { class: "step-indicator", "aria-label": "Add shop progress" },
          ["Details", "Review", "Complete"].map((text, index) =>
            h(
              "li",
              {
                class: { active: step.value === index + 1 },
                "aria-current": step.value === index + 1 ? "step" : undefined,
              },
              text,
            ),
          ),
        ),
        step.value === 1 &&
          h(
            "form",
            {
              class: "commercial-form",
              onSubmit: (event) => {
                event.preventDefault();
                if (!blocked.value) {
                  clearErrors();
                  step.value = 2;
                }
              },
            },
            fields
              .map(([key, label, type, required]) =>
                formField(form, key, label, {
                  type,
                  required: !!required,
                  help: key === "platform_subdomain"
                    ? "Enter the address prefix only, for example myshop."
                    : key === "owner_email"
                      ? "We will email the Primary Owner a secure invitation link. They will set their own password."
                      : "",
                }),
              )
              .concat(submit("Review Tenant details", blocked.value)),
          ),
        step.value === 2 &&
          h("section", { class: "commercial-card" }, [
            h("h3", "Review Tenant and Primary Owner details"),
            facts(
              fields.map(([key, label]) => [label, form[key] || "Not provided"]),
            ),
            note(
              "The Tenant and Shop remain pending until the Primary Owner accepts the secure email invitation. The Owner creates their own password; no temporary password is shared.",
            ),
            h("div", { class: "ui-actions" }, [
              action(
                "Back to details",
                () => {
                  step.value = 1;
                },
                busy.value,
              ),
              action(
                "Create Tenant and send invitation",
                onboard,
                blocked.value,
                "btn-primary",
                {
                  loading: busy.value,
                  busyLabel: "Creating Tenant…",
                  icon: "add",
                },
              ),
            ]),
          ]),
        step.value === 3 &&
          h("section", { class: "commercial-card" }, [
            h("h3", "Tenant onboarding created"),
            facts([
              ["Business reference", result.tenant_public_id],
              ["Shop reference", result.shop_public_id],
              ["Owner email", result.owner_email],
              ["Invitation email", managementLabel(result.invitation_delivery_status)],
              ["DawaTrack web address", result.hostname],
            ]),
            note(
              result.invitation_delivery_status === "SENT"
                ? "The secure Owner invitation email was sent. The Tenant and Shop will activate after the Owner accepts it."
                : "The Tenant is safely saved as pending, but the invitation email was not delivered. Open Tenant details and resend the invitation.",
              result.invitation_delivery_status === "SENT" ? "success" : "warning",
            ),
          ]),
      ].filter(Boolean);
    }
    watch(page, (id, previous) => {

      loadPage(id);
    });
    onMounted(() => {
      loadPage(page.value);
      window.addEventListener("online", connection);
      window.addEventListener("offline", connection);
    });
    onUnmounted(() => {

      window.removeEventListener("online", connection);
      window.removeEventListener("offline", connection);
    });
    return () =>
      h(
        WorkspaceShell,
        {
          title: workspaceNavigation.current.value?.label || "Admin",
          brandName: presentation.brandName,
          userName: presentation.userName,
          roleLabel: "Administrator",
          area: "Admin",
          items: navigation.value,
          currentId: page.value,
          navigation: workspaceNavigation,
          trackEdits: !!workspaceNavigation.current.value?.trackEdits,
        },
        {
          default: () =>
            h(
              "section",
              { class: "management-screen trading-screen ui-scope" },
              [
                !navigation.value.length &&
                  note(
                    "Admin navigation is not available. Reload to check your access.",
                    "error",
                  ),
                error.value &&
                  h(UiAlert, {
                    severity: "error",
                    message: error.value,
                    dismissible: false,
                  }),
                notice.value &&
                  h(UiAlert, {
                    severity: "success",
                    message: notice.value,
                    persistent: true,
                  }),
                fieldSummary(
                  fieldErrors,
                  page.value === "onboard"
                    ? Object.fromEntries(
                        fields.map(([key]) => [key, `admin-onboard-${key}`]),
                      )
                    : page.value === "plans" && errorTarget.value
                      ? Object.fromEntries(
                          Object.keys(fieldErrors).map((key) => [
                            key,
                            `plan-${errorTarget.value}-${key}`,
                          ]),
                        )
                      : {},
                ),
                confirmation.render(),
                !online.value && h(UiStatusPanel, { state: "offline" }),
                pageLoading.value &&
                  h(UiLoading, { label: "Loading this section…" }),
                page.value === "onboard" &&
                  can("platform.onboard") &&
                  onboarding(),
                page.value === "overview" &&
                  !pageLoading.value &&
                  can("platform.access") &&
                  h("div", { class: "trading-stack" }, [
                    action("Refresh dashboard", () => loadPage("overview"), blocked.value, "btn-outline-primary"),
                    dashboardView(),
                  ]),
                page.value === "shops" &&
                  !pageLoading.value &&
                  can("platform.access") &&
                  directoryView(),
                page.value === "plans" &&
                  !pageLoading.value &&
                  can("platform.access") &&
                  h("div", { class: "trading-stack" }, [
                    field("admin-search", "Find a Tenant", search.value, value => { search.value = value; }, { type: "search", disabled: blocked.value }),
                    h("div", { class: "ui-actions" }, [
                      action("Search Tenants", () => loadOperations(1), blocked.value, "btn-primary"),
                      action("Clear search", () => { search.value = ""; loadOperations(1); }, blocked.value),
                      action("Reload this section", () => loadOperations(operationsPagination.value.page), blocked.value, "btn-outline-primary"),
                    ]),
                    operationsLoading.value && h(UiLoading, { label: "Loading plan accounts…" }),
                    !operationsLoading.value && tenants.value.map(tenantCard),
                    !operationsLoading.value && !error.value && !tenants.value.length && h(UiEmptyState, {
                      title: operationsPagination.value.total ? "No matching Tenants" : "No Tenants registered",
                      message: operationsPagination.value.total ? "Change the search and try again." : "Tenant accounts will appear here after onboarding.",
                    }),
                    !operationsLoading.value && operationsPagination.value.total_pages > 1 && pager(operationsPagination.value, blocked.value, loadOperations),
                    h("small", operationsPagination.value.total + " Tenant record(s) · page " + operationsPagination.value.page + " of " + operationsPagination.value.total_pages),
                  ]),
                page.value === "domains" &&
                  !pageLoading.value &&
                  can("platform.domains") &&
                  h("div", { class: "trading-stack" }, [
                    field("admin-search", "Find a web address", search.value, value => { search.value = value; }, { type: "search" }),
                    action("Reload this section", () => confirmation.guard(() => loadPage(page.value)), blocked.value, "btn-outline-primary", { icon: "refresh" }),
                    domains.value.filter(row => (row.hostname + " " + row.shop_name).toLowerCase().includes(search.value.toLowerCase())).map(domainCard),
                    !error.value && !domains.value.length && h(UiEmptyState, { title: "No records to show", message: "Records will appear here when available." }),
                  ]),
                page.value === "activity" &&
                  can("platform.audit") &&
                  h(AuditPanel, {
                    mode: "platform",
                    tenants: tenants.value,
                    canSupport: can("platform.audit_support"),
                  }),
              ].filter(Boolean),
            ),
        },
      );
  },
};
if (document.getElementById("admin-app"))
  createApp(AdminApp).mount("#admin-app");
