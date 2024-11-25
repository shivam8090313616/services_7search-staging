<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

class Advertiser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {

        $origin = $request->headers->get('origin');
        $serkey = $_SERVER['HTTP_X_API_KEY']??'';
        $authtoken = base64_decode($request->header('Authorization'));
        $user_id = explode(".",$serkey);
        $uid = base64_decode($user_id[1]);
        $user = DB::table('admins')->where('id',$uid)->first();
        
        // if(empty($user_id[2])){
        //     return response()->json([
        //         'code' => 101,
        //         'msg' => 'Missing auth token or current token']);
        // }
        // if($user->api_access_token == $serkey){
        //     return response()->json([
        //         'error' => 'This API sources requests is not allowed!',
        //     ], 403);
        // }else{
        //     DB::table('admins')->where('id', $uid)->update(['api_access_token' => $serkey]);
        // }
        // $currentToken = base64_decode($user_id[2]);
        // $currentTokens = explode("+",$currentToken);
        // $condition = '';
        // foreach ($currentTokens as $key => $value) {
        //     if (isset($value[1])) {
        //         $values = explode(",",$value);
        //         $valueKey = explode(" ",$values[1]);
        //         $valuesKeys = explode(" ",$values[2]);
        //         $crtKey = base64_decode($valueKey[1]);
        //         $valKey = base64_decode($valuesKeys[2]);
               
        //         if(($crtKey == '$2y$10$geyShdgYUdMvPi8tU53Cheoq5DhYvBipzIPeJ4rfT/ibnHdjhGOZe' &&  $valKey == '$2y$10$v99ITu65nQGCBy1.KjBHxufO.FaGGWTPJQgO2ha3.XBAvlrFm9LGu')){
        //             $condition = 'true';
        //         }
        //         if(($crtKey != '$2y$10$lrgWhbQUJg1QHW3q2OfmI.ZmnFhoRYn4r0BttXfhZgUeVePdoHj8m' && $valKey == '$2y$10$aHmmOlO1VMNbdVsvFFZPa.R.mxUnhGAO/NUGmDDD9k7Y58YYZ/8Z.')){
        //             return response()->json([
        //                 'code'=>105,
        //                 'msg'=> 'Admin have been changed role permission!'
        //             ]);
        //         }
        //     } 
        // }
        if(empty($uid)){
            return response()->json([
                'code' => 105,
                'msg' => 'Invalid user']);
        }
        $emprolestatus = DB::table("admins")->where('emp_id',$user->emp_id)->where('login_permission',1)->where('user_type',2)->value('login_permission');
        //if($origin == 'https://crm.7searchppc.in' && $condition){
        if($emprolestatus == 1){
            return response()->json([
                'code'=>105,
                'msg'=> 'Admin have been changed role permission!'
                ]);
        } elseif(Hash::check($authtoken,$user->password)){
                return $next($request);
        } else{
            return response()->json([
                'code' => 105,
                'msg' => 'Api Key Empty'
                ]);
        }
        // }else {
        //     return response()->json([
        //         'error' => 'This API sources request is not allowed!',
        //     ], 403);
        // }
    }
}
