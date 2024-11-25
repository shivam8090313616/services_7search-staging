<?php

namespace App\Http\Controllers\AppPublisher;

use App\Http\Controllers\Controller;
use App\Models\Publisher\PubUserPayoutMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class AppPubUserPayoutModeController extends Controller
{
  public function storePayoutMode (Request $request)
  {
    $validator = Validator::make($request->all(), [
      'payout_id'       	  => 'required',
      'payout_name'   	  => 'required',
      'publisher_id' 		  => 'required',
      'pub_withdrawl_limit' => 'required',
    ]);
    
  if($request->hasFile('file')) {
    $validator = Validator::make($request->all(), [
      'qr_image' => 'image|mimes:jpeg,png,jpg|max:1024',
    ]);
  }
    
    if ($validator->fails()) {
      $return['code'] = 100;
      $return['error'] = $validator->errors();
      $return['message'] = 'Validation error!';
      return json_encode($return);
    }

    DB::table('pub_user_payout_modes')->where('publisher_id', $request->publisher_id)->where('status', 1)->update(['status' => 0]);
    $check = PubUserPayoutMode::where('publisher_id', $request->publisher_id)->where('payout_id', $request->payout_id)->first();
      if ($request->file && $request->file != 'empty') {
        $base_str      = explode(';base64,', $request->file);
        $ext           = str_replace('data:image/', '', $base_str[0]);
        $image         = base64_decode($base_str[1]);
        $imageName     = Str::random(10) . '.' . $ext;
        $directoryPath = public_path('qr_images');
        if (!File::exists($directoryPath)) {
            File::makeDirectory($directoryPath, 0755, true);
        }
        file_put_contents($directoryPath . '/' . $imageName, $image);
    } else if($request->file == 'empty'){
        $imageName = null;
    }
    if(!empty($check))
    {
      $check->payout_id 			    = $request->payout_id;
      $check->pay_account_id 		  = $request->pay_account_id;
      $check->payout_name 		    = $request->payout_name;
      if($request->file && $request->file != 'empty'){
        $check->qr_image          =   $imageName;
      }else if($request->file == 'empty'){
        $check->qr_image          =  null;
      }
      $check->pub_withdrawl_limit = $request->pub_withdrawl_limit;
      $check->status = 1;
      if($check->update()){
        $return['code'] = 200;
        $return['message'] = 'Updated Successfully!';
      }else{
        $return['code'] = 101;
        $return['message'] = 'Something went wrong!';
      }
    }else{
      $payoutmode                     = new PubUserPayoutMode;
      $payoutmode->payout_id 			= $request->payout_id;
      $payoutmode->pay_account_id 	= $request->pay_account_id;
      $payoutmode->publisher_id 		= $request->publisher_id;
      $payoutmode->payout_name 		= $request->payout_name;
      $payoutmode->pub_withdrawl_limit = $request->pub_withdrawl_limit;
      if($payoutmode->save()){
        $return['code'] = 200;
        $return['message'] = 'Added Successfully!';
      }else{
        $return['code'] = 101;
        $return['message'] = 'Something went wrong!';
      }
    }
    return json_encode($return);
  }
}
