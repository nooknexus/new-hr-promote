<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Livewire's wire:stream sends HTTP headers before the action finishes. Symfony
 * 7.3 still calls header() when sending the final response, which Laravel turns
 * into an ErrorException, so the browser never receives the component payload.
 * This returns the final payload through a response that skips header sending.
 */
class CompleteStreamedLivewireResponse
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        if (!$request->hasHeader('X-Livewire') || !$this->headersAlreadySent()) {
            return $response;
        }

        return new class((string) $response->getContent(), $response->getStatusCode()) extends Response
        {
            public function sendHeaders(?int $statusCode = null): static
            {
                return $this;
            }
        };
    }

    protected function headersAlreadySent(): bool
    {
        return headers_sent();
    }
}
