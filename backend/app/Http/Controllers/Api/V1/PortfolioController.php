<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portfolio\StorePortfolioRequest;
use App\Http\Requests\Portfolio\UpdatePortfolioRequest;
use App\Http\Resources\PortfolioResource;
use App\Models\Portfolio;
use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PortfolioController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolios) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Portfolio::class);

        $paginator = $request->user()
            ->portfolios()
            ->orderBy('portfolios.id')
            ->cursorPaginate(15);

        return response()->json([
            'data' => PortfolioResource::collection(collect($paginator->items()))->resolve($request),
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
        ]);
    }

    public function store(StorePortfolioRequest $request): JsonResponse
    {
        $portfolio = $this->portfolios->create($request->user(), $request->validated());

        return (new PortfolioResource($portfolio))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Portfolio $portfolio): PortfolioResource
    {
        $this->authorize('view', $portfolio);

        return new PortfolioResource($portfolio);
    }

    public function update(
        UpdatePortfolioRequest $request,
        Portfolio $portfolio,
    ): PortfolioResource {
        return new PortfolioResource(
            $this->portfolios->update($request->user(), $portfolio, $request->validated()),
        );
    }

    public function destroy(Request $request, Portfolio $portfolio): Response
    {
        $this->portfolios->delete($request->user(), $portfolio);

        return response()->noContent();
    }
}
