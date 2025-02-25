<?php

namespace App\Http\Controllers;

use App\Services\CareerjetService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    private $careerjetService;

    public function __construct(CareerjetService $careerjetService)
    {
        $this->careerjetService = $careerjetService;
    }

    public function index(Request $request)
    {
        $keywords = $request->get('keywords', '');
        $location = $request->get('location', '');
        $salaryMin = $request->get('salary_min');
        $salaryMax = $request->get('salary_max');
        $contractType = $request->get('contract_type');
        $page = $request->get('page', 1);

        $results = null;
        if ($keywords || $location) {
            $results = $this->careerjetService->searchJobs([
                'keywords' => $keywords,
                'location' => $location,
                'page' => $page
            ]);
        }

        return view('home', compact('results', 'keywords', 'location'));
    }
}
