<?php

namespace App\Http\Controllers;

use App\Services\ServerResourcesService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ServerResourcesController extends Controller
{
    public function index(): View
    {
        return view('server-resources');
    }

    public function snapshot(ServerResourcesService $serverResources): JsonResponse
    {
        return response()->json($serverResources->getSnapshot());
    }
}
