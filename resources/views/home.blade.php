<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Job Search - Find Your Next Career</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <h1 class="text-3xl font-bold text-gray-900">
                    Find Your Next Career
                </h1>
            </div>
        </header>

        <!-- Main Content -->
        <main>
            <div id="job-search"
                 data-results="{{ json_encode($results ?? null) }}"
                 data-keywords="{{ $keywords ?? '' }}"
                 data-location="{{ $location ?? '' }}">
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <p class="text-center text-gray-500 text-sm">
                    &copy; {{ date('Y') }} Job Catcher. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</body>
</html>
