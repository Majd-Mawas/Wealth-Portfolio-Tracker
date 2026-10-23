<?php

namespace App\Http\Middleware;

use App\Models\Portfolio;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortfolioMembership
{
    /**
     * Hide portfolio existence from authenticated non-members.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $portfolio = $request->route('portfolio');

        if (! $portfolio instanceof Portfolio || ! $request->user()?->can('view', $portfolio)) {
            throw (new ModelNotFoundException)->setModel(
                Portfolio::class,
                [$portfolio instanceof Portfolio ? $portfolio->getKey() : $portfolio],
            );
        }

        return $next($request);
    }
}
