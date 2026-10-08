<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LINE's in-app browser keeps its own cookies, but SSO providers (ThaiD in
 * particular) return to the device's default browser, so the callback cannot
 * see the session that holds the OAuth state. Before a login starts inside
 * LINE, reload the page with openExternalBrowser=1, which LINE honours by
 * opening it in Safari or Chrome so the whole flow stays in one browser.
 */
class OpenLineLinksInExternalBrowser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')
            && ! $request->query->has('openExternalBrowser')
            && preg_match('/\bLine\/\d/i', (string) $request->userAgent())) {
            return redirect()->to($request->fullUrlWithQuery(['openExternalBrowser' => 1]));
        }

        return $next($request);
    }
}
