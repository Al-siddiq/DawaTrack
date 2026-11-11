import {
  UiAccordion,
  UiBadge,
  UiLoading,
  UiEmptyState,
  UiStatusPanel,
} from "./ui/index.js";
import { field, action, submit } from "./commercial-ui.js";
import {
  managementLabel,
  permissionLabel,
  facts,
  note,
  selectField,
  fieldSummary,
  useTradingConfirm,
} from "./management-ui.js";
import { useNavigationGuard } from "../shared/workspace-navigation.js";
const { h, ref, onMounted, onUnmounted } = window.Vue;
export const OwnerPanel = {
  props: { page: String, model: Object, readOnly: Boolean },
  setup(props) {
    const confirmation = useTradingConfirm();
    const online = ref(navigator.onLine),
      search = ref(""),
      file = ref(null);
    const connection = () => {
      online.value = navigator.onLine;
    };
    onMounted(() => {
      window.addEventListener("online", connection);
      window.addEventListener("offline", connection);
    });
    onUnmounted(() => {
      props.model.staffForm.password = "";
      window.removeEventListener("online", connection);
      window.removeEventListener("offline", connection);
    });
    useNavigationGuard(() =>
      props.model.dnsChallenge.dns_record_value
        ? {
            message:
              "The web address setup value is shown only once. Copy it before leaving, or leave without keeping it.",
          }
        : null,
    );
    const blocked = () =>
      props.readOnly ||
      props.model.busy ||
      props.model.ownerLoading ||
      !online.value;
    const form = (work, children, extra = "") =>
      h(
        "form",
        {
          class: `commercial-form ${extra}`,
          onSubmit: (event) => {
            event.preventDefault();
            if (!blocked()) work();
          },
        },
        children,
      );
    function input(object, key, label, options = {}) {
      return field(
        `owner-${key}`,
        label,
        object[key] ?? "",
        (value) => {
          object[key] = value;
        },
        {
          disabled: blocked(),
          errors: props.model.fieldErrors[key] || [],
          ...options,
        },
      );
    }
    function staff() {
      const m = props.model;
      const matches = m.staff.filter((row) =>
        `${row.username} ${row.email || ""}`
          .toLowerCase()
          .includes(search.value.toLowerCase()),
      );
      return [
        note(
          "Each person signs in with their own account. Choose what they can do after adding them.",
        ),
        h(
          UiAccordion,
          { title: "Add a staff member" },
          {
            default: () =>
              form(m.createStaff, [
                input(m.staffForm, "name", "Name", {
                  required: true,
                  autocomplete: "name",
                }),
                input(m.staffForm, "email", "Email address", {
                  type: "email",
                  required: true,
                  autocomplete: "email",
                }),
                input(m.staffForm, "password", "Temporary password", {
                  type: "password",
                  required: true,
                  minlength: 12,
                  autocomplete: "new-password",
                  help: "Use at least 12 characters. The person will choose a new password when they sign in.",
                }),
                input(
                  m.staffForm,
                  "reason",
                  "Why are you adding this person?",
                  { required: true, maxlength: 500 },
                ),
                submit("Add staff member", blocked(), {
                  loading: m.busy,
                  busyLabel: "Adding staff member…",
                  icon: "add",
                }),
              ]),
          },
        ),
        field(
          "owner-staff-search",
          "Find a staff member",
          search.value,
          (value) => {
            search.value = value;
          },
          { type: "search" },
        ),
        !matches.length &&
          h(UiEmptyState, {
            title: "No matching staff",
            message: "Try another name or add a staff member.",
          }),
        ...matches.map((member) =>
          h("article", { class: "commercial-card", key: member.id }, [
            h("div", { class: "trading-task-header" }, [
              h("h3", member.username),
              h(UiBadge, { label: managementLabel(member.status) }),
            ]),
            h(
              "p",
              `${member.email || "No email shown"} · ${managementLabel(member.role)}`,
            ),
            member.role === "STAFF" && [
              h("div", { class: "ui-actions" }, [
                action(
                  member.status === "ACTIVE" ? "Make inactive" : "Make active",
                  () => m.openDialog("status", member),
                  blocked(),
                  "btn-outline-primary",
                  { icon: "account" },
                ),
                action(
                  "Reset password",
                  () => m.openDialog("reset", member),
                  blocked(),
                  "btn-outline-primary",
                  { icon: "refresh" },
                ),
              ]),
              h(
                UiAccordion,
                {
                  title: "What this person can do",
                  "onUpdate:expanded": (open) => {
                    if (open && !m.permissionStates[member.id])
                      m.loadPermissions(member);
                  },
                },
                {
                  default: () => {
                    const state = m.permissionStates[member.id];
                    if (!state)
                      return h(
                        "p",
                        "Open this section to load access settings.",
                      );
                    if (state.loading)
                      return h(UiLoading, {
                        label: "Loading access settings…",
                      });
                    if (state.error)
                      return [
                        note(state.error, "error"),
                        action(
                          "Reload access settings",
                          () => m.loadPermissions(member),
                          m.busy,
                          "btn-outline-primary",
                          { icon: "refresh" },
                        ),
                      ];
                    return [
                      field(
                        `permission-search-${member.id}`,
                        "Find an action",
                        m.permissionSearch[member.id] || "",
                        (value) => {
                          m.permissionSearch[member.id] = value;
                        },
                        { type: "search" },
                      ),
                      ...m.permissionGroups(member).map((group) =>
                        h(
                          "section",
                          { class: "permission-group", key: group.name },
                          [
                            h("h4", group.name),
                            ...group.permissions.map((permission) =>
                              h(
                                "label",
                                {
                                  class: "permission-option",
                                  key: permission.code,
                                },
                                [
                                  h("input", {
                                    type: "checkbox",
                                    checked: state.active.includes(
                                      permission.code,
                                    ),
                                    disabled:
                                      blocked() || member.status !== "ACTIVE",
                                    onClick: (event) => {
                                      event.preventDefault();
                                      m.openDialog(
                                        "permission",
                                        member,
                                        permission,
                                      );
                                    },
                                  }),
                                  h("span", permissionLabel(permission)),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      h(
                        UiAccordion,
                        { title: "Access change history" },
                        {
                          default: () =>
                            !state.history.length
                              ? h("p", "No access changes yet.")
                              : h(
                                  "ul",
                                  state.history.map((entry, index) =>
                                    h(
                                      "li",
                                      { key: entry.id || index },
                                      `${permissionLabel({ code: entry.permission_code, label: entry.permission_code })} · Allowed ${entry.granted_at}${entry.revoked_at ? ` · Removed ${entry.revoked_at}` : " · Still allowed"}`,
                                    ),
                                  ),
                                ),
                        },
                      ),
                    ];
                  },
                },
              ),
            ],
          ]),
        ),
      ];
    }
    function settings() {
      const m = props.model;
      return [
        note("Keep your shop contact details and local time correct."),
        form(m.saveSettings, [
          ...m.settingFields.map((f) =>
            input(m.settings, f.key, f.label, {
              type: f.type || "text",
              required: f.required,
              help:
                f.key === "timezone_name"
                  ? "For Nigeria, use Africa/Lagos. Other shops should use their own local time zone."
                  : "",
            }),
          ),
          submit("Save shop details", blocked(), { loading: m.busy }),
        ]),
      ];
    }
    function appearance() {
      const m = props.model;
      return [
        h(
          "aside",
          { class: "brand-preview", "aria-label": "Shop appearance preview" },
          [
            h("strong", m.branding.brand_display_name),
            h("span", m.branding.tagline),
            h("small", m.branding.application_title),
          ],
        ),
        note(
          "Change how your shop looks. Reload the app after saving to see the new appearance everywhere.",
        ),
        form(m.saveBranding, [
          ...m.brandFields.map((f) =>
            input(m.branding, f.key, f.label, {
              type: f.colour ? "color" : "text",
              required: !["tagline", "public_description"].includes(f.key),
              help: f.colour
                ? "Keep buttons and text easy to read against the background."
                : "",
            }),
          ),
          submit("Save shop appearance", blocked(), {
            loading: m.busy,
            icon: "appearance",
          }),
        ]),
        h(
          UiAccordion,
          { title: "Shop logo and app images", defaultExpanded: true },
          {
            default: () => [
              note(
                "Choose a PNG or JPEG image. App icons must be exactly the pixel size shown.",
              ),
              form(
                () => m.uploadAsset(file.value?.files?.[0]),
                [
                  selectField(
                    "owner-asset-type",
                    "Image to change",
                    m.assetType,
                    (value) => {
                      m.assetType = value;
                    },
                    m.assetTypes.map((value) => [
                      value,
                      managementLabel(value),
                    ]),
                    { disabled: blocked() },
                  ),
                  h("label", { class: "ui-field" }, [
                    h("span", "Choose image"),
                    h("input", {
                      id: "owner-asset-file",
                      ref: file,
                      type: "file",
                      accept: "image/png,image/jpeg",
                      required: true,
                      disabled: blocked(),
                      class: "form-control",
                    }),
                  ]),
                  submit("Upload image", blocked(), {
                    loading: m.busy,
                    icon: "add",
                  }),
                ],
              ),
              ...m.assets.map((row) =>
                h(
                  "article",
                  {
                    class: "commercial-card",
                    key: row.public_id || row.asset_type,
                  },
                  [
                    h("h3", managementLabel(row.asset_type)),
                    h(
                      "p",
                      `${row.width_px} × ${row.height_px} pixels · ${row.mime_type}`,
                    ),
                    row.public_id &&
                      h("img", {
                        class: "asset-preview",
                        src: `/api/v1/shop/assets/${row.public_id}`,
                        alt: `${managementLabel(row.asset_type)} preview`,
                      }),
                  ],
                ),
              ),
            ],
          },
        ),
      ];
    }
    function domains() {
      const m = props.model;
      return [
        note(
          "Your DawaTrack address stays available. To use your own web address, add the setting below with your domain provider. An Admin completes secure connection setup and activation.",
        ),
        ...m.domains.map((row) =>
          h("article", { class: "commercial-card", key: row.public_id }, [
            h("h3", { class: "break-all" }, row.hostname),
            facts([
              ["Address type", managementLabel(row.domain_type)],
              ["Ownership", managementLabel(row.verification_status)],
              [
                "Secure connection",
                managementLabel(row.certificate_status || "PENDING"),
              ],
              [
                "Use",
                row.is_primary
                  ? "Main address"
                  : row.is_active
                    ? "Available"
                    : "Not active",
              ],
            ]),
            row.verification_reference &&
              h(
                "p",
                { class: "trading-record-meta" },
                `Setup reference: ${row.verification_reference}`,
              ),
            row.domain_type === "CUSTOM" &&
              !row.is_active &&
              h("div", { class: "ui-actions" }, [
                action(
                  "Get a new setup value",
                  async () => {
                    if (
                      await confirmation.ask({
                        title: "Replace the setup value?",
                        message:
                          "The old setup value will no longer work. Copy the new value and update it with your web address provider.",
                        confirm: "Get new setup value",
                      })
                    )
                      m.rotateDomain(row);
                  },
                  blocked(),
                  "btn-outline-primary",
                  { icon: "refresh" },
                ),
                action(
                  "Check address ownership",
                  () => m.verifyDomain(row),
                  blocked(),
                  "btn-primary",
                  { icon: "web-address", loading: m.busy },
                ),
              ]),
          ]),
        ),
        form(m.requestDomain, [
          input(m, "hostname", "Your web address", {
            required: true,
            placeholder: "shop.example.com",
            help: "Enter the address only, without https:// or a page path.",
          }),
          submit("Get address setup details", blocked(), {
            loading: m.busy,
            icon: "web-address",
          }),
        ]),
        m.dnsChallenge.dns_record_value &&
          h("section", { class: "management-secret", role: "status" }, [
            h("h3", "Copy this setup value now"),
            h(
              "p",
              "This value is shown once. Add these exact DNS settings with the company that manages your web address.",
            ),
            facts([
              ["Record type", m.dnsChallenge.dns_record_type],
              ["Record name", m.dnsChallenge.dns_record_name],
              ["Record value", m.dnsChallenge.dns_record_value],
            ]),
            action(
              "I have copied the setup value",
              () => {
                delete m.dnsChallenge.dns_record_value;
              },
              false,
              "btn-outline-primary",
              { icon: "success" },
            ),
          ]),
      ].filter(Boolean);
    }
    function subscription() {
      const s = props.model.subscription;
      const periods = (title, rows) =>
        h("section", { class: "commercial-card" }, [
          h("h3", title),
          !rows?.length && h("p", "No plan periods to show."),
          ...(rows || []).map((row) =>
            h(
              "article",
              { class: "return-line", key: row.public_id || row.starts_at },
              [
                h("h4", row.plan_name),
                h("p", `${row.starts_at} → ${row.ends_at} UTC`),
                row.max_active_staff_snapshot != null &&
                  h("p", `Staff places: ${row.max_active_staff_snapshot}`),
              ],
            ),
          ),
        ]);
      return [
        note(s.access?.message || "Your current shop access is shown below."),
        h("article", { class: "commercial-card" }, [
          facts([
            [
              "Current plan",
              s.current_entitlement?.plan_name || "No current plan",
            ],
            ["Access", managementLabel(s.access?.mode)],
            ["Starts", s.current_entitlement?.starts_at || "Not set"],
            ["Ends", s.current_entitlement?.ends_at || "Not set"],
            [
              "Staff places used",
              `${s.active_staff ?? "—"} / ${s.staff_limit ?? "—"}`,
            ],
            ["Staff places left", s.remaining_staff_capacity ?? "—"],
          ]),
          h(
            "p",
            "Plan changes are managed by an Admin. There is no payment checkout on this screen.",
          ),
        ]),
        periods("Upcoming plan periods", s.future_periods),
        h(
          UiAccordion,
          { title: "Previous plan periods" },
          { default: () => periods("Plan history", s.historical_periods) },
        ),
      ];
    }
    const titles = {
      staff: "Staff",
      settings: "Shop details",
      branding: "Shop appearance",
      domains: "Web address",
      subscription: "Plan and access",
    };
    return () =>
      h(
        "section",
        {
          class: "management-screen trading-screen ui-scope",
          "aria-busy": props.model.busy || props.model.ownerLoading,
        },
        [
          h("h2", titles[props.page]),
          h(UiStatusPanel, {
            state: !online.value
              ? "offline"
              : props.readOnly
                ? "readonly"
                : "ready",
          }),
          fieldSummary(
            props.model.fieldErrors,
            Object.fromEntries(
              Object.keys(props.model.fieldErrors).map((key) => [
                key,
                key === "asset"
                  ? "owner-asset-file"
                  : key === "asset_type"
                    ? "owner-asset-type"
                    : `owner-${key}`,
              ]),
            ),
          ),
          confirmation.render(),
          props.model.ownerLoading
            ? h(UiLoading, { label: "Loading this section…" })
            : { staff, settings, branding: appearance, domains, subscription }[
                props.page
              ]?.() || [],
          action(
            "Reload this section",
            () => confirmation.guard(props.model.reload),
            props.model.busy || props.model.ownerLoading || !online.value,
            "btn-outline-primary",
            { icon: "refresh" },
          ),
        ],
      );
  },
};
