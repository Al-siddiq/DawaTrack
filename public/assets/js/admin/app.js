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
import { field, action, submit, message } from "../components/commercial-ui.js";
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
      fallback: "shops",
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
      operationForms = reactive({});
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
      owner_password: "",
      reason: "New shop account",
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
      ["owner_email", "Shop owner email", "email", true],
      ["owner_password", "Temporary owner password", "password", true],
      ["reason", "Why are you adding this shop?", "text", true],
    ];
    const blocked = computed(
      () => busy.value || pageLoading.value || !online.value,
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
    async function loadOperations() {
      tenants.value = (await phaseOneApi.platformOperations()).data;
    }
    async function loadPage(id) {
      const generation = ++loadGeneration;
      pageLoading.value = true;
      clearErrors();
      search.value = "";
      try {
        if (id === "domains" && can("platform.domains")) await loadDomains();
        else if (
          ["shops", "plans", "activity"].includes(id) &&
          can("platform.access")
        )
          await loadOperations();
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
        try {
          Object.assign(result, (await phaseOneApi.onboard(form)).data);
          step.value = 3;
        } finally {
          form.owner_password = "";
        }
      }, "Shop account created.");
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
        { title: "Plan periods and change history" },
        {
          default: () => [
            ...[
              ["Upcoming", subscription.future_periods],
              ["Previous", subscription.historical_periods],
            ].map(([title, rows]) =>
              h("section", [
                h("h4", title),
                !rows?.length && h("p", "None"),
                ...(rows || []).map((row) =>
                  h(
                    "p",
                    { key: row.public_id || row.starts_at },
                    `${row.plan_name} · ${row.starts_at} → ${row.ends_at} UTC`,
                  ),
                ),
              ]),
            ),
            h("h4", "Recorded changes"),
            ...(subscription.events || []).map((event, index) =>
              h(
                "p",
                { key: event.public_id || index },
                `${managementLabel(event.event_type)} · ${event.effective_at} · ${event.reason}`,
              ),
            ),
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
            ["Current plan", current?.plan_name || "No current plan"],
            [
              "Current plan period",
              current
                ? `${current.starts_at} → ${current.ends_at} UTC`
                : "View only when account access permits",
            ],
            ["Active staff", tenant.active_staff],
            ["Upcoming plan periods", subscription.future_periods.length],
          ]),
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
    function onboarding() {
      return [
        h("h2", "Add a shop"),
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
                  minlength: type === "password" ? 12 : undefined,
                  autocomplete:
                    type === "password" ? "new-password" : undefined,
                  help:
                    key === "platform_subdomain"
                      ? "Enter the address prefix only, for example myshop."
                      : key === "owner_password"
                        ? "At least 12 characters. The owner must change this password on first sign-in."
                        : "",
                }),
              )
              .concat(submit("Review shop details", blocked.value)),
          ),
        step.value === 2 &&
          h("section", { class: "commercial-card" }, [
            h("h3", "Check shop and owner details"),
            facts(
              fields.map(([key, label, type]) => [
                label,
                type === "password"
                  ? "Set (hidden)"
                  : form[key] || "Not provided",
              ]),
            ),
            note(
              "The password is not shown here and is cleared after submission. Creating this account also creates its first shop and owner.",
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
                "Create shop account",
                onboard,
                blocked.value,
                "btn-primary",
                {
                  loading: busy.value,
                  busyLabel: "Creating shop account…",
                  icon: "add",
                },
              ),
            ]),
          ]),
        step.value === 3 &&
          h("section", { class: "commercial-card" }, [
            h("h3", "Shop account created"),
            facts([
              ["Business reference", result.tenant_public_id],
              ["Shop reference", result.shop_public_id],
              ["Owner email", result.owner_email],
              ["DawaTrack web address", result.hostname],
            ]),
            note(
              "Keep these details for your records. The temporary password is not shown again.",
            ),
          ]),
      ].filter(Boolean);
    }
    watch(page, (id, previous) => {
      if (previous === "onboard") form.owner_password = "";
      loadPage(id);
    });
    onMounted(() => {
      loadPage(page.value);
      window.addEventListener("online", connection);
      window.addEventListener("offline", connection);
    });
    onUnmounted(() => {
      form.owner_password = "";
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
                ["shops", "plans", "domains"].includes(page.value) &&
                  !pageLoading.value &&
                  h("div", { class: "trading-stack" }, [
                    field(
                      "admin-search",
                      page.value === "domains"
                        ? "Find a web address"
                        : "Find a shop",
                      search.value,
                      (value) => {
                        search.value = value;
                      },
                      { type: "search" },
                    ),
                    action(
                      "Reload this section",
                      () => confirmation.guard(() => loadPage(page.value)),
                      blocked.value,
                      "btn-outline-primary",
                      { icon: "refresh" },
                    ),
                    page.value === "domains" && can("platform.domains")
                      ? domains.value
                          .filter((row) =>
                            `${row.hostname} ${row.shop_name}`
                              .toLowerCase()
                              .includes(search.value.toLowerCase()),
                          )
                          .map(domainCard)
                      : can("platform.access")
                        ? tenants.value
                            .filter((row) =>
                              `${row.shop_name} ${row.legal_or_business_name}`
                                .toLowerCase()
                                .includes(search.value.toLowerCase()),
                            )
                            .map(tenantCard)
                        : note(
                            "Your current Admin access does not include the shop list.",
                            "warning",
                          ),
                    !error.value &&
                      ((page.value === "domains" && !domains.value.length) ||
                        (page.value !== "domains" && !tenants.value.length)) &&
                      h(UiEmptyState, {
                        title: "No records to show",
                        message: "Records will appear here when available.",
                      }),
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
