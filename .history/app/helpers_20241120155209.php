<?php

use Carbon\Carbon;
use App\Models\User;
use App\Models\Agent;
use App\Models\Coupon;
use App\Models\Country;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\PubAdunit;
use App\Models\PubWebsite;
use App\Models\UsedCoupon;
use App\Models\Notification;
use App\Models\TransactionLog;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer\PHPMailer;
use Illuminate\Support\Facades\Redis;
// use DateTime;
// use DateTimeZone;

function randomuid($utype = '')

{
  $uid =  $utype . strtoupper(uniqid());
  $checkdata = User::where('uid', $uid)->count();
  if ($checkdata > 0) {
    randomuid($utype);
  } else {
    return $uid;
  }
}

function randomClientid($agttype = '')
{
  $characters = "0123456789";
  $random_chars = '';
  for ($i = 0; $i < 4; $i++) {
    $random_chars .= $characters[rand(0, strlen($characters) - 1)];
  }
  $agent_id = ($agttype . $random_chars);
  $checkdata = Agent::where('agent_id', $agent_id)->count();
  if ($checkdata > 0) {
    randomClientid($agttype);
  } else {
    return substr($agent_id, 0, 7);
  }
}

function listNotificationMassages($cmpid, $advcode, $dbudget)
{
  $return = '';
  $date = date('Y-m-d');
  $endDlyBudget = DB::table('camp_budget_utilize')->selectRaw('sum(amount) as cur_budget,camp_id')->where('camp_id', $cmpid)->where('advertiser_code', $advcode)->whereDate('udate', $date)->first();
  if (!empty($endDlyBudget->camp_id)) {
    if (($dbudget - 1) <= $endDlyBudget->cur_budget) {
      $specificDate = date('Y-m-d');
      $existUsers = Notification::where('uid', $cmpid)->whereDate('created_at', $specificDate)->count();
      if (empty($existUsers)) {
        $noti_title = "Your campaign's daily budget has been exhausted.";
        $noti_desc  = "Dear advertiser, your campaign's (" . $cmpid . ") daily budget is being exhausted. update the daily budget to enjoy the benefits.";
        $notification = new Notification();
        $notification->notif_id = gennotificationuniq();
        $notification->title = $noti_title;
        $notification->noti_desc = $noti_desc;
        $notification->noti_type = 1;
        $notification->noti_for = 1;
        $notification->all_users = 0;
        $notification->status = 1;
        $notification->uid = $cmpid;
        if ($notification->save()) {
          $noti = new UserNotification();
          $noti->notifuser_id = gennotificationuseruniq();
          $noti->noti_id = $notification->id;
          $noti->user_id = $advcode;
          $noti->user_type = 1;
          $noti->view = 0;
          $noti->created_at = Carbon::now();
          $noti->updated_at = now();
          $noti->save();
        }
        $return = 'Send message successfully!';
      }
    }
  }
  return $return;
}
function listNotificationMassagesFront($impData)
{
  $return = "";
  $date = date("Y-m-d");

  $user = User::select("first_name", "last_name", "email")
    ->where("uid", $impData["advertiser_code"])
    ->first();
  $camdailybudget = $impData["d_budget"];
  $totimpclickd = DB::table("camp_budget_utilize")
    ->where("camp_id", $impData["campaign_id"])
    ->where("advertiser_code", $impData["advertiser_code"])
    ->whereDate("udate", $date)
    ->sum("amount");
  $mainbalce = $camdailybudget - $totimpclickd;
  $email = $user["email"];
  $fullname = $user["first_name"] . " " . $user["last_name"];
  if (round($mainbalce, 2) <= 0.5) {
    $data["details"] = [
      "full_name" => $fullname,
      "email" => $email,
    ];
    $subject =
      "Your campaign's daily budget is about to be exhausted - 7Search PPC";
    $data["email"] = $email;
    $data["title"] = $subject;
    $body = View("emailtemp.dailybudgetexhausted", $data);
    $endBudgetMailirst = DB::table("end_budget_mail")
      ->where("uid", $impData["advertiser_code"])
      ->where("camp_id", $impData["campaign_id"])
      ->where("send_mail_first", 1)
      ->whereDate("created_at", date("Y-m-d"))
      ->first();
    if (empty($endBudgetMailirst)) {
      sendmailUser($subject, $body, $email);
      DB::table("end_budget_mail")->insert([
        "uid" => $impData["advertiser_code"],
        "camp_id" => $impData["campaign_id"],
        "send_mail_count" => 0,
        "send_mail_first" => 1,
      ]);
    }
    /* User Mail Section */
    $endBudgetMail = DB::table("end_budget_mail")
      ->where("uid", $impData["advertiser_code"])
      ->where("camp_id", $impData["campaign_id"])
      ->where("send_mail_count", 1)
      ->whereDate("created_at", date("Y-m-d"))
      ->first();
    if (!empty($endBudgetMail)) {
      sendmailUser($subject, $body, $email);
      DB::table("end_budget_mail")
        ->where([
          "uid" => $impData["advertiser_code"],
          "camp_id" => $impData["campaign_id"],
        ])
        ->whereDate("created_at", date("Y-m-d"))
        ->update(["send_mail_count" => 0]);
    }
  }

  $endDlyBudget = DB::table("camp_budget_utilize")
    ->selectRaw("sum(amount) as cur_budget,camp_id")
    ->where("camp_id", $impData["campaign_id"])
    ->where("advertiser_code", $impData["advertiser_code"])
    ->whereDate("udate", $date)
    ->first();
  if (!empty($endDlyBudget->camp_id)) {
    if ($impData["d_budget"] - 1 <= $endDlyBudget->cur_budget) {
      $specificDate = date("Y-m-d");
      $existUsers = Notification::where(
        "uid",
        $impData["advertiser_code"]
      )
        ->whereDate("created_at", $specificDate)
        ->count();
      if (empty($existUsers)) {
        $noti_title =
          "Your campaign's daily budget has been exhausted.";
        $noti_desc =
          "Dear advertiser, your campaign's (" .
          $impData["campaign_id"] .
          ") daily budget is being exhausted. update the daily budget to enjoy the benefits.";
        $notification = new Notification();
        $notification->notif_id = gennotificationuniq();
        $notification->title = $noti_title;
        $notification->noti_desc = $noti_desc;
        $notification->noti_type = 1;
        $notification->noti_for = 1;
        $notification->all_users = 0;
        $notification->status = 1;
        $notification->uid = $impData["advertiser_code"];
        if ($notification->save()) {
          $noti = new UserNotification();
          $noti->notifuser_id = gennotificationuseruniq();
          $noti->noti_id = $notification->id;
          $noti->user_id = $impData["advertiser_code"];
          $noti->user_type = 1;
          $noti->view = 0;
          $noti->created_at = Carbon::now();
          $noti->updated_at = now();
          $noti->save();
        }
        $return = "Send message successfully!";
      }
    }
  }
  return $return;
}
function gennotificationuniq()

{
  $notigen = 'NOTIF';

  $unqid =  $notigen . strtoupper(uniqid());

  $checkdata = Notification::where('notif_id', $unqid)->count();

  if ($checkdata > 0) {

    gennotificationuniq();
  } else {

    return $unqid;
  }
}



function gennotificationuseruniq()

{

  $notigen = 'NOTIFU';

  $unqid =  $notigen . strtoupper(uniqid());

  $checkdata = UserNotification::where('notifuser_id', $unqid)->count();

  if ($checkdata > 0) {

    gennotificationuseruniq();
  } else {

    return $unqid;
  }
}



function real_ip()

{

  if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {

    $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];

    $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
  }

  $client  = @$_SERVER['HTTP_CLIENT_IP'];

  $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];

  $remote  = $_SERVER['REMOTE_ADDR'];

  if (filter_var($client, FILTER_VALIDATE_IP)) {

    $ip = $client;
  } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {

    $ip = $forward;
  } else {

    $ip = $remote;
  }

  return $ip;
}





function getliveconvert($from, $to)

{

  /* API key  */

  $apikey = 'a3f649319bffc5655b3dd8b8e77bd823';

  $url = "http://apilayer.net/api/live?access_key=" . $apikey . "&currencies=" . $to . "&source=" . $from . "&format=1";

  $ch = curl_init($url);

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $json = curl_exec($ch);

  curl_close($ch);

  $api_result = json_decode($json, true);

  return $api_result;
}


function ipaddressconr($ip)

