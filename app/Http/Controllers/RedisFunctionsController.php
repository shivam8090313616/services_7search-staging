<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\AdImpression;
use App\Models\PubAdunit;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerfiedUserMail;
use PHPMailer\PHPMailer\PHPMailer;
class RedisFunctionsController extends Controller
{
    /* This will update users wallet */
    
    public function userWalletUpdate(){
        $redisCon = Redis::connection('default');
        $users = DB::table('users')->select('uid')->get()->toArray();
        foreach ($users as $user){
            $adv_wallet = $redisCon->rawCommand('hget', 'adv_wallet', $user->uid);
            $pub_wallet = $redisCon->rawCommand('hget', 'pub_wallet', $user->uid);
            if($adv_wallet != "" && $pub_wallet != ""){
                DB::table('users')->where('uid', $user->uid)->update(['wallet' => $adv_wallet, 'pub_wallet' => $pub_wallet]);
            }
        }
        return ['code' => 200, 'message' => 'Wallet updated successfully!'];
    }
    
    /* This will remove ad_sessions in every hour */
    
    public function removeAdSession(){
        $redisCon = Redis::connection('default');
        $data = $redisCon->rawCommand('hgetall', 'ad_sessions');
        $data =  array_reduce($data, function ($data, $row) {
            if (strlen($row) > 30) {
              $data[] =  json_decode($row,true);
            }
            return $data;
        });
        $data = array_filter($data, function ($item) {
            $time = date('Y-m-d H:i:s', strtotime('-1 hour'));
            return strtotime($item['date_time']) > strtotime($time);
        });
        $redisCon->rawCommand('del', "ad_sessions");
        foreach ($data as $value){
            $redisCon->rawCommand('hset', "ad_sessions", $value['ad_session_id'], json_encode($value));
        }
        return;
    }
    
    /* This will remove daily click count from Redis at EOD */
    
    public function removeDailyClickCount(){
        $redisCon = Redis::connection('default');
        $redisCon->rawCommand('del', "daily_click_count");
        return;
    }
    
    /* This will insert bulk impressions data into db */
    
    public function setBulkImp(){
        $redisCon = Redis::connection('default');
        $imp = json_decode($redisCon->rawCommand('json.get', 'impressions'), true);
        $chunk = 2000;
        foreach (array_chunk($imp, $chunk) as $imps){
            AdImpression::insert($imps);
        }
        $redisCon->rawCommand('json.set', 'impressions', '$', json_encode([]));
        return;
    }
    
    /* This will insert bulk clicks data into db */
    
    public function setBulkClk(){
        $redisCon = Redis::connection('default');
        $clk = json_decode($redisCon->rawCommand('json.get', 'clicks'), true);
        DB::table('user_camp_click_logs')->insert($clk);
        $redisCon->rawCommand('json.set', 'clicks', '$', json_encode([]));
        return;
    }
    
    /* This will remove old data from daily budget set */
    
    public function removeDailyBudget(){
        $redisCon = Redis::connection('default');
        $users = DB::table('users')->select('uid')->where('status', 0)->where('user_type', '!=', 2)->get()->toArray();
        foreach ($users as $user){
            $data = json_decode($redisCon->rawCommand('hget', 'budget_utilize', $user->uid), true);
            if(!empty($data)){
                $redisCon->rawCommand('hdel', 'budget_utilize', $user->uid);
                $filteredData = array_filter($data, function ($item) {
                    return $item['udate'] == date('Y-m-d');
                });
                if(!empty($filteredData)){
                    $redisCon->rawCommand('hset', 'budget_utilize', $user->uid, json_encode($filteredData));
                }
            }
        }
        return ['msg'=>'data removed'];
    }
    
