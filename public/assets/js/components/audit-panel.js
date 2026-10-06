import { phaseOneApi } from '../api/phase-one.js';

const { ref, reactive, h, onMounted, nextTick } = window.Vue;

function filterField(label, control) {
    return h('label', { class: 'audit-filter' }, [
        h('span', label),
        control,
    ]);
}

function displayValue(value) {
    return value === null || value === undefined || value === ''
        ? '—'
        : String(value);
}

export const AuditPanel = {
    props: {
        mode: { type: String, required: true },
        tenants: { type: Array, default: () => [] },
    },

    setup(props) {
        const view = ref('normal');
        const events = ref([]);
        const selected = ref(null);
        const loading = ref(false);
        const error = ref('');
        const nextCursor = ref(null);
        const detailTrigger = ref(null);
        const supportTargetInput = ref(null);
        const filters = reactive({
            from: '',
            to: '',
            category: '',
            action: '',
            actor: '',
            resource_type: '',
            resource_public_id: '',
            correlation_id: '',
        });
        const support = reactive({
            tenant_public_id: '',
            shop_public_id: '',
            reason: '',
            access_id: '',
            tenant_name: '',
            shop_name: '',
        });

        function queryString(cursor = null) {
            const params = new URLSearchParams();

            Object.entries(filters).forEach(([key, item]) => {
                if (item) {
                    params.set(key, item);
                }
            });

            if (cursor) {
                params.set('cursor', cursor);
            }

            const query = params.toString();
            return query ? `?${query}` : '';
        }

        async function load(reset = true) {
            loading.value = true;
            error.value = '';

            try {
                let response;

                if (props.mode === 'platform' && view.value === 'support') {
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
                    Object.assign(support, response.data.support);
                } else {
                    const query = queryString(reset ? null : nextCursor.value);
                    response = props.mode === 'owner'
                        ? await phaseOneApi.auditOwner(query)
                        : await phaseOneApi.platformAudit(query);
                }

                const data = response.data;
                events.value = reset
                    ? data.events
                    : [...events.value, ...data.events];
                nextCursor.value = data.next_cursor;
                selected.value = null;
            } catch (exception) {
                error.value = `${exception.message}${
                    exception.requestId ? ` Reference ${exception.requestId}.` : ''
                }`;
            } finally {
                loading.value = false;
            }
        }

        async function openDetail(event, trigger) {
            detailTrigger.value = trigger;
            loading.value = true;
            error.value = '';

            try {
                let response;

                if (props.mode === 'owner') {
                    response = await phaseOneApi.auditOwnerDetail(event.public_id);
                } else if (view.value === 'support') {
                    response = await phaseOneApi.supportAuditDetail(event.public_id, {
                        access_id: support.access_id,
                    });
                } else {
                    response = await phaseOneApi.platformAuditDetail(event.public_id);
                }

                selected.value = response.data.event ?? response.data;
            } catch (exception) {
                error.value = `${exception.message}${
                    exception.requestId ? ` Reference ${exception.requestId}.` : ''
                }`;
            } finally {
                loading.value = false;
            }
        }

        function resetFilters() {
            Object.keys(filters).forEach((key) => {
                filters[key] = '';
            });

            nextCursor.value = null;
            load(true);
        }

        function leaveSupport() {
            view.value = 'normal';
            Object.assign(support, {
                tenant_public_id: '',
                shop_public_id: '',
                reason: '',
                access_id: '',
                tenant_name: '',
                shop_name: '',
            });
            events.value = [];
            selected.value = null;
            load(true);
        }

        function enterSupport() {
            view.value = 'support';
            events.value = [];
            selected.value = null;
            error.value = '';
            nextTick(() => supportTargetInput.value?.focus());
        }

        function closeDetail() {
            selected.value = null;
            nextTick(() => detailTrigger.value?.focus());
        }

        function auditCard(event) {
            return h('li', { class: 'audit-card', key: event.public_id }, [
                h('div', { class: 'audit-card-head' }, [
                    h('strong', event.action_code),
                    h('time', event.occurred_at),
                ]),
                h(
                    'p',
                    `${event.actor?.name ?? event.actor_type} · ${event.resource_type} · ${
                        displayValue(event.resource_public_id)
                    }`
                ),
                h(
                    'button',
                    {
                        type: 'button',
                        class: 'btn btn-outline-secondary',
                        onClick: (clickEvent) => openDetail(event, clickEvent.currentTarget),
                    },
                    'View details'
                ),
            ]);
        }

        function detailView() {
            if (!selected.value) {
                return null;
            }

            const event = selected.value;
            const values = {
                Action: event.action_code,
                Category: event.category,
                Actor: event.actor?.name,
                Authority: event.actor?.authority,
                Subject: `${event.resource_type} ${displayValue(event.resource_public_id)}`,
                Occurred: event.occurred_at,
                Recorded: event.created_at,
                Reason: event.reason,
                Correlation: event.correlation_id,
                'Caused by': event.caused_by_public_id,
            };

            return h(
                'section',
                {
                    class: 'audit-detail',
                    'aria-labelledby': 'audit-detail-title',
                },
                [
                    h('h3', { id: 'audit-detail-title' }, 'Activity details'),
                    h(
                        'dl',
                        { class: 'detail-grid' },
                        Object.entries(values).map(([term, description]) =>
                            h('div', [
                                h('dt', term),
                                h(
                                    'dd',
                                    { class: ['Correlation', 'Caused by'].includes(term) ? 'break-all' : '' },
                                    displayValue(description)
                                ),
                            ])
                        )
                    ),
                    event.change_metadata
                        ? h('pre', { class: 'audit-metadata' }, JSON.stringify(event.change_metadata, null, 2))
                        : null,
                    event.related_events?.length
                        ? h('div', [
                            h('h4', 'Related activity'),
                            h(
                                'ul',
                                event.related_events.map((related) =>
                                    h('li', `${related.action_code} · ${related.occurred_at}`)
                                )
                            ),
                        ])
                        : h('p', 'No authorized related activity is available.'),
                    h(
                        'button',
                        {
                            type: 'button',
                            class: 'btn btn-outline-secondary',
                            onClick: closeDetail,
                        },
                        'Close details'
                    ),
                ]
            );
        }

        function supportSelector() {
            if (props.mode !== 'platform' || view.value !== 'support' || support.access_id) {
                return null;
            }

            return h(
                'form',
                {
                    class: 'responsive-form',
                    onSubmit: (event) => {
                        event.preventDefault();
                        load(true);
                    },
                },
                [
                    filterField(
                        'Tenant',
                        h(
                            'select',
                            {
                                class: 'form-select',
                                ref: supportTargetInput,
                                required: true,
                                value: support.tenant_public_id,
                                onChange: (event) => {
                                    support.tenant_public_id = event.target.value;
                                    const tenant = props.tenants.find(
                                        (item) => item.public_id === event.target.value
                                    );
                                    support.shop_public_id = tenant?.shop_public_id ?? '';
                                },
                            },
                            [
                                h('option', { value: '' }, 'Choose Tenant'),
                                ...props.tenants.map((tenant) =>
                                    h(
                                        'option',
                                        { value: tenant.public_id },
                                        tenant.legal_or_business_name
                                    )
                                ),
                            ]
                        )
                    ),
                    filterField(
                        'Shop',
                        h('input', {
                            class: 'form-control break-all',
                            value: support.shop_public_id,
                            readonly: true,
                        })
                    ),
                    filterField(
                        'Support reason',
                        h('textarea', {
                            class: 'form-control',
                            required: true,
                            maxlength: 500,
                            value: support.reason,
                            onInput: (event) => {
                                support.reason = event.target.value;
                            },
                        })
                    ),
                    h(
                        'button',
                        {
                            class: 'btn btn-primary',
                            disabled: loading.value,
                        },
                        loading.value ? 'Authorizing…' : 'Confirm support access'
                    ),
                ]
            );
        }

        function supportBanner() {
            if (props.mode !== 'platform' || view.value !== 'support' || !support.access_id) {
                return null;
            }

            return h('aside', { class: 'alert alert-warning support-banner', role: 'status' }, [
                h('strong', 'Platform support view — not Tenant impersonation'),
                h('p', `${support.tenant_name} · ${support.shop_name}`),
                h('p', support.reason),
                h(
                    'button',
                    {
                        type: 'button',
                        class: 'btn btn-outline-secondary',
                        onClick: leaveSupport,
                    },
                    'Leave support view'
                ),
            ]);
        }

        function filterForm() {
            const authorized = props.mode === 'owner'
                || view.value === 'normal'
                || Boolean(support.access_id);

            if (!authorized) {
                return null;
            }

            const input = (key, type = 'text') => h('input', {
                type,
                class: 'form-control',
                value: filters[key],
                onInput: (event) => {
                    filters[key] = key === 'category'
                        ? event.target.value.toUpperCase()
                        : event.target.value;
                },
            });

            return h(
                'form',
                {
                    class: 'audit-filters',
                    onSubmit: (event) => {
                        event.preventDefault();
                        load(true);
                    },
                },
                [
                    filterField('From', input('from', 'date')),
                    filterField('To', input('to', 'date')),
                    filterField('Category', input('category')),
                    filterField('Action', input('action')),
                    filterField('Actor', input('actor')),
                    filterField('Resource type', input('resource_type')),
                    filterField('Subject UUID', input('resource_public_id')),
                    filterField('Correlation', input('correlation_id')),
                    h('div', { class: 'button-row' }, [
                        h(
                            'button',
                            {
                                class: 'btn btn-primary',
                                disabled: loading.value,
                            },
                            loading.value ? 'Loading…' : 'Apply filters'
                        ),
                        h(
                            'button',
                            {
                                type: 'button',
                                class: 'btn btn-outline-secondary',
                                disabled: loading.value,
                                onClick: resetFilters,
                            },
                            'Reset'
                        ),
                    ]),
                ]
            );
        }

        onMounted(() => load(true));

        return () => {
            const canShowResults = props.mode === 'owner'
                || view.value === 'normal'
                || Boolean(support.access_id);

            return h('section', { class: 'workspace audit-workspace' }, [
                h('h2', props.mode === 'owner' ? 'Activity history' : 'Platform Audit'),
                props.mode === 'platform'
                    ? h('div', { class: 'button-row', 'aria-label': 'Audit view' }, [
                        h(
                            'button',
                            {
                                type: 'button',
                                class: 'btn btn-outline-secondary',
                                onClick: leaveSupport,
                            },
                            'Platform Audit'
                        ),
                        h(
                            'button',
                            {
                                type: 'button',
                                class: 'btn btn-outline-secondary',
                                onClick: enterSupport,
                            },
                            'Access Tenant Audit for support'
                        ),
                    ])
                    : null,
                supportSelector(),
                supportBanner(),
                filterForm(),
                error.value
                    ? h('div', { class: 'alert alert-danger', role: 'alert' }, error.value)
                    : null,
                !loading.value && !events.value.length && canShowResults
                    ? h('div', { class: 'empty-panel' }, 'No activity matches these filters.')
                    : null,
                loading.value && !events.value.length
                    ? h('p', { role: 'status' }, 'Loading activity…')
                    : null,
                h('ol', { class: 'audit-timeline' }, events.value.map(auditCard)),
                nextCursor.value
                    ? h(
                        'button',
                        {
                            type: 'button',
                            class: 'btn btn-outline-secondary',
                            disabled: loading.value,
                            onClick: () => load(false),
                        },
                        loading.value ? 'Loading…' : 'Load more'
                    )
                    : null,
                detailView(),
            ]);
        };
    },
};