{

  $curl = curl_init();

  curl_setopt_array($curl, array(

    CURLOPT_URL => 'http://ip-api.com/json/' . $ip,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_ENCODING => '',

    CURLOPT_MAXREDIRS => 10,

    CURLOPT_TIMEOUT => 0,

    CURLOPT_FOLLOWLOCATION => true,

    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

    CURLOPT_CUSTOMREQUEST => 'GET',

  ));

  $response = curl_exec($curl);

  $getCodes =  json_decode($response);

  //   print_r($getCodes); exit;

  $getCode = $getCodes->countryCode;

  $getdeatil = Country::where('iso', $getCode)->first()->toArray();

  if ($getdeatil) {

    if ($getdeatil['currency_code'] != 'USD') {

      $custCurrency =  $getdeatil['currency_code'];

      $usdcurrency = 'USD';

      $currencyData = getliveconvert($usdcurrency, $custCurrency);

      $finalamt = (float) filter_var($currencyData['quotes']['USD' . $custCurrency], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

      $return['data'] = array('famt' => $finalamt, 'currency' => $custCurrency);
    } else {

      $custCurrencyusd = 'USD';

      $return['data'] = array('famt' => 1, 'currency' => $custCurrencyusd);
    }
  } else {

    $return['code']    = 101;

    $return['message'] = 'Something went wrong!';
  }

  return $return;
}

function ipaddressconrTaza($ip)

{

  $curl = curl_init();

  curl_setopt_array($curl, array(

    CURLOPT_URL => 'http://ip-api.com/json/' . $ip,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_ENCODING => '',

    CURLOPT_MAXREDIRS => 10,

    CURLOPT_TIMEOUT => 0,

    CURLOPT_FOLLOWLOCATION => true,

    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

    CURLOPT_CUSTOMREQUEST => 'GET',

  ));

  $response = curl_exec($curl);

  $getCodes =  json_decode($response);

  //   print_r($getCodes); exit;

  $getCode = $getCodes->countryCode;

  $getdeatil = Country::where('iso', $getCode)->first()->toArray();

  if ($getdeatil) {

    if ($getdeatil['currency_code'] != 'USD') {

      $custCurrency =  $getdeatil['currency_code'];

      $country =  $getdeatil['iso'];

      $usdcurrency = 'USD';

      $currencyData = getliveconvert($usdcurrency, $custCurrency);

      $finalamt = (float) filter_var($currencyData['quotes']['USD' . $custCurrency], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

      // $return['data'] = array('famt' => $finalamt, 'currency' => $custCurrency, 'country' => $country);
      $return['data'] = array('famt' => 1, 'currency' => 'USD', 'country' => 'US');
    } else {

      $custCurrencyusd = 'USD';

      $return['data'] = array('famt' => 1, 'currency' => $custCurrencyusd, 'country' => 'US');
    }
  } else {

    $return['code']    = 101;

    $return['message'] = 'Something went wrong!';
  }

  return $return;
}


function ipaddressconrPayu($ip)

{

  $curl = curl_init();

  curl_setopt_array($curl, array(

    CURLOPT_URL => 'http://ip-api.com/json/' . $ip,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_ENCODING => '',

    CURLOPT_MAXREDIRS => 10,

    CURLOPT_TIMEOUT => 0,

    CURLOPT_FOLLOWLOCATION => true,

    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

    CURLOPT_CUSTOMREQUEST => 'GET',

  ));

  $response = curl_exec($curl);

  $getCodes =  json_decode($response);
  // $getCode = $getCodes->countryCode;
  $getCode = $getCodes->countryCode;

  $getdeatil = Country::where('iso', $getCode)->first();

  if ($getdeatil) {

    //   $custCurrency=  $getdeatil['currency_code']; 

    $custCurrency =  'INR';

    $usdcurrency = 'USD';

    $currencyData = getliveconvert($usdcurrency, $custCurrency);

    $finalamt = (float) filter_var($currencyData['quotes']['USD' . $custCurrency], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    $return['data'] = array('famt' => $finalamt, 'currency' => $custCurrency);
  } else {

    $return['code']    = 101;

    $return['message'] = 'Something went wrong!';
  }

  return $return;
}



function ipaddressconrAirpay($ip)

{

  $curl = curl_init();

  curl_setopt_array($curl, array(

    CURLOPT_URL => 'http://ip-api.com/json/' . $ip,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_ENCODING => '',

    CURLOPT_MAXREDIRS => 10,

    CURLOPT_TIMEOUT => 0,

    CURLOPT_FOLLOWLOCATION => true,

    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

    CURLOPT_CUSTOMREQUEST => 'GET',

  ));

  $response = curl_exec($curl);

  $getCodes =  json_decode($response);

  // $getCode = $getCodes->countryCode;
  $getCode = "IN";

  $getdeatil = Country::where('iso', $getCode)->first();


  if ($getdeatil) {

    //   $custCurrency=  $getdeatil['currency_code']; 

    $custCurrencyNumcode =  $getdeatil['numcode'];

    $custCountry =  $getdeatil['nicename'];



    $custCurrency =  'INR';

    $usdcurrency = 'USD';

    $currencyData = getliveconvert($usdcurrency, $custCurrency);

    $finalamt = (float) filter_var($currencyData['quotes']['USD' . $custCurrency], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    $return['data'] = array('famt' => $finalamt, 'currency' => $custCurrency, 'numcode' => $custCurrencyNumcode, 'nicename' => $custCountry);
  } else {

    $return['code']    = 101;

    $return['message'] = 'Something went wrong!';
  }

  return $return;
}


function getCampPrefix($type)

{

  if ($type == 'text') {

    $aType = 'CMPT';
  } elseif ($type == 'banner') {

    $aType = 'CMPB';
  } elseif ($type == 'native') {

    $aType = 'CMPN';
  } elseif ($type == 'video') {

    $aType = 'CMPV';
  } elseif ($type == 'popup') {

    $aType = 'CMPP';
  } elseif ($type == 'social') {

    $aType = 'CMPS';
  } else {

    $aType = '';
  }

  return $aType;
}



function getCountryName($ip)

{

  $curl = curl_init();

  curl_setopt_array($curl, array(

    CURLOPT_URL => 'http://ip-api.com/json/' . $ip,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_ENCODING => '',

    CURLOPT_MAXREDIRS => 10,

    CURLOPT_TIMEOUT => 0,

    CURLOPT_FOLLOWLOCATION => true,

    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

    CURLOPT_CUSTOMREQUEST => 'GET',

  ));

  $response = curl_exec($curl);

  curl_close($curl);

  return json_decode($response);
}

function getCountryNameAdScript($ip)
{
  $data  = file_get_contents('https://ipinfo.7searchppc.com/?ip=' . $ip);
  return json_decode($data, true);
}

function getCountryIpLocal($ip)
{
  $db  = new PDO('sqlite:' . public_path() . '/dbs/7sdb_ips.sqlite');
  $qry = $db->query("select * from ss_ip_stacks where ip_addrs='" . $ip . "'"); // select all rows in the
  $data = $qry->fetchAll(PDO::FETCH_ASSOC);

  return json_encode($data, true);
}

function insertCountryIpLocal($data)
{
  $db  = new PDO('sqlite:' . public_path() . '/dbs/7sdb_ips.sqlite');
  $sql = "INSERT INTO ss_ip_stacks ( `ip_addrs`, `continent_code`, `continent_name`, `country_code`, `country_name`, `region_code`, `region_name`, `city`, `zip`, `time_zone`) VALUES ('" . $data->ip . "','" . $data->continent_code . "','" . $data->continent_name . "','" . $data->country_code . "','" . $data->country_name . "','" . $data->region_code . "','" . $data->region_name . "','" . $data->city . "','" . $data->zip . "','" . $data->time_zone->id . "')";
  $sql2 = $db->query($sql);
}



function campaignStatusCntryUpdate($camp_id, $status)

{

  $camp = Campaign::where('id', $camp_id)->first();

  $camp->status = $status;

  $camp->update();
}
function getCouponCal($userid, $couponcode, $amoumt, $couponid)
{
  $getcmpdata  = Coupon::where('coupon_id', $couponid)->where('trash', 0)->where('status', 1)->first();
  if (empty($getcmpdata)) {
    $return['code']    = 101;
    $return['message'] = 'Please Enter Coupon Code!';
    return $return;
  } else {
    $datenow = date('Y-m-d');
    $enddate = $getcmpdata->end_date;
    $date_now = Carbon::createFromFormat('Y-m-d', $datenow);
    $enddate1 = Carbon::parse($enddate);
    if ($date_now <= $enddate1) {
      $coupontype = $getcmpdata->coupon_type;
      if ($getcmpdata->coupon_limit_type === 'Limited') {
        $usedcoupon = UsedCoupon::where('advertiser_code', $userid)->where('coupon_id', $couponid)->count();
        if ($getcmpdata->coupon_limit_value <= $usedcoupon) {
          $return['code']    = 101;
          $return['message'] = 'Coupon Limit is Expired!';
          return $return;
        } else {
          $return = getCouponTypeCodition($coupontype, $getcmpdata, $amoumt, $couponcode, $couponid, $userid);
          return $return;
        }
      }
      if ($getcmpdata->coupon_limit_type === 'Unlimited') {
        $return = getCouponTypeCodition($coupontype, $getcmpdata, $amoumt, $couponcode, $couponid, $userid);
        return $return;
      }
    } else {
      $return['code']    = 101;
      $return['message'] = 'Coupon Expired!';
    }
  }
  return $return;
}

function getCouponTypeCodition($coupontype, $getcmpdata, $amoumt, $couponcode, $couponid, $userid)
{
  if ($coupontype == 'Percent') {
    $cmpid = $getcmpdata->id;
    $coupontypes  = Coupon::where('id', $cmpid)->where('user_ids', 0)->first();
    if ($coupontypes) {
      $minamoutcmp = $getcmpdata->min_bil_amt;
      if ($amoumt >= $minamoutcmp) {
        $valueamt = $getcmpdata->coupon_value;
        $finalamt = $amoumt * $valueamt / 100;
        $valuedisamt = $getcmpdata->max_disc;
        if ($valuedisamt > $finalamt) {
          $cmpamtnew1 = $amoumt - $finalamt;
          $return['code']            = 200;
          $return['amount']          = $amoumt;
          $return['bonus_amount']    = $finalamt;
          $return['total_amount']    = $amoumt + $finalamt;
          $return['coupon_code']     = $couponcode;
          $return['coupon_id']       = $couponid;
          return $return;
        } else {
          $cmpamtnew = $amoumt - $valuedisamt;
          $return['code']            = 200;
          $return['amount']          = $amoumt;
          $return['bonus_amount']    = $valuedisamt;
          $return['total_amount']    = $amoumt + $valuedisamt;
          $return['coupon_code']     = $couponcode;
          $return['coupon_id']       = $couponid;
          return $return;
        }
      } else {
        $return['code']    = 101;
        $return['message'] = "Minimum $$minamoutcmp required for this coupon.";
        return $return;
      }
    } else {
      $userdid = User::where('uid', $userid)->first();
      $useridn = $userdid->id;
      $getids = explode(",", $getcmpdata->user_ids);
      if (in_array($useridn, $getids)) {
        $minamoutcmp = $getcmpdata->min_bil_amt;
        if ($amoumt >= $minamoutcmp) {
          $valueamt = $getcmpdata->coupon_value;
          $finalamt = $amoumt * $valueamt / 100;
          $valuedisamt = $getcmpdata->max_disc;
          if ($valuedisamt > $finalamt) {
            $cmpamtnew1 = $amoumt - $finalamt;
            $return['code']    = 200;
            $return['amount']    = $amoumt;
            $return['bonus_amount']    = $finalamt;
            $return['total_amount']    = $amoumt + $finalamt;
            $return['coupon_code']    = $couponcode;
            $return['coupon_id']    = $couponid;
            return $return;
          } else {
            $cmpamtnew = $amoumt - $valuedisamt;
            $return['code']    = 200;
            $return['amount']    = $amoumt;
            $return['bonus_amount']    = $finalamt;
            $return['total_amount']    = $amoumt + $finalamt;
            $return['coupon_code']    = $couponcode;
            $return['coupon_id']    = $couponid;
            return $return;
          }
        } else {
          $return['code']    = 101;
          $return['message'] = "Minimum $$minamoutcmp required for this coupon.";
          return $return;
        }
      } else {
        $return['code']    = 101;
        $return['message'] = 'No Eligible Coupon!';
        return $return;
      }
    }
  } else {
    $cmpid = $getcmpdata->id;
    $coupontypes  = Coupon::where('id', $cmpid)->where('user_ids', 0)->first();
    if ($coupontypes) {
      $minamoutcmp = $getcmpdata->min_bil_amt;
      if ($amoumt >= $minamoutcmp) {
        $valueamt = $getcmpdata->coupon_value;
        $finalamt = $valueamt;
        $valuedisamt = $getcmpdata->max_disc;
        if ($valuedisamt > $finalamt) {
          $cmpamtnew1 = $amoumt - $finalamt;
          $return['code']    = 200;
          $return['amount']    = $amoumt;
          $return['bonus_amount']    = $finalamt;
          $return['total_amount']    = $amoumt + $finalamt;
          $return['coupon_code']    = $couponcode;
          $return['coupon_id']    = $couponid;
          return $return;
        } else {
          $cmpamtnew  =   $amoumt - $valuedisamt;
          $return['code']    = 200;
          $return['amount']    = $amoumt;
          $return['bonus_amount']    = $valuedisamt;
          $return['total_amount']    = $amoumt + $valuedisamt;
          $return['coupon_code']    = $couponcode;
          $return['coupon_id']    = $couponid;
          return $return;
        }
      } else {
        $return['code']    = 101;
        $return['message'] = "Minimum $$minamoutcmp required for this coupon.";
        return $return;
      }
    } else {
      $getids = explode(",", $getcmpdata->user_ids);
      $userdidn = User::where('uid', $userid)->first();
      $useridnews = $userdidn->id;
      if (in_array($useridnews, $getids)) {
        $minamoutcmp = $getcmpdata->min_bil_amt;
        if ($amoumt >= $minamoutcmp) {
          $valueamt = $getcmpdata->coupon_value;
          $finalamt = $amoumt * $valueamt / 100;
          $valuedisamt = $getcmpdata->max_disc;
          if ($valuedisamt > $finalamt) {
            $cmpamtnew1 = $amoumt - $finalamt;
            $return['code']    = 200;
            $return['amount']    = $amoumt;
            $return['bonus_amount']    = $finalamt;
            $return['total_amount']    = $amoumt + $finalamt;
            $return['coupon_code']    = $couponcode;
            $return['coupon_id']    = $couponid;
            return $return;
          } else {
            $cmpamtnew = $amoumt - $valuedisamt;
            $return['code']    = 200;
            $return['amount']    = $amoumt;
            $return['bonus_amount']    = $valuedisamt;
            $return['total_amount']    = $amoumt + $valuedisamt;
            $return['coupon_code']    = $couponcode;
            $return['coupon_id']    = $couponid;
            return $return;
          }
        } else {
          $return['code']    = 101;
          $return['message'] = "Minimum $$minamoutcmp required for this coupon.";
          return $return;
        }
      } else {
        $return['code']    = 101;
        $return['message'] = 'No Eligible Coupon!';
        return $return;
      }
    }
  }
}

function sendmailUser($subject, $body, $email)

{
  $isHTML = true;

  $mail = new PHPMailer();

  $mail->IsSMTP();

  $mail->CharSet = 'UTF-8';

  $mail->Host       = env('MAIL_HOST', "");

  $mail->SMTPDebug  = 0;

  $mail->SMTPAuth   = true;

  $mail->Port       = env('MAIL_PORT', "");

  $mail->Username   = env('mail_username', "");

  $mail->Password   = env('MAIL_PASSWORD', "");

  $mail->setFrom(env('mail_from_address', ""), "7Search PPC");

  $mail->addAddress($email);

  $mail->SMTPSecure = 'ssl';

  $mail->isHTML($isHTML);

  $mail->Subject = $subject;

  $mail->Body    = $body;

  if ($mail->send()) {

    return 1;
  } else {

    return 0;
  }
}

function sendmailpaymentupdate($subject, $body, $emails)
{
  $isHTML = true;
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->CharSet = 'UTF-8';
    $mail->Host = env('MAIL_HOST', "");
    $mail->SMTPAuth = true;
    $mail->Port = env('MAIL_PORT', "");
    $mail->Username = env('mail_username', "");
    $mail->Password = env('MAIL_PASSWORD', "");
    $mail->SMTPSecure = 'ssl';
    $mail->setFrom(env('mail_from_address', ""), "7Search PPC");
    foreach ($emails as $email) {
      $mail->addAddress($email);
      $mail->isHTML($isHTML);
      $mail->Subject = $subject;
      $mail->Body = $body;
      $mail->send();
      $mail->clearAddresses();
    }
    return 1;
  } catch (Exception $e) {
    return 0;
  }
}

function sendmailAdmin($subjectadmin, $bodyadmin, $adminmail1, $adminmail2)
{
  $isHTMLAdmin = true;

  $mailadmin = new PHPMailer();

  $mailadmin->IsSMTP();

  $mailadmin->CharSet = 'UTF-8';

  $mailadmin->Host       = env('MAIL_HOST', "");

  $mailadmin->SMTPDebug  = 0;

  $mailadmin->SMTPAuth   = true;

  $mailadmin->Port       = env('MAIL_PORT', "");
  //   $mailadmin->Port       = 587;

  $mailadmin->Username   = env('mail_username', "");

  $mailadmin->Password   = env('MAIL_PASSWORD', "");

  $mailadmin->setFrom(env('mail_from_address', ""), "7Search PPC");

  $mailadmin->addAddress($adminmail1);

  $mailadmin->AddCC($adminmail2);
  $mailadmin->AddCC('anandlogilite@gmail.com');

  //   $mailadmin->SMTPSecure = 'tls';
  $mailadmin->SMTPSecure = env('MAIL_ENCRYPTION', "tls");
  //   $mailadmin->SMTPDebug = 2;

  $mailadmin->isHTML($isHTMLAdmin);

  $mailadmin->Subject = $subjectadmin;

  $mailadmin->Body    = $bodyadmin;
  // print_r($mailadmin->send());exit();
  if ($mailadmin->send()) {

    return 1;
  } else {

    return 0;
    // return $mailadmin->ErrorInfo;
  }
}


function sendmailTest($subjectadmin, $bodyadmin, $adminmail1, $adminmail2)

{
  $isHTMLAdmin = true;

  $mailadmin = new PHPMailer();

  $mailadmin->IsSMTP();

  $mailadmin->CharSet = 'UTF-8';

  $mailadmin->Host       = env('MAIL_HOST', "");

  $mailadmin->SMTPDebug  = 0;

  $mailadmin->SMTPAuth   = true;

  $mailadmin->Port       = env('MAIL_PORT', "");
  //   $mailadmin->Port       = 587;

  $mailadmin->Username   = env('mail_username', "");

  $mailadmin->Password   = env('MAIL_PASSWORD', "");

  $mailadmin->setFrom(env('mail_from_address', ""), "7Search PPC");

  $mailadmin->addAddress($adminmail1);

  $mailadmin->AddCC($adminmail2);

  //   $mailadmin->SMTPSecure = 'tls';
  $mailadmin->SMTPSecure = env('MAIL_ENCRYPTION', "tls");
  //   $mailadmin->SMTPDebug = 2;

  $mailadmin->isHTML($isHTMLAdmin);

  $mailadmin->Subject = $subjectadmin;

  $mailadmin->Body    = $bodyadmin;
  // print_r($mailadmin->send());exit();
  if ($mailadmin->send()) {

    return 1;
  } else {

    return 0;
    // return $mailadmin->ErrorInfo;
  }
}



function sendFcmNotification($title, $msg)
{



  $adm = DB::table('admin_login_logs')->get()->toArray();

  $tokens = array_filter(array_column($adm, 'noti_token'));

  $tks = [];

  foreach ($tokens as $token) {

    $tks[] = $token;
  }



  $curl = curl_init();

  $data = json_encode([

    "registration_ids" => $tks,

    "notification" => [

      "body" => $msg,

      "title" => $title

    ]

  ]);



  curl_setopt_array($curl, array(

    CURLOPT_URL => 'https://fcm.googleapis.com/fcm/send',

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_ENCODING => '',

    CURLOPT_MAXREDIRS => 10,

    CURLOPT_TIMEOUT => 0,

    CURLOPT_FOLLOWLOCATION => true,

    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

    CURLOPT_CUSTOMREQUEST => 'POST',

    CURLOPT_POSTFIELDS => $data,

    CURLOPT_HTTPHEADER => array(

      'Authorization: key=AAAAVYh9IkY:APA91bHbyuOioazKL-_Jhwy7kpZ0vzq9wkIzYHeeUZN2H_9a2fCQK92cp7Ywm4Yg0ERmsVRsZep_KAw2YvpIE-6XXAW1igs4KJXFir6Uf-PEytCQCb3_WGGgbeJA1qKqbroFUqnMOi1p',

      'Content-Type: application/json'

    ),

  ));



  $response = curl_exec($curl);



  curl_close($curl);

  $response;
}



function sendFcmPubNotification($title, $msg, $tks)
{



  $curl = curl_init();

  $data = json_encode([

    "registration_ids" => $tks,

    "notification" => [

      "body" => $msg,

      "title" => $title

    ]

  ]);



  curl_setopt_array($curl, array(

    CURLOPT_URL => 'https://fcm.googleapis.com/fcm/send',

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_ENCODING => '',

    CURLOPT_MAXREDIRS => 10,

    CURLOPT_TIMEOUT => 0,

    CURLOPT_FOLLOWLOCATION => true,

    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

    CURLOPT_CUSTOMREQUEST => 'POST',

    CURLOPT_POSTFIELDS => $data,

    CURLOPT_HTTPHEADER => array(

      'Authorization: key=AAAAV4lzPQs:APA91bHnkz0wjVPC4U3UPDD0tceeimP0-S5grV3HSg_O6vAksfJi36VptX1O1AN-7_20PbVinddNjBiDLW1rJe9OsqftzavRtazap6ozR9X-VT8CEjq85KExbz53imH1StRJ-A_eaH0a',

      'Content-Type: application/json'

    ),

  ));



  $response = curl_exec($curl);



  curl_close($curl);

  $response;
}
function getImpClickData($imp, $clk, $os, $dv)
{

  $comb = [
    'windows' => [
      ['os' => 'windows', 'device' => 'Desktop']
    ],
    'android' => [
      ['os' => 'android', 'device' => 'Mobile'],
      ['os' => 'android', 'device' => 'Tablet']
    ],
    'apple' => [
      ['os' => 'apple', 'device' => 'Desktop'],
      ['os' => 'apple', 'device' => 'Mobile']
    ],
    'linux' => [
      ['os' => 'linux', 'device' => 'Desktop']
    ],
  ];



  $im = [];
  $ck = [];
  $i = 0;


  foreach ($comb as $key1 => $val1) {
    if (in_array($key1, $os)) {
      foreach ($comb[$key1] as $k1 => $v1) {
        if (!in_array($v1['device'], $dv)) {
          unset($comb[$key1][$k1]);
        }
      }
    } else {
      unset($comb[$key1]);
    }
  }


  // print_r($comb);
  $comb = $arr = array_filter(array_map('array_values', $comb));
  $co = count($comb);

  foreach ($comb as $key => $val) {
    if (in_array($key, $os)) {

      $i++;

      if ($key == 'windows') {
        if (!empty($comb[$key])) {
          if ($co > $i) {
            $imp2 = floor(($imp / 100) * rand(50, 60));
            $im[$key] = $imp2;
            $imp = ($imp - $imp2);

            $clk2 = floor(($clk / 100) * rand(50, 60));
            $ck[$key] = $clk2;
            $clk = ($clk - $clk2);
          } else {
            $im[$key] = $imp;
            $ck[$key] = $clk;
          }
        } else {
          unset($comb[$key]);
        }
      } elseif ($key == 'android') {

        if (!empty($comb[$key])) {
          if ($co > $i) {
            $imp2 = floor(($imp / 100) * rand(50, 70));
            $im[$key] = $imp2;
            $imp = ($imp - $imp2);

            $clk2 = floor(($clk / 100) * rand(50, 70));
            $ck[$key] = $clk2;
            $clk = ($clk - $clk2);
          } else {
            $im[$key] = $imp;
            $ck[$key] = $clk;
          }
        } else {
          unset($comb[$key]);
        }
      } elseif ($key == 'apple') {

        if (!empty($comb[$key])) {
          if ($co > $i) {
            $imp2 = floor(($imp / 100) * rand(60, 80));
            $im[$key] = $imp2;
            $imp = ($imp - $imp2);

            $clk2 = floor(($clk / 100) * rand(60, 80));
            $ck[$key] = $clk2;
            $clk = ($clk - $clk2);
          } else {
            $im[$key] = $imp;
            $ck[$key] = $clk;
          }
        } else {
          unset($comb[$key]);
        }
      } elseif ($key == 'linux') {
        if (!empty($comb[$key])) {
          if ($co > $i) {
            $imp2 = floor(($imp / 100) * rand(1, 15));
            $im[$key] = $imp2;
            $imp = ($imp - $imp2);

            $clk2 = floor(($clk / 100) * rand(1, 15));
            $ck[$key] = $clk2;
            $clk = ($clk - $clk2);
          } else {
            $im[$key] = $imp;
            $ck[$key] = $clk;
          }
        } else {
          unset($comb[$key]);
        }
      }
    } else {
      unset($comb[$key]);
    }
  }

  $cns = count($comb);

  foreach ($comb as $key2 => $val2) {

    $co2 = count($comb[$key2]);

    if ($co2 == 2) {

      $imv[] = floor(($im[$key2] / 100) * rand(50, 70));
      $imv[] = $im[$key2] - $imv[0];

      $clv[] = floor(($ck[$key2] / 100) * rand(50, 70));
      $clv[] = $ck[$key2] - $clv[0];
    } else {
      $imv[] = $im[$key2];
      $clv[] = $ck[$key2];
    }

    foreach ($comb[$key2] as $k2 => $v2) {
      $comb[$key2][$k2]['imp'] = $imv[$k2];
      $comb[$key2][$k2]['clk'] = $clv[$k2];
    }
    unset($imv);
    unset($clv);
  }

  return $comb;
}

function generate_serial()
{
  $getserial = TransactionLog::max('serial_no');
  $serial_no = $getserial + 1;
  return  $serial_no;
}

// function paymentSuccessMail($subjects, $fullname, $emailname, $phone, $addressline1, $addressline2, $city, $state, $country, $createdat, $useridas, $transactionid, $paymentmode, $amount, $paybleamt, $fee, $gst, $remark, $subtotal)
// {
//   $data['details'] = ['subject' => $subjects, 'full_name' => $fullname, 'emails' => $emailname, 'phone' => $phone, 'addressline1' => $addressline1, 'addressline2' => $addressline2, 'city' => $city, 'state' => $state, 'country' => $country, 'createdat' => $createdat, 'user_id' => $useridas, 'transaction_id' => $transactionid, 'payment_mode' => $paymentmode, 'amount' => $amount, 'payble_amt' => $paybleamt, 'fee' => $fee, 'gst' => $gst, 'remark' => $remark, 'subtotal' => $subtotal];
//   $data["email"] = $emailname;
//   $data["title"] = $subjects;
//   $pdf = PDF::loadView('emailtemp.pdf.pdf_stripe', $data);
//   $postpdf = time() . '_' . $transactionid;
//   $fileName =  $postpdf . '.' . 'pdf';
//   $path = public_path('pdf/invoice');
//   $finalpath = $path . '/' . $fileName;
//   $pdf->save($finalpath);
//   $body =  View('emailtemp.transactionrecipt', $data);
//   $isHTML = true;
//   $mail = new PHPMailer();
//   $mail->IsSMTP();
//   $mail->CharSet = 'UTF-8';
//   $mail->Host       = env('MAIL_HOST', "");
//   $mail->SMTPDebug  = 0;
//   $mail->SMTPAuth   = true;
//   $mail->Port       = env('MAIL_PORT', "");
//   $mail->Username   = env('mail_username', "");
//   $mail->Password   = env('MAIL_PASSWORD', "");
//   $mail->setFrom(env('mail_from_address', ""), "7Search PPC");
//   $mail->addAddress($emailname);
//   $mail->SMTPSecure = 'ssl';
//   $mail->isHTML($isHTML);
//   $mail->Subject = $subjects;
//   $mail->Body    = $body;
//   $mail->addAttachment($finalpath);
//   $mail->send();
//   $msg = "Razorpay|$emailname|Success|$transactionid|$amount|usd";
//   $msg1 = base64_encode($msg);
// }

function paymentSuccessMail($subjects, $fullname, $emailname, $phone, $addressline1, $addressline2, $city, $state, $country, $createdat, $useridas, $transactionid, $paymentmode, $amount, $paybleamt, $fee, $gst, $remark, $subtotal, $gst_no = '', $bank_id = '', $pin = '', $legal_entity = '', $name = '')
{
  if ($paymentmode == 'wiretransfer') {
    $data['termslist'] = DB::table('admin_invoice_terms')->select('terms')->whereNull('deleted_at')->get();
    $phonecode = DB::table("countries")->select('phonecode')->where('name', $country)->first();
    $bankData = DB::table('admin_bank_details')->select('bank_name', 'acc_name', 'acc_number', 'swift_code', 'ifsc_code', 'country', 'acc_address')->where('bank_id', $bank_id)->first();
    $data['acc_name'] = $bankData->acc_name;
    $data['acc_number'] = $bankData->acc_number;
    $data['bank_name'] = $bankData->bank_name;
    $data['swift_code'] = $bankData->swift_code;
    $data['ifsc_code'] = $bankData->ifsc_code;
    $data['country'] = $bankData->country;
    $data['acc_address'] = $bankData->acc_address;
    $data['wordAmount'] = numberToWords($amount);
    $data['gst_no'] =  $gst_no ?? 'N/A';
    $data['payble_amt'] = number_format($amount, 2);
    $data['pin'] = $pin;
    $data['phonecode'] = $phonecode->phonecode;
    $data['name'] = $name ?? $fullname;
    $data['legal_entity'] = $legal_entity ?? '';
    $data['download'] = 1;
  }
  $data['details'] = ['subject' => $subjects, 'full_name' => $fullname, 'emails' => $emailname, 'phone' => $phone, 'addressline1' => $addressline1, 'addressline2' => $addressline2, 'city' => $city, 'state' => $state, 'country' => $country, 'createdat' => $createdat, 'user_id' => $useridas, 'transaction_id' => $transactionid, 'payment_mode' => $paymentmode, 'amount' => $amount, 'payble_amt' => $paybleamt, 'fee' => $fee, 'gst' => $gst, 'remark' => $remark, 'subtotal' => $subtotal];
  $data["email"] = $emailname;
  $data["title"] = $subjects;
  $pdf = $paymentmode == 'wiretransfer' ? PDF::loadView('emailtemp.pdf.pdf_wire_transfer', $data) : PDF::loadView('emailtemp.pdf.pdf_stripe', $data);
  $postpdf = time() . '_' . $transactionid;
  $fileName =  $postpdf . '.' . 'pdf';
  $path = public_path('pdf/invoice');
  $finalpath = $path . '/' . $fileName;
  $pdf->save($finalpath);
  $body =  View('emailtemp.transactionrecipt', $data);
  $isHTML = true;
  $mail = new PHPMailer();
  $mail->IsSMTP();
  $mail->CharSet = 'UTF-8';
  $mail->Host       = env('MAIL_HOST', "");
  $mail->SMTPDebug  = 0;
  $mail->SMTPAuth   = true;
  $mail->Port       = env('MAIL_PORT', "");
  $mail->Username   = env('mail_username', "");
  $mail->Password   = env('MAIL_PASSWORD', "");
  $mail->setFrom(env('mail_from_address', ""), "7Search PPC");
  $mail->addAddress($emailname);
  $mail->SMTPSecure = 'ssl';
  $mail->isHTML($isHTML);
  $mail->Subject = $subjects;
  $mail->Body    = $body;
  $mail->addAttachment($finalpath);
  $mail->send();
  $msg = "$paymentmode|$emailname|Success|$transactionid|$amount|usd";
  $msg1 = base64_encode($msg);
}

function getAdInfo($adid)
{
  $redisCon = Redis::connection('default');
  $data = json_decode($redisCon->rawCommand('hget', 'webdata', $adid));
  //   print_r($data);die;
  return $data;
}
function userUpdateProfile($getreq, $id, $utype)
{
  date_default_timezone_set('Asia/Kolkata');
  $timestamp = date("Y-m-d H:i:s");
  $profileLog = [];
  $uid = $id;
  $users = DB::table('users')->select('first_name', 'last_name', 'phone', 'phonecode', 'address_line1', 'address_line2', 'city', 'state', 'country', 'messenger_name', 'messenger_type', 'profile_lock', 'status', 'ac_verified', 'user_type')->where('uid', $uid)->where('status', 0)->where('ac_verified', 1)->first();
  if (!empty($getreq['password'])) {
    $profileLog['reg_created']['previous'] = '-----';
    $profileLog['reg_created']['updated']  =  '-----';
    $profileLog['message'] =  "New User Profile Registered successfully.";
    ($utype == 1) ? $utype = 1 : $utype = 2;
    $data = json_encode($profileLog);
    DB::table('profile_logs')->insert(['uid' => $uid, 'profile_data' => $data, 'user_type' => $utype, 'created_at' => $timestamp]);
  } else {
    if ($users->first_name != $getreq['first_name']) {
      $profileLog['first_name']['previous'] = $users->first_name;
      $profileLog['first_name']['updated']  =  $getreq['first_name'];
    }
    if ($users->last_name != $getreq['last_name']) {
      $profileLog['last_name']['previous'] = $users->last_name;
      $profileLog['last_name']['updated']  =  $getreq['last_name'];
    }
    if ($users->phone != $getreq['phone']) {
      $profileLog['phone']['previous'] = $users->phone;
      $profileLog['phone']['updated']  =  $getreq['phone'];
    }
    if ($users->phonecode != $getreq['phonecode']) {
      $profileLog['phonecode']['previous'] = $users->phonecode;
      $profileLog['phonecode']['updated']  =  $getreq['phonecode'];
    }
    if ($users->address_line1 != $getreq['address_line1']) {
      $profileLog['address_line1']['previous'] = $users->address_line1;
      $profileLog['address_line1']['updated']  =  $getreq['address_line1'];
    }
    if ($users->address_line2 != $getreq['address_line2']) {
      $profileLog['address_line2']['previous'] = $users->address_line2;
      $profileLog['address_line2']['updated']  =  $getreq['address_line2'];
    }
    if ($users->city != $getreq['city']) {
      $profileLog['city']['previous'] = $users->city;
      $profileLog['city']['updated']  =  $getreq['city'];
    }
    if ($users->state != $getreq['state']) {
      $profileLog['state']['previous'] = $users->state;
      $profileLog['state']['updated']  =  $getreq['state'];
    }
    if ($users->country != $getreq['country']) {
      $profileLog['country']['previous'] = $users->country;
      $profileLog['country']['updated']  =  $getreq['country'];
    }
    if ($users->messenger_name != $getreq['messenger_name']) {
      $profileLog['messenger_name']['previous'] = $users->messenger_name;
      $profileLog['messenger_name']['updated']  =  $getreq['messenger_name'];
    }
    if ($users->messenger_type != $getreq['messenger_type']) {
      $profileLog['messenger_type']['previous'] = $users->messenger_type;
      $profileLog['messenger_type']['updated']  =  $getreq['messenger_type'];
    }
    if (count($profileLog) > 0) {
      $profileLog['message'] =  "Profile updated successfully.";
      $data = json_encode($profileLog);
      DB::table('profile_logs')->insert(['uid' => $uid, 'profile_data' => $data, 'user_type' => $utype, 'created_at' => $timestamp]);
    }
  }
}




function getIPinfo($ip)
{
  $db  = new PDO('sqlite:' . public_path() . '/dbs/7sdb_ips.sqlite');

  $qry = $db->query("select * from ss_ip_stacks order by id desc limit 1"); // select all rows in the
  $data = $qry->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode($data);


  //   $data = getCountryIpLocal($ip);
  //   $alldata = json_decode($data, true);
  //   if (count($alldata) > 0) {
  //     $list = $alldata[0];
  //     return ($list);
  //   } else {
  //     $data  = file_get_contents('http://api.ipstack.com/' . $ip . '?access_key=73edfcf302ecac3b68b27d0aee4ba152');
  //     insertCountryIpLocal(json_decode($data));
  //     return json_decode($data, true);
  //   }
}

// *** *** *** Redis Functions *** *** *** //

function manageMinimumPayment()
{
  $min = DB::table('panel_customizations')->select('payment_min_amt')->first();
  //   $minAmt = 25;
  $minAmt = $min->payment_min_amt;
  return $minAmt;
}

function getWalletAmount($advertiser_code)
{
  // $redisCon = Redis::connection('default');
  // $walletAmt = $redisCon->rawCommand('hget', ['adv_wallet',  $advertiser_code]);
  // return $walletAmt;
  $redisCon = Redis::connection('default');
  $walletAmt = $redisCon->hget('adv_wallet', $advertiser_code);
  return $walletAmt;
}
function getPubWalletAmount($publisher_code)
{
  $redisCon = Redis::connection('default');
  $pubWalletAmt = $redisCon->rawCommand('hget', 'pub_wallet',  $publisher_code);
  return $pubWalletAmt;
}

/* ========================================================================== User Functions Start ========================================================================== */

// Use to add or remove Campaigns and Ad Units into Redis when user become active or inactive
// *** Function Start *** //
function updateUserCampsAdunits($uid, $status)
{
  $redisCon = Redis::connection('default');
  $user = DB::table('users')->select('user_type')->where('uid', $uid)->first();
  //if ($user->user_type == 1 || $user->user_type == 3) {
    $camp = DB::table('campaigns')->select('campaigns.campaign_id', 'campaigns.advertiser_code', 'campaigns.device_type', 'campaigns.device_os', 'campaigns.campaign_name', 'campaigns.ad_type', 'campaigns.social_ad_type', 'campaigns.ad_title', 'campaigns.ad_description', 'campaigns.target_url', 'campaigns.website_category', 'campaigns.daily_budget', 'campaigns.pricing_model', 'campaigns.cpc_amt',  'campaigns.country_ids', 'campaigns.country_code', 'campaigns.country_name', 'categories.display_brand')->join('categories', 'campaigns.website_category', '=', 'categories.id')->where('campaigns.advertiser_code', $uid)->where('campaigns.status', 2)->where('campaigns.trash', 0)->get()->toArray();
  //}
  //if ($user->user_type == 2 || $user->user_type == 3) {
    $adunits = PubAdunit::select('website_category', 'uid', 'web_code', 'ad_code', 'grid_type', 'site_url', 'erotic_ads', 'alert_ads', 'ad_size')->where('uid', $uid)->where('status', 2)->get()->toArray();
  //}
  if ($status == 0) {
    if (!empty($camp)) {
      foreach ($camp as $camps) {
        $camps->country_ids = explode(',', $camps->country_ids);
        $camps->device_os = explode(',', $camps->device_os);
        $camps->device_type = explode(',', $camps->device_type);
        if ($camps->ad_type == 'banner' || $camps->ad_type == 'native' || $camps->ad_type == 'social') {
          $images = DB::table('ad_banner_images')->where('campaign_id', $camps->campaign_id)->get()->toArray();
          if ($camps->ad_type == 'social') {
            $camps->image_path = env('STOREAD_IMAGE_URL', "").$images[0]->image_path;
          } else {
            foreach ($images as $val) {
              $camps->images[$val->image_type] = [
                "image_type" => $val->image_type,
                "image_path" => env('STOREAD_IMAGE_URL', "").$val->image_path,
              ];
            }
          }
        }
        $data = json_encode($camps);
        $redisCon->rawCommand('json.set', '7s_camps:' . $camps->campaign_id, '$', $data);
      }
    }
    if (!empty($adunits)) {
      foreach ($adunits as $value) {
        $redisCon->rawCommand('hset', 'webdata', $value['ad_code'], json_encode($value));
      }
    }
  } else {
    if (!empty($camp)) {
      foreach ($camp as $value) {
        $redisCon->rawCommand('json.del', '7s_camps:' . $value->campaign_id, '$');
      }
    }
    if (!empty($adunits)) {
      foreach ($adunits as $value) {
        $redisCon->rawCommand('hdel', 'webdata', $value['ad_code']);
      }
    }
  }
}
// *** Function End *** //

// Use to add or remove Campaigns and Ad Units into Redis when user become active or inactive
// *** Function Start *** //
function updateBulkUserCampsAdunits($uids, $status)
{
  $redisCon = Redis::connection('default');
  foreach ($uids as $uid) {
    $user = DB::table('users')->select('user_type')->where('uid', $uid)->first();
    // if ($user->user_type == 1 || $user->user_type == 3) {
      $camp = DB::table('campaigns')->select('campaigns.campaign_id', 'campaigns.advertiser_code', 'campaigns.device_type', 'campaigns.device_os', 'campaigns.campaign_name', 'campaigns.ad_type', 'campaigns.social_ad_type', 'campaigns.ad_title', 'campaigns.ad_description', 'campaigns.target_url', 'campaigns.website_category', 'campaigns.daily_budget', 'campaigns.pricing_model', 'campaigns.cpc_amt',  'campaigns.country_ids', 'campaigns.country_code', 'campaigns.country_name', 'categories.display_brand')->join('categories', 'campaigns.website_category', '=', 'categories.id')->where('campaigns.advertiser_code', $uid)->where('campaigns.status', 2)->where('campaigns.trash', 0)->get()->toArray();
    //}
    //if ($user->user_type == 2 || $user->user_type == 3) {
      $adunits = PubAdunit::select('website_category', 'uid', 'web_code', 'ad_code', 'grid_type', 'site_url', 'erotic_ads', 'alert_ads', 'ad_size')->where('uid', $uid)->where('status', 2)->get()->toArray();
    //}
    if ($status == 'active') {
      if (!empty($camp)) {
        foreach ($camp as $camps) {
          $camps->country_ids = explode(',', $camps->country_ids);
          $camps->device_os = explode(',', $camps->device_os);
          $camps->device_type = explode(',', $camps->device_type);
          if ($camps->ad_type == 'banner' || $camps->ad_type == 'native' || $camps->ad_type == 'social') {
            $images = DB::table('ad_banner_images')->where('campaign_id', $camps->campaign_id)->get()->toArray();
            if ($camps->ad_type == 'social') {
              $camps->image_path = env('STOREAD_IMAGE_URL', "").$images[0]->image_path;
            } else {
              foreach ($images as $val) {
                $camps->images[$val->image_type] = [
                  "image_type" => $val->image_type,
                  "image_path" => env('STOREAD_IMAGE_URL', "").$val->image_path,
                ];
              }
            }
          }
          $data = json_encode($camps);
          $redisCon->rawCommand('json.set', '7s_camps:' . $camps->campaign_id, '$', $data);
        }
      }
      if (!empty($adunits)) {
        foreach ($adunits as $value) {
          $redisCon->rawCommand('hset', 'webdata', $value['ad_code'], json_encode($value));
        }
      }
    } else {
      if (!empty($camp)) {
        foreach ($camp as $value) {
          $redisCon->rawCommand('json.del', '7s_camps:' . $value->campaign_id, '$');
        }
      }
      if (!empty($adunits)) {
        foreach ($adunits as $value) {
          $redisCon->rawCommand('hdel', 'webdata', $value['ad_code']);
        }
      }
    }
  }
}
// *** Function End *** //

/* ========================================================================== User Functions End ========================================================================== */

/* ========================================================================== Pub AdUnit Functions Start ========================================================================== */

// Use to set all active Ad Units into Redis
// *** Function Start *** //
function setWebData()
{
  $redisCon = Redis::connection('default');
  $adunits = PubAdunit::select('website_category', 'uid', 'web_code', 'ad_code', 'grid_type', 'site_url', 'erotic_ads', 'alert_ads', 'ad_size')->where('status', 2)->get()->toArray();
  foreach ($adunits as $row) {
    $redisCon->rawCommand('hset', 'webdata', $row['ad_code'], json_encode($row));
  }
}
// *** Function End *** //

// Use to update Ad Units into Redis
// *** Function Start *** //
function updateWebData($ad_code, $status)
{
  $redisCon = Redis::connection('default');
  $redisCon->rawCommand('hdel', 'webdata', $ad_code);
  if ($status == 2) {
    $adunit = PubAdunit::select('website_category', 'uid', 'web_code', 'ad_code', 'grid_type', 'site_url', 'erotic_ads', 'alert_ads', 'ad_size')->where('ad_code', $ad_code)->where('status', 2)->first();
    $redisCon->rawCommand('hset', 'webdata', $ad_code, json_encode($adunit));
  }
}
// *** Function End *** //

/* ========================================================================== Pub AdUnit Functions End ========================================================================== */

/* ========================================================================== Campaigns Functions Start ========================================================================== */

// Use to set all active Text Campaign into Redis
// *** Function Start *** //
function setTextCamp()
{
  $redisCon = Redis::connection('default');
  $campaigns = DB::table('campaigns')->select('campaigns.campaign_id', 'campaigns.advertiser_code', 'campaigns.device_type', 'campaigns.device_os', 'campaigns.campaign_name', 'campaigns.ad_type', 'campaigns.social_ad_type', 'campaigns.ad_title', 'campaigns.ad_description', 'campaigns.target_url', 'campaigns.website_category', 'campaigns.daily_budget', 'campaigns.pricing_model', 'campaigns.cpc_amt',  'campaigns.country_ids', 'campaigns.country_code', 'campaigns.country_name', 'categories.display_brand')->join('categories', 'campaigns.website_category', '=', 'categories.id')->where('campaigns.ad_type', 'text')->where('campaigns.status', 2)->where('campaigns.trash', 0)->get()->toArray();

  foreach ($campaigns as $camp) {
    $camp->country_ids  = explode(',', $camp->country_ids);
    $camp->country_code = explode(',', $camp->country_code);
    $camp->device_os    = explode(',', $camp->device_os);
    $camp->device_type  = explode(',', $camp->device_type);
    $data = json_encode($camp);
    $res = $redisCon->rawCommand('json.set', '7s_camps:' . $camp->campaign_id, '$', $data);
  }
  $return = [
    'code' => 200,
    'message' => 'Campaigns added successfully!'
  ];
  return $return;
}
// *** Function End *** //

// Use to set all active Banner Campaign into Redis
// *** Function Start *** //
function setBannerCamp()
{
  $redisCon = Redis::connection('default');
  $campaigns = DB::table('campaigns')->select('campaigns.campaign_id', 'campaigns.advertiser_code', 'campaigns.device_type', 'campaigns.device_os', 'campaigns.campaign_name', 'campaigns.ad_type', 'campaigns.social_ad_type', 'campaigns.ad_title', 'campaigns.ad_description', 'campaigns.target_url', 'campaigns.website_category', 'campaigns.daily_budget', 'campaigns.pricing_model', 'campaigns.cpc_amt',  'campaigns.country_ids', 'campaigns.country_code', 'campaigns.country_name', 'categories.display_brand')->join('categories', 'campaigns.website_category', '=', 'categories.id')->where('campaigns.ad_type', 'banner')->where('campaigns.status', 2)->where('campaigns.trash', 0)->get()->toArray();
  $camps = json_decode(json_encode($campaigns), true);
  foreach ($camps as $key => $value) {
    $images = DB::table('ad_banner_images')->where('campaign_id', $value['campaign_id'])->get()->toArray();
    $image = json_decode(json_encode($images), true);
    foreach ($image as $val) {
      $value['images'][$val["image_type"]] = [
        "image_type" => $val["image_type"],
        "image_path" => env('STOREAD_IMAGE_URL', "") . $val["image_path"],
      ];
    }
    $value['country_ids'] = explode(',', $value['country_ids']);
    $value['country_code'] = explode(',', $value['country_code']);
    $value['device_os'] = explode(',', $value['device_os']);
    $value['device_type'] = explode(',', $value['device_type']);
    $data = json_encode($value);
    $res = $redisCon->rawCommand('json.set', '7s_camps:' . $value['campaign_id'], '$', $data);
  }
}
// *** Function End *** //

// Use to set all active Native Campaign into Redis
// *** Function Start *** //
function setNativeCamp()
{
  $redisCon = Redis::connection('default');
  $campaigns = DB::table('campaigns')->select('campaigns.campaign_id', 'campaigns.advertiser_code', 'campaigns.device_type', 'campaigns.device_os', 'campaigns.campaign_name', 'campaigns.ad_type', 'campaigns.social_ad_type', 'campaigns.ad_title', 'campaigns.ad_description', 'campaigns.target_url', 'campaigns.website_category', 'campaigns.daily_budget', 'campaigns.pricing_model', 'campaigns.cpc_amt',  'campaigns.country_ids', 'campaigns.country_code', 'campaigns.country_name', 'categories.display_brand')->join('categories', 'campaigns.website_category', '=', 'categories.id')->where('campaigns.ad_type', 'native')->where('campaigns.status', 2)->where('campaigns.trash', 0)->get()->toArray();
  $camps = json_decode(json_encode($campaigns), true);
  foreach ($camps as $key => $value) {
    $images = DB::table('ad_banner_images')->where('campaign_id', $value['campaign_id'])->get()->toArray();
    $image = json_decode(json_encode($images), true);
    foreach ($image as $val) {
      $value['images'][$val["image_type"]] = [
        "image_type" => $val["image_type"],
        "image_path" => env('STOREAD_IMAGE_URL', "") . $val["image_path"],
      ];
    }
    $value['country_ids'] = explode(',', $value['country_ids']);
    $value['country_code'] = explode(',', $value['country_code']);
    $value['device_os'] = explode(',', $value['device_os']);
    $value['device_type'] = explode(',', $value['device_type']);
    $data = json_encode($value);
    $res = $redisCon->rawCommand('json.set', '7s_camps:' . $value['campaign_id'], '$', $data);
  }
}
// *** Function End *** //

// Use to set all active In-Page Push Campaign into Redis
// *** Function Start *** //
function setInPagePushCamp()
{
  $redisCon = Redis::connection('default');
  $campaigns = DB::table('campaigns')->select('campaigns.campaign_id', 'campaigns.advertiser_code', 'campaigns.device_type', 'campaigns.device_os', 'campaigns.campaign_name', 'campaigns.ad_type', 'campaigns.social_ad_type', 'campaigns.ad_title', 'campaigns.ad_description', 'campaigns.target_url', 'campaigns.website_category', 'campaigns.daily_budget', 'campaigns.pricing_model', 'campaigns.cpc_amt',  'campaigns.country_ids', 'campaigns.country_code', 'campaigns.country_name', 'categories.display_brand')->join('categories', 'campaigns.website_category', '=', 'categories.id')->where('campaigns.ad_type', 'social')->where('campaigns.status', 2)->where('campaigns.trash', 0)->get()->toArray();
  $camps = json_decode(json_encode($campaigns), true);
  foreach ($camps as $key => $value) {
    $images = DB::table('ad_banner_images')->where('campaign_id', $value['campaign_id'])->get()->toArray();
    $image = json_decode(json_encode($images), true);
    foreach ($image as $val) {
      $value['image_path'] = env('STOREAD_IMAGE_URL', "") . $val["image_path"];
    }
    $value['country_ids'] = explode(',', $value['country_ids']);
    $value['country_code'] = explode(',', $value['country_code']);
    $value['device_os'] = explode(',', $value['device_os']);
    $value['device_type'] = explode(',', $value['device_type']);
    $data = json_encode($value);
    $res = $redisCon->rawCommand('json.set', '7s_camps:' . $value['campaign_id'], '$', $data);
  }
}
// *** Function End *** //

// Use to set all active Popunder Campaign into Redis
// *** Function Start *** //
function setPopUnderCamp()
{
  $redisCon = Redis::connection('default');
  $campaigns = DB::table('campaigns')->select('campaigns.campaign_id', 'campaigns.advertiser_code', 'campaigns.device_type', 'campaigns.device_os', 'campaigns.campaign_name', 'campaigns.ad_type', 'campaigns.social_ad_type', 'campaigns.ad_title', 'campaigns.ad_description', 'campaigns.target_url', 'campaigns.website_category', 'campaigns.daily_budget', 'campaigns.pricing_model', 'campaigns.cpc_amt',  'campaigns.country_ids', 'campaigns.country_code', 'campaigns.country_name', 'categories.display_brand')->join('categories', 'campaigns.website_category', '=', 'categories.id')->where('campaigns.ad_type', 'popup')->where('campaigns.status', 2)->where('campaigns.trash', 0)->get()->toArray();
  foreach ($campaigns as $camp) {
    $camp->country_ids = explode(',', $camp->country_ids);
    $camp->country_code = explode(',', $camp->country_code);
    $camp->device_os = explode(',', $camp->device_os);
    $camp->device_type = explode(',', $camp->device_type);
    $data = json_encode($camp);
    $res = $redisCon->rawCommand('json.set', '7s_camps:' . $camp->campaign_id, '$', $data);
  }
}
// *** Function End *** //

// Use to get All Campaigns Ad from Redis
// *** Function Start *** //
function getCampAd($category, $country, $device, $os, $erotics, $alert, $ad_type, $grid = 0, $image_type = null)
{
  $redisCon = Redis::connection('default');
  $query1 = "@country_code:{'$country'} @device_type:{'$device'} @device_os:{'$os'}  (@website_category:[$category $category] @ad_type:($ad_type))";
  $query2 = "@country_code:{'$country'} @device_type:{'$device'} @device_os:{'$os'} @ad_type:($ad_type)";
  $query3 = "@country_code:{''} @device_type:{'$device'} @device_os:{'$os'} @ad_type:($ad_type)";

  if ($category == 64) {
    $data = $redisCon->rawCommand('ft.search', "7sAds", $query2, 'LIMIT', 0, 1000);
  } else {
    $data = $redisCon->rawCommand('ft.search', "7sAds", $query1, 'LIMIT', 0, 1000);
  }

  $qr = 1;
  if (empty($data[0])) {
    $data = $redisCon->rawCommand('ft.search', "7sAds", $query2, 'LIMIT', 0, 1000);
    $qr = 2;
    if (empty($data[0])) {
      $data = $redisCon->rawCommand('ft.search', "7sAds", $query3, 'LIMIT', 0, 1000);
      $qr = 3;
      //   print_r($os);die;
    }
  }
  $camp = filteredData($data, $erotics, $alert, $image_type, $country);

  if (empty($camp)) {
    if ($qr == 1) {
      $data = $redisCon->rawCommand('ft.search', "7sAds", $query2, 'LIMIT', 0, 1000);
      if (empty($data[0])) {
        $data = $redisCon->rawCommand('ft.search', "7sAds", $query3, 'LIMIT', 0, 1000);
      }
    } elseif ($qr == 2) {
      $data = $redisCon->rawCommand('ft.search', "7sAds", $query3, 'LIMIT', 0, 1000);
    }
    $camp = filteredData($data, $erotics, $alert, $image_type, $country);
  }

  if (!empty($camp)) {
    shuffle($camp);
    if ($ad_type == 'native' && $grid > 0) {
      $camp = nativeGrid($camp, $grid);
      return $camp;
    }
    return $camp[0];
  } else {
    $data = $redisCon->rawCommand('ft.search', "7sAds", $query3, 'LIMIT', 0, 1000);
    $camp = filteredData($data, $erotics, $alert, $image_type, $country);
    if (empty($camp)) {
      return [];
    }
    shuffle($camp);
    if ($ad_type == 'native' && $grid > 0) {
      $camp = nativeGrid($camp, $grid);
      return $camp;
    }
    return $camp[0];
  }
}

function nativeGrid($camp, $grid)
{

  if ($grid == 1) {
    return $camp[0];
  }
  if ($grid == 2) {
    if (count($camp) >= 2) {
      $ad[] = $camp[0];
      $ad[] = $camp[1];
    } else {
      $ad[] = $camp[0];
      $ad[] = $camp[0];
    }
    return $ad;
  }
  if ($grid == 3) {
    if (count($camp) >= 3) {
      $ad[] = $camp[0];
      $ad[] = $camp[1];
      $ad[] = $camp[2];
    }
    if (count($camp) == 2) {
      $ad[] = $camp[0];
      $ad[] = $camp[1];
      $ad[] = $camp[0];
    }
    if (count($camp) == 1) {
      $ad[] = $camp[0];
      $ad[] = $camp[0];
      $ad[] = $camp[0];
    }
    return $ad;
  }
  if ($grid == 4) {
    if (count($camp) >= 4) {
      $ad[] = $camp[0];
      $ad[] = $camp[1];
      $ad[] = $camp[2];
      $ad[] = $camp[3];
    }
    if (count($camp) == 3) {
      $ad[] = $camp[0];
      $ad[] = $camp[1];
      $ad[] = $camp[2];
      $ad[] = $camp[0];
    }
    if (count($camp) == 2) {
      $ad[] = $camp[0];
      $ad[] = $camp[1];
      $ad[] = $camp[0];
      $ad[] = $camp[1];
    }
    if (count($camp) == 1) {
      $ad[] = $camp[0];
      $ad[] = $camp[0];
      $ad[] = $camp[0];
      $ad[] = $camp[0];
    }
    return $ad;
  }
}

function filteredData($data, $erotics, $alert, $image_type, $country)
{
  $redisCon = Redis::connection('default');
  $data =  array_reduce($data, function ($data, $row) {
    if (is_array($row)) {
      $data[] =  $row[1];
    }
    return $data;
  });
  if ($image_type === 0) {
    $image_type = 1;
  }

  $camps = [];
  foreach ($data as $row) {
    $camp = json_decode($row, true);
    $wallet = $redisCon->rawCommand('hget', "adv_wallet", $camp['advertiser_code']);
    // print_r($camp['advertiser_code']);echo'=>';
    if (!($erotics == 1 && $camp['website_category'] == 63) || !($alert == 1 && $camp['website_category'] == 17)) {
      if ($wallet > 0) {
        $dt = md5($camp['website_category'] . $country);
        $ad_rate = json_decode($redisCon->rawCommand('hget', 'pub_rate_masters', $dt), true);
        if (empty($ad_rate)) {
          $ad_rate = json_decode($redisCon->rawCommand('hget', 'categories_data', $camp['website_category']), true);
        }
        $daily_budget = getDailyBudget($camp['advertiser_code'], $camp['campaign_id']);
        if ($camp['daily_budget'] > $daily_budget) {
          $camp['spent_amt'] = $daily_budget;
          unset($camp['country_ids']);
          unset($camp['device_os']);
          unset($camp['device_type']);
          if ($camp['pricing_model'] == 'CPM') {
            if ($wallet >= $camp['cpc_amt']) {
              $camp['adv_cpm'] = $camp['cpc_amt'];
              $camp['pub_cpm'] = $ad_rate['pub_cpm'];
              if ($image_type != null) {
                if (array_key_exists($image_type, $camp['images'])) {
                  $camp['image_path'] = $camp['images'][$image_type]['image_path'];
                  $camp['image_type'] = $camp['images'][$image_type]['image_type'];
                  unset($camp['images']);
                  $camps[] = $camp;
                }
              } else {
                $camps[] = $camp;
              }
            }
          } else {
            if ($wallet >= $ad_rate['cpm']) {
              $camp['adv_cpm'] = $ad_rate['cpm'];
              $camp['pub_cpm'] = $ad_rate['pub_cpm'];
              if ($image_type != null) {
                if (array_key_exists($image_type, $camp['images'])) {
                  $camp['image_path'] = $camp['images'][$image_type]['image_path'];
                  $camp['image_type'] = $camp['images'][$image_type]['image_type'];
                  unset($camp['images']);
                  $camps[] = $camp;
                }
              } else {
                $camps[] = $camp;
              }
            }
          }
        }
      }
    }
  }
  return $camps;
}

// *** Function End *** //

// Use to add/update/remove campaigns into Redis
// *** Function Start *** //
function updateCamps($camp_id, $status)
{
  $redisCon = Redis::connection('default');
  if ($status == 2) {
    $camps = DB::table('campaigns')->select('campaigns.campaign_id', 'campaigns.advertiser_code', 'campaigns.device_type', 'campaigns.device_os', 'campaigns.campaign_name', 'campaigns.ad_type', 'campaigns.social_ad_type', 'campaigns.ad_title', 'campaigns.ad_description', 'campaigns.target_url', 'campaigns.website_category', 'campaigns.daily_budget', 'campaigns.pricing_model', 'campaigns.cpc_amt',  'campaigns.country_ids', 'campaigns.country_code', 'campaigns.country_name', 'categories.display_brand')->join('categories', 'campaigns.website_category', '=', 'categories.id')->where('campaigns.campaign_id', $camp_id)->where('campaigns.status', 2)->where('campaigns.trash', 0)->first();
    if (!empty($camps)) {
      $camps->country_ids = explode(',', $camps->country_ids);
      $camps->country_code = explode(',', $camps->country_code);
      $camps->device_os = explode(',', $camps->device_os);
      $camps->device_type = explode(',', $camps->device_type);
      if ($camps->ad_type == 'banner' || $camps->ad_type == 'native' || $camps->ad_type == 'social') {
        $images = DB::table('ad_banner_images')->where('campaign_id', $camp_id)->get()->toArray();
        if ($camps->ad_type == 'social') {
          $camps->image_path = $images[0]->image_path;
        } else {
          foreach ($images as $val) {
            $camps->images[$val->image_type] = [
              "image_type" => $val->image_type,
              "image_path" => env('STOREAD_IMAGE_URL', "") . $val->image_path,
            ];
          }
        }
      }
      $data = json_encode($camps);
      $redisCon->rawCommand('json.set', '7s_camps:' . $camp_id, '$', $data);
    }
  } else {
    $redisCon->rawCommand('json.del', '7s_camps:' . $camp_id, '$');
  }
  return ['code' => 200, 'message' => 'Campaign updated successfully!'];
}
// *** Function End *** //

// Use to update bulk campaigns into Redis
// *** Function Start *** //
function updateBulkCamps($camp_id, $status)
{
  $redisCon = Redis::connection('default');
  if ($status == 'active') {
    foreach ($camp_id as $id) {
      $camps = DB::table('campaigns')->select('campaigns.campaign_id', 'campaigns.advertiser_code', 'campaigns.device_type', 'campaigns.device_os', 'campaigns.campaign_name', 'campaigns.ad_type', 'campaigns.social_ad_type', 'campaigns.ad_title', 'campaigns.ad_description', 'campaigns.target_url', 'campaigns.website_category', 'campaigns.daily_budget', 'campaigns.pricing_model', 'campaigns.cpc_amt',  'campaigns.country_ids', 'campaigns.country_code', 'campaigns.country_name', 'categories.display_brand')->join('categories', 'campaigns.website_category', '=', 'categories.id')->where('campaigns.campaign_id', $id)->where('campaigns.status', 2)->where('campaigns.trash', 0)->first();
      if (!empty($camps)) {
        $camps->country_ids = explode(',', $camps->country_ids);
        $camps->country_code = explode(',', $camps->country_code);
        $camps->device_os = explode(',', $camps->device_os);
        $camps->device_type = explode(',', $camps->device_type);
        if ($camps->ad_type == 'banner' || $camps->ad_type == 'native' || $camps->ad_type == 'social') {
          $images = DB::table('ad_banner_images')->where('campaign_id', $id)->get()->toArray();
          if ($camps->ad_type == 'social') {
            $camps->image_path = $images[0]->image_path;
          } else {
            foreach ($images as $val) {
              $camps->images[$val->image_type] = [
                "image_type" => $val->image_type,
                "image_path" => env('STOREAD_IMAGE_URL', "") . $val->image_path,
              ];
            }
          }
        }
        $data = json_encode($camps);
        $redisCon->rawCommand('json.set', '7s_camps:' . $id, '$', $data);
      }
    }
  } else {
    foreach ($camp_id as $id) {
      $redisCon->rawCommand('json.del', '7s_camps:' . $id, '$');
    }
  }
  return ['code' => 200, 'message' => 'Campaign updated successfully!'];
}
// *** Function End *** //

//Use to update display brand Icon on the Ad
// *** Function Start *** //
function setDisplayBrand($category, $brand)
{
  $redisCon = Redis::connection('default');
  $data = $redisCon->rawCommand('ft.search', "7sAds", "@website_category:[$category $category]", 'LIMIT', 0, 1000);
  $data =  array_reduce($data, function ($data, $row) {
    if (is_array($row)) {
      $data[] =  $row[1];
    }
    return $data;
  });
  foreach ($data as $row) {
    $camp = json_decode($row, true);
    $camp['display_brand'] = $brand;
    $data = json_encode($camp);
    $redisCon->rawCommand('json.set', '7s_camps:' . $camp['campaign_id'], '$', $data);
  }
  return;
}
// *** Function End *** //

/* ========================================================================== Campaigns Functions End ========================================================================== */

/* ========================================================================== Pub Stats Functions Start ========================================================================== */

// Use to set all Publisher stats into Redis
// *** Function Start *** //
function setPubStats()
{
  $redisCon = Redis::connection('default');
  $startDate = Carbon::parse('2024-04-12');
  $endDate = Carbon::parse('2024-05-13');
  $stats = DB::table('pub_stats')
    ->whereBetween('udate', [$startDate, $endDate])
    ->get()->toArray();

  $data = array_reduce($stats, function ($carry, $item) {
    $carry[$item->publisher_code][] = $item;
    return $carry;
  }, []);
  foreach ($data as $key => $value) {
    $redisCon->rawCommand('hset', 'pub_stats', $key, json_encode($data[$key]));
  }
}
// *** Function End *** //

// Use to get Publisher dashboard stats from Redis
// *** Function Start *** //
function getPubDash($uid, $option)
{
  $redisCon = Redis::connection('default');
  $data = json_decode($redisCon->rawCommand('hget', 'pub_stats', $uid), true);
  $wallet = json_decode($redisCon->rawCommand('hget', 'pub_wallet', $uid), true);
  $gdata = [];
  $totalcampclicks = [];
  $totalcampimp = [];
  $totaldate = [];
  $maindata = [
    'data' => [
      $totaldata = [
        'click' => 0,
        'impression' => 0,
        'ctr' => 0,
        'amount' => 0
      ],
    ],
    'date' => [],
    'click' => [],
    'impression' => [],
  ];
  $cimps = [
    "countries" => [],
    "data" => []
  ];
  $cclks = [
    "countries" => [],
    "data" => []
  ];
  $ndate = date('d-m-Y');

  if ($option == 0) {
    $newDate = date("d-m-Y", strtotime($ndate . "-$option day"));
    $sdate = date('Y-m-d', strtotime("-$option day"));
  } else {
    $ddd =  $option - 1;
    $newDate = date("d-m-Y", strtotime($ndate . "-$ddd day"));
    $sdate = date('Y-m-d', strtotime("-$ddd day"));
  }

  $startDate = strtotime($newDate);
  $endDate = strtotime($ndate);

  $device_imp = [
    "desktop" => [
      "impression" => 0,
      "percent" => 0
    ],
    "mobile" => [
      "impression" => 0,
      "percent" => 0
    ],
    "tablet" => [
      "impression" => 0,
      "percent" => 0
    ]
  ];
  $device_click = [
    "desktop" => [
      "click" => 0,
      "percent" => 0
    ],
    "mobile" => [
      "click" => 0,
      "percent" => 0
    ],
    "tablet" => [
      "click" => 0,
      "percent" => 0
    ]
  ];
  if (!empty($data)) {
    $filtered_data = array_filter($data, function ($date) use ($newDate, $ndate) {
      $newDates = Carbon::createFromFormat('d-m-Y', $newDate)->format('Y-m-d');
      $ndates = Carbon::createFromFormat('d-m-Y', $ndate)->format('Y-m-d');

      return ($date['udate'] >= $newDates && $date['udate'] <= $ndates);
    });

    foreach ($filtered_data as $key => $value) {
      if (array_key_exists($value['udate'], $gdata)) {
        $gdata[$value['udate']] = [
          "date" => $value['udate'],
          "imps" => $gdata[$value['udate']]['imps'] + $value['impressions'],
          "click" => $gdata[$value['udate']]['click'] + $value['clicks'],
          "rev" => $gdata[$value['udate']]['rev'] + $value['amount']
        ];
      } else {
        $gdata[$value['udate']] = [
          "date" => $value['udate'],
          "imps" => $value['impressions'],
          "click" => $value['clicks'],
          "rev" => $value['amount'],
        ];
      }
    }


    $imps = array_sum(array_column($filtered_data, 'impressions'));
    $clks = array_sum(array_column($filtered_data, 'clicks'));
    $amts = array_sum(array_column($filtered_data, 'amount'));
    $countries = array_unique(array_column($filtered_data, 'country'));
    $dates = array_unique(array_column($filtered_data, 'udate'));

    $totaldata = [
      'click' => $clks,
      'impression' => $imps,
      'amount' => number_format($amts, 2)
    ];

    for ($currentDate = $startDate; $currentDate <= $endDate; $currentDate += (86400)) {
      $xxdate = date('Y-m-d', $currentDate);
      $totaldate[] = $xxdate;

      if (in_array($xxdate, $dates)) {
        $uclick = 0;
        $uimp = 0;
        foreach ($gdata as $imp) {
          if ($imp['date'] == $xxdate) {
            $uclick = $imp['click'];
            $uimp = $imp['imps'];
            $urev = $imp['rev'];
          }
        }
        $totalcampclicks[] = $uclick;
        $totalcampimp[] = $uimp;
        $totalrev[] = $urev;
      } else {
        $totalcampclicks[] = 0;
        $totalcampimp[] = 0;
        $totalrev[] = 0;
      }
    }
    $maindata = array('data' => $totaldata, 'date' => $totaldate, 'click' => $totalcampclicks, 'impression' => $totalcampimp, 'rev' => $totalrev);
    foreach ($countries as $country) {
      $data2[$country] = array_filter($filtered_data, function ($item) use ($country) {
        return $item['country'] == $country;
      });
      $data3[] = [
        'total' => array_sum(array_column($data2[$country], 'impressions')),
        'country' => $country,
      ];
      $data5[] = [
        'clicks' => array_sum(array_column($data2[$country], 'clicks')),
        'country' => $country,
      ];
    }
    !empty($data3) ? arsort($data3) : $data3 = [];
    !empty($data5) ? arsort($data5) : $data5 = [];
    $cimps = array_slice($data3, 0, 10);
    $cclks = array_slice($data5, 0, 10);
    $cimps = [
      "countries" =>  array_column($cimps, 'country'),
      "data" => array_column($cimps, 'total')
    ];
    $cclks = [
      "countries" =>  array_column($cclks, 'country'),
      "data" => array_column($cclks, 'clicks')
    ];

    $tab = array_filter($filtered_data, function ($item) {
      return $item['device_type'] == "Tablet";
    });
    $mob = array_filter($filtered_data, function ($item) {
      return $item['device_type'] == "Mobile";
    });
    $desk = array_filter($filtered_data, function ($item) {
      return $item['device_type'] == "Desktop";
    });

    $device_imp['tablet'] = [
      'impression' => array_sum(array_column($tab, 'impressions')),
    ];
    $device_imp['mobile'] = [
      'impression' => array_sum(array_column($mob, 'impressions')),
    ];
    $device_imp['desktop'] = [
      'impression' => array_sum(array_column($desk, 'impressions')),
    ];

    $device_click['tablet'] = [
      'click' => array_sum(array_column($tab, 'clicks'))
    ];
    $device_click['mobile'] = [
      'click' => array_sum(array_column($mob, 'clicks'))
    ];
    $device_click['desktop'] = [
      'click' => array_sum(array_column($desk, 'clicks'))
    ];

    if ($imps > 0) {
      $prc1 = ($device_imp['mobile']['impression'] / $imps) * 100;
      $device_imp['mobile']['percent']  = number_format($prc1, 2);
      $prc2 = ($device_imp['desktop']['impression'] / $imps) * 100;
      $device_imp['desktop']['percent']  = number_format($prc2, 2);
      $prc3 = ($device_imp['tablet']['impression'] / $imps) * 100;
      $device_imp['tablet']['percent']  = number_format($prc3, 2);
    } else {
      $device_imp['mobile']['percent']  = 0;
      $device_imp['desktop']['percent']  = 0;
      $device_imp['tablet']['percent']  = 0;
    }

    if ($clks > 0) {
      $prc1 = ($device_click['mobile']['click'] / $clks) * 100;
      $device_click['mobile']['percent']  = number_format($prc1, 2);
      $prc2 = ($device_click['desktop']['click'] / $clks) * 100;
      $device_click['desktop']['percent']  = number_format($prc2, 2);
      $prc3 = ($device_click['tablet']['click'] / $clks) * 100;
      $device_click['tablet']['percent']  = number_format($prc3, 2);
    } else {
      $device_click['mobile']['percent']  = 0;
      $device_click['desktop']['percent']  = 0;
      $device_click['tablet']['percent']  = 0;
    }
  }



  $return['code'] = 200;
  if ($option == 0) {
    $return['option'] = "Today";
  } else {
    $return['option'] = "$option days";
  }
  $return['graph'] = $maindata;
  $return['device_imp'] = $device_imp;
  $return['device_click'] = $device_click;
  $return['country_imp'] = $cimps;
  $return['country_click'] = $cclks;
  $return['wallet'] = number_format($wallet, 2);
  return $return;
}
// *** Function End *** //

/* ========================================================================== Pub Stats Functions End ========================================================================== */

// Use to set all advertiser stats into Redis
// *** Function Start *** //
function setAdvStats()
{
  $redisCon = Redis::connection('default');
  $startDate = Carbon::parse('2024-04-12');
  $endDate = Carbon::parse('2024-05-13');
  $stats = DB::table('adv_stats')
    ->select('advertiser_code', 'uni_imp_id', 'camp_id', 'impressions', 'clicks', 'amount', 'device_type', 'device_os', 'country', 'udate')
    ->whereBetween('udate', [$startDate, $endDate])
    ->get()->toArray();
  $data = array_reduce($stats, function ($carry, $item) {
    $carry[$item->advertiser_code][] = $item;
    return $carry;
  }, []);
  foreach ($data as $key => $value) {
    $redisCon->rawCommand('hset', 'adv_stats', $key, json_encode($data[$key]));
  }
}
// *** Function End *** //

// Use to get all dashboard filters from advertiser stats set from Redis
// *** Function Start *** //
function advDashboard($uid, $option)
{
  $redisCon = Redis::connection('default');
  // $uid = 'ADV657D38AD13796';
  // $uid = 'ADV6539F679F410A';
  $data = json_decode($redisCon->rawCommand('hget', 'adv_stats', $uid), true);
  $wallet = $redisCon->rawCommand('hget', 'adv_wallet', $uid);
  $gdata = [];
  $cdata = [];
  $country = [];
  $maindata = [
    'data' => [
      'click' => 0,
      'impression' => 0,
      'ctr' => 0,
      'amount' => 0
    ],
    'date' => [],
    'click' => [0],
    'impression' => [0],
  ];
  $totalcampclicks = [];
  $totalcampimp = [];
  $totaldate = [];
  // $option = 30;
  $ndate = date('d-m-Y');
  // $ndate = date('31-01-2024');
  $device = [
    "desktop" => [
      "impression" => 0,
      "click" => 0,
      "percent" => 0,
    ],
    "mobile" => [
      "impression" => 0,
      "click" => 0,
      "percent" => 0,
    ],
    "tablet" => [
      "impression" => 0,
      "click" => 0,
      "percent" => 0,
    ]
  ];

  $os = [
    "linux" => [
      "impression" => 0,
      "click" => 0,
      "percent" => 0,
    ],
    "windows" =>  [
      "impression" => 0,
      "click" => 0,
      "percent" => 0,
    ],
    "android" =>  [
      "impression" => 0,
      "click" => 0,
      "percent" => 0,
    ],
    "apple" =>  [
      "impression" => 0,
      "click" => 0,
      "percent" => 0,
    ]
  ];

  if ($option == 0) {
    $newDate = date("d-m-Y", strtotime($ndate . "-$option day"));
    $sdate = date('Y-m-d', strtotime("-$option day"));
  } else {
    $ddd =  $option - 1;
    $newDate = date("d-m-Y", strtotime($ndate . "-$ddd day"));
    $sdate = date('Y-m-d', strtotime("-$ddd day"));
  }

  $startDate = strtotime($newDate);
  $endDate = strtotime($ndate);

  if (!empty($data)) {
    $filtered_data = array_filter($data, function ($date) use ($newDate, $ndate) {
      $newDates = Carbon::createFromFormat('d-m-Y', $newDate)->format('Y-m-d');
      $ndates = Carbon::createFromFormat('d-m-Y', $ndate)->format('Y-m-d');
      return ($date['udate'] >= $newDates && $date['udate'] <= $ndates);
    });
    foreach ($filtered_data as $key => $value) {
      if (array_key_exists($value['camp_id'], $cdata)) {
        $cdata[$value['camp_id']] = [
          "impamt" => $cdata[$value['camp_id']]['impamt'] + $value['amount'],
          "campaign_id" => $value['camp_id'],
          "campaign_name" => $cdata[$value['camp_id']]['campaign_name'],
          "clickamt" => 0,
          "totalclick" => $cdata[$value['camp_id']]['totalclick'] + $value['clicks'],
          "totalimp" => $cdata[$value['camp_id']]['totalimp'] + $value['impressions'],
        ];
      } else {
        $res = json_decode($redisCon->rawCommand('json.get', '7s_camps:' . $value['camp_id'], '$'), true);
        $cdata[$value['camp_id']] = [
          "impamt" => $value['amount'],
          "campaign_id" => $value['camp_id'],
          "campaign_name" => !empty($res) ? $res[0]['campaign_name'] : '',
          "clickamt" => 0,
          "totalclick" => $value['clicks'],
          "totalimp" => $value['impressions'],
        ];
      }

      if (array_key_exists($value['udate'], $gdata)) {
        $gdata[$value['udate']] = [
          "date" => $value['udate'],
          "imps" => $gdata[$value['udate']]['imps'] + $value['impressions'],
          "click" => $gdata[$value['udate']]['click'] + $value['clicks']
        ];
      } else {
        $gdata[$value['udate']] = [
          "date" => $value['udate'],
          "imps" => $value['impressions'],
          "click" => $value['clicks'],
        ];
      }
    }
    $cdata = array_filter($cdata, function ($item) {
      return $item['campaign_name'] != "";
    });
    arsort($cdata);
    $cdata = array_reduce($cdata, function ($carry, $item) {
      $carry[] = $item;
      return $carry;
    }, []);
    $cdata = array_slice($cdata, 0, 5);
    $imps = array_sum(array_column($filtered_data, 'impressions'));
    $clks = array_sum(array_column($filtered_data, 'clicks'));
    $amts = array_sum(array_column($filtered_data, 'amount'));
    $countries = array_unique(array_column($filtered_data, 'country'));
    $dates = array_unique(array_column($filtered_data, 'udate'));

    if ($imps == 0 || $clks == 0) {
      $ctrs = 0;
    } else {
      $ctrs = ($clks / $imps) * 100;
    }
    $totaldata = [
      'click' => $clks,
      'impression' => $imps,
      'ctr' => number_format($ctrs, 2),
      'amount' => number_format($amts, 2)
    ];

    for ($currentDate = $startDate; $currentDate <= $endDate; $currentDate += (86400)) {
      $xxdate = date('Y-m-d', $currentDate);
      $totaldate[] = $xxdate;

      if (in_array($xxdate, $dates)) {
        $uclick = 0;
        $uimp = 0;
        foreach ($gdata as $imp) {
          if ($imp['date'] == $xxdate) {
            $uclick = $imp['click'];
            $uimp = $imp['imps'];
          }
        }
        $totalcampclicks[] = $uclick;
        $totalcampimp[] = $uimp;
      } else {
        $totalcampclicks[] = 0;
        $totalcampimp[] = 0;
      }
    }
    $maindata = array('data' => $totaldata, 'date' => $totaldate, 'click' => $totalcampclicks, 'impression' => $totalcampimp);
    foreach ($countries as $country) {
      $data2[$country] = array_filter($filtered_data, function ($item) use ($country) {
        return $item['country'] == $country;
      });
      $data3[] = [
        'total' => array_sum(array_column($data2[$country], 'impressions')),
        'country' => $country,
      ];
    }
    !empty($data3) ? arsort($data3) : $data3 = [];
    $country = array_slice($data3, 0, 6);

    $tab = array_filter($filtered_data, function ($item) {
      return $item['device_type'] == "Tablet";
    });
    $mob = array_filter($filtered_data, function ($item) {
      return $item['device_type'] == "Mobile";
    });
    $desk = array_filter($filtered_data, function ($item) {
      return $item['device_type'] == "Desktop";
    });
    $device['tablet'] = [
      'impression' => array_sum(array_column($tab, 'impressions')),
      'click' => array_sum(array_column($tab, 'clicks'))
    ];
    $device['mobile'] = [
      'impression' => array_sum(array_column($mob, 'impressions')),
      'click' => array_sum(array_column($mob, 'clicks'))
    ];
    $device['desktop'] = [
      'impression' => array_sum(array_column($desk, 'impressions')),
      'click' => array_sum(array_column($desk, 'clicks'))
    ];

    // if ($imps > 0) {
    $prc1 = $imps > 0 ? ($device['mobile']['impression'] / $imps) * 100 : 0;
    $device['mobile']['percent']  = number_format($prc1, 2);
    $prc2 = $imps > 0 ? ($device['desktop']['impression'] / $imps) * 100 : 0;
    $device['desktop']['percent']  = number_format($prc2, 2);
    $prc3 = $imps > 0 ? ($device['tablet']['impression'] / $imps) * 100 : 0;
    $device['tablet']['percent']  = number_format($prc3, 2);
    // }

    $android = array_filter($filtered_data, function ($item) {
      return $item['device_os'] == "android";
    });
    $apple = array_filter($filtered_data, function ($item) {
      return $item['device_os'] == "apple";
    });
    $windows = array_filter($filtered_data, function ($item) {
      return $item['device_os'] == "windows";
    });
    $linux = array_filter($filtered_data, function ($item) {
      return $item['device_os'] == "linux";
    });

    $os = [
      "linux" => [
        'impression' => array_sum(array_column($linux, 'impressions')),
        'click' => array_sum(array_column($linux, 'clicks'))
      ],
      "windows" =>  [
        'impression' => array_sum(array_column($windows, 'impressions')),
        'click' => array_sum(array_column($windows, 'clicks'))
      ],
      "android" =>  [
        'impression' => array_sum(array_column($android, 'impressions')),
        'click' => array_sum(array_column($android, 'clicks'))
      ],
      "apple" =>  [
        'impression' => array_sum(array_column($apple, 'impressions')),
        'click' => array_sum(array_column($apple, 'clicks'))
      ]
    ];

    if ($imps > 0) {
      $prc4 = ($os['linux']['impression'] / $imps) * 100;
      $os['linux']['percent']  = number_format($prc4, 2);
      $prc5 = ($os['windows']['impression'] / $imps) * 100;
      $os['windows']['percent']  = number_format($prc5, 2);
      $prc6 = ($os['android']['impression'] / $imps) * 100;
      $os['android']['percent']  = number_format($prc6, 2);
      $prc7 = ($os['apple']['impression'] / $imps) * 100;
      $os['apple']['percent']  = number_format($prc7, 2);
    }
  }

  $return['code'] = 200;
  if ($option == 0) {
    $return['option'] = "Today";
  } else {
    $return['option'] = "$option days";
  }
  $return['country'] = $country;
  $return['device'] = $device;
  $return['os'] = $os;
  $return['graph'] = $maindata;
  $return['topcamp'] = $cdata;
  $return['wallet'] = $wallet;
  return $return;
}
// *** Function End *** //

// Use to add or update advertiser stats and set into Redis
// *** Function Start *** //
function advStatsUpdate($impressionamt, $clickamt, $uni, $uid, $campid, $device_os, $device_type, $ucountry, $date, $click)
{
  $redisCon = Redis::connection('default');
  $data = json_decode($redisCon->rawCommand('hget', 'adv_stats', $uid), true);
  $data = !empty($data) ? $data : [];
  $data = array_reduce($data, function ($carry, $item) {
    $carry[$item['uni_imp_id']] = $item;
    return $carry;
  }, []);
  if (array_key_exists($uni, $data)) {
    if ($click > 0) {
      $data[$uni]['clicks'] = $data[$uni]['clicks'] + 1;
      $data[$uni]['amount'] = $data[$uni]['amount'] + $clickamt;
    } else {
      $data[$uni]['impressions'] = $data[$uni]['impressions'] + 1;
      $data[$uni]['amount'] = $data[$uni]['amount'] + $impressionamt;
    }
  } else {
    $ddd = 31;
    $expire_date = date("Y-m-d", strtotime($date . "-$ddd day"));
    $uni_imp_id = md5($uid . $campid . $device_os . $device_type . $ucountry . date('Ymd', strtotime($date)));
    $stats = [
      'advertiser_code' => $uid,
      'uni_imp_id' => $uni_imp_id,
      'camp_id' => $campid,
      'impressions' => 1,
      'clicks' => 0,
      'amount' => $impressionamt,
      'device_type' => $device_type,
      'device_os' => $device_os,
      'country' => $ucountry,
      'udate' => $date,
    ];
    $data = array_filter($data, function ($item) use ($expire_date) {
      return $item['udate'] != $expire_date;
    });
    $data[] = $stats;
  }
  $data = array_reduce($data, function ($carry, $item) {
    $carry[] = $item;
    return $carry;
  }, []);
  $redisCon->rawCommand('hset', 'adv_stats', $uid, json_encode($data));
  $return = [
    'code' => 200,
    'message' => 'Successfull'
  ];
  return $return;
}
// *** Function End *** //

// Use to add or update publisher stats and set into Redis
// *** Function Start *** //
function pubStatsUpdate($uid, $uni_pub_imp_id, $newamt, $adunit_id, $website_id, $device_os, $device_type, $impressions, $clicks, $country, $date)
{
  $redisCon = Redis::connection('default');
  // $uid = 'PUB6549D736E1EC0';
  // $uni_pub_imp_id = '02f91998db7d0062146fa4551287bc7a';
  // $newamt = 0.001;
  // $adunit_id = '7SAD1565CC4E2155F1A';
  // $website_id = '7SWB106549E8AFEBFED';
  // $device_os = 'windows';
  // $device_type = 'Desktop';
  // $impressions = 1;
  // $clicks = 1;
  // $country = 'UNITED STATES';
  // $date = '2024-03-03';

  /**** pub_stats set data get ****/
  $data = json_decode($redisCon->rawCommand('hget', 'pub_stats', $uid), true);

  /**** pub_stats set data get for adScript ****/
  $data = !empty($data) ? $data : [];
  $data = array_reduce($data, function ($carry, $item) {
    $carry[$item['uni_pub_imp_id']] = $item;
    return $carry;
  }, []);

  if (array_key_exists($uni_pub_imp_id, $data)) {
    if ($clicks > 0) {
      $data[$uni_pub_imp_id]['clicks'] = $data[$uni_pub_imp_id]['clicks'] + 1;
      $data[$uni_pub_imp_id]['amount'] = $data[$uni_pub_imp_id]['amount'] + $newamt;
    } else {
      $data[$uni_pub_imp_id]['impressions'] = $data[$uni_pub_imp_id]['impressions'] + 1;
      $data[$uni_pub_imp_id]['amount'] = $data[$uni_pub_imp_id]['amount'] + $newamt;
    }
  } else {
    $ddd = 31;
    $expire_date = date("Y-m-d", strtotime($date . "-$ddd day"));
    $pub_uni_id = md5($uid . $adunit_id . $device_type . $device_os . $country . $date);
    $insert = [
      'publisher_code' => $uid,
      'uni_pub_imp_id' => $pub_uni_id,
      'adunit_id' => $adunit_id,
      'website_id' => $website_id,
      'device_type' => $device_type,
      'device_os' => $device_os,
      'impressions' => $impressions,
      'clicks' => 0,
      'amount' => $newamt,
      'country' => $country,
      'udate' => $date
    ];
    $data = array_filter($data, function ($item) use ($expire_date) {
      return $item['udate'] != $expire_date;
    });
    $data[] = $insert;
  }
  $data = array_reduce($data, function ($carry, $item) {
    $carry[] = $item;
    return $carry;
  }, []);
  $redisCon->rawCommand('hset', 'pub_stats', $uid, json_encode($data));
  $return = [
    'code' => 200,
    'message' => 'Successfull'
  ];
  return $return;
}
// *** Function End *** //

//Use to remove Publisher all data when it becomes inactive from Redis
// *** Function Start *** //
function delWebdata()
{
  $uid = "PUB65417DC4B4BF6";
  $webdata = json_decode(Redis::get('webdata'), true);
  $data = array_filter($webdata, function ($item) use ($uid) {
    return $item['uid'] != $uid;
  });
  // Redis::set('webdata', json_encode($data));
  print_r($data);
  exit();
}
// *** Function End *** //

// Use to set category table data into Redis
// *** Function Start *** //
function setCategory()
{
  $redisCon = Redis::connection('default');
  $categ = DB::table('categories')->select('id', 'cat_name', 'cpm', 'cpc', 'cpa_imp', 'cpa_click', 'video_adv', 'video_pub', 'pub_cpm', 'pub_cpc', 'display_brand')
    ->where('status', 1)->where('trash', 0)->get()->toArray();
  $data = json_decode(json_encode($categ), true);

  foreach ($data as $value) {
    $redisCon->rawCommand('hset', 'categories_data', $value['id'], json_encode($value));
  }
  return ['code' => 200, 'message' => 'Category set into redis Successfully'];
}
// *** Function End *** //

// Use to update category table data into Redis
// *** Function Start *** //
function updateCategory($category_id, $status)
{
  $redisCon = Redis::connection('default');
  $redisCon->rawCommand('hdel', 'categories_data', $category_id);
  if ($status == 1) {
    $categ = DB::table('categories')->select('id', 'cat_name', 'cpm', 'cpc', 'cpa_imp', 'cpa_click', 'video_adv', 'video_pub', 'pub_cpm', 'pub_cpc', 'display_brand')
      ->where('id', $category_id)->where('status', 1)->where('trash', 0)->first();
    if (!empty($categ)) {
      $cat = json_decode(json_encode($categ), true);
      $redisCon->rawCommand('hset', 'categories_data', $category_id, json_encode($cat));
    }
  }

  $return = [
    'code' => 200,
    'message' => 'Category updated successfully!'
  ];
  return $return;
}
// *** Function End *** //

//Use to set countries data into Redis
// *** Function Start *** //
function setCountries()
{
  $redisCon = Redis::connection('default');
  $countries =  Country::get()->toArray();
  foreach ($countries as $key => $value) {
    $redisCon->rawCommand('hset', 'countries', $value['iso'], json_encode($value));
  }
}
// *** Function End *** //

//Use to update countries data into Redis
// *** Function Start *** //
function updateCountries($id, $status)
{
  $redisCon = Redis::connection('default');
  $countries =  Country::where('id', $id)->first();
  $redisCon->rawCommand('hdel', 'countries', $countries->iso);
  if ($status == 1) {
    $redisCon->rawCommand('hset', 'countries', $countries->iso, json_encode($countries));
  }
}
// *** Function End *** //

// Use to set Ad Rate table data into Redis
// *** Function Start *** //
function setAdRate()
{
  $redisCon = Redis::connection('default');
  $ad_rate = DB::table('pub_rate_masters')->select('pub_rate_masters.category_id', 'pub_rate_masters.category_name', 'pub_rate_masters.country_id', 'pub_rate_masters.country_name', 'pub_rate_masters.cpc', 'pub_rate_masters.cpm', 'pub_rate_masters.cpa_imp', 'pub_rate_masters.cpa_click', 'pub_rate_masters.video_adv', 'pub_rate_masters.video_pub', 'pub_rate_masters.pub_cpm', 'pub_rate_masters.pub_cpc', 'countries.iso')
    ->join('countries', 'pub_rate_masters.country_id', '=', 'countries.id')
    ->where('pub_rate_masters.status', 0)->get()->toArray();

  foreach ($ad_rate as $value) {
    $dt = md5($value->category_id . $value->iso);
    $redisCon->rawCommand('hset', 'pub_rate_masters', $dt, json_encode($value));
  }
  return ['code' => 200, 'message' => 'Ad rate set into redis Successfully'];
}
// *** Function End *** //

// Use to update Ad Rate table data into Redis
// *** Function Start *** //
function updateAdRate($category_id, $country_id, $status)
{
  $redisCon = Redis::connection('default');
  $ad_rate = DB::table('pub_rate_masters')->select('pub_rate_masters.category_id', 'pub_rate_masters.category_name', 'pub_rate_masters.country_id', 'pub_rate_masters.country_name', 'pub_rate_masters.cpc', 'pub_rate_masters.cpm', 'pub_rate_masters.cpa_imp', 'pub_rate_masters.cpa_click', 'pub_rate_masters.video_adv', 'pub_rate_masters.video_pub', 'pub_rate_masters.pub_cpm', 'pub_rate_masters.pub_cpc', 'countries.iso')
    ->join('countries', 'pub_rate_masters.country_id', '=', 'countries.id')
    ->where('pub_rate_masters.category_id', $category_id)->where('pub_rate_masters.country_id', $country_id)->first();
  $dt = md5($category_id . $ad_rate->iso);
  $redisCon->rawCommand('hdel', 'pub_rate_masters', $dt);
  if ($status == 0) {
    if (!empty($ad_rate)) {
      $dt = md5($ad_rate->category_id . $ad_rate->iso);
      $redisCon->rawCommand('hset', 'pub_rate_masters', $dt, json_encode($ad_rate));
    }
  }
  $return = [
    'code' => 200,
    'message' => 'Ad Rate updated successfully!'
  ];
  return $return;
}
// *** Function End *** //

// Use to set Advertiser and Publisher wallet into Redis
// *** Function Start *** //
function setAdvPubWallet()
{
  $redisCon = Redis::connection('default');
  $users = DB::table('users')->select('uid', 'wallet', 'pub_wallet')->get()->toArray();
  foreach ($users as $user) {
    $redisCon->rawCommand('hset', 'adv_wallet', $user->uid, $user->wallet);
    $redisCon->rawCommand('hset', 'pub_wallet', $user->uid, $user->pub_wallet);
  }
}
// *** Function End *** //

// Use to update Advertiser wallet into Redis
// *** Function Start *** //
function updateAdvWallet($advertiser_code, $amount)
{
  $redisCon = Redis::connection('default');
  $redisCon->rawCommand('hincrbyfloat', 'adv_wallet',  $advertiser_code,  $amount);
}
// *** Function End *** //

// ========================//
// **** Ashraf Function ***//
// =======================//

// Use to set daily budget utilize data into Redis
// *** Function Start *** //
function setDailyBudgetUtlize()
{
  $redisCon = Redis::connection('default');
  $udata = DB::table('camp_budget_utilize')->select('*')->get()->toArray();
  // echo count($udata);
  // $udata = array_column($udata, 'uid');
  // print_r($udata);

  $gdata = array_reduce($udata, function ($carry, $item) {
    unset($item->id);
    $carry[$item->advertiser_code][] = $item;
    return $carry;
  }, []);

  // print_r($gdata);
  foreach ($gdata as $key => $val) {
    unset($val['id']);
    // echo $val->uid;
    // $redisCon->rawCommand('hset', 'adv_wallet', $val->uid, $val->wallet);
    // $redisCon->rawCommand('hset', 'pub_wallet', $val->uid, $val->pub_wallet);
    unset($val->wallet);
    unset($val->pub_wallet);
    // print_r($val);
    $redisCon->rawCommand('hset', 'budget_utilize', $key, json_encode($val));
  }
}
// *** Function End *** //

// Use to get the remaining time until the end of the day in seconds
// *** Function Start *** //
function getEndOfDayInSeconds()
{
  $currentTimestamp = time();
  $endOfDayTimestamp = strtotime('tomorrow') - 1;
  $secondsLeft = $endOfDayTimestamp - $currentTimestamp;
  return $secondsLeft;
}
// *** Function End *** //

// Use to set daily budget utilize data into Redis
// *** Function Start *** //
function setDailyBudgetOld()
{
  $redisCon = Redis::connection('default');
  $udata = DB::table('camp_budget_utilize')->select('*')->get()->toArray();

  $gdata = array_reduce($udata, function ($carry, $item) {
    unset($item->id);
    $carry[$item->advertiser_code][] = $item;
    return $carry;
  }, []);

  foreach ($gdata as $key => $val) {
    unset($val['id']);
    // echo $val->uid;
    // $redisCon->rawCommand('hset', 'adv_wallet', $val->uid, $val->wallet);
    // $redisCon->rawCommand('hset', 'pub_wallet', $val->uid, $val->pub_wallet);
    // unset($val->wallet);
    // unset($val->pub_wallet);
    // print_r($val);
    $redisCon->rawCommand('hset', 'budget_utilize', $key, json_encode($val));
  }
}
function setDailyBudget()
{
  $redisCon = Redis::connection('default');
  $udata = DB::table('camp_budget_utilize')->select('advertiser_code', 'camp_id', 'amount', 'udate')->whereDate('udate', date('Y-m-d'))->get()->toArray();
  foreach ($udata as $key => $val) {
    $time = getEndOfDayInSeconds();
    $md = md5($val->advertiser_code . $val->camp_id . date('Ymd'));
    $redisCon->rawCommand('setex', "budget_utilize:" . $md, $time, $val->amount);
  }
  return ['message' => 'Daily budget set successfully'];
}
// *** Function End *** //

// Use to get ads spent amount data from Redis
// *** Function Start *** //
function getDailyBudget($adv_id, $camp_id)
{
  $redisCon = Redis::connection('default');
  // $data = json_decode($redisCon->rawCommand('hget', 'budget_utilize', $adv_id), true);
  // // $date = date('Y-m-d');
  // $date = date('Y-m-d');
  // $amt = 0;
  // if (!empty($data)) {
  //   $data = array_reduce($data, function ($carry, $item) {
  //     // $carry[$item['camp_id']][] = $item;
  //     $carry[$item['camp_id']][$item['udate']] = $item;
  //     return $carry;
  //   }, []);

  //   if (array_key_exists($camp_id, $data)) {
  //     if (array_key_exists($date, $data[$camp_id])) {
  //       $amt = $data[$camp_id][$date]['amount'];
  //     }
  //     // foreach ($data[$camp_id] as $val) {
  //     //   if ($val['udate'] == $date) {
  //     //     $amt = $val['amount'];
  //     //   }
  //     // }
  //   } else {
  //     $amt = 0;
  //   }
  // }

  $amt = 0;
  $uni_bd_id = md5($adv_id . $camp_id . date('Ymd'));
  $data = $redisCon->rawCommand('get', 'budget_utilize:' . $uni_bd_id);
  if (!empty($data)) {
    $amt = $data;
  }
  return $amt;
}
// *** Function End *** //

// Use to get ads spent amount for Admin data from Redis
// *** Function Start *** //
function getDailyBudgetAdmin($adv_id, $camp_id, $dates)
{
  $redisCon = Redis::connection('default');
  //$data = json_decode($redisCon->rawCommand('hget', 'budget_utilize', $adv_id), true);
  // $date = date('Y-m-d', strtotime($dates));
  // $amt = 0;
  // if (!empty($data)) {
  //   $data = array_reduce($data, function ($carry, $item) {
  //     $carry[$item['camp_id']][] = $item;
  //     return $carry;
  //   }, []);

  //   if (array_key_exists($camp_id, $data)) {
  //     foreach ($data[$camp_id] as $val) {
  //       if ($val['udate'] == $date) {
  //         $amt = $val['amount'];
  //       }
  //     }
  //   } else {
  //     $amt = 0;
  //   }
  // }
  $amt = 0;
  $uni_bd_id = md5($adv_id . $camp_id . date('Ymd'));
  $data = $redisCon->rawCommand('get', 'budget_utilize:' . $uni_bd_id);
  if (!empty($data)) {
    $amt = $data;
  }
  return $amt;
}
// *** Function End *** //

// Use to set Ad impressions into Redis
// *** Function Start *** //
function setImpression($impData, $pricing_model, $country, $adv_cpm, $pub_cpm)
{
  $redisCon = Redis::connection('default');

  $cpm = ($adv_cpm * $pub_cpm) / 100;

  $redisCon->rawCommand('hincrbyfloat', 'adv_wallet',  $impData['advertiser_code'], '-' . $adv_cpm);
  $redisCon->rawCommand('hincrbyfloat', 'pub_wallet',  $impData['publisher_code'], $cpm);
  $uniDate = date('Ymd');
  $uni_imp_id = md5($impData['advertiser_code'] . $impData['campaign_id'] . $impData['device_os'] . $impData['device_type'] . $impData['country'] . $uniDate);
  $pub_uni_id = md5($impData['publisher_code'] . $impData['adunit_id'] . $impData['device_type'] . $impData['device_os'] .  $impData['country'] . $uniDate);
  $uni_bd_id = md5($impData['advertiser_code'] . $impData['campaign_id'] . $uniDate);
  $impData['pub_imp_credit'] = $cpm;
  $impData['uni_imp_id'] = $uni_imp_id;
  $impData['uni_bd_id'] = $uni_bd_id;
  $impData['created_at'] = date('Y-m-d h:i:s');
  $date = date('Y-m-d');
  $redisCon->rawCommand('json.arrinsert', "impressions", '$', '0', json_encode($impData));
  $data = $redisCon->rawCommand('get', 'budget_utilize:' . $uni_bd_id);
  if (!empty($data)) {
    $totalSpent = $data + $adv_cpm;
  } else {
    $totalSpent = $adv_cpm;
  }
  $time = getEndOfDayInSeconds();
  $redisCon->rawCommand('setex', "budget_utilize:" . $uni_bd_id, $time, $totalSpent);
  // $data = json_decode($redisCon->rawCommand('hget', 'budget_utilize', $impData['advertiser_code']), true);
  // if (!empty($data)) {
  //   $data2 = array_reduce($data, function ($carry, $item) {
  //     $carry[$item['camp_id']][] = $item;
  //     return $carry;
  //   }, []);

  //   if (array_key_exists($impData['campaign_id'], $data2)) {
  //     foreach ($data2[$impData['campaign_id']] as $val) {
  //       if ($val['udate'] == $date) {
  //         $camp = $val;
  //       }
  //     }
  //   } else {
  //     $camp = [];
  //   }

  //   if (!empty($camp)) {

  //     $camp['amount'] = $camp['amount'] + $adv_cpm;
  //     $camp['impressions'] = $camp['impressions'] + 1;
  //     $camp['imp_amount'] = $camp['imp_amount'] + $adv_cpm;

  //     foreach ($data as $row) {
  //       if ($row['uni_bd_id'] != $uni_bd_id) {
  //         $data4[] = $row;
  //       } else {
  //         $data4[] = $camp;
  //       }
  //     }

  //     $data = $data4;
  //   } else {
  //     $data3 = [
  //       "uni_bd_id" => $uni_bd_id,
  //       "advertiser_code" => $impData['advertiser_code'],
  //       "camp_id" => $impData['campaign_id'],
  //       "impressions" => 1,
  //       "clicks" => 0,
  //       "imp_amount" => $adv_cpm,
  //       "click_amount" => 0,
  //       "amount" => $adv_cpm,
  //       "udate" => $date
  //     ];

  //     $data[] = $data3;
  //   }
  // } else {
  //   $data3 = [
  //     "uni_bd_id" => $uni_bd_id,
  //     "advertiser_code" => $impData['advertiser_code'],
  //     "camp_id" => $impData['campaign_id'],
  //     "impressions" => 1,
  //     "clicks" => 0,
  //     "imp_amount" => $adv_cpm,
  //     "click_amount" => 0,
  //     "amount" => $adv_cpm,
  //     "udate" => $date
  //   ];

  //   $data[] = $data3;
  // }
  // $redisCon->rawCommand('hset', 'budget_utilize', $impData['advertiser_code'], json_encode($data));
  //   advStatsUpdate($adv_cpm, 0, $uni_imp_id, $impData['advertiser_code'], $impData['campaign_id'], $impData['device_os'], $impData['device_type'], $impData['country'], $date, 0);
  //   pubStatsUpdate($impData['publisher_code'], $pub_uni_id, $cpm, $impData['adunit_id'], $impData['website_id'], $impData['device_os'], $impData['device_type'], 1, 0, $impData['country'], $date);
  //   $data2 = $redisCon->rawCommand('hget', "budget_utilize", $impData['advertiser_code']);
  //   print_r($data2);

  //   print_r($data3);
}
// *** Function End *** //


/* Generate support pin */
function generateSupportPin()
{
  $support_pin = rand(100000, 999999);
  $checkdata = DB::table("users")->where('support_pin', $support_pin)->count();
  if ($checkdata > 0) {
    return generateSupportPin();
  } else {
    return $support_pin;
  }
}

/* Generate Employee id */
function randomEmpid()
{
  $characters = "0123456789";
  $random_chars = '';
  for ($i = 0; $i < 4; $i++) {
    $random_chars .= $characters[rand(0, strlen($characters) - 1)];
  }
  $emp_id = ('EMP' . $random_chars);
  $checkdata = DB::table('admins')->where('emp_id', $emp_id)->count();
  if ($checkdata > 0) {
    return randomEmpid();
  } else {
    return substr($emp_id, 0, 7);
  }
}

function setCountryIso($countries)
{
  $array = explode(",", $countries);
  $iso = Country::select('iso')->whereIn('id', $array)->get()->toArray();
  $iso = array_reduce($iso, function ($carry, $item) {
    $carry[] = $item['iso'];
    return $carry;
  }, []);
  $data = implode(",", $iso);
  return $data;
}

function updateIso()
{
  $campaigns = Campaign::where('countries', '!=', 'All')->get();
  foreach ($campaigns as $campaign) {
    if ($campaign->country_ids != null) {
      $data = setCountryIso($campaign->country_ids);
      $campaign->country_code = $data;
      $campaign->save();
    }
  }
  print_r('data set successfully!');
}

function sendFcmNotificationAdmin($title, $msg, $tokens)
{
  $curl = curl_init();
  $data = json_encode([
    "registration_ids" => $tokens,
    "notification" => [
      "body" => $msg,
      "title" => $title,
      'icon' => 'https://7searchppc.com/assets/img/logo.png',
      'click_action' => 'https://advertiser.7searchppc.in/inbox'
    ],
    "fcmOptions" => [
      'link' => 'https://advertiser.7searchppc.in/inbox'
    ]
  ]);
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://fcm.googleapis.com/fcm/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => array(
      'Authorization: key=AAAAVYh9IkY:APA91bHbyuOioazKL-_Jhwy7kpZ0vzq9wkIzYHeeUZN2H_9a2fCQK92cp7Ywm4Yg0ERmsVRsZep_KAw2YvpIE-6XXAW1igs4KJXFir6Uf-PEytCQCb3_WGGgbeJA1qKqbroFUqnMOi1p',
      'Content-Type: application/json'
    ),
  ));
  $response = curl_exec($curl);
  curl_close($curl);
  $response;
}

