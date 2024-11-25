<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Campaign;

use Illuminate\Support\Facades\DB;

class AdvStatsUserController extends Controller
{
    
   public function advStatistics(Request $request)

    {
        $validator = Validator::make($request->all(), [

            'uid'       => 'required',

            'to_date'   => 'required|date_format:Y-m-d',

            'from_date' => 'required|date_format:Y-m-d',

        ]);

        if ($validator->fails()) {

            $return['code'] = 100;

            $return['error'] = $validator->errors();

            $return['message'] = 'Validation error!';

            return json_encode($return);
        }



        $uid = $request->uid;

        $camp_id = $request->camp_id;

        $ad_type = $request->ad_type;

        $todate = $request->to_date;

        $fromdate = $request->from_date;

        $grpby = $request->group_by;

        $limit = $request->lim;

        $page = $request->page;

        $pg = $page - 1;

        $start = ($pg > 0) ? $limit * $pg : 0;

        $userdata = DB::table("users")->select('wallet')->where('uid', $uid)->first();


        $sql = DB::table('adv_stats')
            ->select(
                "adv_stats.camp_id",
                "adv_stats.country",
                "adv_stats.ad_type",
                "adv_stats.device_type",
                "adv_stats.device_os",
                DB::raw("DATE_FORMAT(ss_adv_stats.udate, '%d-%m-%Y') as created"),
                DB::raw("SUM(ss_adv_stats.impressions) as Imprs"),
                DB::raw("SUM(ss_adv_stats.clicks) as Clicks"),
                DB::raw("SUM(ss_adv_stats.imp_amount) as avg_cpm"),
                DB::raw("SUM(ss_adv_stats.click_amount) as avg_cpc"),
                DB::raw("IF(SUM(ss_adv_stats.amount) IS NOT NULL, SUM(ss_adv_stats.amount), 0) as Totals")

            )

            ->where('adv_stats.advertiser_code', $uid)
            ->whereBetween('adv_stats.udate', [$todate, $fromdate]);

        if (!empty($camp_id)) {
            $sql->where('adv_stats.camp_id', $camp_id);
        }

        if (!empty($ad_type)) {
            $sql->where(function($query) use ($ad_type, $camp_id) {
                $query->where('adv_stats.ad_type', $ad_type)
                      ->orWhere('adv_stats.camp_id', $camp_id);
            });
        }


        if ($grpby == 'date') {
            $sql->groupByRaw('DATE(ss_adv_stats.udate)');
        } else {
            $sql->groupByRaw($grpby);
        }
        $row   = $sql->get()->count();
        $datas = $sql->offset($start)
            ->limit($limit)
            ->orderBy('adv_stats.udate', 'DESC')
            ->get();


        if (!empty($datas)) {

            $totalclk = '0';

            $totalimp = '0';

            $totalamt = '0';

            $totalctr = '0';
            $totavgcpc = '0';
            $totavgcpm = '0';
            $totalavgcpc = '0';
            $totalavgcpm = '0';
            foreach ($datas as $vallue) {

                if ($vallue->Clicks == 0 || $vallue->Imprs == 0) {
                    $vallue->CTR = 0;
                } else {
                    $vallue->CTR = ($vallue->Clicks / $vallue->Imprs) * 100;
                }
                if($vallue->Clicks > 0){
                    $vallue->avg_cpc = $vallue->avg_cpc/$vallue->Clicks;
                }
                if($vallue->Imprs > 0){
                    $vallue->avg_cpm = $vallue->avg_cpm/$vallue->Imprs;
                }
                
                

                $totalimp += $vallue->Imprs;
                $totalclk += $vallue->Clicks;
                $totavgcpc += $vallue->avg_cpc;
                $totavgcpm += $vallue->avg_cpm;
                $totalamt += $vallue->Totals;
                $vallue->Total = $vallue->Totals;
                unset($vallue->Totals);
            }

            $totalctr = ($totalclk) ? $totalclk / $totalimp * 100 : 0;

            if ($totalclk == 0 && $totalimp == 0) {
                $totalavgcpc = 0;
                $totalavgcpm = 0;
            } else {
                $totalavgcpc = $totavgcpc;
                $totalavgcpm = $totavgcpm;
            }
            $asdsdas = array('total_impression' => $totalimp, 'total_click' => $totalclk, 'total_amount' => number_format($totalamt, 4, '.', ''), 'total_ctr' => number_format($totalctr, 4, '.', ''), 'total_avgcpc' => number_format($totalavgcpc, 4, '.', ''), 'total_avgcpm' => number_format($totalavgcpm, 4, '.', ''));

            $return['code']    = 200;
            $return['data']    = $datas;
            $return['total']    = $asdsdas;
            $return['row']     = $row;

            $return['mail_verified'] = User::where('uid', $uid)->value('mail_verified');
            $wltAmt = getWalletAmount($uid);
            $return['wallet']        = ($wltAmt) > 0 ? number_format($wltAmt, 3, '.', '') : number_format($userdata->wallet, 3, '.', '');
            $return['message'] = 'Successfully';
        } else {
            $return['code']    = 100;
            $wltAmt = getWalletAmount($uid);
            $return['wallet']        = ($wltAmt) > 0 ? number_format($wltAmt, 3, '.', '') : number_format($userdata->wallet, 3, '.', '');
            $return['message'] = 'Record not found!';
            
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    
    public function advCampaignStatistics(Request $request)

    {
        $validator = Validator::make($request->all(), [
            'camp_id'       => 'required',
            'to_date'   => 'required|date_format:Y-m-d',
            'from_date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            $return['code'] = 100;
            $return['error'] = $validator->errors();
            $return['message'] = 'Validation error!';
            return json_encode($return);
        }



        $camp_id = $request->camp_id;
        $uid = $request->uid;
        $country = $request->country;
        $device_type = $request->device_type;
        $device_os = $request->device_os;
        $todate = $request->to_date;
        $fromdate = $request->from_date;
        $limit = $request->lim;
        $page = $request->page;
        $pg = $page - 1;
        $start = ( $pg > 0 ) ? $limit * $pg : 0;
        $userdata = DB::table("users")->select('wallet')->where('uid', $uid)->first();
        
        $sql = DB::table('adv_stats')
        ->select(
            "adv_stats.country",
            "adv_stats.device_type",
            "adv_stats.device_os",
            DB::raw("DATE_FORMAT(ss_adv_stats.udate, '%d-%m-%Y') as created"),
            DB::raw("SUM(ss_adv_stats.impressions) as Imprs"),
            DB::raw("SUM(ss_adv_stats.clicks) as Clicks"),
            DB::raw("SUM(ss_adv_stats.imp_amount) as avg_cpm"),
            DB::raw("SUM(ss_adv_stats.click_amount) as avg_cpc"),
            DB::raw("IF(SUM(ss_adv_stats.amount) IS NOT NULL, SUM(ss_adv_stats.amount), 0) as Totals")
           
        )
        
        ->where('adv_stats.camp_id', $camp_id)
        ->where('adv_stats.device_os', '!=', '')
        ->where('adv_stats.device_type', '!=', '')
        ->where('adv_stats.country', '!=', '')
        ->whereBetween('adv_stats.udate', [$todate, $fromdate]);
        
        if(!empty($country))
        {
            $sql->where('adv_stats.country', $country);
        }
        if(!empty($device_type))
        {
            $sql->where('adv_stats.device_type', $device_type);
        }
        
        if(!empty($device_os))
        {
            $sql->where('adv_stats.device_os', $device_os);
        }

        $sql->groupByRaw('ss_adv_stats.country');
        
        $row   = $sql->get()->count();
        $datas = $sql->offset($start)
            ->limit($limit)
            ->orderBy('adv_stats.country', 'DESC')
            ->get();
        if (!empty($datas)) {

            $totalclk = '0';
            $totalimp = '0';
            $totalamt = '0';
            $totalctr = '0';
            $totavgcpc = '0';
            $totavgcpm = '0';
            $totalavgcpc = '0';
            $totalavgcpm = '0';
            foreach ($datas as $vallue) {

                if ($vallue->Clicks == 0 || $vallue->Imprs == 0) {
                    $vallue->CTR = 0;
                } else {
                    $vallue->CTR = ($vallue->Clicks / $vallue->Imprs) * 100;
                }
                
                if($vallue->Clicks > 0){
                    $vallue->avg_cpc = $vallue->avg_cpc/$vallue->Clicks;
                }
                if($vallue->Imprs > 0){
                    $vallue->avg_cpm = $vallue->avg_cpm/$vallue->Imprs;
                }
               
                $totalimp += $vallue->Imprs;
                $totalclk += $vallue->Clicks;
                $totavgcpc += $vallue->avg_cpc;
                $totavgcpm += $vallue->avg_cpm;
                $totalamt += $vallue->Totals;
                $vallue->Total = $vallue->Totals;
                unset($vallue->Totals);
            }
            
           $totalctr = ($totalclk) ? $totalclk / $totalimp * 100 : 0;
            if ($totalclk == 0 && $totalimp == 0) {
                $totalavgcpc = 0;
                $totalavgcpm = 0;
            } else {
                $totalavgcpc = $totavgcpc;
                $totalavgcpm = $totavgcpm;
            }
            $asdsdas = array(
                'total_impression' => $totalimp, 
                'total_click' => $totalclk, 
                'total_amount' => number_format($totalamt, 4, '.', ''), 
                'total_ctr' => number_format($totalctr, 4, '.', ''), 
                'total_avgcpc' => number_format($totalavgcpc, 4, '.', ''), 
                'total_avgcpm' => number_format($totalavgcpm, 4, '.', '')
                );

            $return['code']    = 200;
            $return['data']    = $datas;
            $return['total']    = $asdsdas;
            $return['row']     = $row;

            $wltAmt = getWalletAmount($uid);
            $return['wallet']        = ($wltAmt) > 0 ? number_format($wltAmt, 3, '.', '') : number_format($userdata->wallet, 3, '.', '');
            $return['message'] = 'Successfully';
        } else {
            $return['code']    = 100;
            $wltAmt = getWalletAmount($uid);
            $return['wallet']        = ($wltAmt) > 0 ? number_format($wltAmt, 3, '.', '') : number_format($userdata->wallet, 3, '.', '');
            $return['message'] = 'Record not found!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);

    }
    
  // fetch advertiser adtype campaign dropdown list
    public function advAdtypeCampaign(Request $request)
    {
        $advertiser_code = $request->uid;
        $ad_type = $request->ad_type;
        $validator = Validator::make($request->all(), [
            'uid' => 'required|exists:adv_stats,advertiser_code',
            'ad_type' => $ad_type != 'All' ? 'required|exists:campaigns,ad_type': 'required'
        ]);
        if ($validator->fails()) {
            $return['code'] = 100;
            $return['error'] = $validator->errors();
            $return['message'] = 'Validation error!';
            return json_encode($return);
        }
        $data = Campaign::select("ad_type", "campaign_id")
            ->where('advertiser_code', $advertiser_code)
            ->when($ad_type != 'All', function($query) use ($ad_type) {
                return $query->where('ad_type', $ad_type);
            })
           ->get();
        if ($data->isNotEmpty()) {
            $return['code'] = 200;
            $return['message'] = "Campaign id fetched successfully";
            $return['data'] = $data;
            $return['row'] = $data->count();
        } else {
            $return['code'] = 101;
            $return['message'] = 'Data not found!';
        }
        
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
}
