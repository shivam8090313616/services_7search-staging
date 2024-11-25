<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Activitylog;
use Illuminate\Support\Str;
use App\Mail\CreateCampMail;
use App\Models\CampaignLogs;
use Illuminate\Http\Request;
use App\Models\AdBannerImage;
use App\Mail\CreateCampMailAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreCampaignRequest;

class CampaignController extends Controller
{

     // START --- Universal function for update and store campaign
     private function logActivity($uid, $type, $description, $campaign_id)
     {
         $activitylog = new Activitylog();
         $activitylog->uid = $uid;
         $activitylog->type = $type;
         $activitylog->description = $description;
         $type == 'Added Campaign' ? '' . $campaign_id . ' is Added Successfully' : '' . $campaign_id . ' is edit Successfully';
         $activitylog->status = '1';
         $activitylog->save();
     }
 
     private function getCampaignTypePrefix($adType)
     {
         $adTypeMapping = [
             'text' => 'CMPT',
             'banner' => 'CMPB',
             'native' => 'CMPN',
             'video' => 'CMPV',
             'popup' => 'CMPP',
             'social' => 'CMPS',
         ];
         return $adTypeMapping[$adType] ?? 'Invalid';
     }
 
     private function sendCampaignEmail($campaign, $uid, $isUpdate = false)
     {
         $user = DB::table('users')->select('email', 'first_name', 'last_name')->where('uid', $uid)->first();
         $email = $user->email;
         $fullname = $user->first_name . ' ' . $user->last_name;
 
         $subject = $isUpdate ? 'Campaign Updated Successfully - 7Search PPC' : 'Campaign Created successfully - 7Search PPC';
 
         $data['details'] = array(
             'subject' => $subject,
             'fullname' => $fullname,
             'usersid' => $campaign->advertiser_code,
             'campaignid' => $campaign->campaign_id,
             'campaignname' => $campaign->campaign_name,
             'campaignadtype' => $campaign->ad_type
         );
 
         $body = View('emailtemp.campaigncreate', $data);
         sendmailUser($subject, $body, $email);
     }
 
     private function sendAdminEmail($campaign, $isUpdate = false)
     {
         $adminmail1 = 'advertisersupport@7searchppc.com';
         $adminmail2 = 'info@7searchppc.com';
 
         $subject = $isUpdate ? 'Campaign Updated Successfully - 7Search PPC' : 'Campaign Created successfully - 7Search PPC';
 
         $data['details'] = array(
             'subject' => $subject,
             'fullname' => $campaign->campaign_name,
             'usersid' => $campaign->advertiser_code,
             'campaignid' => $campaign->campaign_id,
             'campaignname' => $campaign->campaign_name,
             'campaignadtype' => $campaign->ad_type
         );
 
         $bodyadmin = View('emailtemp.campaigncreateadmin', $data);
         sendmailAdmin($subject, $bodyadmin, $adminmail1, $adminmail2);
     }
 
     // END --- Universal function for update and store campaign


    /* Text Campaign Funtions */
    public function storeText(Request $request)
    {
        $campLogData = [];
        // $validator = Validator::make($request->all(), (new StoreCampaignRequest)->StoreUpdateTextValidation());
        $validator = Validator::make($request->all(), 
        (new StoreCampaignRequest)->StoreUpdateTextValidation(), 
        (new StoreCampaignRequest)->messages());
        if ($validator->fails()) {
            return response()->json([
                'code' => 100,
                'errors' => $validator->errors(),
                'message' => 'Validation Error'
            ], 422);
        }
        if ((float) $request->daily_budget < (float) $request->cpc_amt) {
            $return['code'] = 101;
            $return['message'] = 'Bidding Price must be less than or equal to Daily Budget.';
            return response()->json($return);
        }
        
        // $validator = Validator::make(
        //     $request->all(),
        //     [
        //         'uid'               => 'required',
        //         'ad_type'           => 'required',
        //         'campaign_name'     => 'required',
        //         'campaign_type'     => 'required',
        //         'website_category'  => 'required',
        //         'device_type'       => 'required',
        //         'device_os'         => 'required',
        //         'ad_title'          => 'required',
        //         'ad_description'    => 'required',
        //         'target_url'        => 'required',
        //         'countries'         => 'required',
        //         'pricing_model'     => 'required',
        //         'daily_budget'      => 'required|numeric|min:15',
        //         'cpc_amt'           => ($request->countries == 'All') ? ['required','numeric', 'gte:0.0001'] : ['required','numeric', 'gte:0.000001'],
        //     ],[
        //         'cpc_amt.numeric' => 'The cpc_amt field must be a number.',
        //         'cpc_amt.gte' => 'The cpc_amt field must be greater than or equal to 0.0001.',
        //       ]);
        // if ($validator->fails()) {
        //     $return['code']    = 100;
        //     $return['error']   = $validator->errors();
        //     $return['message'] = 'Validation Error';
        //     return json_encode($return);
        // }
        // $catid = Category::select('cat_name')->where('id', $request->website_category)->where('status', 1)->where('trash', 0)->first();
        // $result = onchangecpcValidation($request->pricing_model,$catid->cat_name,$request->countries);
        // $arrResult = json_decode($result);
        // $base_amt = $arrResult->base_amt;
        // if($request->countries == 'All' && $request->cpc_amt < $base_amt){
        //     $return['code']    = 101;
        //     $return['message'] = 'Error! Invalid Bidding Amount.';
        //     return json_encode($return);
        // }else if($request->countries != 'All' && $request->cpc_amt < $base_amt){
        //     $return['code']    = 101;
        //     $return['message'] = 'Error! Invalid Bidding Amount.';
        //     return json_encode($return);
        // }       
        $campaign = new Campaign();
        $campaign->ad_type = $request->ad_type;
        $user = DB::table('users')->select('id', 'first_name', 'last_name', 'email')->where('uid', $request->uid)->first();
        if(empty($user)){
            $return['code']    = 101;
            $return['message'] = 'Error! Invalid user Id.';
            return json_encode($return);
        }
        // $campaign->countries =  $request->countries;
        // if ($request->countries != 'All') {
        //     $targetCountries    = json_decode($request->countries);
        //     $countryId          = implode(',', array_column($targetCountries, 'value'));
        //     $countryName        = implode(',', array_column($targetCountries, 'label'));
        //     $campaign->country_name     = $countryName;
        //     $campaign->country_ids      = $countryId;
        //     $countryIso                 = setCountryIso($countryId);
        //     $campaign->country_code     = $countryIso;
        // }
        $campaign->countries = $request->countries;
        if ($request->countries != 'All') {
            $targetCountries = json_decode($request->countries);
            $campaign->country_name = implode(',', array_column($targetCountries, 'label'));
            $campaign->country_ids = implode(',', array_column($targetCountries, 'value'));
            $campaign->country_code = setCountryIso($campaign->country_ids);
        }
        $campaign->advertiser_id = DB::table('users')->where('uid', $request->uid)->value('id');
        $campaign->advertiser_code  = $request->uid;
        $campaign->campaign_name    = $request->campaign_name;
        $campaign->campaign_id = $this->getCampaignTypePrefix($request->ad_type) . strtoupper(uniqid());
        $campaign->campaign_type    = $request->campaign_type;
        $campaign->device_type      = $request->device_type;
        $campaign->device_os        = $request->device_os;
        $campaign->ad_title         = $request->ad_title;
        $campaign->ad_description   = $request->ad_description;
        $campaign->target_url       = $request->target_url;
        $campaign->conversion_url   = $request->conversion_url;
        $campaign->website_category = $request->website_category;
        $campaign->daily_budget     = $request->daily_budget;
        $campaign->pricing_model    = $request->pricing_model;
        $campaign->cpc_amt          = $request->cpc_amt;
        $campaign->countries        =  $request->countries;
        // if ($request->pricing_model == 'CPM') {
        //     $cat = Category::where('id', $request->website_category)->first();
        //     $cpm = $cat->cpm;
        //     $campaign->cpc_amt          = $cpm;
        // } else {
        //     $campaign->cpc_amt          = $request->cpc_amt;
        // }
        if ($campaign->save()) {
            $this->logActivity($request->uid, $campaign->campaign_id, 'Added Campaign', $campaign->campaign_id);
            $this->sendCampaignEmail($campaign, $request->uid);
            $this->sendAdminEmail($campaign);
            return json_encode([
                'code' => 200,
                'data' => $campaign,
                'message' => 'Campaign created successfully!'
            ]);
        }

        return json_encode([
            'code' => 101,
            'message' => 'Something went wrong!'
        ]);
    
    }