/* Generate Bank Id */
function generateBankId()
{

  $bank_id = 'BNK' . rand(10, 999);
  $checkdata = DB::table('admin_bank_details')->select('bank_id')->where('bank_id', $bank_id)->count();
  if ($checkdata > 0) {
    return generateBankId();
  } else {
    return $bank_id;
  }
}

// number two words
function numberToWords($num)
{
  $belowTwenty = [
    "",
    "One",
    "Two",
    "Three",
    "Four",
    "Five",
    "Six",
    "Seven",
    "Eight",
    "Nine",
    "Ten",
    "Eleven",
    "Twelve",
    "Thirteen",
    "Fourteen",
    "Fifteen",
    "Sixteen",
    "Seventeen",
    "Eighteen",
    "Nineteen"
  ];
  $tens = [
    "",
    "",
    "Twenty",
    "Thirty",
    "Forty",
    "Fifty",
    "Sixty",
    "Seventy",
    "Eighty",
    "Ninety"
  ];
  $thousands = [
    "",
    "Thousand",
    "Million",
    "Billion"
  ];

  if ($num == 0) return "Zero";

  function helper($n, $belowTwenty, $tens, $thousands)
  {
    if ($n < 20) return $belowTwenty[$n];
    if ($n < 100) return $tens[intval($n / 10)] . ($n % 10 == 0 ? "" : " " . $belowTwenty[$n % 10]);
    if ($n < 1000) return $belowTwenty[intval($n / 100)] . " Hundred" . ($n % 100 == 0 ? "" : " " . helper($n % 100, $belowTwenty, $tens, $thousands));
    for ($i = 0; $i < count($thousands); $i++) {
      $unit = pow(1000, $i);
      if ($n < $unit * 1000) return helper(intval($n / $unit), $belowTwenty, $tens, $thousands) . " " . $thousands[$i] . ($n % $unit == 0 ? "" : " " . helper($n % $unit, $belowTwenty, $tens, $thousands));
    }
  }

  return helper($num, $belowTwenty, $tens, $thousands);
}

