<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Campaign;
use Carbon\Carbon;

class StatsReportAdminController extends Controller
{
     
     public function getReportList(Request $request)
     {
        
        $validator = Validator::make($request->all(), [
            'to_date'   => 'required|date_format:Y-m-d',
            'from_date' => 'required|date_format:Y-m-d',
            'group_by'  => 'required',
        ]);
        if ($validator->fails()) {
            $return['code'] = 100;
            $return['error'] = $validator->errors();
            $return['message'] = 'Validation error!';
            return json_encode($return);
        }

        $toDate      = $request->to_date;
        $toDate      = Carbon::parse($toDate);
        $fromDate    = $request->from_date;
        $fromDate     = Carbon::parse($fromDate);
        $grpby       = $request->group_by;
        $advertiser  = $request->advertiser;
        $ad_type     = $request->ad_type;
        $campaign    = $request->campaign;
        $limit       = $request->lim ?? 10;
        $page        = $request->page ?? 1;
        $pg          = $page - 1;
        $start       = ($pg > 0) ? $limit * $pg : 0;

        $query = DB::table('adv_stats')
        ->selectRaw("
            DATE_FORMAT(ss_adv_stats.udate, '%d-%m-%Y') as created,
            SUM(ss_adv_stats.impressions) as Imprs,
            SUM(ss_adv_stats.clicks) as Clicks,
            SUM(ss_adv_stats.imp_amount) as avg_cpm,
            SUM(ss_adv_stats.click_amount) as avg_cpc,
            COALESCE(SUM(ss_adv_stats.amount), 0) as Total
        ")
        ->addSelect('adv_stats.camp_id', 'adv_stats.country', 'adv_stats.ad_type', 'adv_stats.device_type', 'adv_stats.device_os')
        ->whereBetween('adv_stats.udate', [$fromDate, $toDate]);

        if (!empty($advertiser)) {
            $query->where('adv_stats.advertiser_code', $advertiser);
        }
        if (!empty($ad_type)) {
            $query->where('adv_stats.ad_type', $ad_type);
        }
        if (!empty($campaign)) {
            $query->where('adv_stats.camp_id', $campaign);
        }
        // Apply grouping
        
        switch ($grpby) {
            case 'date':
                $query->groupByRaw('DATE(ss_adv_stats.udate)');
                break;
            case 'advertiser':
                $query->join('users', 'adv_stats.advertiser_code', '=', 'users.uid')
                    ->addSelect(
                        DB::raw("CONCAT(ss_users.first_name, ' ', ss_users.last_name) as name"),
                        'users.uid'
                    )->selectRaw('(SELECT COUNT(*) FROM ss_campaigns WHERE ss_campaigns.advertiser_code = ss_adv_stats.advertiser_code AND ss_campaigns.trash=0) as Cpm_count')
                    ->groupBy('adv_stats.advertiser_code');
                break;
            case 'campaign':
                $query->join('users', 'adv_stats.advertiser_code', '=', 'users.uid')
                    ->addSelect(
                        DB::raw("CONCAT(ss_users.first_name, ' ', ss_users.last_name) as name"),
                        'users.uid'
                    )
                    ->groupBy('adv_stats.camp_id');
                break;
            case 'country':
                $query->groupBy('adv_stats.country');
                break;
            case 'device':
                $query->groupBy('adv_stats.device_type');
                break;
            case 'os':
                
                $query->groupBy('adv_stats.device_os');
                break;
            default:
                $query->groupBy($grpby);
                break;
        }
        $row = $query->get()->count();
        $getData = $query->offset(($page - 1) * $limit)
        ->limit($limit)
        ->orderBy('adv_stats.udate', 'DESC')
        ->get();

        // Calculate totals
        $totals = $getData->reduce(function ($carry, $item) {
            $totalitemClicks = ($item->Clicks > 0) ? $item->Clicks : 0;
            $totalitemImprs = ($item->Imprs > 0) ? $item->Imprs : 0;
            $item->CTR = ($totalitemImprs > 0) ? ($item->Clicks / $item->Imprs) * 100 : 0;
            $item->avg_cpc = ($item->Clicks > 0) ?  $item->avg_cpc/$item->Clicks : 0;
            $item->avg_cpm = ($item->Imprs > 0) ?  $item->avg_cpm/$item->Imprs : 0;
            $carry['total_impression'] += $item->Imprs;
            $carry['total_click'] += $item->Clicks;
            $carry['total_amount'] += $item->Total;
            $carry['total_ctr']  = 0;
            if ($totalitemClicks > 0) {
                $carry['total_avgcpc'] += $item->avg_cpc;
            }
            if ($totalitemImprs > 0) {
                $carry['total_avgcpm'] += $item->avg_cpm;
            }
            $carry['total_camp_count'] += $item->Cpm_count ?? 0;
            return $carry;

        }, [
            'total_impression' => 0,
            'total_click' => 0,
            'total_amount' => 0,
            'total_ctr' => 0,
            'total_avgcpc' => 0,
            'total_avgcpm' => 0,
            'total_camp_count' => 0,
        ]);

            //Format totals
            $totals['total_amount']      = number_format($totals['total_amount'], 4, '.', '');
            $totals['total_ctr']         = $totals['total_impression'] > 0 ? number_format($totals['total_click']/$totals['total_impression']*100, 4, '.', '') : 1;
            $totals['total_avgcpc']      = $totals['total_click'] > 0 ?number_format($totals['total_avgcpc']/$totals['total_click'], 4, '.', '') : 0;
            $totals['total_avgcpm']      = $totals['total_impression'] > 0 ? number_format($totals['total_avgcpm']/$totals['total_impression'], 4, '.', '') : 1;
            $totals['total_camp_count']  = number_format($totals['total_camp_count'], 4, '.', '');

            // Prepare response
            $prepareResponse =   [
                    'code' => 200,
                    'data' => $getData,
                    'total' => $totals,
                    'row' => $row,
                    'message' => 'Successfully',
                ];
        return json_encode($prepareResponse, JSON_NUMERIC_CHECK);
    }
    
    
     public function getCampReportList(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'campaign'  => 'required',
        ]);
        if ($validator->fails()) {
            $return['code'] = 100;
            $return['error'] = $validator->errors();
            $return['message'] = 'Validation error!';
            return json_encode($return);
        }

