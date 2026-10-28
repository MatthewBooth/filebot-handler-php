import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['feedback'];

    async delete(event) {
        const button = event.currentTarget;
        const jobId = button.dataset.jobId;

        if (!window.confirm(`Delete queued job ${jobId}?`)) {
            return;
        }

        button.disabled = true;

        try {
            const response = await fetch(button.dataset.url, {
                method: 'DELETE',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                const result = await response.json();
                this.feedbackTarget.textContent = result.message || 'Unable to delete this job.';
                this.feedbackTarget.classList.remove('d-none');
                button.disabled = false;
                return;
            }

            window.location.reload();
        } catch {
            this.feedbackTarget.textContent = 'Unable to reach the job queue. Please try again.';
            this.feedbackTarget.classList.remove('d-none');
            button.disabled = false;
        }
    }
}
