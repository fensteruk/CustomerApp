import '@fontsource-variable/inter';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('notificationBell', (initialUnreadCount = 0) => ({
        open: false,
        loading: false,
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

            try {
                const response = await fetch(document.querySelector('meta[name="portal-notifications-index"]')?.content ?? '/portal/notifications', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) throw new Error('Unable to load notifications.');

                const payload = await response.json();
                this.notifications = payload.data ?? [];
                this.unreadCount = payload.unread_count ?? 0;
            } catch (error) {
                this.notifications = [];
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
