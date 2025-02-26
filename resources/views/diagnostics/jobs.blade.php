<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Job Catcher Diagnostics</title>
    @viteReactRefresh
    @vite(['resources/css/app.css'])
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <h1 class="text-3xl font-bold mb-6">Job Catcher Diagnostics</h1>

        <div class="mb-6">
            <h2 class="text-xl font-semibold mb-3">Search Parameters</h2>
            <div class="bg-white p-4 rounded shadow">
                <form action="{{ url('/diagnostics/jobs') }}" method="get" class="flex flex-col md:flex-row gap-4 items-end">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Keywords</label>
                        <input type="text" name="keywords" value="{{ $keywords }}" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" name="location" value="{{ $location }}" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <div>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">
                            Check Stats
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Database Stats -->
            <div class="bg-white p-4 rounded shadow">
                <h2 class="text-xl font-semibold mb-3">Database Stats</h2>
                <dl class="divide-y">
                    <div class="py-3 flex justify-between">
                        <dt class="text-gray-600">Matching Jobs Count</dt>
                        <dd class="font-semibold">{{ $diagnostics['database_stats']['matching_jobs_count'] }}</dd>
                    </div>
                    <div class="py-3 flex justify-between">
                        <dt class="text-gray-600">Page Count</dt>
                        <dd class="font-semibold">{{ $diagnostics['database_stats']['page_count'] }}</dd>
                    </div>
                    <div class="py-3 flex justify-between">
                        <dt class="text-gray-600">Current Page</dt>
                        <dd class="font-semibold">{{ $diagnostics['database_stats']['current_page'] }}</dd>
                    </div>
                    <div class="py-3 flex justify-between">
                        <dt class="text-gray-600">Per Page</dt>
                        <dd class="font-semibold">{{ $diagnostics['database_stats']['per_page'] }}</dd>
                    </div>
                </dl>
            </div>

            <!-- API Stats -->
            <div class="bg-white p-4 rounded shadow">
                <h2 class="text-xl font-semibold mb-3">API Stats</h2>
                <dl class="divide-y">
                    <div class="py-3 flex justify-between">
                        <dt class="text-gray-600">Total Jobs</dt>
                        <dd class="font-semibold">{{ $diagnostics['api_stats']['total_jobs'] }}</dd>
                    </div>
                    <div class="py-3 flex justify-between">
                        <dt class="text-gray-600">Total Pages</dt>
                        <dd class="font-semibold">{{ $diagnostics['api_stats']['total_pages'] }}</dd>
                    </div>
                    <div class="py-3 flex justify-between">
                        <dt class="text-gray-600">Jobs This Page</dt>
                        <dd class="font-semibold">{{ $diagnostics['api_stats']['jobs_this_page'] }}</dd>
                    </div>
                    <div class="py-3 flex justify-between">
                        <dt class="text-gray-600">Gap (API vs DB)</dt>
                        <dd class="font-semibold {{ $diagnostics['api_stats']['total_jobs'] > $diagnostics['database_stats']['matching_jobs_count'] ? 'text-red-600' : 'text-green-600' }}">
                            {{ $diagnostics['api_stats']['total_jobs'] - $diagnostics['database_stats']['matching_jobs_count'] }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Sample Jobs -->
        <div class="bg-white p-4 rounded shadow mb-8">
            <h2 class="text-xl font-semibold mb-3">Sample Jobs (First 5)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Has Salary</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($diagnostics['sample_jobs'] as $job)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job['id'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job['title'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job['company'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job['location'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job['date'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($job['has_salary'])
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Yes</span>
                                @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">No</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Test Fetch Jobs -->
        <div class="bg-white p-4 rounded shadow mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Test API Job Fetching</h2>
                <form action="{{ url('/diagnostics/jobs') }}" method="get">
                    <input type="hidden" name="keywords" value="{{ $keywords }}">
                    <input type="hidden" name="location" value="{{ $location }}">
                    <input type="hidden" name="fetch" value="1">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded">
                        Run Test Fetch (1 page)
                    </button>
                </form>
            </div>

            @if($diagnostics['fetch_test_results'])
                <div class="mb-4">
                    <h3 class="font-medium text-lg mb-2">Summary</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-3 rounded">
                            <dt class="text-sm text-gray-500">Pages Attempted</dt>
                            <dd class="mt-1 text-xl font-semibold">{{ $diagnostics['fetch_test_results']['pages_attempted'] }}</dd>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <dt class="text-sm text-gray-500">Pages Succeeded</dt>
                            <dd class="mt-1 text-xl font-semibold {{ $diagnostics['fetch_test_results']['pages_succeeded'] == $diagnostics['fetch_test_results']['pages_attempted'] ? 'text-green-600' : 'text-red-600' }}">
                                {{ $diagnostics['fetch_test_results']['pages_succeeded'] }}/{{ $diagnostics['fetch_test_results']['pages_attempted'] }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <dt class="text-sm text-gray-500">Jobs Fetched</dt>
                            <dd class="mt-1 text-xl font-semibold">{{ $diagnostics['fetch_test_results']['jobs_fetched'] }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="mb-4">
                    <h3 class="font-medium text-lg mb-2">Timing</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-3 rounded">
                            <dt class="text-sm text-gray-500">Started</dt>
                            <dd class="mt-1">{{ $diagnostics['fetch_test_results']['time_started'] }}</dd>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <dt class="text-sm text-gray-500">Ended</dt>
                            <dd class="mt-1">{{ $diagnostics['fetch_test_results']['time_ended'] }}</dd>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="font-medium text-lg mb-2">Database Changes</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-3 rounded">
                            <dt class="text-sm text-gray-500">DB Count Before</dt>
                            <dd class="mt-1 text-xl font-semibold">{{ $diagnostics['fetch_test_results']['db_count_before'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <dt class="text-sm text-gray-500">DB Count After</dt>
                            <dd class="mt-1 text-xl font-semibold">{{ $diagnostics['fetch_test_results']['total_jobs_after'] }}</dd>
                        </div>
                        <div class="bg-gray-50 p-3 rounded">
                            <dt class="text-sm text-gray-500">Jobs Added</dt>
                            <dd class="mt-1 text-xl font-semibold {{ ($diagnostics['fetch_test_results']['db_count_difference'] ?? 0) > 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $diagnostics['fetch_test_results']['db_count_difference'] ?? 'N/A' }}
                            </dd>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="font-medium text-lg mb-2">Per-Page Details</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Page #</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jobs Returned</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Taken</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Success</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($diagnostics['fetch_test_results']['details'] as $detail)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $detail['page'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $detail['jobs_returned'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $detail['time_taken'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($detail['success'])
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Yes</span>
                                        @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">No</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="text-center py-8 text-gray-600">
                    Use the "Run Test Fetch" button to test API job fetching (will try to fetch 1 page of jobs)
                </div>
            @endif
        </div>

        <div class="flex justify-between">
            <a href="{{ url('/') }}" class="px-4 py-2 bg-gray-600 text-white rounded">Back to Home</a>
            <a href="{{ url('/analytics?keywords='.$keywords.'&location='.$location.'&refresh=1') }}" class="px-4 py-2 bg-blue-600 text-white rounded">View Analytics</a>
        </div>
    </div>
</body>
</html>
