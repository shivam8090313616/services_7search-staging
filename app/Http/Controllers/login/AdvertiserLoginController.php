<?php

namespace App\Http\Controllers\login;
use Illuminate\Support\Str;

use App\Http\Controllers\Controller;
use App\Models\RoleManagement;
use App\Models\EmpClientsRecord;
use App\Models\Admin;
use App\Models\Publisher\AdminLoginLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


class AdvertiserLoginController extends Controller
{
    public function login(Request $request)
    {
      // $ContentLength = (int) $request->header('Content-Length');
      // $userAgent = $request->header('User-Agent', null);
      // if (strpos($userAgent, 'Postman') !== false || strpos($userAgent, 'insomnia') !== false || $userAgent === null) {
      //   if($ContentLength > 0){
      //     return response()->json([
      //       'error' => 'This API source request is not allowed!'
      //   ], 403);
      //   }
      // }
      $ContentLength = (int) $request->header('Content-Length');
      $userAgent = $request->header('User-Agent', null);
      $blockedTools = ['Postman','Insomnia','SoapUI','JMeter','RestAssured','Katalon Studio','Apache HttpClient','Paw','Swagger UI','Hoppscotch','cURL','Postwoman','Fiddler','API Fortress','TestRail'];
      $blocked = false;
      foreach ($blockedTools as $tool) {
          if (strpos($userAgent,  $tool) !== false || $userAgent === null) {
              $blocked = true;
              break;
          }
      }
      if ($blocked && $ContentLength > 0) {
          return response()->json([
              'error' => 'This API source request is not allowed!'
          ], 403);
      }
        $token = Str::random(60);
      	$ipadr = $_SERVER['REMOTE_ADDR'];
        $apitoken = hash('sha256', $token);
        $validator = Validator::make($request->all(),
         [
             'username' => 'required',
             'password' => 'required'
         ]);
         if($validator->fails())
         {
             $return ['code']    = 100;
             $return ['error']   = $validator->errors();
             $return ['message'] = 'Validation Error!';
             return json_encode($return, JSON_NUMERIC_CHECK);
         }
         $username = $request->input('username');
         $users = Admin::where('username', $username)->where('status',1)->first();
         if (empty($users)) 
         {
            $return['code'] = 101;
            $return['msg'] = 'User id is invalid or not registered!';
            return response()->json($return);
         }
         $password = $request->input('password');
         $mytime = Carbon::now();   
         if (Hash::check($password, $users->password)) 
         {
           EmpClientsRecord::where('emp_id',$users->emp_id)->where('role_status',1)->update(['role_status'=>0]); // update role status
           if ($users->id) 
           { 
                $accessRole =RoleManagement::select('id','role_name','role_permission')->where('id',$users->role_id)->first();
                $users->remember_token= $apitoken.'.'.$users->id;
                $users->last_login = $mytime; 
                $otp =  rand(100000, 999999);
                $data['details'] = array(
                  'subject' => 'Admin Verification OTP - 7Search PPC ',
                  'otp' => $otp,
                  'uid' => $users->id,
                  'username' => $users->username,
                  'email' => $users->email
              );
                if($users->save())
                {
                  	$adminLog = new AdminLoginLog;
                  	$adminLog->admin_id = $users->id;
                  	$adminLog->username = $users->username;
                    $adminLog->email = $users->email;
                    $adminLog->ip_addrs = $ipadr;
                    $adminLog->auth_token = $apitoken;
                  	$adminLog->created_at = $mytime;
                  	$adminLog->save();
                    $return['code'] = 200;
                    $return['otp'] = $otp;
                    $return['msg'] = 'Login Successfully.';
                    // $return['token'] = $apitoken.'.'.$users->id;
                    $return['token'] = $apitoken.'.'.base64_encode($users->id);
                    $return['acesspass'] = base64_encode($password);
                    $return['accessRole'] = $accessRole;
                    $return['name'] = $users->name;
                    $return['username'] = $users->username;
                    $return['email'] =  $users->email;
                    $return['emp_id'] =  $users->emp_id;
                    $return['utype'] =  $users->user_type;
                    $return['tokens'] = base64_encode($token.'-'. base64_encode(123456) .'-'.$token);
                     /* Admin Section  */
                    // $adminmail1 = 'advertisersupport@7searchppc.com';
                    // $adminmail2 = 'info@7searchppc.com';
                    $adminmail1 = 'ry0085840@gmail.com';
                    $adminmail2 = 'rjshkumaryadav3@gmail.com';
                    $bodyadmin = View('emailtemp.otpverify', $data);
                    $subjectadmin = 'Admin Verification OTP - 7Search PPC';
                    //sendmailAdmin($subjectadmin, $bodyadmin, $adminmail1, $adminmail2);
                    return response()->json($return);
                }            
            }
         }else{
             $return['code'] = 101;
             $return['msg'] = 'Username is invalid  Incorrect!';
             return response()->json($return);
         }
    }

