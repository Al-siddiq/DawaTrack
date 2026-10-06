import { phaseOneApi } from '../api/phase-one.js';
import { AuditPanel } from '../components/audit-panel.js';

const { createApp, reactive, ref, h } = window.Vue;

createApp({
    setup() {
        const step = ref(1);
        const busy = ref(false);
        const error = ref('');
        const fieldErrors = reactive({});
        const result = reactive({});
        const domains = ref([]);
        const domainReason = reactive({});
        const tenants = ref([]);
        const operationForms = reactive({});

        const form = reactive({
            business_name: '',
            shop_name: '',
            contact_phone: '',
            contact_email: '',
            address_line_1: '',
            address_line_2: '',
            city_town: '',
            state_region: '',
            platform_subdomain: '',
            owner_name: '',
            owner_email: '',
            owner_password: '',
            reason: 'New Tenant onboarding',
        });

        const fields = [
            ['business_name', 'Business/legal name', 'text', true],
            ['shop_name', 'Shop display name', 'text', true],
            ['contact_phone', 'Contact phone', 'tel', false],
            ['contact_email', 'Contact email', 'email', false],
            ['address_line_1', 'Address', 'text', true],
            ['address_line_2', 'Address line 2', 'text', false],
            ['city_town', 'City/town', 'text', true],
            ['state_region', 'State', 'text', true],
            ['platform_subdomain', 'Platform subdomain', 'text', true],
            ['owner_name', 'Primary Owner name', 'text', true],
            ['owner_email', 'Primary Owner email', 'email', true],
            ['owner_password', 'Temporary password', 'password', true],
            ['reason', 'Onboarding reason', 'text', true],
        ].map(([key, label, type, required]) => ({
            key,
            label,
            type,
            required,
        }));

        function clearErrors() {
            error.value = '';
            Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);
        }

        async function submit() {
            busy.value = true;
            clearErrors();

            try {
                const response = await phaseOneApi.onboard(form);

                Object.assign(result, response.data);

                form.owner_password = '';
                step.value = 3;
            } catch (exception) {
                error.value = exception.message;
                Object.assign(fieldErrors, exception.details ?? {});
                form.owner_password = '';
                step.value = 1;
            } finally {
                busy.value = false;
            }
        }

        async function loadDomains() {
            try {
                domains.value = (await phaseOneApi.platformDomains()).data;
            } catch (exception) {
                error.value = exception.message;
            }
        }

        async function domainAction(domain, action, ready = undefined) {
            const reason = (domainReason[domain.public_id] ?? '').trim();

            if (!reason) {
                error.value = 'Enter an operational reason for the domain action.';
                return;
            }

            busy.value = true;
            clearErrors();

            try {
                const body = { reason };

                if (ready !== undefined) {
                    body.ready = ready;
                }

                await phaseOneApi.domainAction(
                    domain.public_id,
                    action,
                    body
                );

                await loadDomains();
            } catch (exception) {
                error.value = exception.message;
            } finally {
                busy.value = false;
            }
        }

        function operationForm(tenant) {
            return operationForms[tenant.public_id] ??= reactive({
                operation: 'ACTIVATE',
                plan_code: 'STARTER',
                starts_at: '',
                ends_at: '',
                external_reference: '',
                reason: '',
            });
        }

        async function loadOperations() {
            try {
                tenants.value = (
                    await phaseOneApi.platformOperations()
                ).data;
            } catch (exception) {
                error.value = exception.message;
            }
        }

        async function subscriptionAction(tenant) {
            busy.value = true;
            clearErrors();

            try {
                await phaseOneApi.subscriptionAction(
                    tenant.public_id,
                    operationForm(tenant)
                );

                await loadOperations();
            } catch (exception) {
                error.value = exception.message;

                Object.assign(
                    fieldErrors,
                    exception.details ?? {}
                );
            } finally {
                busy.value = false;
            }
        }

        async function lifecycle(target, type, action) {
            const data = operationForm(target);

            if (!data.reason.trim()) {
                error.value =
                    'Enter a reason before changing lifecycle state.';
                return;
            }

            if (
                action === 'close'
                && !window.confirm(
                    'Close this Tenant permanently? Data will be retained but Shop access becomes CLOSED.'
                )
            ) {
                return;
            }

            busy.value = true;
            clearErrors();

            try {
                if (type === 'tenant') {
                    await phaseOneApi.tenantAction(
                        target.public_id,
                        action,
                        {
                            reason: data.reason,
                        }
                    );
                } else {
                    await phaseOneApi.shopAction(
                        target.shop_public_id,
                        action,
                        {
                            reason: data.reason,
                        }
                    );
                }

                await loadOperations();
            } catch (exception) {
                error.value = exception.message;
            } finally {
                busy.value = false;
            }
        }

        const inputValue = (object, key) => ({
            value: object[key] ?? '',
            onInput: (event) => {
                object[key] = event.target.value;
            },
        });

        const selectValue = (object, key) => ({
            value: object[key] ?? '',
            onChange: (event) => {
                object[key] = event.target.value;
            },
        });

        const button = (label, props = {}) =>
            h(
                'button',
                {
                    type: 'button',
                    ...props,
                },
                label
            );

        const detailItem = (term, description) =>
            h('div', [
                h('dt', term),
                h('dd', description),
            ]);

        function renderOnboardingForm() {
            if (step.value !== 1) {
                return null;
            }

            return h(
                'form',
                {
                    class: 'responsive-form',
                    novalidate: true,

                    onSubmit: (event) => {
                        event.preventDefault();

                        clearErrors();
                        step.value = 2;
                    },
                },
                [
                    ...fields.map((field) =>
                        h(
                            'label',
                            {
                                key: field.key,
                            },
                            [
                                field.label,

                                h('input', {
                                    ...inputValue(
                                        form,
                                        field.key
                                    ),

                                    class: [
                                        'form-control',
                                        {
                                            'is-invalid':
                                                Boolean(
                                                    fieldErrors[
                                                        field.key
                                                    ]
                                                ),
                                        },
                                    ],

                                    type:
                                        field.type
                                        || 'text',

                                    autocomplete:
                                        field.type ===
                                        'password'
                                            ? 'new-password'
                                            : undefined,

                                    required:
                                        field.required,

                                    minlength:
                                        field.type ===
                                        'password'
                                            ? 12
                                            : undefined,
                                }),

                                fieldErrors[
                                    field.key
                                ]
                                    ? h(
                                        'span',
                                        {
                                            class:
                                                'invalid-feedback',
                                        },
                                        fieldErrors[
                                            field.key
                                        ][0]
                                    )
                                    : null,
                            ]
                        )
                    ),

                    h(
                        'button',
                        {
                            class:
                                'btn btn-primary',
                            type: 'submit',
                        },
                        'Review onboarding'
                    ),
                ]
            );
        }

        function renderReview() {
            if (step.value !== 2) {
                return null;
            }

            return h(
                'section',
                {
                    'aria-labelledby':
                        'review-heading',
                },
                [
                    h(
                        'h2',
                        {
                            id:
                                'review-heading',
                        },
                        'Confirm details'
                    ),

                    h(
                        'p',
                        'The temporary password is never redisplayed after submission.'
                    ),

                    h(
                        'dl',
                        {
                            class:
                                'detail-grid',
                        },
                        fields.map((field) =>
                            detailItem(
                                field.label,
                                field.type ===
                                    'password'
                                    ? '••••••••••••'
                                    : form[
                                        field.key
                                    ]
                            )
                        )
                    ),

                    h(
                        'div',
                        {
                            class:
                                'button-row',
                        },
                        [
                            button(
                                'Back',
                                {
                                    class:
                                        'btn btn-outline-secondary',

                                    onClick: () => {
                                        step.value =
                                            1;
                                    },
                                }
                            ),

                            button(
                                busy.value
                                    ? 'Creating…'
                                    : 'Create Tenant and Shop',
                                {
                                    class:
                                        'btn btn-primary',

                                    disabled:
                                        busy.value,

                                    onClick:
                                        submit,
                                }
                            ),
                        ]
                    ),
                ]
            );
        }

        function renderComplete() {
            if (step.value !== 3) {
                return null;
            }

            return h(
                'section',
                {
                    class:
                        'success-panel',

                    role:
                        'status',
                },
                [
                    h(
                        'h2',
                        'Shop onboarding complete'
                    ),

                    h(
                        'dl',
                        {
                            class:
                                'detail-grid',
                        },
                        [
                            detailItem(
                                'Tenant',
                                result.tenant_public_id
                                ?? ''
                            ),

                            detailItem(
                                'Shop',
                                result.shop_public_id
                                ?? ''
                            ),

                            detailItem(
                                'Owner',
                                result.owner_email
                                ?? ''
                            ),

                            detailItem(
                                'Platform address',
                                result.hostname
                                ?? ''
                            ),

                            detailItem(
                                'Trial',
                                '30 days · Active'
                            ),
                        ]
                    ),
                ]
            );
        }

        function renderDomain(domain) {
            return h(
                'article',
                {
                    class:
                        'record-card',

                    key:
                        domain.public_id,
                },
                [
                    h(
                        'strong',
                        {
                            class:
                                'break-all',
                        },
                        domain.hostname
                    ),

                    h(
                        'span',
                        `${domain.shop_name} · ${domain.domain_type}`
                    ),

                    h(
                        'span',
                        [
                            `DNS ${domain.verification_status} · TLS ${domain.certificate_status || 'pending'} · ${domain.is_active ? 'Active' : 'Inactive'} `,

                            domain.is_primary
                                ? h(
                                    'b',
                                    '· Primary'
                                )
                                : null,
                        ]
                    ),

                    h(
                        'label',
                        [
                            'Operational reason',

                            h('input', {
                                class:
                                    'form-control',

                                required:
                                    true,

                                value:
                                    domainReason[
                                        domain.public_id
                                    ] ?? '',

                                onInput:
                                    (event) => {
                                        domainReason[
                                            domain.public_id
                                        ] =
                                            event.target.value;
                                    },
                            }),
                        ]
                    ),

                    h(
                        'div',
                        {
                            class:
                                'button-row',
                        },
                        [
                            domain.domain_type
                            === 'CUSTOM'
                                ? button(
                                    'TLS ready',
                                    {
                                        class:
                                            'btn btn-outline-success',

                                        disabled:
                                            busy.value,

                                        onClick:
                                            () =>
                                                domainAction(
                                                    domain,
                                                    'tls',
                                                    true
                                                ),
                                    }
                                )
                                : null,

                            domain.domain_type
                            === 'CUSTOM'
                                ? button(
                                    'TLS failed',
                                    {
                                        class:
                                            'btn btn-outline-danger',

                                        disabled:
                                            busy.value,

                                        onClick:
                                            () =>
                                                domainAction(
                                                    domain,
                                                    'tls',
                                                    false
                                                ),
                                    }
                                )
                                : null,

                            domain.domain_type
                            === 'CUSTOM'
                            && !domain.is_active
                                ? button(
                                    'Activate',
                                    {
                                        class:
                                            'btn btn-primary',

                                        disabled:
                                            busy.value,

                                        onClick:
                                            () =>
                                                domainAction(
                                                    domain,
                                                    'activate'
                                                ),
                                    }
                                )
                                : null,

                            domain.is_active
                            && !domain.is_primary
                                ? button(
                                    'Make primary',
                                    {
                                        class:
                                            'btn btn-outline-primary',

                                        disabled:
                                            busy.value,

                                        onClick:
                                            () =>
                                                domainAction(
                                                    domain,
                                                    domain.domain_type
                                                    === 'PLATFORM_SUBDOMAIN'
                                                        ? 'fallback'
                                                        : 'primary'
                                                ),
                                    }
                                )
                                : null,

                            domain.domain_type
                            === 'CUSTOM'
                            && domain.is_active
                            && !domain.is_primary
                                ? button(
                                    'Deactivate',
                                    {
                                        class:
                                            'btn btn-outline-danger',

                                        disabled:
                                            busy.value,

                                        onClick:
                                            () =>
                                                domainAction(
                                                    domain,
                                                    'deactivate'
                                                ),
                                    }
                                )
                                : null,
                        ]
                    ),
                ]
            );
        }

        function renderDomains() {
            return h(
                'section',
                {
                    class:
                        'workspace',

                    'aria-labelledby':
                        'domain-operations',
                },
                [
                    h(
                        'div',
                        {
                            class:
                                'button-row',
                        },
                        [
                            h(
                                'div',
                                [
                                    h(
                                        'div',
                                        {
                                            class:
                                                'eyebrow',
                                        },
                                        'Recent MFA required'
                                    ),

                                    h(
                                        'h2',
                                        {
                                            id:
                                                'domain-operations',
                                        },
                                        'Domain operations'
                                    ),
                                ]
                            ),

                            button(
                                'Refresh',
                                {
                                    class:
                                        'btn btn-outline-secondary',

                                    onClick:
                                        loadDomains,
                                }
                            ),
                        ]
                    ),

                    h(
                        'p',
                        'Ownership verification and active TLS are enforced before activation. Every mutation requires a reason.'
                    ),

                    h(
                        'div',
                        {
                            class:
                                'record-grid',
                        },
                        domains.value.map(
                            renderDomain
                        )
                    ),
                ]
            );
        }

        function renderSubscriptionTimeline(tenant) {
            const subscription =
                tenant.subscription;

            return h(
                'details',
                [
                    h(
                        'summary',
                        'Periods and events'
                    ),

                    h(
                        'h3',
                        'Future'
                    ),

                    subscription.future_periods.length
                    === 0
                        ? h(
                            'p',
                            'None'
                        )
                        : null,

                    ...subscription.future_periods.map(
                        (period) =>
                            h(
                                'div',
                                {
                                    class:
                                        'timeline-item',

                                    key:
                                        `future-${period.public_id ?? period.starts_at}`,
                                },
                                `${period.plan_name} · ${period.starts_at} → ${period.ends_at}`
                            )
                    ),

                    h(
                        'h3',
                        'History'
                    ),

                    subscription.historical_periods.length
                    === 0
                        ? h(
                            'p',
                            'None'
                        )
                        : null,

                    ...subscription.historical_periods.map(
                        (period) =>
                            h(
                                'div',
                                {
                                    class:
                                        'timeline-item',

                                    key:
                                        `history-${period.public_id ?? period.starts_at}`,
                                },
                                `${period.plan_name} · ${period.starts_at} → ${period.ends_at}`
                            )
                    ),

                    h(
                        'h3',
                        'Events'
                    ),

                    ...subscription.events.map(
                        (event) =>
                            h(
                                'div',
                                {
                                    class:
                                        'timeline-item',

                                    key:
                                        event.public_id
                                        ?? `${event.event_type}-${event.effective_at}`,
                                },
                                `${event.event_type} · ${event.effective_at} · ${event.reason}`
                            )
                    ),
                ]
            );
        }

        function renderSubscriptionForm(tenant) {
            const data =
                operationForm(tenant);

            return h(
                'form',
                {
                    class:
                        'responsive-form',

                    onSubmit:
                        (event) => {
                            event.preventDefault();

                            subscriptionAction(
                                tenant
                            );
                        },
                },
                [
                    h(
                        'label',
                        [
                            'Operation',

                            h(
                                'select',
                                {
                                    ...selectValue(
                                        data,
                                        'operation'
                                    ),

                                    class:
                                        'form-select',
                                },
                                [
                                    h(
                                        'option',
                                        {
                                            value:
                                                'ACTIVATE',
                                        },
                                        'ACTIVATE'
                                    ),

                                    h(
                                        'option',
                                        {
                                            value:
                                                'RENEW',
                                        },
                                        'RENEW'
                                    ),

                                    h(
                                        'option',
                                        {
                                            value:
                                                'UPGRADE',
                                        },
                                        'UPGRADE'
                                    ),

                                    h(
                                        'option',
                                        {
                                            value:
                                                'DOWNGRADE',
                                        },
                                        'DOWNGRADE'
                                    ),

                                    h(
                                        'option',
                                        {
                                            value:
                                                'EXTEND',
                                        },
                                        'EXTEND'
                                    ),
                                ]
                            ),
                        ]
                    ),

                    h(
                        'label',
                        [
                            'Plan',

                            h(
                                'select',
                                {
                                    ...selectValue(
                                        data,
                                        'plan_code'
                                    ),

                                    class:
                                        'form-select',
                                },
                                [
                                    h(
                                        'option',
                                        {
                                            value:
                                                'STARTER',
                                        },
                                        'STARTER'
                                    ),

                                    h(
                                        'option',
                                        {
                                            value:
                                                'GROWTH',
                                        },
                                        'GROWTH'
                                    ),

                                    h(
                                        'option',
                                        {
                                            value:
                                                'TRIAL',
                                        },
                                        'TRIAL'
                                    ),
                                ]
                            ),
                        ]
                    ),

                    h(
                        'label',
                        [
                            'Starts (UTC)',

                            h(
                                'input',
                                {
                                    ...inputValue(
                                        data,
                                        'starts_at'
                                    ),

                                    type:
                                        'datetime-local',

                                    class:
                                        'form-control',

                                    required:
                                        true,
                                }
                            ),
                        ]
                    ),

                    h(
                        'label',
                        [
                            'Ends (UTC)',

                            h(
                                'input',
                                {
                                    ...inputValue(
                                        data,
                                        'ends_at'
                                    ),

                                    type:
                                        'datetime-local',

                                    class:
                                        'form-control',

                                    required:
                                        true,
                                }
                            ),
                        ]
                    ),

                    h(
                        'label',
                        [
                            'Reference',

                            h(
                                'input',
                                {
                                    ...inputValue(
                                        data,
                                        'external_reference'
                                    ),

                                    class:
                                        'form-control',

                                    maxlength:
                                        120,
                                }
                            ),
                        ]
                    ),

                    h(
                        'label',
                        [
                            'Reason',

                            h(
                                'input',
                                {
                                    ...inputValue(
                                        data,
                                        'reason'
                                    ),

                                    class:
                                        'form-control',

                                    maxlength:
                                        500,

                                    required:
                                        true,
                                }
                            ),
                        ]
                    ),

                    h(
                        'button',
                        {
                            class:
                                'btn btn-primary',

                            type:
                                'submit',

                            disabled:
                                busy.value,
                        },
                        'Apply entitlement'
                    ),
                ]
            );
        }

        function renderLifecycleButtons(tenant) {
            return h(
                'div',
                {
                    class:
                        'button-row',
                },
                [
                    tenant.status
                    === 'ACTIVE'
                        ? button(
                            'Suspend Tenant',
                            {
                                class:
                                    'btn btn-outline-warning',

                                disabled:
                                    busy.value,

                                onClick:
                                    () =>
                                        lifecycle(
                                            tenant,
                                            'tenant',
                                            'suspend'
                                        ),
                            }
                        )
                        : null,

                    tenant.status
                    === 'SUSPENDED'
                        ? button(
                            'Reactivate Tenant',
                            {
                                class:
                                    'btn btn-outline-success',

                                disabled:
                                    busy.value,

                                onClick:
                                    () =>
                                        lifecycle(
                                            tenant,
                                            'tenant',
                                            'reactivate'
                                        ),
                            }
                        )
                        : null,

                    tenant.status
                    !== 'CLOSED'
                        ? button(
                            'Close Tenant',
                            {
                                class:
                                    'btn btn-danger',

                                disabled:
                                    busy.value,

                                onClick:
                                    () =>
                                        lifecycle(
                                            tenant,
                                            'tenant',
                                            'close'
                                        ),
                            }
                        )
                        : null,

                    tenant.shop_status
                    === 'ACTIVE'
                        ? button(
                            'Deactivate Shop',
                            {
                                class:
                                    'btn btn-outline-danger',

                                disabled:
                                    busy.value,

                                onClick:
                                    () =>
                                        lifecycle(
                                            tenant,
                                            'shop',
                                            'deactivate'
                                        ),
                            }
                        )
                        : button(
                            'Reactivate Shop',
                            {
                                class:
                                    'btn btn-outline-success',

                                disabled:
                                    busy.value
                                    || tenant.status
                                    === 'CLOSED',

                                onClick:
                                    () =>
                                        lifecycle(
                                            tenant,
                                            'shop',
                                            'reactivate'
                                        ),
                            }
                        ),
                ]
            );
        }

        function renderTenant(tenant) {
            const current =
                tenant.subscription
                    .current_entitlement;

            return h(
                'article',
                {
                    class:
                        'record-card operation-card',

                    key:
                        tenant.public_id,
                },
                [
                    h(
                        'header',
                        [
                            h(
                                'strong',
                                tenant.legal_or_business_name
                            ),

                            h(
                                'p',
                                `Tenant ${tenant.status} · Shop ${tenant.shop_status} · ${tenant.shop_name}`
                            ),
                        ]
                    ),

                    h(
                        'dl',
                        {
                            class:
                                'detail-grid',
                        },
                        [
                            detailItem(
                                'Current',
                                current?.plan_name
                                || 'None'
                            ),

                            detailItem(
                                'Current window',
                                current
                                    ? `${current.starts_at} → ${current.ends_at}`
                                    : 'READ_ONLY when lifecycle permits'
                            ),

                            detailItem(
                                'Active Staff',
                                String(
                                    tenant.active_staff
                                )
                            ),

                            detailItem(
                                'Future periods',
                                String(
                                    tenant.subscription
                                        .future_periods
                                        .length
                                )
                            ),
                        ]
                    ),

                    renderSubscriptionTimeline(
                        tenant
                    ),

                    renderSubscriptionForm(
                        tenant
                    ),

                    renderLifecycleButtons(
                        tenant
                    ),
                ]
            );
        }

        function renderOperations() {
            return h(
                'section',
                {
                    class:
                        'workspace',

                    'aria-labelledby':
                        'tenant-operations',
                },
                [
                    h(
                        'div',
                        {
                            class:
                                'button-row',
                        },
                        [
                            h(
                                'div',
                                [
                                    h(
                                        'div',
                                        {
                                            class:
                                                'eyebrow',
                                        },
                                        'Phase 1 operations · Recent MFA required'
                                    ),

                                    h(
                                        'h2',
                                        {
                                            id:
                                                'tenant-operations',
                                        },
                                        'Tenant, Shop and subscriptions'
                                    ),
                                ]
                            ),

                            button(
                                'Refresh',
                                {
                                    class:
                                        'btn btn-outline-secondary',

                                    onClick:
                                        loadOperations,
                                }
                            ),
                        ]
                    ),

                    h(
                        'div',
                        {
                            class:
                                'record-grid',
                        },
                        tenants.value.map(
                            renderTenant
                        )
                    ),
                ]
            );
        }

        loadDomains();
        loadOperations();

        return () =>
            h(
                'div',
                {
                    class:
                        'workspace',
                },
                [
                    h(
                        'header',
                        [
                            h(
                                'div',
                                {
                                    class:
                                        'eyebrow',
                                },
                                'Platform administration'
                            ),

                            h(
                                'h1',
                                'Onboard a Shop'
                            ),

                            h(
                                'p',
                                'Create the Owner, Tenant, Shop, brand, platform address and Trial in one audited transaction.'
                            ),
                        ]
                    ),

                    h(
                        'ol',
                        {
                            class:
                                'step-indicator',

                            'aria-label':
                                'Onboarding progress',
                        },
                        [
                            h(
                                'li',
                                {
                                    class: {
                                        active:
                                            step.value
                                            === 1,
                                    },
                                },
                                'Details'
                            ),

                            h(
                                'li',
                                {
                                    class: {
                                        active:
                                            step.value
                                            === 2,
                                    },
                                },
                                'Review'
                            ),

                            h(
                                'li',
                                {
                                    class: {
                                        active:
                                            step.value
                                            === 3,
                                    },
                                },
                                'Complete'
                            ),
                        ]
                    ),

                    error.value
                        ? h(
                            'div',
                            {
                                class:
                                    'alert alert-danger',

                                role:
                                    'alert',
                            },
                            `${error.value} No partial account was retained.`
                        )
                        : null,

                    renderOnboardingForm(),
                    renderReview(),
                    renderComplete(),
                    renderDomains(),
                    renderOperations(),
                    h(AuditPanel, { mode: 'platform', tenants: tenants.value }),
                ]
            );
    },
}).mount('#admin-app');
