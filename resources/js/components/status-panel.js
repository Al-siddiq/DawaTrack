export const StatusPanel = {
    setup() {
        return { message: 'Foundation active', detail: 'No business data or future feature is represented here.' };
    },
    template: `<aside class="status-panel" role="status"><span class="status-dot" aria-hidden="true"></span><div><strong>{{ message }}</strong><p>{{ detail }}</p></div></aside>`,
};