        $toDate      = $request->to_date;
        $fromDate    = $request->from_date;
        $websiteId  = $request->website_id;
        $advertiser  = $request->advertiser;
        $country     = $request->country;
        $campaign    = $request->campaign;
        $limit       = $request->lim ?? 10;
        $page        = $request->page ?? 1;
        $pg    = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;

        $sql = DB::table('adv_stats_upgrades')
            ->select(
                "adv_stats_upgrades.website_id",
                "adv_stats_upgrades.impressions",
                "adv_stats_upgrades.clicks",
                DB::raw("SUM(ss_adv_stats_upgrades.impressions) as Imprs"),
                DB::raw("SUM(ss_adv_stats_upgrades.clicks) as Clicks"),
                DB::raw("SUM(ss_adv_stats_upgrades.imp_amt) as avg_cpm"),
                DB::raw("SUM(ss_adv_stats_upgrades.clk_amt) as avg_cpc"), 
                DB::raw("IF(SUM(ss_adv_stats_upgrades.clk_amt) IS NOT NULL, SUM(ss_adv_stats_upgrades.imp_amt), 0) as Total")
            );
        $sql->where('adv_stats_upgrades.camp_id', $campaign);
        if (!empty($fromDate) && !empty($toDate)) {
            $sql->whereBetween('adv_stats_upgrades.udate', [$fromDate, $toDate]);
        }
        $sql->leftjoin('pub_websites', 'adv_stats_upgrades.website_id', '=', 'pub_websites.web_code')
            ->addSelect(
                DB::raw("(ss_pub_websites.site_url) as website_name")
            );
        if (!empty($websiteId)) {
            $sql->where('adv_stats_upgrades.website_id', $websiteId);
        }
        if (!empty($country)) {
            $sql->where('adv_stats_upgrades.country', $country);
        }
        $sql->groupByRaw('ss_adv_stats_upgrades.website_id');
        $row     = $sql->get()->count();
        $getData = $sql->offset($start)->limit($limit)->orderBy('adv_stats_upgrades.udate', 'DESC')->get();