    public function updateText(Request $request)
    {
        // $validator = Validator::make($request->all(), (new StoreCampaignRequest)->StoreUpdateTextValidation());
        $validator = Validator::make($request->all(), 
        (new StoreCampaignRequest)->StoreUpdateTextValidation(), 
        (new StoreCampaignRequest)->messages());
        if ($validator->fails()) {
            return response()->json([
                'code' => 100,
                'errors' => $validator->errors(),
                'message' => 'Validation Error'
            ], 422);
        }
        if ((float) $request->daily_budget < (float) $request->cpc_amt) {
            $return['code'] = 101;
            $return['message'] = 'Bidding Price must be less than or equal to Daily Budget.';
            return response()->json($return);
        }

        $cid = $request->cid;
        $campaign = Campaign::where('campaign_id', $cid)->first();
        if (!$campaign) {
            return json_encode([
                'code' => 101,
                'message' => 'Something went wrong!'
            ]);
        }

        if ($request->daily_budget >= $campaign->daily_budget) {
            DB::table('end_budget_mail')
                ->where([
                    'uid' => $campaign->advertiser_code,
                    'camp_id' => $cid,
                ])
                ->whereDate('created_at', date('Y-m-d'))
                ->update(['send_mail_count' => 1]);
        }

        if ($request->device_type == $campaign->device_type &&
                $request->device_os == $campaign->device_os &&
                $request->ad_title == $campaign->ad_title &&
                $request->ad_description == $campaign->ad_description &&
                $request->website_category == $campaign->website_category &&
                $request->pricing_model == $campaign->pricing_model &&
                $request->cpc_amt == $campaign->cpc_amt &&
                $campaign->countries == $request->countries) {
            $status = 1;
            if ($request->campaign_name != $campaign->campaign_name || $request->daily_budget != $campaign->daily_budget) {
                if ($campaign->status == 2) {
                    $status = 2;
                    $campaign->status = 2;
                }
            }
        } else {
            $status = 1;
            if (in_array($campaign->status, [2, 4, 5])) {
                $campaign->status = 1;
            }
        }

        $campaign->campaign_name = $request->campaign_name;
        $campaign->device_type = $request->device_type;
        $campaign->device_os = $request->device_os;
        $campaign->ad_title = $request->ad_title;
        $campaign->ad_description = $request->ad_description;
        $campaign->website_category = $request->website_category;
        $campaign->daily_budget = $request->daily_budget;
        $campaign->pricing_model = $request->pricing_model;
        $campaign->cpc_amt = $request->cpc_amt;
        if ($request->target_url != '') {
            $data['target_url'] = $request->target_url;
        }
        $campaign->conversion_url = $request->conversion_url;
        $campaign->countries = $request->countries;
        if ($request->countries != 'All') {
            $targetCountries = json_decode($request->countries);
            $campaign->country_name = implode(',', array_column($targetCountries, 'label'));
            $campaign->country_ids = implode(',', array_column($targetCountries, 'value'));
            $campaign->country_code = setCountryIso($campaign->country_ids);
        } else {
            $campaign->country_name = '';
            $campaign->country_ids = '';
            $campaign->country_code = '';
        }

        if ($campaign->update()) {
            updateCamps($cid, $status);
            $this->logActivity($request->uid, $campaign->campaign_id, 'Update Campaign', $campaign->campaign_id);
            $this->sendCampaignEmail($campaign, $request->uid, true);
            $this->sendAdminEmail($campaign, true);
            return json_encode([
                'code' => 200,
                'data' => $campaign,
                'message' => 'Operation completed successfully!'
            ]);
        }

        return json_encode([
            'code' => 101,
            'message' => 'Something went wrong!'
        ]);
    }




    /* PopUnder Campaign Funtions */


    public function storePopunder(Request $request)
    {
        // $validator = Validator::make($request->all(), (new StoreCampaignRequest)->StoreUpdatePopunderValidation());
        $validator = Validator::make($request->all(), 
        (new StoreCampaignRequest)->StoreUpdatePopunderValidation(), 
        (new StoreCampaignRequest)->messages());
        if ($validator->fails()) {
            return response()->json([
                'code' => 100,
                'errors' => $validator->errors(),
                'message' => 'Validation Error'
            ], 422);
        }
        if ((float) $request->daily_budget < (float) $request->cpc_amt) {
            $return['code'] = 101;
            $return['message'] = 'Bidding Price must be less than or equal to Daily Budget.';
            return response()->json($return);
        }

        $campaign = new Campaign();

        $campaign->ad_type = $request->ad_type;
        $campaign->campaign_id = $this->getCampaignTypePrefix($request->ad_type) . strtoupper(uniqid());

        $user = DB::table('users')->select('id', 'first_name', 'last_name', 'email')->where('uid', $request->uid)->first();
        if (empty($user)) {
            $return['code'] = 101;
            $return['message'] = 'Error! Invalid user Id.';
            return json_encode($return);
        }
        $campaign->campaign_id = $this->getCampaignTypePrefix($request->ad_type) . strtoupper(uniqid());
        $campaign->advertiser_id = $user->id;
        $campaign->advertiser_code = $request->uid;
        $campaign->campaign_name = $request->campaign_name;
        $campaign->campaign_type = $request->campaign_type;
        $campaign->device_type = $request->device_type;
        $campaign->device_os = $request->device_os;
        $campaign->target_url = $request->target_url;
        $campaign->website_category = $request->website_category;
        $campaign->daily_budget = $request->daily_budget;
        $campaign->pricing_model = 'CPM';
        $cat = Category::where('id', $request->website_category)->first();
        $campaign->cpc_amt = $request->cpc_amt;
        $campaign->countries = $request->countries;
        if ($request->countries != 'All') {
            $targetCountries = json_decode($request->countries);
            $campaign->country_name = implode(',', array_column($targetCountries, 'label'));
            $campaign->country_ids = implode(',', array_column($targetCountries, 'value'));
            $campaign->country_code = setCountryIso($campaign->country_ids);
        }
        if ($campaign->save()) {
            $this->logActivity($request->uid, $campaign->campaign_id, 'Added Campaign', $campaign->campaign_id);
            $this->sendCampaignEmail($campaign, $request->uid);
            $this->sendAdminEmail($campaign);
            return json_encode([
                'code' => 200,
                'data' => $campaign,
                'message' => 'Operation completed successfully!'
            ]);
        }
        return json_encode([
                'code' => 101,
                'message' => 'Something went wrong!'
            ]);
    }

    public function updatePopUnder(Request $request)
    {
        // $validator = Validator::make($request->all(), (new StoreCampaignRequest)->StoreUpdatePopunderValidation());
        $validator = Validator::make($request->all(), 
        (new StoreCampaignRequest)->StoreUpdatePopunderValidation(), 
        (new StoreCampaignRequest)->messages());
        if ($validator->fails()) {
            return response()->json([
                'code' => 100,
                'errors' => $validator->errors(),
                'message' => 'Validation Error'
            ], 422);
        }
        if ((float) $request->daily_budget < (float) $request->cpc_amt) {
            $return['code'] = 101;
            $return['message'] = 'Bidding Price must be less than or equal to Daily Budget.';
            return response()->json($return);
        }

        $cid = $request->cid;
        $campaign = Campaign::where('campaign_id', $cid)->first();
        if (!$campaign) {
            return json_encode([
                'code' => 101,
                'message' => 'Something went wrong!'
            ]);
        }

        if ($request->daily_budget >= $campaign->daily_budget) {
            DB::table('end_budget_mail')
                ->where([
                    'uid' => $campaign->advertiser_code,
                    'camp_id' => $cid,
                ])
                ->whereDate('created_at', date('Y-m-d'))
                ->update(['send_mail_count' => 1]);
        }

        if ($request->device_type == $campaign->device_type &&
                $request->device_os == $campaign->device_os &&
                $request->ad_title == $campaign->ad_title &&
                $request->ad_description == $campaign->ad_description &&
                $request->website_category == $campaign->website_category &&
                $request->pricing_model == $campaign->pricing_model &&
                $request->cpc_amt == $campaign->cpc_amt &&
                $campaign->countries == $request->countries) {
            $status = 1;
            if ($request->campaign_name != $campaign->campaign_name || $request->daily_budget != $campaign->daily_budget) {
                if ($campaign->status == 2) {
                    $status = 2;
                    $campaign->status = 2;
                }
            }
        } else {
            $status = 1;
            if (in_array($campaign->status, [2, 4, 5])) {
                $campaign->status = 1;
            }
        }

        $campaign->campaign_name = $request->campaign_name;
        $campaign->device_type = $request->device_type;
        $campaign->device_os = $request->device_os;
        $campaign->website_category = $request->website_category;
        $campaign->daily_budget = $request->daily_budget;
        if ($request->target_url != '') {
            $campaign->target_url = $request->target_url;
        }
        $campaign->pricing_model = 'CPM';
        $cat = Category::where('id', $request->website_category)->first();
        $campaign->cpc_amt = $request->cpc_amt;
        $campaign->countries = $request->countries;
        if ($request->countries != 'All') {
            $targetCountries = json_decode($request->countries);
            $campaign->country_name = implode(',', array_column($targetCountries, 'label'));
            $campaign->country_ids = implode(',', array_column($targetCountries, 'value'));
            $campaign->country_code = setCountryIso($campaign->country_ids);
        } else {
            $campaign->country_name = '';
            $campaign->country_ids = '';
            $campaign->country_code = '';
        }
        if ($campaign->update()) {
            updateCamps($cid, $status);
            $this->logActivity($request->uid, $campaign->campaign_id, 'Update Campaign', $campaign->campaign_id);
            $this->sendCampaignEmail($campaign, $request->uid, true);
            $this->sendAdminEmail($campaign, true);
            return json_encode([
                'code' => 200,
                'data' => $campaign,
                'message' => 'Operation completed successfully!'
            ]);
        }
        return json_encode([
            'code' => 101,
            'message' => 'Something went wrong!'
        ]);
    }

