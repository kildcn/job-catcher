import React, { useState } from 'react';
import {
    LineChart, Line, BarChart, Bar, PieChart, Pie, XAxis, YAxis,
    CartesianGrid, Tooltip, Legend, ResponsiveContainer, Cell
} from 'recharts';

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#FF99E6'];

const JobAnalyticsDashboard = ({ analytics }) => {
    const [salaryView, setSalaryView] = useState('permanent');

    const formatCurrency = (value) => {
        return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
            maximumFractionDigits: 0
        }).format(value);
    };

    const formatSalaryData = () => {
        const data = analytics.salary_ranges[salaryView]
            .filter(job => job.min && job.max)
            .map(job => ({
                company: job.company,
                minSalary: job.min,
                maxSalary: job.max
            }));
        return data.sort((a, b) => b.maxSalary - a.maxSalary).slice(0, 10);
    };

    const formatSkillsData = () => Object.entries(analytics.skills)
        .map(([name, count]) => ({ name, value: count }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 8);

    const formatExperienceData = () => Object.entries(analytics.experience_levels)
        .map(([name, count]) => ({
            name: name.charAt(0).toUpperCase() + name.slice(1),
            value: count
        }))
        .filter(item => item.value > 0);

    const timelineTooltipFormatter = (value, name) => {
        if (name === 'count') {
            return [`${value} jobs`, 'Jobs Posted'];
        } else if (name === 'avgSalary') {
            return [formatCurrency(value), 'Average Salary'];
        }
        return [value, name];
    };

    return (
        <div className="p-6 max-w-7xl mx-auto space-y-6">
            {/* Error Message */}
            {analytics.error && (
                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p className="text-yellow-800">{analytics.error}</p>
                </div>
            )}

            {/* Search Context with Return Button */}
            {analytics.search_params && (
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div className="flex justify-between items-start">
                        <div>
                            <h2 className="text-lg font-semibold text-blue-800 mb-2">Market Analysis</h2>
                            <p className="text-blue-600">
                                Showing results for{' '}
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
                            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                        >
                            Return to Search
                        </a>
                    </div>
                </div>
            )}

            {/* Summary Cards */}
            {/* Summary Cards */}
<div className="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div className="bg-white p-4 rounded-lg shadow">
        <h3 className="text-lg font-semibold mb-2">Total Jobs</h3>
        <p className="text-3xl font-bold text-blue-600">{analytics.total_jobs}</p>
        {analytics.total_results > analytics.total_jobs && (
            <p className="text-sm text-gray-500 mt-1">
                from {analytics.total_results} total results
            </p>
        )}
    </div>
    <div className="bg-white p-4 rounded-lg shadow">
        <h3 className="text-lg font-semibold mb-2">Average Salary</h3>
        <p className="text-3xl font-bold text-green-600">
            {formatCurrency(analytics.avg_salaries?.permanent || 0)}
        </p>
        <p className="text-sm text-gray-500 mt-1">Permanent Positions</p>
    </div>
    <div className="bg-white p-4 rounded-lg shadow">
        <h3 className="text-lg font-semibold mb-2">Skills Found</h3>
        <p className="text-3xl font-bold text-purple-600">
            {Object.keys(analytics.skills).length}
        </p>
        <p className="text-sm text-gray-500 mt-1">Unique skills identified</p>
    </div>
</div>

            {/* Timeline Chart */}
            <div className="bg-white p-4 rounded-lg shadow">
                <h2 className="text-xl font-semibold mb-4">Job Postings Timeline</h2>
                <div className="h-80">
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={analytics.timeline_data}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis
                                dataKey="month"
                                angle={-45}
                                textAnchor="end"
                                height={60}
                                interval={0}
                            />
                            <YAxis
                                yAxisId="left"
                                label={{
                                    value: 'Number of Jobs',
                                    angle: -90,
                                    position: 'insideLeft',
                                    offset: -5
                                }}
                            />
                            <YAxis
                                yAxisId="right"
                                orientation="right"
                                tickFormatter={(value) => formatCurrency(value)}
                                label={{
                                    value: 'Average Salary',
                                    angle: 90,
                                    position: 'insideRight',
                                    offset: -5
                                }}
                            />
                            <Tooltip formatter={timelineTooltipFormatter} />
                            <Legend />
                            <Line
                                yAxisId="left"
                                type="monotone"
                                dataKey="count"
                                name="Jobs Posted"
                                stroke="#8884d8"
                                strokeWidth={2}
                                dot={{ r: 4 }}
                            />
                            <Line
                                yAxisId="right"
                                type="monotone"
                                dataKey="avgSalary"
                                name="Average Salary"
                                stroke="#82ca9d"
                                strokeWidth={2}
                                dot={{ r: 4 }}
                            />
                        </LineChart>
                    </ResponsiveContainer>
                </div>
            </div>

            {/* Charts Grid */}
            <div className="grid gap-4 md:grid-cols-3">
                {/* Skills Chart */}
                <div className="bg-white p-4 rounded-lg shadow">
                    <h2 className="text-xl font-semibold mb-4">Skills Distribution</h2>
                    <div className="h-80">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={formatSkillsData()}
                                    cx="50%"
                                    cy="50%"
                                    labelLine={false}
                                    label={({name, percent}) =>
                                        `${name} (${(percent * 100).toFixed(0)}%)`}
                                    outerRadius={80}
                                    dataKey="value"
                                >
                                    {formatSkillsData().map((entry, index) => (
                                        <Cell key={`cell-${index}`}
                                              fill={COLORS[index % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                {/* Experience Level Chart */}
                <div className="bg-white p-4 rounded-lg shadow">
                    <h2 className="text-xl font-semibold mb-4">Experience Levels</h2>
                    <div className="h-80">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={formatExperienceData()}
                                    cx="50%"
                                    cy="50%"
                                    labelLine={false}
                                    label={({name, percent}) =>
                                        `${name} (${(percent * 100).toFixed(0)}%)`}
                                    outerRadius={80}
                                    dataKey="value"
                                >
                                    {formatExperienceData().map((entry, index) => (
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
                <div className="bg-white p-4 rounded-lg shadow">
                    <h2 className="text-xl font-semibold mb-4">Salary Distribution</h2>
                    <div className="flex gap-2 mb-4">
                        <button
                            onClick={() => setSalaryView('permanent')}
                            className={`px-4 py-2 rounded ${
                                salaryView === 'permanent'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-700'
                            }`}
                        >
                            Permanent
                        </button>
                        <button
                            onClick={() => setSalaryView('contract')}
                            className={`px-4 py-2 rounded ${
                                salaryView === 'contract'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-700'
                            }`}
                        >
                            Contract
                        </button>
                    </div>
                    <div className="h-80">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={formatSalaryData()} layout="vertical">
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis
                                    type="number"
                                    tickFormatter={(value) =>
                                        formatCurrency(value).replace('£', '')}
                                />
                                <YAxis type="category" dataKey="company" width={100} />
                                <Tooltip
                                    formatter={(value) => formatCurrency(value)}
                                    labelFormatter={(label) => `Company: ${label}`}
                                />
                                <Legend />
                                <Bar dataKey="minSalary" name="Min" fill="#8884d8" />
                                <Bar dataKey="maxSalary" name="Max" fill="#82ca9d" />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default JobAnalyticsDashboard;
