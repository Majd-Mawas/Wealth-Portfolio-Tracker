<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\ListAssetsRequest;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\Portfolio;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssetController extends Controller
{
    public function __construct(private readonly AssetService $assets) {}

    public function index(ListAssetsRequest $request, Portfolio $portfolio): JsonResponse
    {
        $paginator = $this->assets->paginate($request->user(), $portfolio, $request->validated());

        return response()->json([
            'data' => AssetResource::collection(collect($paginator->items()))->resolve($request),
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
        ]);
    }

    public function store(StoreAssetRequest $request, Portfolio $portfolio): JsonResponse
    {
        $asset = $this->assets->create($request->user(), $portfolio, $request->validated());

        return (new AssetResource($asset))->response()->setStatusCode(201);
    }

    public function show(Request $request, Portfolio $portfolio, Asset $asset): AssetResource
    {
        return new AssetResource($this->assets->show($request->user(), $portfolio, $asset));
    }

    public function update(
        UpdateAssetRequest $request,
        Portfolio $portfolio,
        Asset $asset,
    ): AssetResource {
        return new AssetResource(
            $this->assets->update($request->user(), $portfolio, $asset, $request->validated()),
        );
    }

    public function destroy(
        Request $request,
        Portfolio $portfolio,
        Asset $asset,
    ): Response {
        $this->assets->archive($request->user(), $portfolio, $asset);

        return response()->noContent();
    }
}
