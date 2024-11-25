<?php



namespace App\Http\Middleware;

use Illuminate\Support\Facades\DB;
use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class UserAdvertiser

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
        $key = 'cR9i43OnLk7r9Ty44QespV2h';
        $serkey = $_SERVER['HTTP_X_API_KEY'];
        $sup = @$_SERVER['HTTP_LOG_TYPE'];
        $email = $request->email;
        $uid = ($request->user_id) ? $request->user_id : $request->uid;
        $authtoken = $request->header('Authorization');
        // **** SIGNATURE CREATION CODE START ****//
        // Retrieve the signature and timestamp from the X-Signature header
        $signatureHeader = $request->header('Signature');

        if (!$signatureHeader || strpos($signatureHeader, ':') === false) {
            return response()->json(['error' => 'Invalid signature format'], 403);
        }

        // Split the signature and timestamp
        [$clientSignature, $timestamp] = explode(':', $signatureHeader);
        $decodedTimestamp = base64_decode($timestamp);
        $timestamp = (int)$decodedTimestamp; // Convert the decoded timestamp back to an integer

        // Get the current server timestamp
        $currentTimestamp = time(); // In seconds

        // Ensure the timestamp exists and is within a 6-seconds window
        if (abs($currentTimestamp - $timestamp) > 6) {
            return response()->json(['error' => 'Request expired or invalid timestamp'], 403);
        }

        // Concatenate data with the timestamp, just like on the frontend
        $dataToSign = $authtoken . ':' . $timestamp;

        // Generate the server-side signature
        $serverSignature = hash_hmac('sha256', $dataToSign, $key);

        // Compare the signatures
        if ($clientSignature !== $serverSignature) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }
        // **** SIGNATURE CREATION CODE END ****//
        $getuid = User::where('uid', $uid)->where('password', base64_decode($authtoken))->first();

        if (!is_null($getuid) && $sup == 1) {
            @$roleStatus = DB::table("emp_clients_records")->where("client_id", $getuid->uid)->value('role_status');
        }
        if (empty($serkey)) {
            return response()->json('Api Key Empty');
        }
        if ($serkey == $key) {
            if (strlen($email)) {
                return $next($request);
            } elseif (empty($getuid)) {
                return response()->json([
                    'code' => 403,
                    'msg' => 'Your Account is not exist!'
                ]);
            } elseif ($getuid->ac_verified == 0) {
                return response()->json([
                    'code' => 403,
                    'msg' => 'Your Account is not verified!'
                ]);
            } elseif ($getuid->status == '3') {
                return response()->json([
                    'code' => 403,
                    'msg' => 'Your Account is suspended!'
                ]);
            } elseif ($getuid->status == '4') {
                return response()->json([
                    'code' => 403,
                    'msg' => 'Your Account is on hold!'
                ]);
            } elseif ($getuid->trash == 1) {
                return response()->json([
                    'code' => 403,
                    'msg' => 'Your Account is Removed!'
                ]);
            } elseif (@$roleStatus == 1) {
                return response()->json([
                    'code' => 403,
                    'msg' => 'Admin have been changed role permission!'
                ]);
            } else {
                return $next($request);
            }
        } else {
            return response()->json([
                'code' => 403,
                'msg' => 'Invalid Api key!'
            ]);
        }
    }

    public function handleOld(Request $request, Closure $next)

    {
        $key = 'cR9i43OnLk7r9Ty44QespV2h';
        $serkey = $_SERVER['HTTP_X_API_KEY'];
        $key = 'cR9i43OnLk7r9Ty44QespV2h';
        $serkey = $_SERVER['HTTP_X_API_KEY'];
        $email = $request->email;
        $uid = ($request->user_id) ? $request->user_id : $request->uid;
        // $uid = null;
        $getuid = User::where('uid', $uid)->first();
        //     $dstr = date('Ynj-G:').ltrim(date('i'),0);
        //     $key = hash('sha256','cR9i43OnLk7r9Ty44QespV2h|'.$dstr);
        //   	$serkey = $_SERVER['HTTP_X_API_KEY'];
        //     $email = $request->email;
        //     $uid = ($request->user_id) ? $request->user_id : $request->uid;
        //     $getuid = User::where('uid', $uid)->first();
        if (empty($serkey)) {
            return response()->json('Api Key Empty');
        }
        if ($serkey == $key) {
            // dd($getuid);
            if (strlen($email)) {
                return $next($request);
            } else if (empty($getuid)) {
                return response()->json([
                    'code' => 403,
                    'msg' => 'Your Account is not exist!'
                ]);
            } else if ($getuid->trash == 1) {
                return response()->json([
                    'code' => 403,
                    'msg' => 'Your Account is not exist!'
                ]);
            } else if ($getuid->ac_verified == 0) {
                return response()->json([
                    'code' => 403,
                    'msg' => 'Your Account is not verified!'
                ]);
            } elseif ($getuid->status == '3') {
                return response()->json([
                    'code' => 403,
                    'msg' => 'Your Account is suspended!'
                ]);
            } elseif ($getuid->status == '4') {
                return response()->json([
                    'code' => 403,
                    'msg' => 'Your Account is on hold!'
                ]);
            } else {
                return $next($request);
            }
        } else {
            return response()->json([
                'code' => 403,
                'msg' => 'Invalid Api key!'
            ]);
        }
    }
}
