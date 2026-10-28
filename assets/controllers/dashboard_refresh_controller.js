import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content', 'status'];
    static values = {
        url: String,
        interval: { type: Number, default: 10000 },
    };

    connect() {
        this.refreshing = false;
        this.timer = window.setInterval(() => this.refresh(), this.intervalValue);
        this.handleVisibilityChange = () => {
            if (!document.hidden) {
                this.refresh();
            }
        };
        document.addEventListener('visibilitychange', this.handleVisibilityChange);

        this.refresh();
    }

    disconnect() {
        window.clearInterval(this.timer);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
        this.abortController?.abort();
    }

    async refresh() {
        if (this.refreshing || document.hidden) {
            return;
        }

        this.refreshing = true;
        this.abortController = new AbortController();

        try {
            const response = await fetch(this.urlValue, {
                headers: { Accept: 'text/html' },
                cache: 'no-store',
                signal: this.abortController.signal,
            });
            const html = await response.text();
            const documentFragment = new DOMParser().parseFromString(html, 'text/html');
            const fragment = documentFragment.querySelector('[data-dashboard-refresh-fragment]');

            if (!fragment) {
                throw new Error('The dashboard refresh response did not contain the expected fragment.');
            }

            this.contentTarget.innerHTML = fragment.innerHTML;
            this.statusTarget.textContent = response.ok
                ? `Last updated at ${new Date().toLocaleTimeString()}.`
                : 'The queue is unavailable. Retrying automatically.';
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.statusTarget.textContent = 'Refresh failed. Showing the last available queue data.';
            }
        } finally {
            this.refreshing = false;
            this.abortController = null;
        }
    }
}
