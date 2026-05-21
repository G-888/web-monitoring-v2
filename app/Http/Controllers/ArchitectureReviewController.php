<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\ArchitectureOnboardingReviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArchitectureReviewController extends Controller
{
    public function show(Request $request, Application $application, ArchitectureOnboardingReviewService $review): View
    {
        $application = $this->resolveApplication($request, $application);

        return view('clients.architecture-review', [
            'review' => $review->build($application),
        ]);
    }

    private function resolveApplication(Request $request, Application $application): Application
    {
        if ($application->exists) {
            return $application;
        }

        return Application::findOrFail($request->route('application'));
    }
}
