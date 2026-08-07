<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSiteIsAssigned
{
    public const SESSION_KEY = 'active_site_id';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isSiteRole()) {
            abort(403);
        }

        $siteId = $request->session()->get(self::SESSION_KEY);

        if (! is_int($siteId) && ! ctype_digit((string) $siteId)) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('sites.select');
        }

        $site = Site::query()->find((int) $siteId);

        if ($site === null || ! $user->canAccessSite($site)) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('sites.select');
        }

        $request->attributes->set('activeSite', $site);

        return $next($request);
    }
}