    /* ----------------------------- Banner Campaign Funtions ---------------------------------- */

    public function storeBanner(Request $request)
    {

        $validator = Validator::make($request->all(), 
        (new StoreCampaignRequest)->StoreUpdateBannerValidation(), 
        (new StoreCampaignRequest)->messages());
        if ($validator->fails()) {
            return response()->json([
                'code' => 100,
                'errors' => $validator->errors(),
                'message' => 'Validation Error'
            ], 422);
        }
        if ((float) $request->daily_budget < (float) $request->cpc_amt) {
            $return['code'] = 101;
            $return['message'] = 'Bidding Price must be less than or equal to Daily Budget.';
            return response()->json($return);
        }
        $images =  $request->images;
        $validationResponse = StoreCampaignRequest::validateImages($images);
        if ($validationResponse !== null) {
             $code = $validationResponse->getData()->code ?? null;
             $message = $validationResponse->getData()->message ?? null;
             if($code == 101 || $code == 102 || $code == 103){
                 return response()->json(['message' => $message]); 
             }
        }
        $campaign = new Campaign();
        $campLogData = [];
        $user = DB::table('users')->select('id', 'first_name', 'last_name', 'email')->where('uid', $request->uid)->first();
        if (empty($user)) {
            $return['code'] = 101;
            $return['message'] = 'Error! Invalid user Id.';
            return json_encode($return);
        }
        $campaign->countries = $request->countries;
        $campaign->ad_type = $request->ad_type;
        if ($request->countries != 'All') {
            $targetCountries = json_decode($request->countries);
            $campaign->country_name = implode(',', array_column($targetCountries, 'label'));
            $campaign->country_ids = implode(',', array_column($targetCountries, 'value'));
            $campaign->country_code = setCountryIso($campaign->country_ids);
        }
        $campaign->campaign_id = $this->getCampaignTypePrefix($request->ad_type) . strtoupper(uniqid());
        $campaign->advertiser_id = $user->id;
        $campaign->advertiser_code = $request->uid;
        $campaign->campaign_name = $request->campaign_name;
        $campaign->campaign_type = $request->campaign_type;
        $campaign->device_type = $request->device_type;
        $campaign->device_os = $request->device_os;
        $campaign->ad_title = $request->ad_title;
        $campaign->ad_description = $request->ad_description;
        $campaign->target_url = $request->target_url;
        $campaign->conversion_url = $request->conversion_url;
        $campaign->website_category = $request->website_category;
        $campaign->daily_budget = $request->daily_budget;
        $campaign->pricing_model = $request->pricing_model;
        $campaign->cpc_amt = $request->cpc_amt;
        $images = $request->images;
        if ($campaign->save()) {
            $this->logActivity($request->uid, $campaign->campaign_id, 'Add Campaign', $campaign->campaign_id);
            if ($images) {
                foreach ($images as $image) {
                    $imageName = basename($image['img']);
                    $response = storeCDNImage($imageName);
                    $arr = [
                        'campaign_id' => $campaign->campaign_id,
                        'advertiser_code' => $campaign->advertiser_code,
                        'image_type' => $image['type'],
                        'image_path' => $image['img'],
                        'cdn_path' => $response['image-path'],
                        'cdnimg_id' => $response['image-id'],
                    ];

                    AdBannerImage::insert($arr);
                }
            }
            $this->sendCampaignEmail($campaign, $request->uid);
            $this->sendAdminEmail($campaign);
            return json_encode([
                'code' => 200,
                'data' => $campaign,
                'message' => 'Operation completed successfully!'
            ]);
        }

        return json_encode([
            'code' => 101,
            'message' => 'Something went wrong!'
        ]);
    }

    public function updateBanner(Request $request)
    {

        $validator = Validator::make($request->all(), 
        (new StoreCampaignRequest)->StoreUpdateBannerValidation(), 
        (new StoreCampaignRequest)->messages());
        if ($validator->fails()) {
            return response()->json([
                'code' => 100,
                'errors' => $validator->errors(),
                'message' => 'Validation Error'
            ], 422);
        }
        if ((float) $request->daily_budget < (float) $request->cpc_amt) {
            $return['code'] = 101;
            $return['message'] = 'Bidding Price must be less than or equal to Daily Budget.';
            return response()->json($return);
        }

        $campLogData = [];
        $cid = $request->cid;
        $campaign = Campaign::where('campaign_id', $cid)->first();
        if (!$campaign) {
            return json_encode([
                'code' => 101,
                'message' => 'Something went wrong!'
            ]);
        }

        if ($request->daily_budget >= $campaign->daily_budget) {
            DB::table('end_budget_mail')
                ->where([
                    'uid' => $campaign->advertiser_code,
                    'camp_id' => $cid,
                ])
                ->whereDate('created_at', date('Y-m-d'))
                ->update(['send_mail_count' => 1]);
        }
        $bannerImages = AdBannerImage::where('campaign_id', $cid)->get();
        if ($request->device_type == $campaign->device_type &&
                $request->device_os == $campaign->device_os &&
                $request->ad_title == $campaign->ad_title &&
                $request->ad_description == $campaign->ad_description &&
                $request->website_category == $campaign->website_category &&
                $request->pricing_model == $campaign->pricing_model &&
                $request->cpc_amt == $campaign->cpc_amt &&
                $campaign->images == $request->images &&
                $campaign->countries == $request->countries) {
            $status = 1;
            $hint = 1;
            if ($request->campaign_name != $campaign->campaign_name || $request->daily_budget != $campaign->daily_budget) {
                if ($campaign->status == 2) {
                    $status = 2;
                    $hint = 2;
                    $campaign->status = 2;
                }
            }
        } else {
            $status = 1;
            $hint = 2;
            if (in_array($campaign->status, [2, 4, 5])) {
                $campaign->status = 1;
            }
        }

        if ($request->images || $request->del_images) {
            $previousImages = [];
            $updatedImages = [];
            $arr = [];
            if ($status == 1) {
                if ($hint == 2) {
                    $status = 2;
                } else {
                    $status = 1;
                }
            } else {
                $status = 2;
            }
            foreach ($bannerImages as $bannerImage) {
                $previousImages[] = $bannerImage->image_type;
                $arr[] = $bannerImage->image_type;
            }
            if (!empty($request->images)) {
                foreach ($request->images as $image) {
                    $image['type'];
                    if (!in_array($image['type'], $arr)) {
                        $arr2[] = $image['type'];
                    } else {
                        $arr1[] = $image['type'];
                    }
                    (!empty($arr1) && $updatedImages['update'] = $arr1);
                    (!empty($arr2) && $updatedImages['added'] = $arr2);
                }
            }
            if (!empty($request->del_images)) {
                foreach ($request->del_images as $del_image) {
                    $updatedImages['delete'][] = $del_image;
                    $previousImages[] = $del_image;
                }
            }
            $campLogData['images']['previous'] = array_unique($previousImages);
            $campLogData['images']['updated'] = $updatedImages;
        }
        $campLogData['message'] = 'User has updated the campaign!';

        $campaign->countries = $request->countries;
        if ($request->countries != 'All') {
            $targetCountries = json_decode($request->countries);
            $campaign->country_name = implode(',', array_column($targetCountries, 'label'));
            $campaign->country_ids = implode(',', array_column($targetCountries, 'value'));
            $campaign->country_code = setCountryIso($campaign->country_ids);
        } else {
            $campaign->country_name = '';
            $campaign->country_ids = '';
            $campaign->country_code = '';
        }
        $campaign->campaign_name = $request->campaign_name;
        $campaign->device_type = $request->device_type;
        $campaign->device_os = $request->device_os;
        $campaign->ad_title = $request->ad_title;
        $campaign->ad_description = $request->ad_description;
        if ($request->target_url != '') {
            $campaign->target_url = $request->target_url;
        }
        $campaign->conversion_url = $request->conversion_url;
        $campaign->website_category = $request->website_category;
        $campaign->daily_budget = $request->daily_budget;
        $campaign->pricing_model = $request->pricing_model;
        $campaign->cpc_amt = $request->cpc_amt;
        $campaign->countries = $request->countries;
        $images = $request->images;

        if ($campaign->update()) {
            updateCamps($cid, $status);
            if(!empty($request->images)) {     
            $campaignLogs = new CampaignLogs();
            $campaignLogs->uid = $request->uid;
            $campaignLogs->campaign_type = $campaign->ad_type;
            $campaignLogs->campaign_id = $campaign->campaign_id;
            $campaignLogs->campaign_data = json_encode($campLogData);
            $campaignLogs->action = 2;
            $campaignLogs->user_type = 1;
            $campaignLogs->save();
            }
            $this->logActivity($request->uid, $campaign->campaign_id, 'Update Campaign', $campaign->campaign_id);
            if ($images) {
                foreach ($images as $image) {
                    $imageName = basename($image['img']);
                    $response = storeCDNImage($imageName);
                    $img = AdBannerImage::where([
                        ['campaign_id', '=', $campaign->campaign_id],
                        ['advertiser_code', '=', $campaign->advertiser_code],
                        ['image_type', '=', $image['type']],
                    ])->first();

                    if ($img == null) {
                        AdBannerImage::create([
                            'campaign_id' => $campaign->campaign_id,
                            'advertiser_code' => $campaign->advertiser_code,
                            'image_type' => $image['type'],
                            'image_path' => $image['img'],
                            'cdn_path'=> $response['image-path'],
                            'cdnimg_id'=> $response['image-id'],
                        ]);
                    } else {
                        $img->image_path = $image['img'];
                        $img->cdn_path = $response['image-path'];
                        $img->cdnimg_id = $response['image-id'];
                        $img->save();
                    }
                    DB::table('campaigns')->where('advertiser_code', $campaign->advertiser_code)->where('campaign_id', $campaign->campaign_id)->update(['status' => 1]);
                }
            }
            $this->sendCampaignEmail($campaign, $request->uid, true);
            $this->sendAdminEmail($campaign, true);
            return json_encode([
                'code' => 200,
                'data' => $campaign,
                'message' => 'Operation completed successfully!'
            ]);
        }

        return json_encode([
            'code' => 101,
            'message' => 'Something went wrong!'
        ]);
    }

