<?php

namespace Tests\Support;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** Caller-side fault after the real controller committed, never registered by application code. */
final class LostSubmissionResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->getStatusCode() === 303) {
            throw new RuntimeException('Synthetic lost response after committed SQL result');
        }

        return $response;
    }
}
