<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogHeaders
{
    public function handle(Request $request, Closure $next)
    {
        Log::debug('Request headers', [
            'X-Forwarded-For' => $request->header('X-Forwarded-For'),
            'X-Real-IP' => $request->header('X-Real-IP'),
            'Remote-Addr' => $request->server('REMOTE_ADDR'),
            'All Headers' => $request->headers->all(),
        ]);

        return $next($request);
    }
}
