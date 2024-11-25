<?php

namespace App\Http\Middleware;

use Closure;

class CheckDomainAccess
{
    public function handle($request, Closure $next)
    {
        $headers = $request->headers->all();
        $customHeader = $request->header('X-Custom-Header');
        $clientIp = $request->header('X-Forwarded-For');
        $referrerPolicy = $request->header('Referrer-Policy');
        $act = $request->headers->get('access-control-allow-origin') ?? null;
        $blockedTools = ['Postman','insomnia','soapUI','jMeter','restAssured','katalon Studio','apache HttpClient','paw','swagger UI','Hoppscotch','cURL','postwoman','fiddler','aPI Fortress','testRail','Proxyscotch'];
       // return $referrerPolicy .'=========='.$clientIp;
        $userAgent = $request->header('User-Agent');
        foreach ($blockedTools as $tool) {
            if (stripos($userAgent, $tool) !== false &&  $customHeader) {
                return response()->json(['error' => 'This API sources request is not allowed!'], 403);
            }
        }
        $act = $request->headers->get('access-control-allow-origin') ?? null;
        if($act == null && $act != true){
            return response()->json(['error' => 'This API sources request is not allowed!'], 403);
        }
        $allowedDomains = ['https://crm.7searchppc.in','https://advertiser.7searchppc.in','https://publisher.7searchppc.in','http://localhost:3000'];
        $origin = $request->headers->get('Origin');
        if (!in_array($origin, $allowedDomains)) {
            return response()->json(['error' => 'This API sources request is not allowed!'], 403);
        }
        return $next($request);
    }
}