        if (!empty($getData)) {

            $totals = $getData->reduce(function ($carry, $item) {
                $totalitemClicks = ($item->Clicks > 0) ? $item->Clicks : 0;
                $totalitemImprs = ($item->Imprs > 0) ? $item->Imprs : 0;
                $item->CTR = ($totalitemImprs > 0) ? ($item->Clicks / $item->Imprs) * 100 : 0;
                $item->avg_cpc = ($item->Clicks > 0) ?  $item->avg_cpc/$item->Clicks : 0;
                $item->avg_cpm = ($item->Imprs > 0) ?  $item->avg_cpm/$item->Imprs : 0;
                $carry['total_impression'] += $item->Imprs;
                $carry['total_click'] += $item->Clicks;
                $carry['total_amount'] += $item->Total;
                $carry['total_ctr']  = 0;
                if ($totalitemClicks > 0) {
                    $carry['total_avgcpc'] += $item->avg_cpc;
                }
                if ($totalitemImprs > 0) {
                    $carry['total_avgcpm'] += $item->avg_cpm;
                }
                $carry['total_camp_count'] += $item->Cpm_count ?? 0;
                return $carry;
    
            }, [
                'total_impression' => 0,
                'total_click' => 0,
                'total_amount' => 0,
                'total_ctr' => 0,
                'total_avgcpc' => 0,
                'total_avgcpm' => 0,
                'total_camp_count' => 0,
            ]);
    
                //Format totals
                $totals['total_amount']      = number_format($totals['total_amount'], 4, '.', '');
                $totals['total_ctr']         = number_format($totals['total_click']/$totals['total_impression']*100, 4, '.', '');
                $totals['total_avgcpc']      = $totals['total_click'] > 0 ?number_format($totals['total_avgcpc']/$totals['total_click'], 4, '.', '') : 0;
                $totals['total_avgcpm']      = number_format($totals['total_avgcpm']/$totals['total_impression'], 4, '.', '');
                $totals['total_camp_count']  = number_format($totals['total_camp_count'], 4, '.', '');

                $return['code']     = 200;
                $return['data']     = $getData;
                $return['total']    = $totals;
                $return['row']      = $row;
                $return['message']  = 'Successfully';

       }

        else {
            $return['code']     = 100;
            $return['message']  = 'Record not found!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);



    }


