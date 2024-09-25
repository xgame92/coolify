<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\LogHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogHeadersTest extends TestCase
{
    public function test_it_logs_expected_headers()
    {
        Log::shouldReceive('debug')
            ->once()
            ->with('Request headers', \Mockery::on(function ($argument) {
                return isset($argument['X-Forwarded-For']) &&
                       isset($argument['X-Real-IP']) &&
                       isset($argument['Remote-Addr']) &&
                       isset($argument['All Headers']);
            }));

        $request = Request::create('/', 'GET');
        $middleware = new LogHeaders();
        $middleware->handle($request, function () {});
    }

    public function test_it_passes_request_to_next_middleware()
    {
        $request = Request::create('/', 'GET');
        $middleware = new LogHeaders();

        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response('OK');
        });

        $this->assertTrue($called);
        $this->assertEquals('OK', $response->getContent());
    }
}
