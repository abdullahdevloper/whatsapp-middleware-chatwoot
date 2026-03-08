<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\Observability\Metrics;

class MetricsController extends Controller
{
    public function index()
    {
        return response()->json(Metrics::all(), 200);
    }
}