// store images in resources server
function storeImages($folderName, $file)
{
  $REGION = '';  // If German region, set this to an empty string: ''
  $BASE_HOSTNAME = env('STORAGE_BASE_HOSTNAME');
  $HOSTNAME = (!empty($REGION)) ? "{$REGION}.{$BASE_HOSTNAME}" : $BASE_HOSTNAME;
  $STORAGE_ZONE_NAME = env('STORAGE_ZONE_NAME');
  $ACCESS_KEY = env('STORAGE_ACCESS_KEY');
  $FILE_PATH = public_path('storeimages/' . $file);  // Full path to your local file
  $FILENAME_TO_UPLOAD = $folderName . '/' . $file;

  $url = "http://{$HOSTNAME}/{$STORAGE_ZONE_NAME}/{$FILENAME_TO_UPLOAD}";
  $ch = curl_init();
  $options = array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_PUT => true,
    CURLOPT_INFILE => fopen($FILE_PATH, 'r'),
    CURLOPT_INFILESIZE => filesize($FILE_PATH),
    CURLOPT_HTTPHEADER => array(
      "AccessKey: {$ACCESS_KEY}",
      'Content-Type: application/octet-stream'
    )
  );

  curl_setopt_array($ch, $options);
  $response = curl_exec($ch);
  if (!$response) {
    die("Error: " . curl_error($ch));
  } else {
    unlink('./storeimages/' . $file);
    $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // print_r($response);
    return $responseCode;
  }
  curl_close($ch);
}

