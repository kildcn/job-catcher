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
        <div id="dashboard" data-analytics="{{ json_encode($analytics) }}"></div>
    </div>

    <!-- Add this for debugging -->
    <script>
        console.log('Dashboard data:', {!! json_encode($analytics) !!});
    </script>
</body>
</html>
