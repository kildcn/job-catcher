import React, { useState, useMemo, useCallback } from 'react';
import {
    LineChart, Line, BarChart, Bar, PieChart, Pie, XAxis, YAxis,
    CartesianGrid, Tooltip, Legend, ResponsiveContainer, Cell, Area, AreaChart
} from 'recharts';

// Define a consistent color palette
const COLORS = [
    '#4299E1', // Blue
    '#48BB78', // Green
    '#F6AD55', // Orange
    '#F56565', // Red
    '#9F7AEA', // Purple
    '#38B2AC', // Teal
    '#FC8181', // Pink
    '#F6E05E', // Yellow
    '#68D391', // Light Green
    '#4FD1C5'  // Light Teal
];

const JobAnalyticsDashboard = ({ analytics }) => {
    const [salaryView, setSalaryView] = useState('permanent');
    const [isLoading, setIsLoading] = useState(false);

    // Memoize currency formatter to avoid unnecessary re-creation
    const formatCurrency = useCallback((value) => {
        if (!value && value !== 0) return 'N/A';

        return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
            maximumFractionDigits: 0
        }).format(value);
    }, []);

    // Pre-process data for charts once
    const salaryData = useMemo(() => {
        if (!analytics.salary_ranges || !analytics.salary_ranges[salaryView]) {
            return [];
        }

        const data = analytics.salary_ranges[salaryView]
            .filter(job => job.min && job.max)
            .map(job => ({
                company: job.company || 'Unknown',
                minSalary: job.min,
                maxSalary: job.max,
                avgSalary: job.avg
            }));

        return data.sort((a, b) => b.maxSalary - a.maxSalary).slice(0, 8);
    }, [analytics.salary_ranges, salaryView]);

    const skillsData = useMemo(() => {
        if (!analytics.skills) return [];

        return Object.entries(analytics.skills)
            .map(([name, count]) => ({ name, value: count }))
            .sort((a, b) => b.value - a.value)
            .slice(0, 8);
    }, [analytics.skills]);

    const experienceData = useMemo(() => {
        if (!analytics.experience_levels) return [];

        return Object.entries(analytics.experience_levels)
            .map(([name, count]) => ({
                name: name.charAt(0).toUpperCase() + name.slice(1),
                value: count
            }))
            .filter(item => item.value > 0);
    }, [analytics.experience_levels]);

    const timelineData = useMemo(() => {
        return analytics.timeline_data || [];
    }, [analytics.timeline_data]);

    // Custom tooltip renderers for charts
    const timelineTooltipFormatter = useCallback((value, name) => {
        if (name === 'count') {
            return [`${value} jobs`, 'Jobs Posted'];
        } else if (name === 'avgSalary') {
            return [formatCurrency(value), 'Average Salary'];
        }
        return [value, name];
    }, [formatCurrency]);

    const skillsTooltipFormatter = useCallback((value, name, props) => {
        return [`${value} jobs`, props.payload.name];
    }, []);

    const CustomSkillsPieLabel = useCallback(({ cx, cy, midAngle, innerRadius, outerRadius, name, percent }) => {
        // Only show labels for segments with enough space
        if (percent < 0.05) return null;

        const RADIAN = Math.PI / 180;
        const radius = innerRadius + (outerRadius - innerRadius) * 0.5;
        const x = cx + radius * Math.cos(-midAngle * RADIAN);
        const y = cy + radius * Math.sin(-midAngle * RADIAN);

        return (
            <text
                x={x}
                y={y}
                fill="#fff"
                textAnchor="middle"
                dominantBaseline="central"
                fontSize={12}
                fontWeight="bold"
            >
                {`${(percent * 100).toFixed(0)}%`}
            </text>
        );
    }, []);

    // Handle API error or data not loaded
    if (analytics.error) {
        return (
            <div className="p-6 max-w-7xl mx-auto">
                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                    <h2 className="text-xl font-semibold text-yellow-800 mb-2">Information</h2>
                    <p className="text-yellow-700">{analytics.error}</p>
                    <div className="mt-6">
                        <a
                            href="/"
                            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                        >
                            Return to Search
                        </a>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="p-6 max-w-7xl mx-auto space-y-8">
            {/* Search Context with Return Button */}
            {analytics.search_params && (
                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                    <div className="flex flex-col md:flex-row justify-between items-center gap-4">
                        <div>
                            <h2 className="text-2xl font-bold text-blue-800 mb-2">Job Market Analysis</h2>
                            <p className="text-blue-600">
                                Showing analytics for{' '}
                                <span className="font-semibold">
                                    {analytics.search_params.keywords || 'all jobs'}
                                </span>
                                {analytics.search_params.location && (
                                    <> in <span className="font-semibold">{analytics.search_params.location}</span></>
                                )}
                            </p>
                        </div>
                        <a
                            href="/"
                            className="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors shadow-md"
                        >
                            Return to Search
                        </a>
                    </div>
                </div>
            )}

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                    <h3 className="text-lg font-semibold text-gray-700 mb-2">Total Jobs</h3>
                    <div className="flex items-center">
                        <div className="p-3 rounded-full bg-blue-100 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <p className="text-3xl font-bold text-blue-600">{analytics.total_jobs || 0}</p>
                            {analytics.total_results > analytics.total_jobs && (
                                <p className="text-sm text-gray-500 mt-1">
                                    from {analytics.total_results} total results
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                    <h3 className="text-lg font-semibold text-gray-700 mb-2">Average Salary</h3>
                    <div className="flex items-center">
                        <div className="p-3 rounded-full bg-green-100 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p className="text-3xl font-bold text-green-600">
                                {formatCurrency(analytics.avg_salaries?.permanent || 0)}
                            </p>
                            <p className="text-sm text-gray-500 mt-1">Permanent Positions</p>
                        </div>
                    </div>
                </div>

                <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                    <h3 className="text-lg font-semibold text-gray-700 mb-2">Skills Found</h3>
                    <div className="flex items-center">
                        <div className="p-3 rounded-full bg-purple-100 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <div>
                            <p className="text-3xl font-bold text-purple-600">
                                {Object.keys(analytics.skills || {}).length}
                            </p>
                            <p className="text-sm text-gray-500 mt-1">Unique skills identified</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Timeline Chart */}
            <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                <h2 className="text-xl font-bold text-gray-800 mb-4">Job Postings Timeline</h2>
                <div className="h-80">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={timelineData}>
                            <defs>
                                <linearGradient id="colorCount" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="#4299E1" stopOpacity={0.8}/>
                                    <stop offset="95%" stopColor="#4299E1" stopOpacity={0.1}/>
                                </linearGradient>
                                <linearGradient id="colorSalary" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="#48BB78" stopOpacity={0.8}/>
                                    <stop offset="95%" stopColor="#48BB78" stopOpacity={0.1}/>
                                </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis
                                dataKey="month"
                                angle={-45}
                                textAnchor="end"
                                height={60}
                                interval={0}
                                tick={{ fontSize: 12 }}
                            />
                            <YAxis
                                yAxisId="left"
                                tick={{ fontSize: 12 }}
                                label={{
                                    value: 'Number of Jobs',
                                    angle: -90,
                                    position: 'insideLeft',
                                    offset: -5,
                                    style: { textAnchor: 'middle', fontSize: 12 }
                                }}
                            />
                            <YAxis
                                yAxisId="right"
                                orientation="right"
                                tick={{ fontSize: 12 }}
                                tickFormatter={(value) => formatCurrency(value).replace('£', '£')}
                                label={{
                                    value: 'Average Salary',
                                    angle: 90,
                                    position: 'insideRight',
                                    offset: 5,
                                    style: { textAnchor: 'middle', fontSize: 12 }
                                }}
                            />
                            <Tooltip formatter={timelineTooltipFormatter} />
                            <Legend />
                            <Area
                                yAxisId="left"
                                type="monotone"
                                dataKey="count"
                                name="Jobs Posted"
                                stroke="#4299E1"
                                fillOpacity={1}
                                fill="url(#colorCount)"
                            />
                            <Area
                                yAxisId="right"
                                type="monotone"
                                dataKey="avgSalary"
                                name="Average Salary"
                                stroke="#48BB78"
                                fillOpacity={1}
                                fill="url(#colorSalary)"
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            </div>

            {/* Charts Grid */}
            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                {/* Skills Chart */}
                <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100 col-span-1">
                    <h2 className="text-xl font-bold text-gray-800 mb-4">Top Skills</h2>
                    <div className="h-80">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={skillsData}
                                    cx="50%"
                                    cy="50%"
                                    innerRadius={60}
                                    outerRadius={80}
                                    labelLine={false}
                                    label={CustomSkillsPieLabel}
                                    paddingAngle={1}
                                    dataKey="value"
                                >
                                    {skillsData.map((entry, index) => (
                                        <Cell key={`cell-${index}`}
                                              fill={COLORS[index % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip formatter={skillsTooltipFormatter} />
                                <Legend layout="vertical" align="right" verticalAlign="middle" />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                {/* Experience Level Chart */}
                <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100 col-span-1">
                    <h2 className="text-xl font-bold text-gray-800 mb-4">Experience Levels</h2>
                    <div className="h-80">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={experienceData}
                                    cx="50%"
                                    cy="50%"
                                    innerRadius={60}
                                    outerRadius={80}
                                    fill="#8884d8"
                                    paddingAngle={1}
                                    dataKey="value"
                                    labelLine={true}
                                    label={({name, percent}) =>
                                        `${name} (${(percent * 100).toFixed(0)}%)`}
                                >
                                    {experienceData.map((entry, index) => (
                                        <Cell key={`cell-${index}`}
                                              fill={COLORS[index % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                {/* Salary Chart */}
                <div className="bg-white p-6 rounded-lg shadow-md border border-gray-100 md:col-span-2 lg:col-span-1">
                    <div className="flex justify-between items-center mb-4">
                        <h2 className="text-xl font-bold text-gray-800">Salary Range</h2>
                        <div className="flex space-x-2">
                            <button
                                onClick={() => setSalaryView('permanent')}
                                className={`px-3 py-1 text-sm rounded ${
                                    salaryView === 'permanent'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                }`}
                            >
                                Permanent
                            </button>
                            <button
                                onClick={() => setSalaryView('contract')}
                                className={`px-3 py-1 text-sm rounded ${
                                    salaryView === 'contract'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                }`}
                            >
                                Contract
                            </button>
                        </div>
                    </div>
                    <div className="h-80">
                        {salaryData.length > 0 ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={salaryData} layout="vertical">
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                        type="number"
                                        tickFormatter={(value) =>
                                            formatCurrency(value).replace('£', '£')}
                                    />
                                    <YAxis
                                        type="category"
                                        dataKey="company"
                                        width={100}
                                        tick={{ fontSize: 12 }}
                                    />
                                    <Tooltip
                                        formatter={(value) => formatCurrency(value)}
                                        labelFormatter={(label) => `Company: ${label}`}
                                    />
                                    <Legend />
                                    <Bar dataKey="minSalary" name="Min" fill={COLORS[0]} />
                                    <Bar dataKey="maxSalary" name="Max" fill={COLORS[1]} />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="h-full flex items-center justify-center">
                                <p className="text-gray-500">No salary data available for {salaryView} positions</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default JobAnalyticsDashboard;
