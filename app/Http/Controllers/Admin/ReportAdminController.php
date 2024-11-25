<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdImpression;
use App\Models\UserCampClickLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use PDF;

class ReportAdminController extends Controller
{

    public function cmpreport(Request $request)
    {
        /* Fillter BY Data*/
        $startDate = $request->startDate;
        $nfromdate = date('Y-m-d', strtotime($startDate));
        $endDate = $request->endDate;
        $categ = $request->cat;
        $adtype = $request->camptype;
        $sts = $request->type;
        $limit = $request->lim ?? 10; // Default limit if not provided
        $page = $request->page ?? 1;  // Default page if not provided
        $src = $request->src;
        $start = ($page - 1) * $limit;
        $columnName = $request->col;
        $colOrderBy = $request->srt;    
        /* Query Section */
        $campaign = DB::table('campaigns')
            ->select(
                DB::raw("CONCAT(ss_users.first_name, ' ', ss_users.last_name) as name"),
                DB::raw("(select IFNULL(sum(clicks),0) from ss_camp_budget_utilize camp_ck where camp_ck.camp_id = ss_campaigns.campaign_id) as click"),
                DB::raw("(select IFNULL(sum(impressions),0) from ss_camp_budget_utilize camp_ck where camp_ck.camp_id = ss_campaigns.campaign_id) as imprs"),
                DB::raw("(select IFNULL(sum(amount),0) from ss_camp_budget_utilize camp_ck where camp_ck.camp_id = ss_campaigns.campaign_id) as amount"),
                'campaigns.id', 'campaigns.campaign_name', 'campaigns.campaign_id', 'campaigns.advertiser_code', 
                'campaigns.website_category', 'campaigns.status', 'campaigns.trash', 'campaigns.ad_type', 
                'campaigns.created_at', 'users.account_type', 'categories.cat_name'
            )
            ->join('users', 'campaigns.advertiser_code', '=', 'users.uid')
            ->join('categories', 'campaigns.website_category', '=', 'categories.id');
    
        if ($sts) {
            $campaign->where('campaigns.status', $sts);
        }
        if ($categ) {
            $campaign->where('campaigns.website_category', $categ);
        }
        if ($adtype) {
            $campaign->where('campaigns.ad_type', $adtype);
        }
        if ($startDate && $endDate) {
            $campaign->whereBetween('campaigns.created_at', [$nfromdate, $endDate]);
        }
        if ($src) {
            $campaign->whereRaw('concat(ss_users.uid, ss_campaigns.campaign_id, ss_users.first_name, ss_users.last_name, ss_campaigns.campaign_name) like ?', ["%{$src}%"]);
        }
        // $campaign->orderBy('campaigns.id', 'DESC');
        $row = $campaign->count();
         if($columnName){
            if($columnName == 'Impressions'){
                $data = $campaign->offset( $start )->limit( $limit )->orderBy('imprs', $colOrderBy)->get();
            }elseif($columnName == 'clicks'){
                $data = $campaign->offset( $start )->limit( $limit )->orderBy('click', $colOrderBy)->get();
            }else{
                $data = $campaign->offset( $start )->limit( $limit )->orderBy('amount', $colOrderBy)->get();
            }
          } else{
            $data = $campaign->offset( $start )->limit( $limit )->orderBy('campaigns.id', 'DESC')->get();
          }
    
        /* Pagination Section */
        // $row = $campaign->count();
        // $data = $campaign->offset($start)->limit($limit)->get();
        if ($data) {
            $return = [
                'code' => 200,
                'data' => $data,
                'row' => $row,
                'message' => 'Campaigns list retrieved successfully!'
            ];
            return response()->json($return, $return['code'], [], JSON_NUMERIC_CHECK);
        }
        
        // For other errors:
        $return = [
            'code' => 500,
            'message' => 'Something went wrong!'
        ];
        return response()->json($return, $return['code'], [], JSON_NUMERIC_CHECK);
    }
    
    
  	public function cmpReportExportDateWise(Request $request)
    {
        /* Fillter BY */

        $startDate = $request->startDate;
        $nfromdate = date('Y-m-d', strtotime($startDate));
        $endDate = $request->endDate;
        
        $campaign = DB::table('campaigns')
            ->select('campaigns.id','campaigns.campaign_name',DB::raw("CONCAT(ss_users.first_name, ' ', ss_users.last_name) as name,  
            (select IFNULL(sum(clicks),0) from ss_camp_budget_utilize camp_ck where camp_ck.camp_id = ss_campaigns.campaign_id) as click ,
            (select IFNULL(sum(impressions),0) from ss_camp_budget_utilize camp_ck where camp_ck.camp_id = ss_campaigns.campaign_id) as imprs,
            (select IFNULL(sum(amount),0) from ss_camp_budget_utilize camp_ck where camp_ck.camp_id = ss_campaigns.campaign_id) as amount"), 
              'campaigns.campaign_id', 'campaigns.advertiser_code','campaigns.status', 
            'campaigns.trash', 'campaigns.ad_type', 'campaigns.created_at', 'users.account_type', 'categories.cat_name')
            ->join('users', 'campaigns.advertiser_code', '=', 'users.uid')
            ->join('categories', 'campaigns.website_category', '=', 'categories.id')
            ->whereDate('campaigns.created_at', '>=', $nfromdate)
            ->whereDate('campaigns.created_at', '<=', $endDate);
        $campaign->orderBy('campaigns.id', 'DESC');
        $row = $campaign->count();
        $data = $campaign->get();
        if ($campaign) {
            $return['code']    = 200;
            $return['data']    = $data;
            $return['row']     = $row;
            $return['message'] = 'Campaigns list retrieved successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function cmpreportDetail(Request $request)
    {
        $campid = base64_decode($request->cmp_id);
        $startDate = $request->startDate;
        $nfromdate = date('Y-m-d', strtotime($startDate . ' - 1 days'));
        $endDate = date('Y-m-d', strtotime($request->endDate . ' + 1 days'));
        $limit = $request->lim;
        $page = $request->page;
        $pg = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;

        if ($startDate == '' && $endDate == '') {
           $sql1 = DB::table('camp_budget_utilize')
        ->selectRaw("FORMAT(ss_camp_budget_utilize.amount,2) as Total")
        ->addSelect('udate as Created','impressions as Imprs','clicks as Clicks')->where("camp_id",$campid);
        } else {
           $sql1 = DB::table('camp_budget_utilize')
        ->selectRaw("FORMAT(ss_camp_budget_utilize.amount,2) as Total")
        ->addSelect('udate as Created','impressions as Imprs','clicks as Clicks')->where("camp_id",$campid)->whereBetween('udate',[$nfromdate,$endDate]);
        }
        $datas = $sql1->offset($start)->limit($limit)->get();
        $row = count($datas);
        if ($row != null) {
        $totalData = DB::table("camp_budget_utilize")
                    ->selectRaw("SUM(impressions) as total_impression,
                    SUM(clicks) as total_click,
                    SUM(amount) as total_amount")
                    ->where("camp_id", $campid)
                    ->first();
         $totalData->total_amount = round($totalData->total_amount,2);          
            $return['code']        = 200;
            $return['data']        = $datas;
            $return['row']         = $row;
            $return['total']       = $totalData;
            $return['message']     = 'List retrieved successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function cmpreportImprDetail(Request $request)
    {
        $campid = base64_decode($request->cmp_id);
        $startDate = $request->startDate;
        $nfromdate = date('Y-m-d', strtotime($startDate . ' - 1 days'));
        $endDate = $request->endDate;
        $limit = $request->lim;
        $page = $request->page;
        $pg = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;

        if ($startDate == '' && $endDate == '') {
            $impDetail = DB::table('ad_impressions')
                ->select(DB::raw("DATE(created_at) as date"), DB::raw("FORMAT(sum(amount),2) as total"), DB::raw("count(id) as impr_total"), 'id', 'impression_id', 'advertiser_code', 'device_type', 'device_os', 'ad_type', 'ip_addr', 'country')
                ->where('campaign_id', $campid);
        } else {
            $impDetail = DB::table('ad_impressions')
                ->select(DB::raw("DATE(created_at) as date"), DB::raw("FORMAT(sum(amount),2) as total"), DB::raw("count(id) as impr_total"), 'id', 'impression_id', 'advertiser_code', 'device_type', 'device_os', 'ad_type', 'ip_addr', 'country')
                ->where('campaign_id', $campid)
                ->whereDate('created_at', '>=', $nfromdate)
                ->whereDate('created_at', '<=', $endDate);
        }

        $impDetail->groupBy('date')->orderBy('ad_impressions.id', 'DESC');

        $row1 = $impDetail->get();
        $row = $row1->count();
        $data = $impDetail->offset($start)->limit($limit)->get();
        // print_r($data); exit;

        if ($row != null) {
            //dd($data);
            //exit;
            $totalimp = '0';
            $totalamt = '0';
            foreach ($data as $vallue) {
                $totalimp += $vallue->impr_total;
                $totalamt += $vallue->total;
                $vallue->Total = $vallue->total;
                unset($vallue->Totals);
            }
            $asdsdas = array('total_impression' => round($totalimp, 2), 'total_amount' => round($totalamt, 2));
            $return['code']        = 200;
            $return['data']        = $data;
            $return['row']         = $row;
            $return['total']      = $asdsdas;
            $return['message']     = 'List retrieved successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function cmpreportClicksDetail(Request $request)
    {
        $campid = base64_decode($request->cmp_id);
        $startDate = $request->startDate;
        $nfromdate = date('Y-m-d', strtotime($startDate . ' - 1 days'));
        $endDate = $request->endDate;

        $limit = $request->lim;
        $page = $request->page;
        $pg = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;

        if ($startDate == '' && $endDate == '') {
            $clickDetail = DB::table('user_camp_click_logs')
                ->select(DB::raw("DATE(created_at) as date"), DB::raw("FORMAT(sum(amount),2) as total"), DB::raw("count(id) as click_total"), 'id', 'advertiser_code', 'device_type', 'device_os', 'ad_type', 'ip_address', 'country')
                ->where('campaign_id', $campid);
        } else {
            $clickDetail = DB::table('user_camp_click_logs')
                ->select(DB::raw("DATE(created_at) as date"), DB::raw("FORMAT(sum(amount),2) as total"), DB::raw("count(id) as click_total"), 'id', 'advertiser_code', 'device_type', 'device_os', 'ad_type', 'ip_address', 'country')
                ->where('campaign_id', $campid)
                ->whereDate('created_at', '>=', $nfromdate)
                ->whereDate('created_at', '<=', $endDate);
        }
        // ->groupBy('date');
        $row = $clickDetail->count();
        $clickDetail->groupBy('date')->orderBy('user_camp_click_logs.id', 'DESC');
        $row1 = $clickDetail->get();
        $row = $row1->count();
        $data = $clickDetail->offset($start)->limit($limit)->get();
        // print_r($data);
        // exit;
        // $row = $data->count();
        if ($row != null) {
            $totalclick = '0';
            $totalamt = '0';
            foreach ($data as $vallue) {
                $totalclick += $vallue->click_total;
                $totalamt += $vallue->total;
                $vallue->Total = $vallue->total;
                unset($vallue->Totals);
            }
            $asdsdas = array('total_click' => round($totalclick, 2), 'total_amount' => round($totalamt, 2));
            $return['code']        = 200;
            $return['data']        = $data;
            $return['row']         = $row;
            $return['total']       = $asdsdas;
            $return['message']     = 'List retrieved successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function imprDetailExportExcel(Request $request)
    {
        $campid = base64_decode($request->cmp_id);
        $impDetailExcel = AdImpression::select('device_type', 'device_os', 'amount', 'ip_addr', 'country', 'created_at')->where('campaign_id', $campid)->get();

        $row = $impDetailExcel->count();
        if ($row !== null) {

            $return['code']        = 200;
            $return['data']        = $impDetailExcel;
            $return['message']     = 'List retrieved successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function clickDetailExportExcel(Request $request)
    {
        $campid = base64_decode($request->cmp_id);
        $clickDetailExcel = UserCampClickLog::select('device_type', 'device_os', 'amount', 'ip_address', 'country', 'created_at')->where('campaign_id', $campid)->get();

        $row = $clickDetailExcel->count();
        if ($row !== null) {

            $return['code']        = 200;
            $return['data']        = $clickDetailExcel;
            $return['message']     = 'List retrieved successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function impCampExportExcel(Request $request)
    {
        $startDate = $request->startDate;
        $nfromdate = date('Y-m-d', strtotime($startDate . ' - 1 days'));
        $endDate = $request->endDate;
        $impDetailExcel = DB::table('ad_impressions')
            ->select(DB::raw("DATE(created_at) as date"), DB::raw("FORMAT(sum(amount),2) as total"), DB::raw("count(id) as impr_total"), 'id', 'impression_id', 'advertiser_code', 'campaign_id')
            ->whereDate('created_at', '>=', $nfromdate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('date')
            ->orderBy('ad_impressions.id', 'DESC')->get();

        $row = $impDetailExcel->count();
        if ($row !== null) {

            $return['code']        = 200;
            $return['data']        = $impDetailExcel;
            $return['message']     = 'List retrieved successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }



    public function userreport(Request $request)
    {
        $sort_order = $request->sort_order;
        $col = $request->col;
        /* Pagination Section Add  */
        $startDate = $request->startDate;
        $nfromdate = date('Y-m-d', strtotime($startDate . ' - 1 days'));
        $endDate = $request->endDate;
        $limit = $request->lim;
        $page = $request->page;
        $src = $request->src;
        $pg = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;
        /* Pagination Section */
        $usertype = $request->user_type;
        $acnttype = $request->acttype;
        $statustype = $request->statustype;
        $userdata = DB::table('users')
            ->select(DB::raw("CONCAT(ss_users.first_name, ' ', ss_users.last_name) as name"), 'users.uid', 'users.email', 'users.phone', 'users.wallet', 'users.user_type', 'users.account_type', 'users.ac_verified', 'users.created_at', 'users.status')
            ->where('users.trash', 0);
        if ($usertype) {
            $userdata->where('users.user_type', $usertype);
        }
        if ($startDate && $endDate) {
            $userdata->whereDate('users.created_at', '>=', $nfromdate)
                ->whereDate('users.created_at', '<=', $endDate);
        }
        if ($src) {
            $userdata->whereRaw('concat(ss_users.first_name," ",ss_users.last_name," ",ss_users.email, ss_users.uid,ss_users.phone,ss_users.wallet, ss_users.created_at) like ?', "%{$src}%");
        }
        if ($acnttype > 0 && $acnttype < 2) {
            $userdata->where('users.account_type', 1);
        } elseif ($acnttype == '0') {
            $userdata->where('users.account_type', 0);
        }
        if($statustype == 'Active'){
            $userdata->where('users.status', 0);
        }elseif($statustype == 'Pending'){
            $userdata->where('users.status', 2);
        }elseif($statustype == 'Suspended'){
            $userdata->where('users.status', 3);
        }elseif($statustype == 'Hold'){
            $userdata->where('users.status', 4);
        }

        $row = $userdata->count();
        if($col){
          $data = $userdata->offset( $start )->limit( $limit )->orderBy('users.'.$col, $sort_order)->get();
        } else{
          $data = $userdata->offset( $start )->limit( $limit )->orderBy('users.id', 'desc')->get();
        }
        //$data = $userdata->offset($start)->limit($limit)->get();
        foreach ($data as $value) {
            $usertypes = $value->user_type;
            if ($usertypes == 1) {
                $value->user_type = 'Advertiser';
            } else if ($usertypes == 2) {
                $value->user_type = 'Publisher';
            } else if ($usertypes == 3) {
                $value->user_type = 'Both';
            } else {
                $value->user_type = 'N/A';
            }
            $accounttype = $value->account_type;
            if ($accounttype == '0') {
                $value->account_type = 'Client';
            } else if ($accounttype == '1') {
                $value->account_type = 'Inhouse';
            } else {
                $value->account_type = 'N/A';
            }
            $acverified = $value->ac_verified;
            if ($acverified == 0) {
                $value->ac_verified = 'Pending';
            } else if ($acverified == 1) {
                $value->ac_verified = 'Verified';
            } else {
                $value->ac_verified = 'N/A';
            }
        }
        if ($userdata) {
            $return['code']    = 200;
            $return['data']    = $data;
            $return['row']     = $row;
            $return['message'] = 'User list retrieved successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    public function pdfuser(Request $request)
    {
        $data = DB::table('users')
            ->select(DB::raw("CONCAT(ss_users.first_name, ' ', ss_users.last_name) as name"), 'users.uid', 'users.email', 'users.phone', 'users.wallet', 'users.user_type', 'users.account_type', 'users.ac_verified', 'users.status')
            ->where('users.trash', 0)
            ->orderBy('users.id', 'DESC')
            ->get();
        foreach ($data as $value) {
            $usertypes = $value->user_type;
            if ($usertypes == 1) {
                $value->user_type = 'Advertiser';
            } else if ($usertypes == 2) {
                $value->user_type = 'Publisher';
            } else if ($usertypes == 3) {
                $value->user_type = 'Both';
            } else {
                $value->user_type = 'N/A';
            }
            $accounttype = $value->account_type;
            if ($accounttype == '0') {
                $value->account_type = 'Client';
            } else if ($accounttype == '1') {
                $value->account_type = 'Inhouse';
            } else {
                $value->account_type = 'N/A';
            }
            $acverified = $value->ac_verified;
            if ($acverified == 0) {
                $value->ac_verified = 'Pending';
            } else if ($acverified == 1) {
                $value->ac_verified = 'Verified';
            } else {
                $value->ac_verified = 'N/A';
            }
        }
        $pdf = PDF::loadView('AdminReport.user_report', ['row' => $data])->setPaper('a4', 'landscape');
        return $pdf->download('file-pdf.pdf');
    }
}
