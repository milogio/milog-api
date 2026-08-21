<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class GateFrontendAccess
{
    /**
     * Handle an incoming frontend request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $enabled = (bool) config('milog.frontend.enabled', false);
        $this->logAccess($request, $enabled ? 'allowed' : 'blocked');

        if (! $enabled) {
            abort((int) config('milog.frontend.disabled_status', 404));
        }

        return $next($request);
    }

    /**
     * Write a structured audit entry for frontend access.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $outcome
     * @return void
     */
    protected function logAccess($request, $outcome)
    {
        $route = $request->route();
        $routeName = $route ? $route->getName() : null;
        $purpose = $this->purposeFor($request->path(), $routeName);

        Log::channel(config('milog.frontend.log_channel', config('logging.default')))
            ->info('MiLog frontend access', [
                'outcome' => $outcome,
                'purpose' => $purpose,
                'method' => $request->method(),
                'path' => $request->path(),
                'route_name' => $routeName,
                'ip' => $request->ip(),
                'user_id' => optional($request->user())->id,
                'referer' => $request->headers->get('referer'),
                'user_agent' => $request->userAgent(),
            ]);
    }

    /**
     * Resolve a human-readable purpose for the frontend route.
     *
     * @param  string  $path
     * @param  string|null  $routeName
     * @return string
     */
    protected function purposeFor($path, $routeName)
    {
        $purposes = config('milog.frontend.purposes', []);

        if ($routeName && isset($purposes[$routeName])) {
            return $purposes[$routeName];
        }

        return $purposes[$path] ?? 'legacy frontend access';
    }
}
