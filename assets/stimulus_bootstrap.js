import { startStimulusApp } from '@symfony/stimulus-bundle';
import JobFormController from './controllers/job_form_controller.js';
import JobListController from './controllers/job_list_controller.js';
import DashboardRefreshController from './controllers/dashboard_refresh_controller.js';

const app = startStimulusApp();
app.register('job-form', JobFormController);
app.register('job-list', JobListController);
app.register('dashboard-refresh', DashboardRefreshController);
