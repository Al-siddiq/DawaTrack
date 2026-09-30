import { phaseOneApi } from '../api/phase-one.js';

const { createApp, reactive, ref } = window.Vue;

createApp({
    setup() {
        const step = ref(1);
        const busy = ref(false);
        const error = ref('');
        const fieldErrors = reactive({});
        const result = reactive({});
        const domains = ref([]);
        const domainReason = reactive({});
        const form = reactive({
            business_name: '', shop_name: '', contact_phone: '', contact_email: '',
            address_line_1: '', address_line_2: '', city_town: '', state_region: '',
            platform_subdomain: '', owner_name: '', owner_email: '', owner_password: '',
            reason: 'New Tenant onboarding',
        });
        const fields = [
            ['business_name', 'Business/legal name', 'text', true], ['shop_name', 'Shop display name', 'text', true],
            ['contact_phone', 'Contact phone', 'tel', false], ['contact_email', 'Contact email', 'email', false],
            ['address_line_1', 'Address', 'text', true], ['address_line_2', 'Address line 2', 'text', false],
            ['city_town', 'City/town', 'text', true], ['state_region', 'State', 'text', true],
            ['platform_subdomain', 'Platform subdomain', 'text', true], ['owner_name', 'Primary Owner name', 'text', true],
            ['owner_email', 'Primary Owner email', 'email', true], ['owner_password', 'Temporary password', 'password', true],
            ['reason', 'Onboarding reason', 'text', true],
        ].map(([key, label, type, required]) => ({ key, label, type, required }));

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

        async function loadDomains() { try { domains.value=(await phaseOneApi.platformDomains()).data; } catch(exception) { error.value=exception.message; } }
        async function domainAction(domain, action, ready = undefined) { const reason=(domainReason[domain.public_id]??'').trim();if(!reason){error.value='Enter an operational reason for the domain action.';return;}busy.value=true;clearErrors();try{const body={reason};if(ready!==undefined)body.ready=ready;await phaseOneApi.domainAction(domain.public_id,action,body);await loadDomains();}catch(exception){error.value=exception.message;}finally{busy.value=false;} }
        loadDomains();

        return { step, busy, error, fieldErrors, result, form, fields, domains, domainReason, submit, clearErrors, loadDomains, domainAction };
    },
}).mount('#admin-app');