    public function setFunctions(){
        $redisCon = Redis::connection('default');
        // $data = checkPayAttempts('ADV6672AA674E86B');
        // Get the current time
        // $currentTimestamp = time();
    
        // // Get the timestamp for the end of the current day (23:59:59)
        // $endOfDayTimestamp = strtotime('tomorrow') - 1;
    
        // // Calculate the difference in seconds
        // $secondsLeft = $endOfDayTimestamp - $currentTimestamp;
    
        // return $secondsLeft;
        //         $adminMailData = [
        //     'subject' => 'New User Registration',
        //     'body' => 'A new user has registered.',
        //     'name' => 'Test User',
        //     'email' => 'admin@7searchpp.com',
        // ];

        // // Send email to the admin
        // try{
        //     Mail::to('adnan.logelite@gmail.com')->send(new VerfiedUserMail($adminMailData));
        //     return 1;
        // }catch(\Exception $e){
        //     return 0;
        // }
        // $data = DB::table('users')->limit(10)->get()->toArray();
        // $redisCon->rawCommand('json.set', "clicks", '$', json_encode([]));
        // $redisCon->rawCommand('json.set', "impressions", '$', json_encode([]));
        // $data = $redisCon->rawCommand('json.set', "test", '$', json_encode([])); 1 response
        // $data = $redisCon->rawCommand('json.arrinsert', "test", '$', '0', json_encode([1,2,3]));
        // $data = $redisCon->rawCommand('hgetall', 'ad_sessions');
        // $data = $redisCon->rawCommand('json.get', 'clicks','$');
        // $data = $redisCon->rawCommand('hget', 'ad_sessions','SESID6656DEF247F96');
        // 'CMPT65C4A160250AC';
        // $data2 = json_decode($redisCon->rawCommand('json.get', 'clicks'), true);
        // echo count($data2);
        $amt = 15;
        $uid = 'ADV652E5165508A0';
        // $redisCon->rawCommand('hincrbyfloat', 'pub_wallet',  $uid, $amt);
        // $redisCon->rawCommand('hset', 'adv_wallet', $uid, $amt);
        // $redisCon->rawCommand('hset', 'pub_wallet', $uid, $amt);
        //  $data = json_decode($redisCon->rawCommand('json.get', 'impressions', '$'),true);
        //  $data = json_decode($redisCon->rawCommand('json.get', 'clicks'),true);
        // $uni_imp_id = md5('ADV652E5165508A0' . 'CMPS66680962DF05D' . 'windows' . 'Desktop' . 'UNITED STATES' . date('Ymd'));
        // $data = DB::table('adv_stats')->where('uni_imp_id', $uni_imp_id)->get();
        // $data = DB::table('adv_stats')->where('udate', '2024-06-13')->get()->toArray();
        //  $data = json_decode($redisCon->rawCommand('json.get', 'clicks', '$'),true);
        // $data = json_decode($redisCon->rawCommand('hget', 'budget_utilize', 'ADV6548C32A31637'), true);
        // $data = $redisCon->rawCommand('get', 'budget_utilize:28d29126125ffc05a04e8bbc86d367d8');
        // $data = $redisCon->rawCommand('hgetall', 'budget_utilize');
        // $data = getDailyBudget('ADV666567B4E0531', 'CMPN6665721514677');
        // $data = json_decode($redisCon->rawCommand('json.get', 'impressions'), true);
        $adsess = ['cmp'=>'123'];
        // $redisCon->rawCommand('setex', "ad_sessions:", 10, json_encode($adsess));
        // $data = json_decode($redisCon->rawCommand('get', "ad_sessions:SESID66C82052EFACB"),true);
        // $data = json_decode($redisCon->rawCommand('json.get', 'clicks'), true);
        // $data = $redisCon->rawCommand('hget', 'pub_wallet', 'ADV652E5165508A0');
        // $data = $redisCon->rawCommand('hget', 'adv_wallet', $uid);
        $data = $redisCon->rawCommand('hget', 'pub_wallet', $uid);
        // $dt = md5(30 . 'US');
        // $data = json_decode($redisCon->rawCommand('hget', 'pub_rate_masters', $dt), true);
        
        // $res = ['camp_id' => 'dghdjkg'];
        // $data = $redisCon->rawCommand('json.set', 'addSess:' . 'campaign_id', '$', json_encode($res))  && $redisCon->rawCommand('expire', 'addSess:campaign_id', 30);
        // $query1 = "@country_code:{IN} @device_type:{Desktop} @device_os:{android}  (@ad_type:(text))";
        // $data = $redisCon->rawCommand('ft.search', "7sAds", $query1, 'LIMIT', 0, 10);
        // $data = json_decode($redisCon->rawCommand('hget', 'adv_stats', $uid), true);
        // echo 'sghfd';
        // $data = json_decode($redisCon->rawCommand('json.get', '7s_camps:CMPB65FD571BCE367', '$'), true);
        // $data = json_decode($redisCon->rawCommand('hget', 'categories_data', 114), true);
        // $data = updateUserCampsAdunits('ADV65F030697F34E', 0);
        // $data = json_decode($redisCon->rawCommand('json.get', 'ad_sessions:SESID66C72C6F9A6F1'), true);
        // $data = $redisCon->rawCommand('ft.search', "7sAds", "@website_category:[64 64]", 'LIMIT', 0, 10);
        // $data = getCampAd(113, 33, 'Desktop', 'windows', 0, 0, 'native', 4,1);
        // $data = DB::table('campaigns')->where('ad_type', 'popup')->where('status', 2)->get();
        // $data = DB::table('users')->where('uid', 'ADV64AB8FF0841FF')->first();
        // print_r($data);die;
        //  updateUserCampsAdunits('PUB6542008EEB313', 0);
        // $data = $redisCon->rawCommand('hget', "webdata", '7SAD15669B583D46197');
        print_r($data);
        // print_r(count($data));
        // setWebData();
        // setTextCamp();
        // setBannerCamp();
        // setNativeCamp();
        // setInPagePushCamp();
        // setPopUnderCamp();
        // setPubStats();
        // setAdvStats();
        // setCategory();
        // setAdRate();
        // setAdvPubWallet();
        // setCountries();
        // setDailyBudget();
        // print_r('data set successfully!');
        // die;
        // $data['details'] = array('subject' => 'Campaign Created successfully - 7Search PPC ', 'fullname' => 'adnanf',  'usersid' => 'ADV68gf', 'campaignid' => 'CMPhsjefg87', 'campaignname' => 'cmpname', 'campaignadtype' => 'text');
        // // $adminmail1 = 'advertisersupport@7searchppc.com';
        // $adminmail1 = 'sharif.logelite@gmail.com';

        // // $adminmail2 = 'info@7searchppc.com';
        // $adminmail2 = 'ashraf.logelite@gmail.com';

        // $bodyadmin =   View('emailtemp.campaigncreateadmin', $data);

        // //print_r($data); exit;
        

        // $subjectadmin = 'Campaign Created successfully - 7Search PPC';

        // $sendmailadmin =  sendmailTest($subjectadmin,$bodyadmin,$adminmail1,$adminmail2); 
        
        //             if($sendmailadmin == '1') 

        //     {

        //         $return['code'] = 200;

        //         // $return['data'] = $campaign;

        //         $return['message']  = 'Mail Send & Data Inserted Successfully !';

                

        //     }

        //     else 

        //     {

        //         $return['code'] = 101;

        //         // $return['data'] = $campaign;

        //         $return['message']  = 'Mail Not Send But Data Insert Successfully !';

        //     }
        // return json_encode($return, JSON_NUMERIC_CHECK);
        // return json_encode($sendmailadmin, JSON_NUMERIC_CHECK);
    }
}