// delete store images
function delstoreImages($folderName, $fileName)
{
  $REGION = '';  // If using German region, set this to an empty string: ''
  $BASE_HOSTNAME = env('STORAGE_BASE_HOSTNAME');
  $HOSTNAME = (!empty($REGION)) ? "{$REGION}.{$BASE_HOSTNAME}" : $BASE_HOSTNAME;
  $STORAGE_ZONE_NAME = env('STORAGE_ZONE_NAME');
  $ACCESS_KEY = env('STORAGE_ACCESS_KEY');
  $FILE_PATH = $folderName . '/' . $fileName;  // Path to your file in the storage zone
  $url = "http://{$HOSTNAME}/{$STORAGE_ZONE_NAME}/{$FILE_PATH}";
  $ch = curl_init();
  $options = array(
    CURLOPT_URL => $url,
    CURLOPT_CUSTOMREQUEST => "DELETE",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array(
      "AccessKey: {$ACCESS_KEY}"
    ),
  );
  curl_setopt_array($ch, $options);
  $response = curl_exec($ch);
  $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if (!$response) {
    die("Error: " . curl_error($ch));
  } else {
    return $responseCode;
  }
  curl_close($ch);
}
function PaymentHoldUsers($uid)
{
  // Check today's pending and failed transactions count for the user in a single query
  $today = date('Y-m-d');
  $statusArray = [0, 2]; // Assuming '0' is for pending and '2' is for failed transactions
  $transactionCounts = DB::table('transactions')
    ->where('advertiser_code', $uid)
    ->whereIn('status', $statusArray)
    ->whereDate('created_at', $today)
    ->selectRaw('SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS pending_count, SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS failed_count')
    ->first();
  $pendingCount = $transactionCounts->pending_count ?? 0;
  $failedCount = $transactionCounts->failed_count ?? 0;
  $totalCount = $pendingCount + $failedCount;
  $transactionLimit = 2; // Define your limit here
  if ($totalCount >= $transactionLimit) {
    // Suspend the user's account
    $updatestatus = DB::table('users')->where('uid', $uid)->update(['status' => 4]); // Update this line based on your status field type
    if ($updatestatus) {
      // $adminmail1 = 'advertisersupport@7searchppc.com';
      $adminmail1 = 'rajeevgp1596@gmail.com';
      $adminmail2 = 'ry0085840@gmail.com';
      $data['details'] = array('pendingCount' => $pendingCount, 'failedCount' => $failedCount, 'totalCount' => $totalCount, 'usersid' => $uid);
      $bodyadmin =   View('emailtemp.accountTemporarilyHoldAdmin', $data);
      $subjectadmin = 'Multiple Un-Successful Payment Attempts Detected on User Account - 7Search PPC';
      sendmailAdmin($subjectadmin, $bodyadmin, $adminmail1, $adminmail2);
      $user = User::where('uid', $uid)->first();
      $email = $user->email;
      $fullname = "$user->first_name $user->last_name";
      $subjects = "Account Temporarily On Hold Due to Multiple Un-Successful Payment Attempts - 7Search PPC";
      $fullname = $user->first_name . ' ' . $user->last_namel;
      $data['details'] = array('subject' => $subjects, 'fullname' => $fullname, 'user' => $user);
      $body = View('emailtemp.accountTemporarilyHold', $data);
      sendmailUser($subjects, $body, $email);
    }
    $return['code'] = 102;
    $return['message'] = 'Transaction limit exceeded for today. Your account has been suspended.';
    return response()->json($return);
  }
}
function checkPayAttempts($uid)
{
  $restriction = DB::table('panel_customizations')->select('trans_attempt')->first();
  // Check today's pending and failed transactions count for the user in a single query
  $today             = date('Y-m-d');
  $statusArray       = [0, 2]; // Assuming '0' is for pending and '2' is for failed transactions
  $transactionLimit  = $restriction->trans_attempt; // Define your limit here
  $transactionCounts = DB::table('transactions')
    ->where('advertiser_code', $uid)
    ->whereIn('status', $statusArray)
    ->where('payment_lock', 0)
    ->whereDate('created_at', $today)
    ->selectRaw('SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS pending_count, SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS failed_count')
    ->first();
  $pendingCount = $transactionCounts->pending_count ?? 0;
  $failedCount  = $transactionCounts->failed_count ?? 0;
  $totalCount   = $pendingCount + $failedCount;
  if ($totalCount >= $transactionLimit) {
    // Suspend the user's account
    $updatestatus = DB::table('users')->where('uid', $uid)->update(['payment_lock' => 1, 'payment_lock_at' => date('Y-m-d H:i:s')]); // Update this line based on your status field type
    if ($updatestatus) {
      // $adminmail1 = 'advertisersupport@7searchppc.com';
      $adminmail1 = 'rajeevgp1596@gmail.com';
      $adminmail2 = 'ry0085840@gmail.com';
      $data['details'] = array('usersid' => $uid, 'payment_lock_at'=> date('d-m-Y, h:i:s A'));
      $bodyadmin =   View('emailtemp.paymentlockadmin', $data);
      $subjectadmin = 'User Payment Page Access Blocked - Multiple Payment Attempts - 7Search PPC';
      sendmailAdmin($subjectadmin, $bodyadmin, $adminmail1, $adminmail2);
      $user = User::where('uid', $uid)->first();
      $email = $user->email;
      $fullname = "$user->first_name $user->last_name";
      $subjects = "Your Payment Page Access is Temporarily Locked - 7Search PPC";
      $fullname = $user->first_name . ' ' . $user->last_namel;
      $data['details'] = array('subject' => $subjects, 'fullname' => $fullname, 'user' => $user);
      $body = View('emailtemp.paymentlockuser', $data);
      sendmailUser($subjects, $body, $email);
      // send notification to user
      $noti_title = "Your payment page access is temporarily locked.";
      $noti_desc  = "Dear advertiser, your payment page access has been temporarily locked due to multiple payment attempts.";
      $notification = new Notification();
      $notification->notif_id = gennotificationuniq();
      $notification->title = $noti_title;
      $notification->noti_desc = $noti_desc;
      $notification->noti_type = 1;
      $notification->noti_for = 1;
      $notification->all_users = 0;
      $notification->status = 1;
      $notification->uid = $uid;
      if ($notification->save()) {
          $noti = new UserNotification();
          $noti->notifuser_id = gennotificationuseruniq();
          $noti->noti_id = $notification->id;
          $noti->user_id = $uid;
          $noti->user_type = 1;
          $noti->view = 0;
          $noti->created_at = Carbon::now();
          $noti->updated_at = now();
          $noti->save();
      }
    }
    $return['code']    = 102;
    $return['message'] = 'Transaction limit exceeded for today. Your account has been suspended.';
    return $return;
  }
  $return['code']      = 200;
  $return['message']   = 'Payment Attempts not exceeded.';
  return $return;
}
function onchangecpcValidation($typenames, $catnames, $countrys)
{
        $typename   = $typenames;
        $catname    = $catnames;
        if($countrys === 'All'){
            $country    = []; 
        }else{
            $country[]    = $countrys;
        }
        // dd( $country);
        $catid = Category::where('cat_name', $catname)->where('status', 1)->where('trash', 0)->first();
        if (empty($catid)) {
            $return['code'] = 101;
            $return['message'] = 'Not Found Category Name!';
            return json_encode($return);
        }
         if($typename != 'CPM' && $typename != 'CPC'){
            $return['code'] = 101;
            $return['message'] = 'Invalid type name, allow only- CPM or CPC!';
            return json_encode($return);
        }
        $cpcid = $catid->id;
        $query = DB::table('campaigns')
        ->select('cpc_amt')
        ->where('website_category', $cpcid)
        ->where('trash', 0)
        ->whereIn('status', [2, 4])
        ->where('pricing_model', $typename)
        ->orderBy('cpc_amt', 'DESC')
        ->limit(5)
        ->distinct()
        ->get();
        if (empty($country)) {
            if ($typename == 'CPC') {
                $cpcamt = $catid->cpc;
                if (empty($query)) {
                    $return['code'] = 200;
                    $return['base_amt'] = $cpcamt;
                    $return['high_amt'] = $cpcamt;
                    $return['message'] = 'Successfully found !';
                    return json_encode($return, JSON_NUMERIC_CHECK);
                } else { 
                    $campcpcamt = $query;
                    $return['code'] = 200;
                    $return['base_amt'] = $cpcamt;
                    $return['high_amt'] = $campcpcamt;
                    $return['message'] = 'Successfully found !';
                }
                return json_encode($return, JSON_NUMERIC_CHECK);
            } elseif ($typename == 'CPM') {
                $cpmamt = $catid->cpm;

                if (empty($query)) {
                    $return['code'] = 200;
                    $return['base_amt'] = $cpmamt;
                    $return['high_amt'] = $cpmamt;
                    $return['message'] = 'Successfully found !';
                    return json_encode($return, JSON_NUMERIC_CHECK);
                } else {
                    $campcpcamt = $query;
                    $return['code'] = 200;
                    $return['base_amt'] = $cpmamt;
                    $return['high_amt'] = $campcpcamt;
                    $return['message'] = 'Successfully found !';
                }
            } else {
                $return['code'] = 101;
                $return['message'] = 'Invalid Format';
            }
        }else{
              if (is_array($country) && isset($country[0])) {
                if (is_string($country[0])) {
                    $decodedCountries = json_decode($country[0], true);
                    if (is_array($decodedCountries)) {
                        $country = array_map(function($item) {
                            return $item['label'] ?? null;
                        }, $decodedCountries);
                        $country = array_filter($country);
                    }
                } else {
                    $country = $country[0];
                }
            }
            $pubrateData = DB::table('pub_rate_masters')
                ->select(
                    'category_id',
                    'category_name',
                    'country_name',
                    DB::raw('MAX(ss_pub_rate_masters.cpm) as cpm_amt'),
                    DB::raw('MAX(ss_pub_rate_masters.cpc) as cpc_amt')
                )
                ->where("category_name", $catname)
                ->whereIn("country_name", $country)
                ->where('status', 0)->first();
            $cpmamt = $pubrateData->cpm_amt;
            $cpcamt = $pubrateData->cpc_amt;
            $cpcid = $pubrateData->category_id;
            $query2 = DB::table('campaigns')
            ->select(DB::raw('MAX(cpc_amt) as max_amt'))
            ->where('website_category', $cpcid)
            ->where('trash', 0)
            ->where('status', 2)
            ->where('pricing_model', $typename)
            ->whereIn('country_name',$country)
            ->first();
            if(!empty($pubrateData) && !empty($cpmamt) && !empty($cpcamt))
            {
                if($typename == 'CPM')
                {
                    $return['code'] = 200;
                    $return['base_amt'] = $cpmamt;
                    $return['high_amt'] = $query;
                    $return['message'] = "CPM Data found successfully.";
                }
                else {
                    $return['code'] = 200;
                    $return['base_amt'] = $cpcamt;
                    $return['high_amt'] = $query;
                    $return['message'] = "CPC Data found successfully.";
                }
            }
            else
            {
                if ($typename == 'CPC') {
                    $cpcamt = $catid->cpc;
                    if (empty($query)) {
                        $return['code'] = 200;
                        $return['base_amt'] = $cpcamt;
                        $return['high_amt'] = $cpcamt;
                        $return['message'] = 'Successfully found !';
                        return json_encode($return, JSON_NUMERIC_CHECK);
                    } else {
                        $campcpcamt = $query;
                        $return['code'] = 200;
                        $return['base_amt'] = $cpcamt;
                        $return['high_amt'] = $campcpcamt;
                        $return['message'] = 'Successfully found !';
                    }
                    return json_encode($return, JSON_NUMERIC_CHECK);
                } elseif ($typename == 'CPM') {

                    $cpmamt = $catid->cpm;

                    if (empty($query)) {
                        $return['code'] = 200;
                        $return['base_amt'] = $cpmamt;
                        $return['high_amt'] = $cpmamt;
                        $return['message'] = 'Successfully found !';
                        return json_encode($return, JSON_NUMERIC_CHECK);
                    } else {
                        $campcpcamt = $query;
                        $return['code'] = 200;
                        $return['base_amt'] = $cpmamt;
                        $return['high_amt'] = $campcpcamt;
                        $return['message'] = 'Successfully found !';
                    }
                } else {
                    $return['code'] = 101;
                    $return['message'] = 'Invalid Format';
                }
            }
        }
      return json_encode($return, JSON_NUMERIC_CHECK);
}
function deviceValidating($dtype,$dos){
  $res = [];
  foreach ($dtype as $value) {
      if ($value === 'Desktop') {
          $res[] = ['apple', 'linux', 'windows'];
      } elseif ($value === 'Mobile') {
          $res[] = ['android', 'apple'];
      } elseif ($value === 'Tablet') {
          $res[] = ['android', 'apple'];
      }
  }
  $uniqueOS = collect($res)->flatten()->unique()->values()->all();
  $matchingValues = array_intersect($uniqueOS, $dos);
  return $matchingValues;
}
// Transfer to Referral Partner Commission
function referral_commission($uid, $referal_code, $amounts){
  // == advertiser & both user ==
  $user = User::whereNotNull("referal_code")->whereIn('user_type',[1,3])->first();
  if($user){
  $url = env('REFAPI_URL');
    $refData = [
        'user_id' => $uid,
        'referral_code' => $referal_code,
        'amount' => $amounts,
        'transaction_type' => 'Payment',
    ];
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($refData),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
  }
  }
  
