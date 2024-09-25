<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function testIp(Request $request)
    {
        return response()->json([
            'ip' => $request->ip(),
            'xForwardedFor' => $request->header('X-Forwarded-For'),
            'xRealIp' => $request->header('X-Real-IP'),
            'remoteAddr' => $request->server('REMOTE_ADDR'),
        ]);
    }
}