    /* --------------------------------- Social Campaign Funtions -------------------------------- */

    public function storeSocial(Request $request)
    {

        $validator = Validator::make($request->all(), 
        (new StoreCampaignRequest)->StoreUpdateSocialValidation(), 
        (new StoreCampaignRequest)->messages());
        if ($validator->fails()) {
            return response()->json([
                'code' => 100,
                'errors' => $validator->errors(),
                'message' => 'Validation Error'
            ], 422);
        }
        $images =  $request->images;
        $validationResponse = StoreCampaignRequest::validateImages($images);
        if ($validationResponse !== null) {
             $code = $validationResponse->getData()->code ?? null;
             $message = $validationResponse->getData()->message ?? null;
             if($code == 101 || $code == 102 || $code == 103){
                 return response()->json(['message' => $message]); 
             }
        }
        if ((float) $request->daily_budget < (float) $request->cpc_amt) {
            $return['code'] = 101;
            $return['message'] = 'Bidding Price must be less than or equal to Daily Budget.';
            return response()->json($return);
        }
        $user = DB::table('users')->select('id', 'first_name', 'last_name', 'email')->where('uid', $request->uid)->first();
        if (empty($user)) {
            $return['code'] = 101;
            $return['message'] = 'Error! Invalid user Id.';
            return json_encode($return);
        }

        $campaign = new Campaign();
        $campaign->countries = $request->countries;
        $campaign->ad_type = $request->ad_type;
        if ($request->countries != 'All') {
            $targetCountries = json_decode($request->countries);
            $campaign->country_name = implode(',', array_column($targetCountries, 'label'));
            $campaign->country_ids = implode(',', array_column($targetCountries, 'value'));
            $campaign->country_code = setCountryIso($campaign->country_ids);
        }
        $campaign->campaign_id = $this->getCampaignTypePrefix($request->ad_type) . strtoupper(uniqid());
        $campaign->advertiser_id = $user->id;
        $campaign->advertiser_code = $request->uid;
        $campaign->campaign_name = $request->campaign_name;
        $campaign->campaign_type = $request->campaign_type;
        $campaign->social_ad_type = $request->social_ad_type;
        $campaign->device_type = $request->device_type;
        $campaign->device_os = $request->device_os;
        $campaign->ad_title = $request->ad_title;
        $campaign->ad_description = $request->ad_description;
        $campaign->target_url = $request->target_url;
        $campaign->conversion_url = $request->conversion_url;
        $campaign->website_category = $request->website_category;
        $campaign->daily_budget = $request->daily_budget;
        $campaign->pricing_model = $request->pricing_model;
        $campaign->cpc_amt = $request->cpc_amt;
        $campaign->countries = $request->countries;
        $images = $request->images;
        if ($campaign->save()) {
            $this->logActivity($request->uid, $campaign->campaign_id, 'Added Campaign', $campaign->campaign_id);
            if ($images) {
                foreach ($images as $image) {
                    $imageName = basename($image['img']);
                    $response = storeCDNImage($imageName);
                    $arr = [
                        'campaign_id' => $campaign->campaign_id,
                        'advertiser_code' => $campaign->advertiser_code,
                        'image_type' => $image['type'],
                        'image_path' => $image['img'],
                        'cdn_path' => $response['image-path'],
                        'cdnimg_id' => $response['image-id'],
                    ];
                    AdBannerImage::insert($arr);
                }
            }
            $this->sendCampaignEmail($campaign, $request->uid);
            $this->sendAdminEmail($campaign);
            return json_encode([
                'code' => 200,
                'data' => $campaign,
                'message' => 'Operation completed successfully!'
            ]);
        }

        return json_encode([
            'code' => 101,
            'message' => 'Something went wrong!'
        ]);
    }

    public function updateSocial(Request $request)
    {

        $validator = Validator::make($request->all(), 
        (new StoreCampaignRequest)->StoreUpdateSocialValidation(), 
        (new StoreCampaignRequest)->messages());
        if ($validator->fails()) {
            return response()->json([
                'code' => 100,
                'errors' => $validator->errors(),
                'message' => 'Validation Error'
            ], 422);
        }
        if ((float) $request->daily_budget < (float) $request->cpc_amt) {
            $return['code'] = 101;
            $return['message'] = 'Bidding Price must be less than or equal to Daily Budget.';
            return response()->json($return);
        }
        $cid = $request->cid;
        $campaign = Campaign::where('campaign_id', $cid)->first();
        if (!$campaign) {
            return json_encode([
                'code' => 101,
                'message' => 'Something went wrong!'
            ]);
        }

        if ($request->daily_budget >= $campaign->daily_budget) {
            DB::table('end_budget_mail')
                ->where([
                    'uid' => $campaign->advertiser_code,
                    'camp_id' => $cid,
                ])
                ->whereDate('created_at', date('Y-m-d'))
                ->update(['send_mail_count' => 1]);
        }

        if ($request->device_type == $campaign->device_type &&
                $request->device_os == $campaign->device_os &&
                $request->ad_title == $campaign->ad_title &&
                $request->ad_description == $campaign->ad_description &&
                $request->website_category == $campaign->website_category &&
                $request->pricing_model == $campaign->pricing_model &&
                $request->cpc_amt == $campaign->cpc_amt &&
                $campaign->countries == $request->countries) {
            $status = 1;
            $hint = 1;
            if ($request->campaign_name != $campaign->campaign_name || $request->daily_budget != $campaign->daily_budget) {
                if ($campaign->status == 2) {
                    $status = 2;
                    $hint = 2;
                    $campaign->status = 2;
                }
            }
        } else {
            $status = 1;
            $hint = 1;
            if (in_array($campaign->status, [2, 4, 5])) {
                $campaign->status = 1;
            }
        }

        $inPagePushImages = AdBannerImage::where('campaign_id', $cid)->get();
        if ($request->images) {
            if ($status == 1) {
                if ($hint == 2) {
                    $status = 2;
                } else {
                    $status = 1;
                }
            } else {
                $status = 2;
            }
            foreach ($inPagePushImages as $inPagePushImage) {
                if ($request->images[0]['img'] != $inPagePushImage->image_path) {
                    $campLogData['images']['previous'][] = $inPagePushImage->image_type;
                    $campLogData['images']['updated']['update'][] = $request->images[0]['type'];
                }
            }
        }
        $campLogData['message'] = 'User has updated the campaign!';

        $campaign->countries = $request->countries;
        if ($request->countries != 'All') {
            $targetCountries = json_decode($request->countries);
            $campaign->country_name = implode(',', array_column($targetCountries, 'label'));
            $campaign->country_ids = implode(',', array_column($targetCountries, 'value'));
            $campaign->country_code = setCountryIso($campaign->country_ids);
        } else {
            $campaign->country_name = '';
            $campaign->country_ids = '';
            $campaign->country_code = '';
        }

        $campaign->campaign_name = $request->campaign_name;
        $campaign->device_type = $request->device_type;
        $campaign->device_os = $request->device_os;
        $campaign->ad_title = $request->ad_title;
        $campaign->ad_description = $request->ad_description;
        if ($request->target_url != '') {
            $campaign->target_url = $request->target_url;
        }
        $campaign->conversion_url = $request->conversion_url;
        $campaign->website_category = $request->website_category;
        $campaign->daily_budget = $request->daily_budget;
        $campaign->social_ad_type = $request->social_ad_type;
        $campaign->pricing_model = $request->pricing_model;
        $campaign->cpc_amt = $request->cpc_amt;
        $images = $request->images;
        if ($campaign->update()) {
           // updateCamps($cid, $status);
            $this->logActivity($request->uid, $campaign->campaign_id, 'Update Campaign', $campaign->campaign_id);
            if ($images) {
                foreach ($images as $image) {
                    $imageName = basename($image['img']);
                    $response = storeCDNImage($imageName);
                    AdBannerImage::where([
                        ['campaign_id', '=', $campaign->campaign_id],
                        ['advertiser_code', '=', $campaign->advertiser_code],
                        ['image_type', '=', $image['type']],
                    ])->update(['image_path' => $image['img'],'cdn_path' => $response['image-path'],'cdnimg_id' => $response['image-id']]);
                }
                DB::table('campaigns')->where('advertiser_code', $campaign->advertiser_code)->where('campaign_id', $campaign->campaign_id)->update(['status' => 1]);
            }
            if(!empty($request->images)) {
            $campaignLogs = new CampaignLogs();
            $campaignLogs->uid = $request->uid;
            $campaignLogs->campaign_type = $campaign->ad_type;
            $campaignLogs->campaign_id = $campaign->campaign_id;
            $campaignLogs->campaign_data = json_encode($campLogData);
            $campaignLogs->action = 2;
            $campaignLogs->user_type = 1;
            $campaignLogs->save();
            }
            $this->sendCampaignEmail($campaign, $request->uid, true);
            $this->sendAdminEmail($campaign, true);
            return json_encode([
                'code' => 200,
                'data' => $campaign,
                'message' => 'Operation completed successfully!'
            ]);
        }

        return json_encode([
            'code' => 101,
            'message' => 'Something went wrong!'
        ]);
    }

