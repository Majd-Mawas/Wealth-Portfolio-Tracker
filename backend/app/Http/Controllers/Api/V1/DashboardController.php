<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PortfolioDashboardResource;
use App\Models\Portfolio;
use App\Services\ValuationService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly ValuationService $valuations) {}

    public function __invoke(Request $request, Portfolio $portfolio): PortfolioDashboardResource
    {
        return new PortfolioDashboardResource(
            $this->valuations->dashboard($request->user(), $portfolio),
        );
    }
}
