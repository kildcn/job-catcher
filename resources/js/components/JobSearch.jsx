import React, { useState, useEffect } from 'react';

const JobSearch = ({ initialResults, initialKeywords, initialLocation }) => {
    // State for form and search results
    const [results, setResults] = useState(initialResults);
    const [isLoading, setIsLoading] = useState(false);
    const [filters, setFilters] = useState({
        keywords: initialKeywords || '',
        location: initialLocation || '',
        salaryMin: '',
        salaryMax: '',
        contractType: 'all'
    });

    // Track if this is the initial render
    const [isInitialRender, setIsInitialRender] = useState(true);

    useEffect(() => {
        // Hide the loading spinner once component mounts
        if (isInitialRender) {
            setIsInitialRender(false);
        }
    }, []);

    const handleSearch = (e) => {
        e.preventDefault();
        setIsLoading(true);

        // Build search URL with parameters
        const searchParams = new URLSearchParams();

        // Only add parameters that have values
        for (const [key, value] of Object.entries(filters)) {
            if (value) {
                searchParams.append(key, value);
            }
        }

        // Redirect to the search URL
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

    // Handle filter changes
    const handleFilterChange = (name, value) => {
        setFilters({
            ...filters,
            [name]: value
        });
    };

    // Render loading state during initial page load
    if (isInitialRender) {
        return null; // Let the server-side loading state show
    }

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
                                onChange={(e) => handleFilterChange('keywords', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Job title, skills, or company"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Location</label>
                            <input
                                type="text"
                                value={filters.location}
                                onChange={(e) => handleFilterChange('location', e.target.value)}
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
                                onChange={(e) => handleFilterChange('salaryMin', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="£"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Maximum Salary</label>
                            <input
                                type="number"
                                value={filters.salaryMax}
                                onChange={(e) => handleFilterChange('salaryMax', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="£"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Contract Type</label>
                            <select
                                value={filters.contractType}
                                onChange={(e) => handleFilterChange('contractType', e.target.value)}
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
                            disabled={isLoading}
                            className={`inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white ${
                                isLoading
                                    ? 'bg-blue-400 cursor-not-allowed'
                                    : 'bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500'
                            }`}
                        >
                            {isLoading ? (
                                <>
                                    <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Searching...
                                </>
                            ) : (
                                'Search Jobs'
                            )}
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
                            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                        >
                            View Market Analytics
                            <svg className="ml-2 -mr-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
                            </svg>
                        </a>
                    </div>

                    <div className="space-y-4">
                        {results.jobs && results.jobs.length > 0 ? (
                            results.jobs.map((job, index) => (
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
                                                {decodeHtmlEntities(job.formatted_salary || job.salary)}
                                            </span>
                                        )}
                                    </div>
                                    <div className="mt-2">
                                        <p className="text-sm text-gray-500">{decodeHtmlEntities(job.locations)}</p>
                                        <p className="mt-2 text-sm text-gray-700"
                                           dangerouslySetInnerHTML={{
                                               __html: decodeHtmlEntities(job.short_description || job.description)
                                           }}>
                                        </p>
                                    </div>
                                    <div className="mt-4 flex items-center justify-between">
                                        <span className="text-sm text-gray-500">
                                            Posted: {job.relative_date || new Date(job.date).toLocaleDateString()}
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
                            ))
                        ) : (
                            <div className="bg-white rounded-lg shadow p-6 text-center">
                                <p className="text-gray-600">No jobs found matching your criteria.</p>
                                <p className="mt-2 text-sm text-gray-500">Try adjusting your search parameters.</p>
                            </div>
                        )}
                    </div>

                    {/* Pagination */}
                    {results.pages > 1 && (
                        <div className="flex justify-center mt-8">
                            <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                {Array.from({ length: results.pages }, (_, i) => i + 1).map((pageNum) => {
                                    // Create parameters for pagination link
                                    const pageParams = new URLSearchParams();
                                    for (const [key, value] of Object.entries(filters)) {
                                        if (value) {
                                            pageParams.append(key, value);
                                        }
                                    }
                                    pageParams.set('page', pageNum);

                                    return (
                                        <a
                                            key={pageNum}
                                            href={`?${pageParams.toString()}`}
                                            className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                                                pageNum === results.current_page
                                                    ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                            }`}
                                        >
                                            {pageNum}
                                        </a>
                                    );
                                })}
                            </nav>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default JobSearch;
