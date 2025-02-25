import React, { useState } from 'react';

const JobSearch = ({ initialResults, initialKeywords, initialLocation }) => {
    const [results, setResults] = useState(initialResults);
    const [filters, setFilters] = useState({
        keywords: initialKeywords || '',
        location: initialLocation || '',
        salaryMin: '',
        salaryMax: '',
        contractType: 'all'
    });

    const handleSearch = (e) => {
        e.preventDefault();
        const searchParams = new URLSearchParams(filters);
        window.location.href = `${window.location.pathname}?${searchParams.toString()}`;
    };

    const getAnalyticsUrl = () => {
        const params = new URLSearchParams();
        if (filters.keywords) params.append('keywords', filters.keywords);
        if (filters.location) params.append('location', filters.location);
        return `/analytics?${params.toString()}`;
    };

    const decodeHtmlEntities = (str) => {
        if (!str) return '';
        const textArea = document.createElement('textarea');
        textArea.innerHTML = str;
        return textArea.value;
    };

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {/* Search Form */}
            <div className="bg-white rounded-lg shadow-lg p-6 mb-8">
                <form onSubmit={handleSearch} className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Keywords</label>
                            <input
                                type="text"
                                value={filters.keywords}
                                onChange={(e) => setFilters({...filters, keywords: e.target.value})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Job title, skills, or company"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Location</label>
                            <input
                                type="text"
                                value={filters.location}
                                onChange={(e) => setFilters({...filters, location: e.target.value})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="City or country"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Minimum Salary</label>
                            <input
                                type="number"
                                value={filters.salaryMin}
                                onChange={(e) => setFilters({...filters, salaryMin: e.target.value})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="£"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Maximum Salary</label>
                            <input
                                type="number"
                                value={filters.salaryMax}
                                onChange={(e) => setFilters({...filters, salaryMax: e.target.value})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="£"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Contract Type</label>
                            <select
                                value={filters.contractType}
                                onChange={(e) => setFilters({...filters, contractType: e.target.value})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="all">All Types</option>
                                <option value="permanent">Permanent</option>
                                <option value="contract">Contract</option>
                            </select>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            Search Jobs
                        </button>
                    </div>
                </form>
            </div>

            {/* Results Section */}
            {results && (
                <div className="space-y-6">
                    <div className="flex justify-between items-center">
                        <h2 className="text-xl font-semibold text-gray-900">
                            {results.total} Jobs Found
                        </h2>
                        <a
                            href={getAnalyticsUrl()}
                            className="text-blue-600 hover:text-blue-800"
                        >
                            View Market Analytics →
                        </a>
                    </div>

                    <div className="space-y-4">
                        {results.jobs.map((job, index) => (
                            <div key={index} className="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                                <div className="flex justify-between items-start">
                                    <div>
                                        <h3 className="text-lg font-medium text-gray-900">
                                            <a href={job.url} target="_blank" rel="noopener noreferrer" className="hover:text-blue-600">
                                                {decodeHtmlEntities(job.title)}
                                            </a>
                                        </h3>
                                        <p className="text-sm text-gray-600">{decodeHtmlEntities(job.company)}</p>
                                    </div>
                                    {job.salary && (
                                        <span className="text-green-600 font-medium">
                                            {decodeHtmlEntities(job.salary)}
                                        </span>
                                    )}
                                </div>
                                <div className="mt-2">
                                    <p className="text-sm text-gray-500">{decodeHtmlEntities(job.locations)}</p>
                                    <p className="mt-2 text-sm text-gray-700"
                                       dangerouslySetInnerHTML={{
                                           __html: decodeHtmlEntities(job.description)
                                       }}>
                                    </p>
                                </div>
                                <div className="mt-4 flex items-center justify-between">
                                    <span className="text-sm text-gray-500">
                                        Posted: {new Date(job.date).toLocaleDateString()}
                                    </span>
                                    <a
                                        href={job.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200"
                                    >
                                        Apply Now →
                                    </a>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Pagination */}
                    {results.pages > 1 && (
                        <div className="flex justify-center mt-8">
                            <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                {Array.from({ length: results.pages }, (_, i) => i + 1).map((pageNum) => (
                                    <a
                                        key={pageNum}
                                        href={`?${new URLSearchParams({
                                            ...filters,
                                            page: pageNum
                                        }).toString()}`}
                                        className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                                            pageNum === results.current_page
                                                ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                                : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                        }`}
                                    >
                                        {pageNum}
                                    </a>
                                ))}
                            </nav>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default JobSearch;
