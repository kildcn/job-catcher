<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
Route::get('/api/test-jobs', [JobController::class, 'testSearch']);
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
Route::get('/diagnostics/jobs', [App\Http\Controllers\DiagnosticsController::class, 'jobs']);

// Clear analytics cache
Route::get('/clear-analytics-cache', function() {
  // Simply flush the entire cache for simplicity
  // This is a development-only approach
  Cache::flush();

  return redirect()->back()->with('message', 'Cache cleared successfully');
});

Route::get('/api-debug', function(App\Services\CareerjetService $careerjetService) {
  // Get query parameters
  $keywords = request('keywords', 'developer');
  $location = request('location', 'berlin');
  $page = request('page', 1);
  $pageSize = request('pagesize', 20);

  // Fetch the API response
  $response = $careerjetService->searchJobs([
      'keywords' => $keywords,
      'location' => $location,
      'page' => $page,
      'pagesize' => $pageSize
  ]);

  // Get the jobs database count for comparison
  $dbCount = \App\Models\Job::when($keywords, function ($query) use ($keywords) {
          $keywordArray = explode(' ', $keywords);
          return $query->where(function ($q) use ($keywordArray) {
              foreach ($keywordArray as $keyword) {
                  if (strlen(trim($keyword)) > 2) {
                      $q->where(function ($inner) use ($keyword) {
                          $inner->where('title', 'like', "%{$keyword}%")
                                ->orWhere('description', 'like', "%{$keyword}%");
                      });
                  }
              }
          });
      })
      ->when($location, function ($query) use ($location) {
          return $query->where('locations', 'like', "%{$location}%");
      })
      ->count();

  // Basic debug info
  $debugInfo = [
      'query_params' => [
          'keywords' => $keywords,
          'location' => $location,
          'page' => $page,
          'pagesize' => $pageSize
      ],
      'api_total_jobs' => $response['total'] ?? 0,
      'api_total_pages' => $response['pages'] ?? 0,
      'jobs_returned_this_page' => isset($response['jobs']) ? count($response['jobs']) : 0,
      'database_matching_count' => $dbCount,
      'sample_job' => isset($response['jobs'][0]) ? [
          'title' => $response['jobs'][0]->title ?? 'N/A',
          'company' => $response['jobs'][0]->company ?? 'N/A',
          'has_salary_info' => isset($response['jobs'][0]->salary_min),
          'url_hash' => isset($response['jobs'][0]->url) ? md5($response['jobs'][0]->url) : 'N/A'
      ] : null
  ];

  // Return formatted debug info
  return response()->json($debugInfo);
});
