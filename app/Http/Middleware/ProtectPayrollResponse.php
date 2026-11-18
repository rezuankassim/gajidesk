<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ProtectPayrollResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::encryptHistory();

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
