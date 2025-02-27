<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Job Market Analytics</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="bg-gray-100">
    <div id="app">
        <!-- Header with navigation -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-900">
                    <a href="/">Job Catcher</a>
                </h1>
                <nav class="space-x-4">
                    <a href="/" class="text-gray-600 hover:text-gray-900">Home</a>
                    <a href="/analytics" class="text-gray-600 hover:text-gray-900">Analytics</a>
                    <a href="/diagnostics/jobs" class="text-gray-600 hover:text-gray-900">Diagnostics</a>
                </nav>
            </div>
        </header>

        <!-- Flash Messages -->
        @if(session('message'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('message') }}</span>
                <button type="button" class="absolute top-0 right-0 px-4 py-3" onclick="this.parentElement.remove()">
                    <span class="sr-only">Close</span>
                    <svg class="h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        @endif

        @if(session('error'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
                <button type="button" class="absolute top-0 right-0 px-4 py-3" onclick="this.parentElement.remove()">
                    <span class="sr-only">Close</span>
                    <svg class="h-4 w-4 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        @endif

        <!-- Main Dashboard Content -->
        <div id="dashboard" data-analytics="{{ json_encode($analytics) }}"></div>

        <!-- Add this for background job processing messages -->
        <script>
            // Add JavaScript to handle custom message display
            document.addEventListener('DOMContentLoaded', function() {
                const analytics = {!! json_encode($analytics) !!};

                // Check if there's a message to display
                if (analytics.message) {
                    const messageElement = document.createElement('div');
                    messageElement.className = 'bg-blue-50 border border-blue-200 rounded-lg p-6 text-center mt-6 max-w-7xl mx-auto';
                    messageElement.innerHTML = `
                        <h2 class="text-xl font-semibold text-blue-800 mb-2">Information</h2>
                        <p class="text-blue-700">${analytics.message}</p>
                        <div class="mt-4">
                            <div class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processing
                            </div>
                        </div>
                    `;

                    const dashboardElement = document.getElementById('dashboard');
                    if (dashboardElement.children.length === 0) {
                        dashboardElement.append(messageElement);
                    }
                }

                // Auto-refresh if processing jobs
                if (analytics.message && analytics.message.includes('fetching')) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 30000); // Refresh every 30 seconds
                }
            });
        </script>

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
                        <a href="/diagnostics/jobs" class="text-gray-500 hover:text-gray-700">Diagnostics</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Debug information (only in development) -->
    @if(app()->environment('local'))
    <script>
        console.log('Dashboard data:', {!! json_encode($analytics) !!});
    </script>
    @endif
</body>
</html>
