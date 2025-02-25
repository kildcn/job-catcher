import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';
import JobSearch from './components/JobSearch';
import JobAnalyticsDashboard from './components/JobAnalyticsDashboard';

// Mount Job Search component
const jobSearchElement = document.getElementById('job-search');
if (jobSearchElement) {
    const root = createRoot(jobSearchElement);
    root.render(
        <JobSearch
            initialResults={JSON.parse(jobSearchElement.dataset.results || 'null')}
            initialKeywords={jobSearchElement.dataset.keywords}
            initialLocation={jobSearchElement.dataset.location}
        />
    );
}

// Mount Analytics Dashboard component
const dashboardElement = document.getElementById('dashboard');
if (dashboardElement) {
    const root = createRoot(dashboardElement);
    root.render(
        <JobAnalyticsDashboard
            analytics={JSON.parse(dashboardElement.dataset.analytics || '{}')}
        />
    );
}
