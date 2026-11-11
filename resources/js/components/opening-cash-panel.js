import { UiLoading, UiStatusPanel, UiAccordion } from "./ui/index.js";
import { field, action, submit, feedback, message } from "./commercial-ui.js";
import {
  managementLabel,
  facts,
  note,
  useTradingConfirm,
  fieldSummary,
} from "./management-ui.js";
import { useNavigationGuard } from "../shared/workspace-navigation.js";
import { apiRequest } from "../api/http-client.js";
const { h, ref, onMounted, onUnmounted, computed, nextTick } = window.Vue;
const root = "/api/v1/shop/opening-cash";
const money = (value) => `₦${value ?? "—"}`;
const canonicalCash = (value) => {
  const text = String(value).trim();
  if (!/^(?:0|[1-9][0-9]{0,16})(?:\.[0-9]{1,2})?$/.test(text)) return null;
  const [whole, fraction = ""] = text.split(".");
  return `${whole}.${fraction.padEnd(2, "0")}`;
};

export const OpeningCashPanel = {
  props: { readOnly: { type: Boolean, default: true } },
  setup(props) {
    const confirmation = useTradingConfirm(),
      unknown = ref(false),
      validation = ref({});
    const state = ref({ active: null, baseline: null, history: [] });
    const pending = ref(false),
      loading = ref(true),
      error = ref(""),
      notice = ref("");
    const amount = ref(""),
      reason = ref(""),
      online = ref(navigator.onLine);
    const attemptId = ref(crypto.randomUUID()),
      stageTitle = ref(null);
    const disabled = computed(
      () =>
        pending.value ||
        loading.value ||
        unknown.value ||
        props.readOnly ||
        !online.value,
    );
    useNavigationGuard(() =>
      unknown.value
        ? {
            hard: true,
            message: "Check the saved starting cash status before leaving.",
          }
        : reason.value.trim() ||
            (state.value.active?.status === "COUNTING" &&
              amount.value &&
              canonicalCash(amount.value) !== state.value.active.actual_amount)
          ? {
              message:
                "Your cash entry or reason is not saved. Keep editing or leave without saving it.",
            }
          : null,
    );
    const connection = () => {
      online.value = navigator.onLine;
    };
    async function refresh() {
      const response = await apiRequest(root);
      state.value = response.data;
      unknown.value = false;
      if (state.value.active?.status === "COUNTING")
        amount.value = state.value.active.actual_amount ?? "";
    }
    async function load() {
      loading.value = true;
      error.value = "";
      try {
        await refresh();
      } catch (e) {
        error.value = message(e);
      } finally {
        loading.value = false;
      }
    }
    async function command(action, body = {}) {
      if (disabled.value) return;
      if (
        action === "commit-count" &&
        canonicalCash(amount.value) !== state.value.active?.actual_amount
      ) {
        error.value = "Save the edited Cash amount before confirming.";
        return;
      }
      if (
        ["finalize", "abort"].includes(action) &&
        !(await confirmation.ask({
          title:
            action === "finalize"
              ? "Save starting cash permanently?"
              : "Cancel this starting cash attempt?",
          message:
            action === "finalize"
              ? "This amount becomes the shop’s one-time starting cash record. It cannot be edited afterward."
              : "The attempt stays in history. You can start again if starting cash has not been saved permanently.",
          confirm:
            action === "finalize" ? "Save starting cash" : "Cancel attempt",
          danger: action === "abort",
        }))
      )
        return;
      pending.value = true;
      error.value = "";
      notice.value = "";
      validation.value = {};
      const id = state.value.active?.public_id;
      const path =
        action === "start"
          ? root
          : `${root}/${encodeURIComponent(id)}/${action}`;
      try {
        const response = await apiRequest(path, {
          method: "POST",
          body: JSON.stringify(body),
        });
        state.value = response.data;
        notice.value = "Starting cash status updated.";
        unknown.value = false;
        if (action === "abort") {
          reason.value = "";
          amount.value = "";
          attemptId.value = crypto.randomUUID();
        }
        await nextTick();
        stageTitle.value?.focus();
      } catch (e) {
        validation.value = e.details || {};
        error.value = `${message(e)} Check the current status before trying again.`;
        unknown.value = e.status === 0 || e.status >= 500 || e.status === 403;
        try {
          await refresh();
        } catch (_) {
          /* Preserve error and offer an explicit refresh. */
        }
      } finally {
        pending.value = false;
      }
    }
    onMounted(() => {
      window.addEventListener("online", connection);
      window.addEventListener("offline", connection);
      load();
    });
    onUnmounted(() => {
      window.removeEventListener("online", connection);
      window.removeEventListener("offline", connection);
    });
    const evidence = (row) =>
      h("section", { class: "commercial-card" }, [
        facts([
          ["Status", managementLabel(row.status)],
          ["Cash counted", money(row.actual_amount)],
          ["Count confirmed (UTC)", row.counts_committed_at || "Not confirmed"],
          ["Saved permanently (UTC)", row.finalized_at || "Not yet saved"],
          ["Started by", row.started_by_name],
          ["Saved by", row.finalized_by_name || "Not yet saved"],
        ]),
        h(
          UiAccordion,
          { title: "Reference and support details" },
          {
            default: () =>
              facts([
                ["Reference", row.public_id],
                [
                  "Cash records start (UTC)",
                  row.cash_period_starts_at || "Not set",
                ],
                ["Support generation", row.generation_uuid],
                ["Support sequence", row.cutoff_change_sequence],
              ]),
          },
        ),
      ]);
    return () => {
      const active = state.value.active,
        baseline = state.value.baseline;
      return h(
        "section",
        {
          class: "management-screen trading-screen ui-scope",
          "aria-busy": pending.value || loading.value,
        },
        [
          h("h2", "Starting cash"),
          note(
            "Count the cash in your shop and save it once before taking cash payments or refunds. This is not a daily opening routine.",
          ),
          h(UiStatusPanel, {
            state: !online.value
              ? "offline"
              : props.readOnly
                ? "readonly"
                : "ready",
          }),
          ...feedback(error.value, notice.value),
          fieldSummary(validation.value, {
            amount: "opening-amount",
            reason: "opening-abort-reason",
          }),
          confirmation.render(),
          unknown.value &&
            h(UiStatusPanel, {
              state: "unknown",
              checking: loading.value,
              canCheck: online.value && !pending.value,
              onCheckStatus: load,
            }),
          loading.value
            ? h(UiLoading, { label: "Loading starting cash…" })
            : baseline
              ? h("section", [
                  h("h3", "Starting cash is saved"),
                  h(
                    "p",
                    "This protected record cannot be edited or replaced by another starting cash entry.",
                  ),
                  evidence(baseline),
                ])
              : !active
                ? h("section", { class: "commercial-card" }, [
                    h("h3", "Record your starting cash"),
                    h(
                      "p",
                      "Start an attempt, enter what you counted, then review it before saving permanently.",
                    ),
                    action(
                      "Start counting cash",
                      () => command("start", { public_id: attemptId.value }),
                      disabled.value,
                      "btn-primary",
                      { icon: "cash", loading: pending.value },
                    ),
                  ])
                : h(
                    "div",
                    { class: "trading-stack" },
                    [
                      h("h3", managementLabel(active.status)),
                      active.status === "COUNTING" &&
                        h(
                          "form",
                          {
                            class: "commercial-form",
                            onSubmit: (event) => {
                              event.preventDefault();
                              command("count", { amount: amount.value });
                            },
                          },
                          [
                            field(
                              "opening-amount",
                              "Cash counted (₦)",
                              amount.value,
                              (value) => {
                                amount.value = value;
                              },
                              {
                                inputmode: "decimal",
                                required: true,
                                maxlength: 20,
                                autocomplete: "off",
                                disabled: disabled.value,
                                errors: validation.value.amount || [],
                                help: "Enter one amount, for example 12500.50. Zero is allowed.",
                              },
                            ),
                            active.actual_amount !== null &&
                              h(
                                "p",
                                `Saved count: ${money(active.actual_amount)}. Save any changes before confirming.`,
                              ),
                            h("div", { class: "ui-actions" }, [
                              submit("Save counted amount", disabled.value, {
                                loading: pending.value,
                              }),
                              active.actual_amount !== null &&
                                action(
                                  "Confirm saved amount",
                                  () => command("commit-count"),
                                  disabled.value ||
                                    canonicalCash(amount.value) !==
                                      active.actual_amount,
                                  "btn-primary",
                                  { loading: pending.value },
                                ),
                            ]),
                          ],
                        ),
                      active.status === "COUNTS_COMMITTED" &&
                        h("section", { class: "commercial-card" }, [
                          h(
                            "p",
                            `Confirmed cash count: ${money(active.actual_amount)}`,
                          ),
                          action(
                            "Review confirmed amount",
                            () => command("review"),
                            disabled.value,
                            "btn-primary",
                            { loading: pending.value },
                          ),
                        ]),
                      active.status === "REVIEW" &&
                        h("section", { class: "trading-stack" }, [
                          note(
                            "Check this amount carefully. Saving permanently sets your one-time starting cash.",
                          ),
                          evidence(active),
                          action(
                            "Save starting cash permanently",
                            () => command("finalize"),
                            disabled.value,
                            "btn-primary",
                            { loading: pending.value },
                          ),
                        ]),
                      h(
                        UiAccordion,
                        {
                          title: "Cancel this attempt",
                          class: "trading-danger-zone",
                        },
                        {
                          default: () =>
                            h(
                              "form",
                              {
                                class: "commercial-form",
                                onSubmit: (event) => {
                                  event.preventDefault();
                                  command("abort", { reason: reason.value });
                                },
                              },
                              [
                                h(
                                  "p",
                                  "The attempt and the amounts you entered stay in history.",
                                ),
                                field(
                                  "opening-abort-reason",
                                  "Why are you cancelling this attempt?",
                                  reason.value,
                                  (value) => {
                                    reason.value = value;
                                  },
                                  {
                                    multiline: true,
                                    required: true,
                                    maxlength: 500,
                                    disabled: disabled.value,
                                    errors: validation.value.reason || [],
                                  },
                                ),
                                submit("Cancel attempt", disabled.value, {
                                  variant: "danger-secondary",
                                  loading: pending.value,
                                  icon: "cancel-record",
                                }),
                              ],
                            ),
                        },
                      ),
                    ].filter(Boolean),
                  ),
          action(
            "Refresh saved status",
            () => confirmation.guard(load),
            pending.value || loading.value || !online.value,
            "btn-outline-primary",
            { icon: "refresh", loading: loading.value },
          ),
          state.value.history.length > 0 &&
            h(
              UiAccordion,
              { title: "Previous starting cash attempts" },
              {
                default: () => [
                  h(
                    "p",
                    "The latest 50 attempts are shown, including cancelled attempts.",
                  ),
                  ...state.value.history.map((row) =>
                    h(
                      "article",
                      { class: "commercial-card", key: row.public_id },
                      [
                        h("h3", managementLabel(row.status)),
                        h(
                          "p",
                          `${money(row.actual_amount)} · Started ${row.started_at} UTC`,
                        ),
                        h("p", { class: "trading-record-meta" }, row.public_id),
                        row.abort_reason &&
                          h("p", `Cancellation reason: ${row.abort_reason}`),
                      ],
                    ),
                  ),
                ],
              },
            ),
          pending.value && h(UiLoading, { label: "Updating starting cash…" }),
        ].filter(Boolean),
      );
    };
  },
};
