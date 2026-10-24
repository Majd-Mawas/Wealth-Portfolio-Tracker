<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\ListTransactionsRequest;
use App\Http\Requests\Transaction\ReverseTransactionRequest;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactions) {}

    public function index(ListTransactionsRequest $request, Portfolio $portfolio): JsonResponse
    {
        $paginator = $this->transactions->paginate($request->user(), $portfolio, $request->validated());

        return response()->json([
            'data' => TransactionResource::collection(collect($paginator->items()))->resolve($request),
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
        ]);
    }

    public function store(
        StoreTransactionRequest $request,
        Portfolio $portfolio,
        Asset $asset,
    ): JsonResponse {
        $transaction = $this->transactions->record(
            $request->user(),
            $portfolio,
            $asset,
            $request->validated(),
        );

        return (new TransactionResource($transaction))->response()->setStatusCode(201);
    }

    public function reverse(
        ReverseTransactionRequest $request,
        Portfolio $portfolio,
        Transaction $transaction,
    ): JsonResponse {
        $reversal = $this->transactions->reverse(
            $request->user(),
            $portfolio,
            $transaction,
            $request->validated('reason'),
        );

        return (new TransactionResource($reversal))->response()->setStatusCode(201);
    }
}
