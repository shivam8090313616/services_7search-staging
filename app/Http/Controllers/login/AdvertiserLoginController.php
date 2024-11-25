<?php

namespace App\Http\Controllers\login;
use Illuminate\Support\Str;

use App\Http\Controllers\Controller;
use App\Models\RoleManagement;
use App\Models\Admin;
use App\Models\Publisher\AdminLoginLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


class AdvertiserLoginController extends Controller
{
    public function login(Request $request)
    {
        
        $token = Str::random(60);
      	$ipadr = $_SERVER['REMOTE_ADDR'];
        $apitoken = hash('sha256', $token);
        $validator = Validator::make($request->all(),
         [
             'username' => 'required',
             'password' => 'required',
             'otp' => $request->otp ? ['numeric','digits:6'] : ''
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
        //  $otp = base64_encode($request->otp);
         $otp = "123123";
         $mytime = Carbon::now();
         $otpexpTime = $users->updated_at->addMinute(15); // otp has been expired after 15 minutes.   
         $otptime = $mytime->lessThanOrEqualTo($otpexpTime) ? 1 : 0;   
         $sentmail = $request->sentmail;
        //  if (Hash::check($password, $users->password) && (!$otp || $otp == $users->otp && $otptime ==1)) 
         if (Hash::check($password, $users->password)) 
         {
           Admin::where('emp_id',$users->emp_id)->where('login_permission',1)->where('user_type',2)->update(['login_permission'=>0]); // update role status
           if ($users->id) 
           { 
                $accessRole =RoleManagement::select('id','role_name','role_permission')->where('id',$users->role_id)->first();
                $users->remember_token= $apitoken.'.'.$users->id;
                if(($users->user_type == 1 && $otp == $users->otp) || $users->user_type == 2){
                  $users->last_login = $mytime; 
                }
                if($users->user_type == 1 && $sentmail == 1){
                  $genOtp =  rand(100000, 999999);
                  $users->otp = base64_encode($genOtp);
                  /* Admin Section Mail */
                  $data['details'] = [
                    'subject' => 'Admin Verification OTP - 7Search PPC ',
                    'otp' => $genOtp,
                    'uid' => $users->id,
                    'username' => $users->username,
                    'email' => $users->email,
                    'message'=>'admin/dashboard'
                  ];
                  $adminmail1 = 'deepaklogelite@gmail.com';
                  $adminmail2 = 'rajeevgp1596@gmail.com';
                  $bodyadmin = View('emailtemp.paymentVerificationMail', $data);
                  $subjectadmin = 'Admin Verification OTP - 7Search PPC';
                  // sendmailAdmin($subjectadmin, $bodyadmin, $adminmail1, $adminmail2);
                }
                if($users->save())
                {
                    if(($otp > 0 && $otp == $users->otp) || $users->user_type == 2){
                      $adminLog = new AdminLoginLog;
                      $adminLog->admin_id = $users->id;
                      $adminLog->username = $users->username;
                      $adminLog->email = $users->email;
                      $adminLog->ip_addrs = $ipadr;
                      $adminLog->auth_token = $apitoken;
                      $adminLog->created_at = $mytime;
                      $adminLog->save();
                    }
                    $return['code'] = 200;
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
                    return response()->json($return);
                }            
               }
              } elseif($otp && $otp != $users->otp && $users->user_type ==1){
                $return['code'] = 102;
                $return['msg'] = 'Invalid OTP Code!';
              } elseif($otp && $otp == $users->otp && $otptime ==0 && $users->user_type ==1){
                $return['code'] = 102;
                $return['msg'] = 'OTP has been expired!';
              } else{
                $return['code'] = 101;
                $return['msg'] = 'Username is invalid  Incorrect!';
                }
          return response()->json($return);
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

