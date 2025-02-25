<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Job Catcher - Find Your Next Career</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-900">
                    Job Catcher
                </h1>
                <p class="text-gray-600">Find Your Next Career</p>
            </div>
        </header>

        <!-- Error Display -->
        @if(isset($error) && $error)
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Error:</strong>
                <span class="block sm:inline">{{ $error }}</span>
            </div>
        </div>
        @endif

        <!-- Main Content -->
        <main>
            <div id="job-search"
                 data-results="{{ json_encode($results ?? null) }}"
                 data-keywords="{{ $keywords ?? '' }}"
                 data-location="{{ $location ?? '' }}">
                <!-- React component will mount here -->
                <!-- Add loading spinner in case React is slow to load -->
                <div class="py-12 flex items-center justify-center">
                    <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-gray-600">Loading...</span>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex justify-between items-center">
                    <p class="text-gray-500 text-sm">
                        &copy; {{ date('Y') }} Job Catcher. All rights reserved.
                    </p>
                    <div class="flex space-x-4">
                        <a href="/" class="text-gray-500 hover:text-gray-700">Home</a>
                        <a href="/analytics" class="text-gray-500 hover:text-gray-700">Analytics</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
