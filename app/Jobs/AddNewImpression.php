<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Campaign;
use App\Models\User;
use App\Models\AdImpression;
use App\Models\CountriesIps;
use App\Models\UserCampClickLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendNotificationMail;
use Illuminate\Support\Facades\Artisan;
class AddNewImpression implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $response;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($response)
    {
        //Artisan::call('queue:listen');
        $this->response = $response;
        $this->handle();
    }
    public function __destruct()
    {
        //Artisan::call('queue:work --stop-when-empty');
    }
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    { 
        try {
            $campid = $this->response['campaign_id'];
            $impression = $this->response['impressions'];
            $impressionamt = $this->response['impression_amt'];
            $click = $this->response['click'];
            $clickamt = $this->response['click_amt'];
            $date = $this->response['date'];
            $campdata = Campaign::select('id', 'created_at', 'daily_budget' , 'advertiser_code', 'daily_budget' ,'device_type', 'device_os', 'ad_type', 'country_ids', 'countries')->where('campaign_id', $campid)->whereIn('status', [2, 4])->first();
            $uid = $campdata['advertiser_code'];
            $userwallet = User::select('first_name', 'last_name', 'email', 'wallet')->where('uid', $uid)->first();
            $camdailybudget = $campdata['daily_budget'];
            $totalbudgetcimp = bcadd(bcmul($impression, $impressionamt, 6) , bcmul($click, $clickamt, 6), 6);
            $totimpclickd = DB::table('camp_budget_utilize')->where('camp_id', $campid)->where('advertiser_code', $campdata['advertiser_code'])->whereDate('udate', $date)->sum('amount');
            $mainbalce = $camdailybudget - $totimpclickd;
            $camdailybudget = ($mainbalce > $userwallet['wallet']) ? $userwallet['wallet'] : $mainbalce;
            if ($totalbudgetcimp > $camdailybudget || $userwallet['wallet'] <= 0) {
                dispatch(new SendNotificationMail(['user_id' =>  $campdata['advertiser_code'], 'name' => $userwallet['first_name'] . ' ' . $userwallet['last_name'], 'email' =>  $userwallet['email']]));
             } else {
                $totaluserwallet = $userwallet['wallet'];
                $ad_type = $campdata['ad_type'];
                $countryids = $campdata['country_ids'];
                $countresid = explode(',', $countryids);
                $deductamt = $totalbudgetcimp;
                $countrie = $campdata['countries'];
                if ($countrie == 'All') {
                    $ipcountrow = CountriesIps::select('ip_addr', 'country_name')->orderByRaw('RAND()')
                        ->limit($impression)->get()->toArray();;
                } else {
                    $getisodata = DB::table('countries')->select('iso', 'name')->whereIn('id', $countresid)->get()->toArray();
                    $countrycode = array_column($getisodata, 'iso');
                    $ipcountrow = CountriesIps::select('ip_addr', 'country_name')->whereIn('country_code', $countrycode)->orderByRaw('RAND()')
                        ->limit($impression)->get()->toArray();;
                }
                $cip = array_column($ipcountrow, 'ip_addr');
                $ccountry = array_column($ipcountrow, 'country_name');
                $netdeductamtsf = bcsub($totaluserwallet, $deductamt, 6);
                $netdeductamt = ($netdeductamtsf > 0) ? round($netdeductamtsf, 2) : 0;
                $result = getImpClickData($impression, $click, explode(",",$campdata['device_os']) , explode(",", $campdata['device_type']));
                $impData = $clicData = [];
                foreach ($result as $value) {
                    foreach ($value as $val) {
                        for ($i = 0;$i <= ($val['imp'] - 1);$i++) {
                            $newdateinst = $date . ' ' .date("H:i:s", rand(0, time()));
                            $impData[] = ['created_at' =>   $newdateinst, 'updated_at' => $newdateinst, 'campaign_id' => $campid, 'country' => strtoupper($ccountry[$i]), 'impression_id' =>  'IMP' . strtoupper(uniqid()), 'advertiser_code' => $uid,  'ad_type' => $ad_type, 'amount' => $impressionamt,  'device_type' => $val['device'], 'device_os' => $val['os'], 'uni_imp_id' => md5($uid . $campid . $val['os'] .$val['device'] .$ccountry[$i] . date('Ymd', strtotime($date))), 'uni_bd_id' => md5($uid . $campid . date('Ymd', strtotime($date))), 'ip_addr' =>  $cip[$i]];
                        }
                    }
                }
                if(!empty($impData)){
                    $impArr =  array_chunk($impData,500);
                    foreach($impArr as $value) {
                        AdImpression::insert($value);
                    }  
                    unset($impArr);                  
                }
                unset($impData);
                foreach ($result as $key => $value){
                    foreach ($value as $val) {
                        for ($i = 0;$i <= ($val['clk'] - 1);$i++) {
                            $newdateinst = $date . ' ' .date("H:i:s", rand(0, time()));
                            $clicData[] = ['created_at' =>   $newdateinst, 'updated_at' => $newdateinst, 'campaign_id' => $campid, 'country' => strtoupper($ccountry[$i]), 'advertiser_code' => $uid,  'ad_type' => $ad_type, 'amount' => $clickamt,  'device_type' => $val['device'], 'device_os' => $val['os'], 'uni_imp_id' => md5($uid . $campid . $val['os'] .$val['device'] .$ccountry[$i] . date('Ymd', strtotime($date))), 'uni_bd_id' => md5($uid . $campid . date('Ymd', strtotime($date))), 'ip_address' =>  $cip[$i]];
                        }
                    }
                }
                if(!empty($clicData)){
                    $clkArr =  array_chunk($clicData, 500);
                    foreach($clkArr as $value) {
                        UserCampClickLog::insert($value);
                    }  
                    unset($clkArr);
                }
                unset($clicData);
                DB::table('users')->where('uid', $uid)->decrement('wallet', $deductamt);
                if ($netdeductamt <= 15) {
                    dispatch(new SendNotificationMail(['user_id' => $uid, 'name' => $userwallet['first_name'] . ' ' . $userwallet['last_name'], 'email' =>  $userwallet['email']]))->onQueue('send_notification_mail');
                }
            }
        } catch (\Exception $e) {
           Log::info("Exception ". $e->getMessage());
            dd($e->getMessage());
       }
    }  
  
  	public function savedata($message){
  		DB::table('logs')->insert([
            "message" => $message,
        ]);
  	}
}
