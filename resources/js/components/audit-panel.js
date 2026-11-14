import { phaseOneApi } from "../api/phase-one.js";
import {
  UiLoading,
  UiEmptyState,
  UiSheet,
  UiAccordion,
  UiBadge,
} from "./ui/index.js";
import { field, action, submit, message } from "./commercial-ui.js";
import { facts, note, selectField, managementLabel } from "./management-ui.js";
const { ref, reactive, h, onMounted, onUnmounted, nextTick } = window.Vue;
const readable = (value) =>
  ({ "financial_movement.recorded": "Money record saved", "financial_movement": "Money record", FINANCIAL: "Money movements" })[value] || String(value || "")
    .replace(/[._]/g, " ")
    .toLowerCase();
export const AuditPanel = {
  props: {
    mode: { type: String, required: true },
    tenants: { type: Array, default: () => [] },
    canSupport: { type: Boolean, default: false },
  },
  setup(props) {
    const view = ref("normal"),
      events = ref([]),
      selected = ref(null),
      loading = ref(false),
      error = ref(""),
      nextCursor = ref(null);
    const filters = reactive({
      from: "",
      to: "",
      category: "",
      action: "",
      actor: "",
      resource_type: "",
      resource_public_id: "",
      correlation_id: "",
    });
    const support = reactive({
      tenant_public_id: "",
      shop_public_id: "",
      reason: "",
      access_id: "",
      tenant_name: "",
      shop_name: "",
    });
    let generation = 0,
      alive = true;
    onUnmounted(() => {
      alive = false;
      generation++;
      Object.keys(support).forEach((key) => {
        support[key] = "";
      });
    });
    onMounted(() => load());
    const authorized = () =>
      props.mode === "owner" || view.value === "normal" || !!support.access_id;
    async function load(reset = true) {
      if (loading.value) return;
      if (
        props.mode === "platform" &&
        view.value === "support" &&
        !props.canSupport
      ) {
        error.value =
          "Your Admin access does not include shop support activity.";
        return;
      }
      const request = ++generation;
      loading.value = true;
      error.value = "";
      try {
        let response;
        if (props.mode === "platform" && view.value === "support") {
          const payload = support.access_id
            ? {
                access_id: support.access_id,
                filters: {
                  ...filters,
                  cursor: reset ? null : nextCursor.value,
                },
              }
            : {
                tenant_public_id: support.tenant_public_id,
                shop_public_id: support.shop_public_id,
                reason: support.reason,
                filters: { ...filters },
              };
          response = await phaseOneApi.supportAudit(payload);
          if (!alive || request !== generation) return;
          Object.assign(support, response.data.support);
        } else {
          const params = new URLSearchParams(
            Object.entries(filters).filter(([, value]) => value),
          );
          if (!reset && nextCursor.value)
            params.set("cursor", nextCursor.value);
          const query = params.size ? `?${params}` : "";
          response =
            props.mode === "owner"
              ? await phaseOneApi.auditOwner(query)
              : await phaseOneApi.platformAudit(query);
        }
        if (!alive || request !== generation) return;
        events.value = reset
          ? response.data.events
          : [...events.value, ...response.data.events];
        nextCursor.value = response.data.next_cursor;
        selected.value = null;
      } catch (failure) {
        if (alive && request === generation) error.value = message(failure);
      } finally {
        if (alive && request === generation) loading.value = false;
      }
    }
    async function openDetail(event) {
      if (loading.value) return;
      const request = ++generation;
      loading.value = true;
      error.value = "";
      try {
        const response =
          props.mode === "owner"
            ? await phaseOneApi.auditOwnerDetail(event.public_id)
            : view.value === "support"
              ? await phaseOneApi.supportAuditDetail(event.public_id, {
                  access_id: support.access_id,
                })
              : await phaseOneApi.platformAuditDetail(event.public_id);
        if (alive && request === generation)
          selected.value = response.data.event || response.data;
      } catch (failure) {
        if (alive && request === generation) error.value = message(failure);
      } finally {
        if (alive && request === generation) loading.value = false;
      }
    }
    function changeView(supportMode) {
      if (loading.value || (supportMode && !props.canSupport)) return;
      generation++;
      view.value = supportMode ? "support" : "normal";
      events.value = [];
      selected.value = null;
      nextCursor.value = null;
      error.value = "";
      Object.assign(support, {
        tenant_public_id: "",
        shop_public_id: "",
        reason: "",
        access_id: "",
        tenant_name: "",
        shop_name: "",
      });
      if (supportMode)
        nextTick(() => document.getElementById("support-shop")?.focus());
      else load();
    }
    function filterForm() {
      if (!authorized()) return null;
      return h(
        "form",
        {
          class: "commercial-filters audit-filters",
          onSubmit: (event) => {
            event.preventDefault();
            load();
          },
        },
        [
          ...[
            ["from", "From (UTC date)", "date"],
            ["to", "To (UTC date)", "date"],
            ["category", "Activity group"],
            ["action", "Action"],
            ["actor", "Person"],
            ["resource_type", "Record type"],
            ["resource_public_id", "Record reference"],
            ["correlation_id", "Support reference"],
          ].map(([key, label, type]) =>
            field(
              `activity-${key}`,
              label,
              filters[key],
              (value) => {
                filters[key] = key === "category" ? value.toUpperCase() : value;
              },
              { type: type || "text", disabled: loading.value },
            ),
          ),
          submit("Find activity", loading.value, {
            loading: loading.value,
            icon: "search",
          }),
          action(
            "Clear filters",
            () => {
              Object.keys(filters).forEach((key) => {
                filters[key] = "";
              });
              nextCursor.value = null;
              load();
            },
            loading.value,
          ),
        ],
      );
    }
    return () =>
      h(
        "section",
        {
          class: "management-screen trading-screen ui-scope",
          "aria-busy": loading.value,
        },
        [
          h("h2", props.mode === "owner" ? "Shop activity" : "Admin activity"),
          h("p", "See who changed a record, when it happened and why."),
          props.mode === "platform" &&
            h("div", { class: "ui-actions" }, [
              action("Admin activity", () => changeView(false), loading.value),
              props.canSupport &&
                action(
                  "View a shop’s activity for support",
                  () => changeView(true),
                  loading.value,
                  "btn-outline-primary",
                  { icon: "activity" },
                ),
            ]),
          props.mode === "platform" &&
            view.value === "support" &&
            !support.access_id &&
            props.canSupport &&
            h(
              "form",
              {
                class: "commercial-form",
                onSubmit: (event) => {
                  event.preventDefault();
                  load();
                },
              },
              [
                note(
                  "Choose one shop and enter why support access is needed. This does not sign you in as the shop owner.",
                ),
                selectField(
                  "support-shop",
                  "Shop to support",
                  support.tenant_public_id,
                  (value) => {
                    support.tenant_public_id = value;
                    support.shop_public_id =
                      props.tenants.find((item) => item.public_id === value)
                        ?.shop_public_id || "";
                  },
                  [
                    ["", "Choose a shop"],
                    ...props.tenants.map((item) => [
                      item.public_id,
                      `${item.legal_or_business_name} · ${item.shop_name}`,
                    ]),
                  ],
                  { required: true, disabled: loading.value },
                ),
                field(
                  "support-shop-reference",
                  "Shop reference",
                  support.shop_public_id,
                  () => {},
                  { readonly: true },
                ),
                field(
                  "support-reason",
                  "Why do you need support access?",
                  support.reason,
                  (value) => {
                    support.reason = value;
                  },
                  {
                    multiline: true,
                    maxlength: 500,
                    required: true,
                    disabled: loading.value,
                  },
                ),
                submit("Confirm support access", loading.value, {
                  loading: loading.value,
                }),
              ],
            ),
          view.value === "support" &&
            support.access_id &&
            note(
              `Support activity for ${support.tenant_name} · ${support.shop_name}. Reason: ${support.reason}. You are still using your Admin account.`,
              "warning",
            ),
          filterForm(),
          error.value && note(error.value, "error"),
          loading.value && h(UiLoading, { label: "Loading activity…" }),
          !loading.value &&
            !error.value &&
            !events.value.length &&
            authorized() &&
            h(UiEmptyState, {
              title: "No matching activity",
              message: "Try another date or clear the filters.",
            }),
          ...events.value.map((event) =>
            h("article", { class: "commercial-card", key: event.public_id }, [
              h("div", { class: "trading-task-header" }, [
                h("h3", readable(event.action_code)),
                h(UiBadge, { label: readable(event.category) }),
              ]),
              h(
                "p",
                `${event.actor?.name || readable(event.actor_type)} · ${event.occurred_at} UTC`,
              ),
              h(
                "p",
                `${readable(event.resource_type)} · ${event.resource_public_id || "No reference"}`,
              ),
              action(
                "View activity details",
                () => openDetail(event),
                loading.value,
                "btn-outline-primary",
                { icon: "activity" },
              ),
            ]),
          ),
          nextCursor.value &&
            action(
              "Load more activity",
              () => load(false),
              loading.value,
              "btn-outline-primary",
              { loading: loading.value },
            ),
          h(
            UiSheet,
            {
              open: !!selected.value,
              title: "Activity details",
              placement: "end",
              busy: loading.value,
              "onUpdate:open": (open) => {
                if (!open) selected.value = null;
              },
            },
            {
              default: () => {
                const event = selected.value;
                if (!event) return [];
                return [
                  facts([
                    ["Action", readable(event.action_code)],
                    ["Group", readable(event.category)],
                    ["Person", event.actor?.name || "Not shown"],
                    [
                      "Account role",
                      event.actor?.authority
                        ? managementLabel(event.actor.authority)
                        : "Not shown",
                    ],
                    [
                      "Record",
                      `${readable(event.resource_type)} ${event.resource_public_id || ""}`,
                    ],
                    ["Happened at", event.occurred_at],
                    ["Saved at", event.created_at],
                    ["Reason", event.reason || "Not provided"],
                  ]),
                  h(
                    UiAccordion,
                    { title: "Support details" },
                    {
                      default: () => [
                        facts([
                          ["Exact action code", event.action_code],
                          ["Support reference", event.correlation_id],
                          ["Caused by record", event.caused_by_public_id],
                        ]),
                        event.change_metadata &&
                          h(
                            "pre",
                            { class: "audit-metadata" },
                            JSON.stringify(event.change_metadata, null, 2),
                          ),
                      ],
                    },
                  ),
                  h("h3", "Related activity"),
                  event.related_events?.length
                    ? h(
                        "ul",
                        event.related_events.map((row) =>
                          h(
                            "li",
                            { key: row.public_id || row.occurred_at },
                            `${readable(row.action_code)} · ${row.occurred_at}`,
                          ),
                        ),
                      )
                    : h(
                        "p",
                        "No related activity is available with your current access.",
                      ),
                ];
              },
            },
          ),
        ].filter(Boolean),
      );
  },
};