  	public function tokenUpdate (Request $request)
    {
      $validator = Validator::make($request->all(),
                                   [
                                     'access_token' => 'required',
                                     'noti_token' => 'required',
                                   ]);
      if($validator->fails())
      {
        $return ['code']    = 100;
        $return ['error']   = $validator->errors();
        $return ['message'] = 'Validation Error!';
        return json_encode($return, JSON_NUMERIC_CHECK);
      }
      $adminlog = AdminLoginLog::where('auth_token', $request->access_token)->first();
      if(!empty($adminlog))
      {
        $adminlog->noti_token = $request->noti_token;
        if($adminlog->save())
        {
           $return ['code']    = 200;
           $return ['message'] = 'Noti token updated successfully';
        }
        else
        {
          $return ['code']    = 101;
          $return ['message'] = 'Something went wrong!';
        }
      }
      else
      {
        $return ['code']    = 101;
        $return ['message'] = 'Something went wrong!';
      }
      	return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function change_password(Request $request)
    {
         $validator = Validator::make($request->all(),
         [
             'userid' => 'required',
             'current_password' => 'required',
             'new_password' => 'required',
             'confirm_password' => 'required',
         ]);
         if($validator->fails())
         {
             $return ['code']    = 100;
             $return ['error']   = $validator->errors();
             $return ['message'] = 'Validation Error!';
             return json_encode($return, JSON_NUMERIC_CHECK);
         }
        $userid = $request->input('userid');
        $uid = explode(".",$userid);
        $uidn = base64_decode($uid[1]);
        $users = Admin::where('id', $uidn)->first();
         if (empty($users)) 
         {
             $return['code'] = 101;
             $return['msg'] = 'User id is invalid or not registered!';
             return response()->json($return);
         }
         $password = $request->input('current_password');
         $npassword = $request->input('new_password');
         $compassword = $request->input('confirm_password');
         if($npassword == $compassword)
         {
            if (Hash::check($password, $users->password)) 
              {
                $newpass = Hash::make($npassword);
                $users->password= $newpass;
                if($users->save())
                {
                    $return ['code']    = 200;
                    $return ['message'] = 'Password Chanage Successfully';
                }
                else
                {
                    $return ['code']    = 103;
                    $return ['message'] = 'Not Match Password';
                }
              } 
              else
              {
                 $return ['code']    = 103;
                 $return ['message'] = 'Not Match Password';
              }
         } 
         else 
         {
            $return ['code']    = 102;
            $return ['message'] = 'Not Match New Password & Confirm Password';
         }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }



    public function mobileLogin(Request $request)
    {
        $token = Str::random(60);
        $apitoken = hash('sha256', $token);
        $validator = Validator::make($request->all(),
         [
             'username' => 'required',
             'password' => 'required'
         ]);
         if($validator->fails())
         {
             $return ['code']    = 100;
             $return ['error']   = $validator->errors();
             $return ['message'] = 'Validation Error!';
             return json_encode($return);
         }

         $username = ($request->input('username'));
         $users = User::where('user_name', $username)->first();
         if (empty($users)) 
         {
            $return['code'] = 101;
            $return['msg'] = 'User id is invalid or not registered!';
            return response()->json($return);
         }
         $password = $request->input('password');
         $mytime = Carbon::now();     
       if (Hash::check($password, $users->password)) 
       { 
            $rendumnumber = rand(111111,999999);
            $rendHash = Hash::make($rendumnumber);
            $userslog = User::where('user_name', $username)->first();
            $userslog->login_token = $rendHash;
           if ($userslog->id) 
           {    
                $userslog->remember_token= $apitoken;
                $userslog->last_login = $mytime; 
                if($userslog->save())
                {
                    $return['code'] = 200;
                    $return['msg'] = 'Login Successfully.';
                    //$return['token'] = $apitoken;
                    $return['name'] =  $users->first_name .' '. $users->last_name;
                    $return['uid'] =  $users->uid;
                    $return['login_token'] =  $rendumnumber;
                    return response()->json($return);
                }            
           }
         }
         else
         {
             $return['code'] = 101;
             $return['msg'] = 'Username is invalid  Incorrect!';
             return response()->json($return);
         }
    }
}

