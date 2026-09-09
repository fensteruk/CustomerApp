import '@fontsource-variable/inter';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('portalShell', () => ({
        sidebarOpen: false,
        desktop: false,
        desktopQuery: null,

        init() {
            this.desktopQuery = window.matchMedia('(min-width: 1280px)');
            this.desktop = this.desktopQuery.matches;

            this.desktopQuery.addEventListener('change', (event) => {
                this.desktop = event.matches;

                if (this.desktop) this.sidebarOpen = false;

                document.documentElement.classList.remove('overflow-hidden');
            });
        },

        openSidebar() {
            this.sidebarOpen = true;
            document.documentElement.classList.add('overflow-hidden');
            this.$nextTick(() => this.$refs.sidebarClose?.focus());
        },

        closeSidebar() {
            if (!this.sidebarOpen) return;

            this.sidebarOpen = false;
            document.documentElement.classList.remove('overflow-hidden');
            window.setTimeout(() => document.querySelector('[data-sidebar-trigger]')?.focus(), 0);
        },

        handleSidebarKeydown(event) {
            if (this.desktop || !this.sidebarOpen || event.key !== 'Tab') return;

            const focusable = [...this.$refs.sidebar.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            )].filter((element) => !element.hidden && element.offsetParent !== null);

            if (focusable.length === 0) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }));

    Alpine.data('lifecycleSelection', () => ({
        selected: {},

        get count() {
            return Object.keys(this.selected).length;
        },

        update(event) {
            const input = event.target;

            if (input.checked) {
                this.selected[input.value] = {
                    withdraw: input.dataset.withdraw === 'true',
                    trash: input.dataset.trash === 'true',
                    restore: input.dataset.restore === 'true',
                };
            } else {
                delete this.selected[input.value];
            }
        },

        can(operation) {
            const selections = Object.values(this.selected);

            return selections.length > 0 && selections.every((selection) => selection[operation]);
        },
    }));

    Alpine.data('notificationBell', (initialUnreadCount = 0) => ({
        open: false,
        loading: false,
        fetchError: false,
        notifications: [],
        unreadCount: initialUnreadCount,

        toggle() {
            this.open = !this.open;

            if (this.open) {
                this.$nextTick(() => this.$refs.closeButton?.focus());

                if (this.notifications.length === 0) this.load();
            }
        },

        close() {
            if (!this.open) return;

            this.open = false;
            this.$nextTick(() => this.$refs.bellButton?.focus());
        },

        ariaLabel() {
            return this.unreadCount > 0
                ? `Notifications, ${this.unreadCount} unread`
                : 'Notifications, no unread notifications';
        },

        badgeLabel() {
            return this.unreadCount > 99 ? '99+' : this.unreadCount;
        },

        csrfHeaders() {
            return {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            };
        },

        async load() {
            this.loading = true;
            this.fetchError = false;

            try {
                const response = await fetch(document.querySelector('meta[name="portal-notifications-index"]')?.content ?? '/portal/notifications', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) throw new Error('Unable to load notifications.');

                const payload = await response.json();
                this.notifications = payload.data ?? [];
                this.unreadCount = payload.unread_count ?? 0;
            } catch (error) {
                this.fetchError = true;
            } finally {
                this.loading = false;
            }
        },

        async markRead(uuid) {
            const response = await fetch(`/portal/notifications/${uuid}/read`, { method: 'POST', headers: this.csrfHeaders() });

            if (!response.ok) return;

            const notification = this.notifications.find((item) => item.uuid === uuid);
            if (notification && !notification.read_at) {
                notification.read_at = new Date().toISOString();
                this.unreadCount = Math.max(0, this.unreadCount - 1);
            }
        },

        async markAllRead() {
            const response = await fetch(document.querySelector('meta[name="portal-notifications-read-all"]')?.content ?? '/portal/notifications/read-all', { method: 'POST', headers: this.csrfHeaders() });

            if (!response.ok) return;

            this.notifications = this.notifications.map((notification) => ({ ...notification, read_at: notification.read_at ?? new Date().toISOString() }));
            this.unreadCount = 0;
        },

        async dismiss(uuid) {
            const notification = this.notifications.find((item) => item.uuid === uuid);
            const response = await fetch(`/portal/notifications/${uuid}/dismiss`, { method: 'POST', headers: this.csrfHeaders() });

            if (!response.ok) return;

            this.notifications = this.notifications.filter((item) => item.uuid !== uuid);
            if (notification && !notification.read_at) this.unreadCount = Math.max(0, this.unreadCount - 1);
        },
    }));
});

Alpine.start();