    /* --------------------------- Native Campaign Funtions ----------------------------- */

    public function storeNative(Request $request)
    {
         $validator = Validator::make($request->all(), 
        (new StoreCampaignRequest)->StoreUpdateNativeValidation(), 
        (new StoreCampaignRequest)->messages());
        if ($validator->fails()) {
            return response()->json([
                'code' => 100,
                'errors' => $validator->errors(),
                'message' => 'Validation Error'
            ], 422);
        }
        $images =  $request->images;
        $validationResponse = StoreCampaignRequest::validateImages($images);
        if ($validationResponse !== null) {
             $code = $validationResponse->getData()->code ?? null;
             $message = $validationResponse->getData()->message ?? null;
             if($code == 101 || $code == 102 || $code == 103){
                 return response()->json(['message' => $message]); 
             }
        }

        if ((float) $request->daily_budget < (float) $request->cpc_amt) {
            $return['code'] = 101;
            $return['message'] = 'Bidding Price must be less than or equal to Daily Budget.';
            return response()->json($return);
        }

        $user = DB::table('users')->select('id', 'first_name', 'last_name', 'email')->where('uid', $request->uid)->first();
        if (empty($user)) {
            $return['code'] = 101;
            $return['message'] = 'Error! Invalid user Id.';
            return json_encode($return);
        }
        $campaign = new Campaign();
        $campaign->campaign_id = $this->getCampaignTypePrefix($request->ad_type) . strtoupper(uniqid());
        $campaign->advertiser_id = $user->id;
        $campaign->advertiser_code = $request->uid;
        $campaign->campaign_name = $request->campaign_name;
        $campaign->campaign_type = $request->campaign_type;
        $campaign->device_type = $request->device_type;
        $campaign->device_os = $request->device_os;
        $campaign->ad_title = $request->ad_title;
        $campaign->target_url = $request->target_url;
        $campaign->conversion_url = $request->conversion_url;
        $campaign->website_category = $request->website_category;
        $campaign->daily_budget = $request->daily_budget;
        $campaign->pricing_model = $request->pricing_model;
        $campaign->cpc_amt = $request->cpc_amt;
        $campaign->countries = $request->countries;
        $campaign->ad_type = $request->ad_type;
        if ($request->countries != 'All') {
            $targetCountries = json_decode($request->countries);
            $campaign->country_name = implode(',', array_column($targetCountries, 'label'));
            $campaign->country_ids = implode(',', array_column($targetCountries, 'value'));
            $campaign->country_code = setCountryIso($campaign->country_ids);
        }
        $images = $request->images;
        if ($campaign->save()) {
            $this->logActivity($request->uid, $campaign->campaign_id, 'Added Campaign', $campaign->campaign_id);

            if ($images) {
                foreach ($images as $image) {
                    $imageName = basename($image['img']);
                    $response = storeCDNImage($imageName);
                    $arr = [
                        'campaign_id' => $campaign->campaign_id,
                        'advertiser_code' => $campaign->advertiser_code,
                        'image_type' => $image['type'],
                        'image_path' => $image['img'],
                        'cdn_path' => $response['image-path'],
                        'cdnimg_id' => $response['image-id'],
                    ];
                    AdBannerImage::insert($arr);
                }
            }
            $this->sendCampaignEmail($campaign, $request->uid);
            $this->sendAdminEmail($campaign);
            return json_encode([
                'code' => 200,
                'data' => $campaign,
                'message' => 'Operation completed successfully!'
            ]);
        }

        return json_encode([
            'code' => 101,
            'message' => 'Something went wrong!'
        ]);
    }

    public function updateNative(Request $request)
    {

        $validator = Validator::make($request->all(), 
        (new StoreCampaignRequest)->StoreUpdateNativeValidation(), 
        (new StoreCampaignRequest)->messages());
        if ($validator->fails()) {
            return response()->json([
                'code' => 100,
                'errors' => $validator->errors(),
                'message' => 'Validation Error'
            ], 422);
        }
        if ((float) $request->daily_budget < (float) $request->cpc_amt) {
            $return['code'] = 101;
            $return['message'] = 'Bidding Price must be less than or equal to Daily Budget.';
            return response()->json($return);
        }

        $cid = $request->cid;
        $campaign = Campaign::where('campaign_id', $cid)->first();
        if (!$campaign) {
            return json_encode([
                'code' => 101,
                'message' => 'Something went wrong!'
            ]);
        }

        if ($request->daily_budget >= $campaign->daily_budget) {
            DB::table('end_budget_mail')
                ->where([
                    'uid' => $campaign->advertiser_code,
                    'camp_id' => $cid,
                ])
                ->whereDate('created_at', date('Y-m-d'))
                ->update(['send_mail_count' => 1]);
        }
        $nativeImages = AdBannerImage::where('campaign_id', $cid)->get();
        if ($request->device_type == $campaign->device_type &&
                $request->device_os == $campaign->device_os &&
                $request->ad_title == $campaign->ad_title &&
                $request->ad_description == $campaign->ad_description &&
                $request->website_category == $campaign->website_category &&
                $request->pricing_model == $campaign->pricing_model &&
                $request->cpc_amt == $campaign->cpc_amt &&
                $campaign->countries == $request->countries) {
            $status = 1;
            $hint = 1;
            if ($request->campaign_name != $campaign->campaign_name || $request->daily_budget != $campaign->daily_budget) {
                if ($campaign->status == 2) {
                    $status = 2;
                    $hint = 2;
                    $campaign->status = 2;
                }
            }
        } else {
            $status = 1;
            $hint = 1;
            if (in_array($campaign->status, [2, 4, 5])) {
                $campaign->status = 1;
            }
        }

        if ($request->images) {
            if ($status == 1) {
                if ($hint == 2) {
                    $status = 2;
                } else {
                    $status = 1;
                }
            } else {
                $status = 2;
            }
            foreach ($nativeImages as $nativeImage) {
                if ($request->images[0]['img'] != $nativeImage->image_path) {
                    $campLogData['images']['previous'][] = $nativeImage->image_type;
                    $campLogData['images']['updated']['update'][] = $request->images[0]['type'];
                }
            }
        }
        $campLogData['message'] = 'User has updated the campaign!';
        $campaign->countries = $request->countries;
        if ($request->countries != 'All') {
            $targetCountries = json_decode($request->countries);
            $campaign->country_name = implode(',', array_column($targetCountries, 'label'));
            $campaign->country_ids = implode(',', array_column($targetCountries, 'value'));
            $campaign->country_code = setCountryIso($campaign->country_ids);
        } else {
            $campaign->country_name = '';
            $campaign->country_ids = '';
            $campaign->country_code = '';
        }
        $campaign->campaign_name = $request->campaign_name;
        $campaign->device_type = $request->device_type;
        $campaign->device_os = $request->device_os;
        $campaign->ad_title = $request->ad_title;
        if ($request->target_url != '') {
            $campaign->target_url = $request->target_url;
        }
        $campaign->conversion_url = $request->conversion_url;
        $campaign->website_category = $request->website_category;
        $campaign->daily_budget = $request->daily_budget;
        $campaign->pricing_model = $request->pricing_model;
        $campaign->cpc_amt = $request->cpc_amt;
        $images = $request->images;
        if ($campaign->update()) {
            updateCamps($cid, $status);
            $this->logActivity($request->uid, $campaign->campaign_id, 'Update Campaign', $campaign->campaign_id);

            if ($images) {
                foreach ($images as $image) {
                    $imageName = basename($image['img']);
                    $response = storeCDNImage($imageName);
                    $img = AdBannerImage::where([
                        ['campaign_id', '=', $campaign->campaign_id],
                        ['advertiser_code', '=', $campaign->advertiser_code],
                        ['image_type', '=', $image['type']],
                    ])->first();

                    if ($img == null) {
                        AdBannerImage::create([
                            'campaign_id' => $campaign->campaign_id,
                            'advertiser_code' => $campaign->advertiser_code,
                            'image_type' => $image['type'],
                            'image_path' => $image['img'],
                            'cdn_path' => $response['image-path'],
                            'cdnimg_id' => $response['image-id'],
                        ]);
                    } else {
                        $img->image_path = $image['img'];
                        $img->cdn_path = $response['image-path'];
                        $img->cdnimg_id = $response['image-id'];
                        $img->save();
                    }
                  DB::table('campaigns')->where('advertiser_code', $campaign->advertiser_code)->where('campaign_id', $campaign->campaign_id)->update(['status' => 1]);
                }
            }
            $this->sendCampaignEmail($campaign, $request->uid, true);
            $this->sendAdminEmail($campaign, true);
            if(!empty($request->images)) {
            $campaignLogs = new CampaignLogs();
            $campaignLogs->uid = $request->uid;
            $campaignLogs->campaign_type = $campaign->ad_type;
            $campaignLogs->campaign_id = $campaign->campaign_id;
            $campaignLogs->campaign_data = json_encode($campLogData);
            $campaignLogs->action = 2;
            $campaignLogs->user_type = 1;
            $campaignLogs->save();
            }
            return json_encode([
                'code' => 200,
                'data' => $campaign,
                'message' => 'Operation completed successfully!'
            ]);
        }

        return json_encode([
            'code' => 101,
            'message' => 'Something went wrong!'
        ]);
    }