function actionUsersCrm($user,$cstatus){
  if($user->user_type != 2 && $cstatus == 1){
    if($user->user_type == 3 || $user->user_type == 4 || $user->trash == 0){
        DB::table('campaigns')->where('advertiser_code', $user->uid)->where('status',2)->update(['status' => 4]);
    }
  }elseif($user->user_type != 1 && $cstatus == 2){
      if($user->user_type == 3 || $user->user_type == 4 || $user->trash == 0){
          $websiteIds = PubWebsite::where('uid', $user->uid)->where('status', 4)->pluck('web_code');      //use App\Models\PubWebsite;
          DB::table('pub_websites')->where('uid', $user->uid)->where('status',4)->update(['status' => 7]); //use App\Models\PubAdunit;
          PubAdunit::whereIn('web_code', $websiteIds)->where('status', 2)->update(['status' => 1]);
      }
  }
}
function getUserStatus($uid, $type)
{
    $userStatus = User::select('uid', 'status', 'trash')->where('uid', $uid)->where('user_type', '!=', $type)->first();
    if (!$userStatus) {
        return ['code' => 101,'message' => 'Something went wrong!'];
    }
    if ($userStatus->status == 3) {
        return ['code' => 101,'message' => 'This account has been suspended.'];
    }
    if ($userStatus->status == 4) {
        return ['code' => 101,'message' => 'This account has been Hold.'];
    }
    if ($userStatus->trash == 1) {
        return ['code' => 101,'message' => 'This account has been Deleted.'];
    }
    return $userStatus;
}
