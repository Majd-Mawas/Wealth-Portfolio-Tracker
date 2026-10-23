<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PortfolioRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\StoreMembershipRequest;
use App\Http\Requests\Membership\UpdateMembershipRequest;
use App\Http\Resources\MembershipResource;
use App\Models\Portfolio;
use App\Models\PortfolioMembership;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MembershipController extends Controller
{
    public function __construct(private readonly MembershipService $memberships) {}

    public function index(
        Request $request,
        Portfolio $portfolio,
    ): AnonymousResourceCollection {
        $this->authorize('view', $portfolio);

        return MembershipResource::collection(
            $portfolio->memberships()->with('user')->orderBy('id')->get(),
        );
    }

    public function store(
        StoreMembershipRequest $request,
        Portfolio $portfolio,
    ): JsonResponse {
        $membership = $this->memberships->add(
            $request->user(),
            $portfolio,
            $request->validated('email'),
            PortfolioRole::from($request->validated('role')),
        );

        return (new MembershipResource($membership))->response()->setStatusCode(201);
    }

    public function update(
        UpdateMembershipRequest $request,
        Portfolio $portfolio,
        PortfolioMembership $membership,
    ): MembershipResource {
        return new MembershipResource(
            $this->memberships->update(
                $request->user(),
                $portfolio,
                $membership,
                PortfolioRole::from($request->validated('role')),
            ),
        );
    }

    public function destroy(
        Request $request,
        Portfolio $portfolio,
        PortfolioMembership $membership,
    ): Response {
        $this->memberships->remove($request->user(), $portfolio, $membership);

        return response()->noContent();
    }
}
