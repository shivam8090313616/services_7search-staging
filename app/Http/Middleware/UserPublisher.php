<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class UserPublisher
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
        $key = '580eca75d1ffbacca33edc3278c092e9';
        $serkey = $_SERVER['HTTP_X_API_KEY'];
        $authtoken = $request->header('Authorization');
        $sup = @$_SERVER['HTTP_LOG_TYPE'];
        $getuid = User::where('password',base64_decode($authtoken))->first();
        
         if(!is_null($getuid) && $sup == 1) {
          @$roleStatus = DB::table("emp_clients_records")->where("client_id", $getuid->uid)->value('role_status');
        }

        if(empty($getuid)){
            return response()->json([
                'code' => 403,
                'msg' => 'Your Account is not exist!']);
        }
        if(empty($serkey))
        {
         return response()->json('Api Key Empty');
        }
        if($serkey == $key && $getuid)
        {
            
            if($getuid->status == 3){
                return response()->json([
                'code' => 403,
                'msg' => 'Your Account is suspended!'
            ]);
            } elseif($getuid->status == 4){
                return response()->json([
                'code' => 403,
                'msg' => 'Your Account is on hold!'
            ]);
            } elseif($getuid->status == 2){
                return response()->json([
                'code' => 403,
                'msg' => 'Your Account is pending!'
            ]);
            } elseif($getuid->trash == 1){
                return response()->json([
                'code' => 403,
                'msg' => 'Your Account is Removed!'
            ]);
            } elseif(@$roleStatus == 1){
                return response()->json([
                'code' => 403,
                'msg' => 'Admin have been changed role permission!'
            ]);
            } else{
                return $next($request);
            }
          
        } else {
            return response()->json('Invalid Api key');
        }
        
    }
    
    public function handleOld(Request $request, Closure $next)
    {
        $key = '580eca75d1ffbacca33edc3278c092e9';
        $serkey = $_SERVER['HTTP_X_API_KEY'];
        if(empty($serkey))
        {
         return response()->json('Api Key Empty');
        }
        if($serkey == $key)
        {
            return $next($request);
          
        } else {
            return response()->json('Invalid Api key');
        }
        
    }
}
