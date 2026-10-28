import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static targets = ['feedback', 'submit'];

    async submit(event) {
        event.preventDefault();
        this.feedbackTarget.classList.add('d-none');
        this.submitTarget.disabled = true;

        const formData = new FormData(this.element);
        const payload = {
            path: formData.get('path'),
            label: formData.get('label'),
            order: formData.get('order'),
            conflict: formData.get('conflict'),
            action: formData.get('action'),
            mediaId: formData.get('mediaId') || '',
            strict: formData.has('strict'),
            artwork: formData.has('artwork'),
        };

        try {
            const response = await fetch(this.element.getAttribute('action'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json();

            if (!response.ok) {
                const errors = Array.isArray(result.errors)
                    ? result.errors.map((error) => `${error.field}: ${error.message}`).join(' ')
                    : result.message || 'Unable to queue this job.';

                this.showError(errors);
                return;
            }

            Modal.getOrCreateInstance(this.element.closest('.modal')).hide();
            window.location.reload();
        } catch {
            this.showError('Unable to reach the job queue. Please try again.');
        } finally {
            this.submitTarget.disabled = false;
        }
    }

    showError(message) {
        this.feedbackTarget.textContent = message;
        this.feedbackTarget.classList.remove('d-none');
    }
}