    public function getWebsiteReportList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'website_id'  => 'required',
        ]);
        if ($validator->fails()) {
            $return['code'] = 100;
            $return['error'] = $validator->errors();
            $return['message'] = 'Validation error!';
            return json_encode($return);
        }

        $toDate        = $request->to_date;
        $fromDate      = $request->from_date;
        $websiteId     = $request->website_id;
        $advertiser    = $request->advertiser;
        $devicetype    = $request->device_type;
        $deviceos      = $request->device_os;
        $campaign      = $request->campaign;
        $limit         = $request->lim ?? 10;
        $page          = $request->page ?? 1;
        $pg    = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;

        $sql = DB::table('adv_stats_upgrades')
            ->select(
                "adv_stats_upgrades.country",
                "adv_stats_upgrades.impressions",
                "adv_stats_upgrades.clicks",
                DB::raw("SUM(ss_adv_stats_upgrades.impressions) as Imprs"),
                DB::raw("SUM(ss_adv_stats_upgrades.clicks) as Clicks"),
                DB::raw("SUM(ss_adv_stats_upgrades.imp_amt) as avg_cpm"),
                DB::raw("SUM(ss_adv_stats_upgrades.clk_amt) as avg_cpc"),
                DB::raw("IF(SUM(ss_adv_stats_upgrades.clk_amt) IS NOT NULL, SUM(ss_adv_stats_upgrades.imp_amt), 0) as Total"),
                DB::raw("AVG(ss_adv_stats_upgrades.imp_amt) as avg_cpm"),
                DB::raw("AVG(ss_adv_stats_upgrades.clk_amt) as avg_cpc"),
                DB::raw("IF(SUM(ss_adv_stats_upgrades.clk_amt) IS NOT NULL, SUM(ss_adv_stats_upgrades.imp_amt), 0) as Total")
            );
       
        if(!empty($campaign)){
            $sql->where('adv_stats_upgrades.camp_id',$campaign);
        }
        
        if ($websiteId != 'other') {
            $sql->where('adv_stats_upgrades.website_id', $websiteId);
             $sql->join('pub_websites', 'adv_stats_upgrades.website_id', '=', 'pub_websites.web_code')
            ->addSelect(
                DB::raw("(ss_pub_websites.site_url) as website_name")
            );
        } else{
             $sql->where('website_id', $websiteId);
        }

        if (!empty($fromDate) && !empty($toDate)) {
            $sql->whereBetween('adv_stats_upgrades.udate', [$fromDate, $toDate]);
        }
        
       
        if (!empty($devicetype)) {
            $sql->where('adv_stats_upgrades.device_type', $devicetype);
        }
        if (!empty($deviceos)) {
            $sql->where('adv_stats_upgrades.device_os', $deviceos);
        }
        $sql->groupByRaw('ss_adv_stats_upgrades.country');

        $row     = $sql->get()->count();
        $getData = $sql->offset($start)->limit($limit)->orderBy('adv_stats_upgrades.udate', 'DESC')->get();
        if (!empty($getData)) {


            $totals = $getData->reduce(function ($carry, $item) {
                $totalitemClicks = ($item->Clicks > 0) ? $item->Clicks : 0;
                $totalitemImprs = ($item->Imprs > 0) ? $item->Imprs : 0;
                $item->CTR = ($totalitemImprs > 0) ? ($item->Clicks / $item->Imprs) * 100 : 0;
                $item->avg_cpc = ($item->Clicks > 0) ?  $item->avg_cpc/$item->Clicks : 0;
                $item->avg_cpm = ($item->Imprs > 0) ?  $item->avg_cpm/$item->Imprs : 0;
                $carry['total_impression'] += $item->Imprs;
                $carry['total_click'] += $item->Clicks;
                $carry['total_amount'] += $item->Total;
                $carry['total_ctr']  = 0;
                if ($totalitemClicks > 0) {
                    $carry['total_avgcpc'] += $item->avg_cpc;
                }
                if ($totalitemImprs > 0) {
                    $carry['total_avgcpm'] += $item->avg_cpm;
                }
                $carry['total_camp_count'] += $item->Cpm_count ?? 0;
                return $carry;
    
            }, [
                'total_impression' => 0,
                'total_click' => 0,
                'total_amount' => 0,
                'total_ctr' => 0,
                'total_avgcpc' => 0,
                'total_avgcpm' => 0,
                'total_camp_count' => 0,
            ]);
    
                //Format totals
                $totals['total_amount']      = number_format($totals['total_amount'], 4, '.', '');
                $totals['total_ctr']         = $totals['total_impression'] > 0 ? number_format($totals['total_click']/$totals['total_impression']*100, 4, '.', '') : 0;
                $totals['total_avgcpc']      = $totals['total_click'] > 0 ?number_format($totals['total_avgcpc']/$totals['total_click'], 4, '.', '') : 0;
                $totals['total_avgcpm']      = $totals['total_impression'] > 0 ? number_format($totals['total_avgcpm']/$totals['total_impression'], 4, '.', '') : 0;
                $totals['total_camp_count']  = number_format($totals['total_camp_count'], 4, '.', '');




            $return['code']     = 200;
            $return['data']     = $getData;
            $return['total']    = $totals;
            $return['row']      = $row;
            $return['message']  = 'Successfully';



        } else {
            $return['code']     = 100;
            $return['message']  = 'Record not found!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    
    // fetch user detail on dropdown adv_stats
    public function adv_statslist(){
        $data = DB::table("adv_stats")->select('advertiser_code')->where('advertiser_code','!=','')->orderByDesc('id')->groupBy('advertiser_code')->get();
        if($data->isNotEmpty()){
            $return['code'] = 200;
            $return['message'] = "Advertiser list fethced successfully.";
            $return['row']  = $data->count();
            $return['data'] = $data;
        } else{
            $return['code'] = 101;
            $return['message'] = "Data not found!";
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    
    // fetch advertiser dropdown ad type list
    public function adv_adtypelist(Request $request){
        $advertiser_code = $request->advertiser_code;
        $validator = Validator::make($request->all(), [
             'advertiser_code' => $request->advertiser_code != "All" ? 'required|exists:adv_stats,advertiser_code':''
        ]);
        if($validator->fails()) {
            $return['code'] = 100;
            $return['error'] = $validator->errors();
            $return['message'] = 'Validation error!';
            return json_encode($return);
        }
        $data = Campaign::select('ad_type')
        ->when($request->advertiser_code !== 'All', function ($query) use ($request) {
            return $query->where('advertiser_code', $request->advertiser_code);
        })
        ->orderBy('ad_type', 'asc')
        ->groupBy('ad_type')
        ->get();
        if($data->isNotEmpty()){
            $return['code'] = 200;
            $return['data'] = $data;
            $return['message'] = "Ad type list fetched successfully.";
        } else{
            $return['code'] = 101;
            $return['message'] = "Data not found!";
        }
        

        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    
    
    // fetch advertiser adtype campaign dropdown list
     public function adv_adtypecampaignlist(Request $request){
        $advertiser_code = $request->advertiser_code;
        $ad_type = $request->ad_type;
        $validator = Validator::make($request->all(), [
            'advertiser_code' => $advertiser_code != 'All' ? 'required|exists:adv_stats,advertiser_code' : 'required',
            'ad_type' => $ad_type != 'All' ? 'required|exists:campaigns,ad_type' : 'required'
        ]);
        if($validator->fails()){
            $return['code'] = 100;
            $return['error'] = $validator->errors();
            $return['message'] = 'Validation error!';
            return json_encode($return);
        }
        $query = Campaign::select('advertiser_code', 'ad_type', 'campaign_id');
        if ($advertiser_code !== 'All' && $ad_type !== 'All') {
            $query->where('advertiser_code', $advertiser_code)
                ->where('ad_type', $ad_type);
        }
        $data = $query->get();
        if($data->isNotEmpty()){
            $return['code'] = 200;
            $return['message'] = "Campaign id fetched successfully";
            $return['data'] = $data;
            $return['row'] = $data->count();
        } else{
            $return['code'] = 101;
            $return['message'] = 'Data not found!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    
}