    public function imageUpload(Request $request)
    {
        $base_str = explode(';base64,', $request->img);
        $ext = str_replace('data:image/', '', $base_str[0]);
        $image = base64_decode($base_str[1]);
        $safeName = md5(Str::random(10)) . '.' . $ext;
        $imgUpload = Storage::disk('public')->put('banner-image/' . $safeName, $image);
        $src = '/banner-image/' . $safeName;
        $imagepath = config('app.url').'image' .$src;
        
        $img = config('app.url').'image/banner-image/'.$request->name;
        $cdnimg_id = AdBannerImage::select('cdnimg_id')->where('image_path',$img)->value('cdnimg_id');
        if(!empty($request->name) && $cdnimg_id){
        delCampImage($image=$request->name, $cdnimg_id);
        }
        
        if ($imagepath) {
            $return['code'] = 200;
            $return['image_path'] = $imagepath;
            $return['message'] = 'Image Uploaded successfully!';
        } else {
            $return['code'] = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    
    public function imageUploadBunny(Request $request){
        // $imageMimeType = $request->file('img')->getMimeType();
        // $fileSizeInKB = $request->file('img')->getSize() / 1024;
        // $validator = Validator::make($request->all(), [
        //     'img' => 'required|image|mimes:jpeg,png,gif,webp'
        // ]);
        // if ($validator->fails()) {
        //     $return['code']    = 100;
        //     $return['error']   = $validator->errors();
        //     $return['message'] = 'Invalid image. Ensure it\'s JPEG, PNG, GIF, WebP and less than 500KB.';
        //     return json_encode($return);
        // }
      
        // if (in_array($imageMimeType, ['image/jpeg', 'image/png', 'image/webp'])) {
        //     if ($fileSizeInKB > 500) {
        //         $return['code']    = 101;
        //         $return['error']   = $validator->errors();
        //         $return['message'] = 'Image size must be less than 500KB for JPEG, PNG, or WebP.';
        //         return json_encode($return);
        //     }
        // } elseif ($imageMimeType === 'image/gif') {
        //     if ($fileSizeInKB > 2500) {
        //         $return['code']    = 101;
        //         $return['error']   = $validator->errors();
        //         $return['message'] = 'GIF size must be less than 2.5MB.';
        //         return json_encode($return);
        //     }
        // }
        $base_str = explode(';base64,', $request->img);
        $ext = str_replace('data:image/', '', $base_str[0]);
        $image = base64_decode($base_str[1]);
        $safeName = md5(Str::random(10)) . '.' . $ext;
        $file_path = '/storeimages/' . $safeName;
        file_put_contents(public_path($file_path), $image);
        $response = storeImages($folderName=env('CDN_FOLDER'), $file = $safeName);
        $existimg = env('STOREAD_IMAGE_URL').$request->name; // name is image name.
        if($existimg && !empty($request->name)){
            delstoreImages($folderName=env('CDN_FOLDER'),$fileName=$request->name);
        }
        $imagepath = env('STOREAD_IMAGE_URL').$safeName;
        if ($imagepath && $response == 201) {
            $return['code'] = 200;
            $return['image_path'] = $safeName;
            $return['message'] = 'Image Uploaded successfully!';
        } else {
            $return['code'] = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    /* -------------------------------- Common Campaign Funtions ---------------------------- */

    public function showAd(Request $request)

    {
        $cid = $request->cid;

        $advertiser_code = $request->uid;

        $campaign = DB::table('campaigns')

            ->select(DB::raw("CONCAT(ss_users.first_name, ' ', ss_users.last_name) as name, SUM(ss_adv_stats.impressions) as imprs, SUM(ss_adv_stats.clicks) as click"), 'campaigns.id', 'campaigns.campaign_name', 'campaigns.campaign_id', 'campaigns.device_type',

            'campaigns.device_os', 'campaigns.ad_title', 'campaigns.ad_description', 'campaigns.advertiser_code', 'campaigns.website_category', 'campaigns.status', 'campaigns.ad_type', 'campaigns.daily_budget',

            'campaigns.country_ids', 'campaigns.pricing_model', 'campaigns.cpc_amt', 'campaigns.country_name', 'campaigns.countries', 'campaigns.social_ad_type', 'campaigns.created_at', 'campaigns.target_url',

            'campaigns.conversion_url', 'categories.cat_name','campaigns.campaign_type')

            ->join('users', 'campaigns.advertiser_code', '=', 'users.uid')

            ->Leftjoin('adv_stats', 'campaigns.campaign_id', '=', 'adv_stats.camp_id')

            ->join('categories', 'campaigns.website_category', '=', 'categories.id')

            ->where('campaigns.trash', 0)

            ->where('campaigns.campaign_id', $cid)

            ->where('campaigns.advertiser_code', $advertiser_code)

            ->first();

        if ($campaign) {

            if ($campaign->ad_type == 'text') {

                $return['data'] = $campaign;

            } elseif ($campaign->ad_type == 'banner') {

                $images = AdBannerImage::select('image_type', 'image_path','cdn_path')->where('campaign_id', $campaign->campaign_id)->get();

                $return['data'] = $campaign;

                $i = 0;

                foreach ($images as $img) {

                    $i++;
                    $return['images']['ad' . $img['image_type']] = (Storage::exists('public/banner-image/'.basename($img['image_path']))) ? $img['image_path'] : $img['cdn_path'];
                    // $return['images']['ad' . $img['image_type']] = env('STOREAD_IMAGE_URL').$img['image_path'];


                }

            } elseif ($campaign->ad_type == 'social') {

                $images = AdBannerImage::select('image_type', 'image_path','cdn_path')->where('campaign_id', $campaign->campaign_id)->get();
                $return['data'] = $campaign;

                $i = 0;

                foreach ($images as $img) {

                    $i++;
                    $return['images']['ad' . $i] = (Storage::exists('public/banner-image/'.basename($img['image_path']))) ? $img['image_path'] : $img['cdn_path'];
                    // $return['images']['ad' . $i] = env('STOREAD_IMAGE_URL').$img['image_path'];

                }

            } elseif ($campaign->ad_type == 'native') {

                $images = AdBannerImage::select('image_type', 'image_path','cdn_path')->where('campaign_id', $campaign->campaign_id)->get();
                $return['data'] = $campaign;
                $i = 0;
                foreach ($images as $img) {

                    $i++;
                    $return['images']['ad' .  $img['image_type']] = (Storage::exists('public/banner-image/'.basename($img['image_path']))) ? $img['image_path'] : $img['cdn_path']; 
                    // $return['images']['ad' .  $img['image_type']] = env('STOREAD_IMAGE_URL').$img['image_path'];

                }

            } elseif ($campaign->ad_type == 'popup') {
                $return['data'] = $campaign;
            }
            $return['code'] = 200;
            $return['message'] = 'Campaign data retrieved!';
        } else {

            $return['code'] = 101;

            $return['message'] = 'Something went wrong!';

        }



        return json_encode($return, JSON_NUMERIC_CHECK);

    }

    public function delete(Request $request)
    {
        $campLogData = [];
        $cid = $request->cid;

        $uid = $request->uid;



        $campaign = Campaign::where('campaign_id', $cid)

            ->where('advertiser_code', $uid)

            ->first();

        $campaign->trash = 1;

        if ($campaign->update()) {

            $activitylog = new Activitylog();

            $activitylog->uid    = $request->uid;

            $activitylog->type    = 'Delete Campaign';

            $activitylog->description    = '' . $campaign->campaign_id . ' is deleted Successfully';

            $activitylog->status    = '1';

            $activitylog->save();

            /* Update Campaign Log Start */
            $campLogData['trash']['previous'] = '----';
            $campLogData['trash']['updated'] = 'Deleted';
            $campLogData['message'] = 'User has deleted the campaign!';
            $camp_log = new CampaignLogs();
            $camp_log->uid = $uid;
            $camp_log->campaign_id = $cid;
            $camp_log->campaign_type = $campaign->ad_type;
            $camp_log->campaign_data = json_encode($campLogData);
            $camp_log->action = 2;
            $camp_log->user_type = 1;
            $camp_log->save();
            /* Update Campaign Log End */

            /* Delete Campaign Email Section */

            $usersdetils = User::select('first_name', 'last_name')->where('uid', $request->uid)->first();

            $fullname = $usersdetils->first_name . ' ' . $usersdetils->last_name;

            $userid = $request->uid;

            $campname =  '';

            $campid = $cid;

            $data['details'] = ['fullname' => $fullname, 'userid' => $userid, 'campname' => $campname, 'campid' => $campid];

            /* Admin Section  */

            $adminmail1 = 'advertisersupport@7searchppc.com';

            $adminmail2 = 'info@7searchppc.com';

            $bodyadmin =   View('emailtemp.campaigndeletedmin', $data);

            $subjectadmin = 'Campaign Deleted Successfully !';

            $sendmailadmin =  sendmailAdmin($subjectadmin,$bodyadmin,$adminmail1,$adminmail2);

            if($sendmailadmin == '1')

            {

                $return['code'] = 200;

                $return['message']  = 'Mail Send & Data Inserted Successfully !';

            }

            else

            {

                $return['code'] = 200;

                $return['message']  = 'Mail Not Send But Data Insert Successfully !';

            }







            $return['code']    = 200;

            $return['message'] = 'Campaign deleted successfully!';
        /* This will remove Campaign from Redis*/
        updateCamps($cid, 0);
        } else {

            $return['code'] = 101;

            $return['message'] = 'Something went wrong!';

        }



        return json_encode($return, JSON_NUMERIC_CHECK);

    }



    public function list(Request $request)
    {
        $type = $request->type;
        $uid = $request->uid;
        $limit = $request->lim;
        $page = $request->page;
        $status = $request->status;
        $src = $request->src;
        $pg = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;
        $date = date('Y-m-d');
      	$user = User::where('uid', $uid)->first();
        $campaign = Campaign::selectRaw("ss_campaigns.campaign_name,ss_campaigns.campaign_id,ss_campaigns.status,ss_campaigns.ad_type, ss_campaigns.daily_budget,
                (select IFNULL(sum(clicks),0) from ss_camp_budget_utilize  camp_ck where camp_ck.camp_id = ss_campaigns.campaign_id) as click,
                (select IFNULL(sum(impressions),0) from ss_camp_budget_utilize  ad_imp where ad_imp.camp_id = ss_campaigns.campaign_id) as imprs,
                DATE_FORMAT(ss_campaigns.created_at, '%d %b %Y %h:%i %p') as createdat, ss_categories.cat_name,
                ((select IFNULL(sum(amount),0) from ss_camp_budget_utilize ad_imp where ad_imp.camp_id = ss_campaigns.campaign_id AND DATE(ad_imp.udate) = DATE('".$date."') )) as spent_amt")
                ->join('categories', 'campaigns.website_category', '=', 'categories.id')
                ->where('campaigns.advertiser_code', $uid)->where('campaigns.trash', 0);
        if (strlen($type) > 0 and empty($status)) {
            $campaign = $campaign->where('campaigns.ad_type', $type);
        }
        if ($src) {
            $campaign = $campaign->whereRaw('concat(ss_campaigns.campaign_id,ss_campaigns.campaign_name,ss_campaigns.campaign_type) like ?', "%{$src}%")->orderBy('campaigns.id', 'desc');
        }
        if (strlen($type) > 0 and !empty($status)) {
            $campaign = $campaign->where('campaigns.ad_type', $type)->where('campaigns.status', $status);
        }
        if (strlen($type) <= 0 and !empty($status)) {
            $campaign = $campaign->where('campaigns.status', $status);
        }
            $campaign->orderBy('campaigns.id', 'desc');
            $row = $campaign->count();
            $data = $campaign->offset($start)->limit($limit)->get();
        if ($row !== null) {
            $return['code']    = 200;
            $return['data']    = $data;
            $return['row']     = $row;
          	// $return['wallet']  = number_format($user->wallet, 3, '.', '');
            $wltAmt = getWalletAmount($uid);
            $return['wallet']        = ($wltAmt) > 0 ? number_format($wltAmt, 3, '.', '') : number_format($user->wallet, 3, '.', '');
            $return['message'] = 'Campaigns list retrieved successfully!';
        } else {
            $return['code'] = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function campaignStatusUpdate(Request $request)
    {
        $campLogData = [];
        $cid                = $request->cid;
        $adv_code           = $request->uid;
        $status             = $request->status;
        if ($status == 2) {
            $obj    = Campaign::where('campaign_id', $cid)->where('advertiser_code', $adv_code);
        } elseif ($status == 4) {
            $obj    = Campaign::where('campaign_id', $cid)->where('advertiser_code', $adv_code);
        } else {
            $return['code'] = 102;
            $return['message'] = 'Something went wrong!';
            return json_encode($return);
        }
        $cnt = $obj->count();
        if ($cnt > 0) {
            $campaignData = $obj->first()->only(['status']);
            $requestData = $request->only(['status']);
            $old_status = '';
            $new_status = '';
            foreach ($campaignData as $property => $value) {
                if ($value == 1) {
                    $old_status = 'In Review';
                } elseif ($value == 2) {
                    $old_status = 'Active';
                } elseif ($value == 3) {
                    $old_status = 'In Active';
                } elseif ($value == 4) {
                    $old_status = 'Paused';
                } elseif ($value == 5) {
                    $old_status = 'Hold';
                } else {
                    $old_status = 'Suspend';
                }
                if ($requestData[$property] == 1) {
                    $new_status = 'In Review';
                } elseif ($requestData[$property] == 2) {
                    $new_status = 'Active';
                } elseif ($requestData[$property] == 3) {
                    $new_status = 'In Active';
                } elseif ($requestData[$property] == 4) {
                    $new_status = 'Paused';
                } elseif ($requestData[$property] == 5) {
                    $new_status = 'Hold';
                } else {
                    $new_status = 'Suspend';
                }
                if ($value != $requestData[$property]) {
                    $campLogData[$property]['previous'] = $old_status;
                    $campLogData[$property]['updated'] = $new_status;
                }
            }
            $campLogData['message'] = 'User has changed the status!';
            $cStatus = $obj->first();
            $campaignStatus = $cStatus->status;
            $cStatus->status = $status;
            $cStatus->updated_at = now();
            if ($cStatus->update()) {

                $activitylog = new Activitylog();
                $activitylog->uid    = $request->uid;
                $activitylog->type    = $new_status;
                $activitylog->description    = '' . $cid . ' is '. $new_status .' Successfully';
                $activitylog->status    = '1';
                $activitylog->save();

                $return['code'] = 200;
                $return['message'] = 'Campaign status updated successfully!';
            /* This will update campaign into Redis */
            updateCamps($cid, $status);
            } else {
                $return['code'] = 101;
                $return['message'] = 'Something went wrong!';
            }
        } else {
            $return['code'] = 103;
            $return['message'] = 'Something went wrong!';
            return json_encode($return);
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    public function campaignAction(Request $request)
    {
        $cid    = $request->cid;
        $uid    = $request->uid;
        $type   = $request->type;
        $count  = 0;
        $trs    = 0;
        $campLogData = [];
        	if ($type == 'active') {
                $Camp = Campaign::where('advertiser_code', $uid)->whereIn('campaign_id', $cid)->where('status',4)->get();
                $status = ['status' => 'Active'];
                foreach ($Camp as $val) {
                    if ($val->status == 1) {
                        $val->status = 'In Review';
                    } elseif ($val->status == 2) {
                        $val->status = 'Active';
                    } elseif ($val->status == 3) {
                        $val->status = 'In Active';
                    } elseif ($val->status == 4) {
                        $val->status = 'Paused';
                    } elseif ($val->status == 5) {
                        $val->status = 'Hold';
                    } else {
                        $val->status = 'Suspend';
                    }
                    $oldData = $val->only([
                        'status'
                    ]);
                    $newData = $status;
                    foreach ($oldData as $property => $value) {
                        if ($value != $newData[$property]) {
                            $campLogData[$property]['previous'] = $value;
                            $campLogData[$property]['updated'] = $newData[$property];
                        }
                    }
                    $campLogData['message'] = 'User has changed the status!';
                    $camp_data = json_encode($campLogData);
                    $camp_log = new CampaignLogs();
                    $camp_log->uid = $val->advertiser_code;
                    $camp_log->campaign_id = $val->campaign_id;
                    $camp_log->campaign_type = $val->ad_type;
                    $camp_log->campaign_data = $camp_data;
                    $camp_log->action = 2;
                    $camp_log->user_type = 1;
                    $camp_log->save();
                }
                Campaign::where('advertiser_code', $uid)->whereIn('campaign_id', $cid)->where('status',4)->update(['status'=>2]);
                $count++;
                // $activitylog = new Activitylog();
                // $activitylog->uid    = $uid;
                // $activitylog->type    = $type;
                // $activitylog->description    = '' . $cid . ' is ' . $type . ' Successfully';
                // $activitylog->status    = '1';
                // $activitylog->save();
            } elseif ($type == 'pause') {
                $Camp = Campaign::where('advertiser_code', $uid)->whereIn('campaign_id', $cid)->where('status',2)->get();
                $status = ['status' => 'Paused'];
                foreach ($Camp as $val) {
                    if ($val->status == 1) {
                        $val->status = 'In Review';
                    } elseif ($val->status == 2) {
                        $val->status = 'Active';
                    } elseif ($val->status == 3) {
                        $val->status = 'In Active';
                    } elseif ($val->status == 4) {
                        $val->status = 'Paused';
                    } elseif ($val->status == 5) {
                        $val->status = 'Hold';
                    } else {
                        $val->status = 'Suspend';
                    }
                    $oldData = $val->only([
                        'status'
                    ]);
                    $newData = $status;
                    foreach ($oldData as $property => $value) {
                        if ($value != $newData[$property]) {
                            $campLogData[$property]['previous'] = $value;
                            $campLogData[$property]['updated'] = $newData[$property];
                        }
                    }
                    $campLogData['message'] = 'User has changed the status!';
                    $camp_data = json_encode($campLogData);
                    $camp_log = new CampaignLogs();
                    $camp_log->uid = $val->advertiser_code;
                    $camp_log->campaign_id = $val->campaign_id;
                    $camp_log->campaign_type = $val->ad_type;
                    $camp_log->campaign_data = $camp_data;
                    $camp_log->action = 2;
                    $camp_log->user_type = 1;
                    $camp_log->save();
                }
                Campaign::where('advertiser_code', $uid)->whereIn('campaign_id', $cid)->where('status',2)->update(['status'=>4]);
                $count++;
                // $activitylog = new Activitylog();
                // $activitylog->uid    = $uid;
                // $activitylog->type    = $type;
                // $activitylog->description    = '' . $cid . ' is ' . $type . ' Successfully';
                // $activitylog->status    = '1';
                // $activitylog->save();
            }
            if ($type == 'delete') {
                $Camp = Campaign::where('advertiser_code', $uid)->whereIn('campaign_id', $cid)->get();
                $trash = ['trash' => 'Deleted'];
                foreach ($Camp as $val) {
                    $oldData = $val->only([
                        'trash'
                    ]);
                    $newData = $trash;
                    foreach ($oldData as $property => $value) {
                        if ($value != $newData[$property]) {
                            $campLogData[$property]['previous'] = '----';
                            $campLogData[$property]['updated'] = 'Deleted';
                        }
                    }
                    $campLogData['message'] = 'User has deleted the campaign!';
                    $camp_data = json_encode($campLogData);
                    $camp_log = new CampaignLogs();
                    $camp_log->uid = $val->advertiser_code;
                    $camp_log->campaign_id = $val->campaign_id;
                    $camp_log->campaign_type = $val->ad_type;
                    $camp_log->campaign_data = $camp_data;
                    $camp_log->action = 2;
                    $camp_log->user_type = 1;
                    $camp_log->save();
                }
                Campaign::where('advertiser_code', $uid)->whereIn('campaign_id', $cid)->update(['trash'=> 1]);
                $count++;
            }
        /* This will bulk update Campaign into Redis */
        updateBulkCamps($cid, $type);
        if ($count > 0) {
            $return['code'] = 200;
            $return['message'] = 'Campaign updated successfully!';
        } else {
            $return['code'] = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function duplicateCampaign(Request $request)
    {
        $campLogData = [];
        $cid = $request->cid;
        $uid = $request->uid;
        $copyCampaign = Campaign::select('*')->where('campaign_id', $cid)->where('advertiser_code', $uid)->first()->toArray();
        $adType = $copyCampaign['ad_type'];
        $pref = getCampPrefix($adType);
        $copyCampaign['campaign_id'] = $pref . strtoupper(uniqid());
        $new_id = $copyCampaign['campaign_id'];
        $copyCampaign['status'] = 1;
        $copyCampaign['trash'] = 0;
        unset($copyCampaign['id']);
        $copyCampaign['created_at'] = date('Y-m-d H:i:s');
        $copyCampaign['updated_at'] = date('Y-m-d H:i:s');
        $res = Campaign::insert($copyCampaign);
        if ($res) {
            if ($adType == 'banner' || $adType == 'native' || $adType == 'social') {
                $images = AdBannerImage::where('advertiser_code', $uid)->where('campaign_id', $cid)->get()->toArray();
                foreach ($images as $img) {
                    $url = $img['image_path'];
                    $fileName = basename($img['image_path']);
                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                    $imageName = md5(Str::random(10)) . ($ext ? '.' . $ext : '');
                    $imageContent = file_get_contents($url);
                    if($imageContent){
                    Storage::disk('public')->put('banner-image/' . $imageName, $imageContent);
                    $imagePath = config('app.url').'image/banner-image/' .$imageName;
                    $response = storeCDNImage($imageName);
                    $img['image_path'] =  $imagePath;
                    $img['cdn_path'] =    $response['image-path'];
                    $img['cdnimg_id'] =   $response['image-id'];
                    }
                    $img['campaign_id'] = $copyCampaign['campaign_id'];
                    $img['created_at'] = date('Y-m-d H:i:s');
                    $img['updated_at'] = date('Y-m-d H:i:s');
                    unset($img['id']);
                    AdBannerImage::insert($img);
                }
            }
            $user = DB::table('users')->select('id', 'first_name', 'last_name', 'email')->where('uid', $request->uid)->first();
            /* Create Campaign Send Mail   */
            $email = $user->email;
            $fullname = $user->first_name . ' ' . $user->last_name;
            $useridas = $copyCampaign['advertiser_code'];
            $campsid = $copyCampaign['campaign_id'];
            $campsname =  $copyCampaign['campaign_name'];
            $campadtype =  $copyCampaign['ad_type'];
            /* Send to Admin */
            $mailsentdetals = ['subject' => 'Campaign Creation Request ', 'fullname' => $fullname,  'usersid' => $useridas, 'campaignid' => $campsid, 'campaignname' => $campsname, 'campaignadtype' => $campadtype];
            $adminmail1 = 'advertisersupport@7searchppc.com';
            $adminmail2 = 'info@7searchppc.com';
            $mailTo = [$adminmail1, $adminmail2];
            try {
                Mail::to($mailTo)->send(new CreateCampMailAdmin($mailsentdetals));
                Mail::to($email)->send(new CreateCampMail($mailsentdetals));
                $return['code']    = 200;
                $return['data']    = $copyCampaign;
                $return['message'] = 'Campaign detail added successfully!';
            } catch (Exception $e) {
                $return['code'] = 200;
                $return['data'] = $copyCampaign;
                $return['msg']  = 'Campaign detail added successfully!. But mail not send to the user.';
            }
            /* Campaign Create Send Mail */
            $return['code'] = 200;
            $activitylog = new Activitylog();
            $activitylog->uid    = $uid;
            $activitylog->type    = 'Copy Campaign';
            $activitylog->description    = '' . $cid . ' is copy Successfully';
            $activitylog->status    = '1';
            $activitylog->save();
            $return['camp_id'] = $copyCampaign['campaign_id'];
            $return['adtype']  = $adType;
            $return['message'] = 'Campaign Saved!';
            /* Update Campaign Log Start */
            $campLogData['camp_created']['previous'] = '----';
            $campLogData['camp_created']['updated'] = '----';
            $campLogData['message'] = 'User has created the campaign!';
            $camp_log = new CampaignLogs();
            $camp_log->uid = $uid;
            $camp_log->campaign_id = $new_id;
            $camp_log->campaign_type = $adType;
            $camp_log->campaign_data = json_encode($campLogData);
            $camp_log->action = 1;
            $camp_log->user_type = 1;
            $camp_log->save();
            /* Update Campaign Log End */
        } else {
            $return['code'] = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }

      public function onchangecpc(Request $request)
      {
        $typename   = $request->type;
        $catname   = $request->cat_name;
        $country   = $request->country;
        // $countries = [];

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
        // ->select(DB::raw('MAX(cpc_amt) as cpc_amt'))
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
                    // $campcpcamt = $query->cpc_amt;
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
        else
        {
            // foreach ($country as $count) {
            //     $countries[] = [
            //         'country' => $count,
            //     ];
            // }
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
                    // $return['high_amt'] = ($query2->max_amt > $cpmamt) ? $query2->max_amt : $cpmamt ;
                    $return['high_amt'] = $query;
                    $return['message'] = "CPM Data found successfully.";
                }
                else {
                    $return['code'] = 200;
                    $return['base_amt'] = $cpcamt;
                    // $return['high_amt'] = ($query2->max_amt > $cpcamt) ? $query2->max_amt : $cpcamt ;
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

    public function deleteCampaignImageold(Request $request)
    {
        foreach ($request->images as $key => $value) {
            $path = 'banner-image/' . $value;
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
        AdBannerImage::whereIn('image_type', $request->images)->where('campaign_id',$request->cid)->delete();
        $return['code'] = 200;
        $return['message'] = 'Campaign updated successfully!';
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    
    public function deleteCampaignImage(Request $request)
    {
        
        $data = AdBannerImage::select('image_path','cdnimg_id')->whereIn('image_type',$request->images)->where("advertiser_code",$request->uid)->where("campaign_id",$request->cid)->get();
        if(count($data)>0) {    
            foreach ($data as $img){
            // $response = delstoreImages($folderName=env('CDN_FOLDER'),$fileName=$img['image_path']);
            delCampImage($image=basename($img['image_path']), $cdnimg_id=$img['cdnimg_id']);
            }
        AdBannerImage::whereIn('image_type', $request->images)->where('advertiser_code',$request->uid)->where('campaign_id',$request->cid)->delete();
        $return['code'] = 200;
        $return['message'] = 'Campaign image deleted successfully!';
        } else{
        $return['code'] = 101;
        $return['message'] = "Image data not found!";
        }
        return json_encode($return, JSON_NUMERIC_CHECK);  
    }
}

