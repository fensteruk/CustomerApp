export function officeAdminForm({ redirect, kind }) {
    return {
        saving: false,
        errors: {},
        message: '',
        mustReload: false,
        uncertain: false,
        confirmed: false,

        async save(event) {
            if (this.saving || this.uncertain || this.mustReload || this.confirmed) return;

            this.saving = true;
            this.errors = {};
            this.message = '';
            const form = event.target;
            const controller = new AbortController();
            const timer = window.setTimeout(() => controller.abort(), 20000);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });

                if ([401, 403, 419].includes(response.status) || response.redirected) {
                    this.mustReload = true;
                    this.message = 'Your session or access has changed. Reload before continuing.';
                } else if (response.status === 422) {
                    const payload = await response.json();
                    for (const field of ['name', 'location', 'reason']) {
                        if (Array.isArray(payload.errors?.[field])) this.errors[field] = payload.errors[field];
                    }
                    this.mustReload = Boolean(payload.errors?.lock_version);
                    this.message = this.mustReload
                        ? 'This record has changed. Reload the latest details before saving.'
                        : 'Please check the information and try again.';
                } else if (response.status === 409) {
                    this.mustReload = true;
                    this.message = 'This record has changed. Reload the latest details before saving.';
                } else if (response.status === 429) {
                    this.message = 'Too many requests. Wait a moment, then try again.';
                } else if (response.ok) {
                    const payload = await response.json();
                    const uuid = payload[kind]?.uuid;
                    if (typeof uuid !== 'string' || !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(uuid)) {
                        throw new Error('Missing confirmed record');
                    }
                    this.confirmed = true;
                    window.location.assign(redirect.replace('__UUID__', encodeURIComponent(uuid)));
                    return;
                } else {
                    this.uncertain = true;
                    this.message = 'We could not confirm that your changes were saved.';
                }
            } catch {
                this.uncertain = true;
                this.message = 'We could not confirm that your changes were saved.';
            } finally {
                window.clearTimeout(timer);
                this.saving = this.confirmed;
                if (this.message) this.$nextTick(() => this.$refs.summary?.focus());
            }
        },
    };
}
