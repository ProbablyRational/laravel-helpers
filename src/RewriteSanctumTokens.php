<?php

namespace App\Http\Middleware;

use Closure;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RewriteSanctumTokens
{
    /**
     * Handle an incoming request.
     *
     * @param  Request $request
     * @param  Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->input('api_token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->input('api_token'));
        }

        if ($request->headers->get('Authorization') && Str::startsWith($request->headers->get('Authorization'), 'Token')) {
            $token = ltrim($request->headers->get('Authorization'), 'Token ');
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        if ($request->headers->get('Authorization') && !Str::contains($request->headers->get('Authorization'), '|')) {
            $token = ltrim($request->headers->get('Authorization'), 'Bearer ');
            $tokenObj = DB::table('personal_access_tokens')->select('tokenable_id')->where('token', $token)->first();
            if(!is_null($tokenObj)) {
                // Maybe update token last used here
                Auth::onceUsingId($tokenObj->tokenable_id);
            }
        }

        return $next($request);
    }

}
