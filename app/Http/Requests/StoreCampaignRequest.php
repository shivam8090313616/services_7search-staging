<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Category;
use App\Models\Country;

class StoreCampaignRequest extends FormRequest
{  
    public function StoreUpdateBannerValidation(): array
    {
        return [
            'uid' => 'required',
            'campaign_name' => ['required','string','max:100','regex:/^[a-zA-Z0-9\s]+$/'],
            'ad_type' => ['required', 'string', Rule::in(['banner'])],
            'campaign_type' => ['required', 'string', Rule::in(['sales', 'lead', 'web', 'branding', 'social', 'nogoal'])],
            'website_category' => 'required|integer|exists:categories,id',
            'device_type' => [
                'required',
                    function ($attribute, $value, $fail) {
                        $validTypes = ['Desktop', 'Mobile', 'Tablet'];
                        $types = explode(',', $value);
                        $uniqueTypes = array_unique(array_map('trim', $types));
                        foreach ($uniqueTypes as $type) {
                            if (!in_array($type, $validTypes)) {
                                return $fail("The selected $attribute is invalid. Allowed values are Desktop, Mobile, Tablet.");
                            }
                        }
                        if (count($uniqueTypes) < count($types)) {
                            return $fail("The $attribute must be unique. Duplicates are not allowed.");
                        }
                    }
            ],
            'device_os' => [
                'required',
                 function ($attribute, $value, $fail) {
                        $devicetype = request()->get('device_type');
                        $deviceos = request()->get('device_os');
                        $validOS = $this->deviceTypeOs($devicetype, $deviceos);
                        $types = explode(',', $value);
                        $uniqueTypes = array_unique(array_map('trim', $types));
                        foreach ($uniqueTypes as $type) {
                            if (!in_array($type, $validOS)) {
                                return $fail("Please choose a valid OS for your device type.");
                            }
                        }
                        if (count($uniqueTypes) < count($types)) {
                            return $fail("The $attribute must be unique. Duplicates are not allowed.");
                        }
                    }
              ],
             'target_url' => [
                'required',
                'url',
                function ($attribute, $value, $fail) {
                    if (parse_url($value, PHP_URL_SCHEME) !== 'https') {
                        $fail('The target URL must be a valid secure URL (https).');
                    }
                },
            ],            
            'countries' => [ 
                 'required',
                    function ($attribute, $value, $fail) {
                        $countries = request()->get('countries');
                        if ($countries !== 'All') {
                        $this->validateCountries($attribute, $value, $fail);
                        }
                    }
                ],
            'pricing_model' => 'required|in:CPC,CPM',
            'daily_budget' => 'required|integer|min:15',
            'cpc_amt' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    $websiteCategory = request()->get('website_category');
                    $countries = request()->get('countries');
                    $pricing_model = request()->get('pricing_model');
                    $this->validateCpcAmount($attribute, $value, $fail, $websiteCategory ,$countries, $pricing_model);
                }
            ],
        ];
    }
    public function StoreUpdateTextValidation(): array
    {
        return [
            'uid' => 'required',
            'campaign_name' => ['required','string','max:100','regex:/^[a-zA-Z0-9\s]+$/'],
            'ad_type' => ['required', 'string', Rule::in(['text'])],
            'campaign_type' => ['required', 'string', Rule::in(['sales', 'lead', 'web', 'branding', 'social', 'nogoal'])],
            'website_category' => 'required|integer|exists:categories,id',
            'device_type' => [
                'required',
                    function ($attribute, $value, $fail) {
                        $validTypes = ['Desktop', 'Mobile', 'Tablet'];
                        $types = explode(',', $value);
                        $uniqueTypes = array_unique(array_map('trim', $types));
                        foreach ($uniqueTypes as $type) {
                            if (!in_array($type, $validTypes)) {
                                return $fail("The selected $attribute is invalid. Allowed values are Desktop, Mobile, Tablet.");
                            }
                        }
                        if (count($uniqueTypes) < count($types)) {
                            return $fail("The $attribute must be unique. Duplicates are not allowed.");
                        }
                    }
            ],
            'device_os' => [
                'required',
                 function ($attribute, $value, $fail) {
                        $devicetype = request()->get('device_type');
                        $deviceos = request()->get('device_os');
                        $validOS = $this->deviceTypeOs($devicetype, $deviceos);
                        $types = explode(',', $value);
                        $uniqueTypes = array_unique(array_map('trim', $types));
                        foreach ($uniqueTypes as $type) {
                            if (!in_array($type, $validOS)) {
                                return $fail("Please choose a valid OS for your device type.");
                            }
                        }
                        if (count($uniqueTypes) < count($types)) {
                            return $fail("The $attribute must be unique. Duplicates are not allowed.");
                        }
                    }
              ],
            'target_url' => [
                'required',
                'url',
                function ($attribute, $value, $fail) {
                    if (parse_url($value, PHP_URL_SCHEME) !== 'https') {
                        $fail('The target URL must be a valid secure URL (https).');
                    }
                },
            ],
            'countries' => [
                'required',
                function ($attribute, $value, $fail) {
                    $countries = request()->get('countries');
                    if ($countries !== 'All') {
                    $this->validateCountries($attribute, $value, $fail);
                    }
                }
            ],
            'ad_title' => 'required|string|max:60',
            'ad_description' => 'required|string|max:150',
            'pricing_model' => 'required|in:CPC,CPM',
            'daily_budget' => 'required|integer|min:15',
            'cpc_amt' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    $websiteCategory = request()->get('website_category');
                    $countries = request()->get('countries');
                    $pricing_model = request()->get('pricing_model');
                    $this->validateCpcAmount($attribute, $value, $fail, $websiteCategory ,$countries, $pricing_model);
                }
            ],
        ];
    }
    public function StoreUpdatePopunderValidation(): array
    {
        return [
            'uid' => 'required',
            'campaign_name' => ['required','string','max:100','regex:/^[a-zA-Z0-9\s]+$/'],
            'ad_type' => ['required', 'string', Rule::in(['popup'])],
            'campaign_type' => ['required', 'string', Rule::in(['sales', 'lead', 'web', 'branding', 'social', 'nogoal'])],
            'website_category' => 'required|integer|exists:categories,id',
            'device_type' => [
                'required',
                    function ($attribute, $value, $fail) {
                        $validTypes = ['Desktop', 'Mobile', 'Tablet'];
                        $types = explode(',', $value);
                        $uniqueTypes = array_unique(array_map('trim', $types));
                        foreach ($uniqueTypes as $type) {
                            if (!in_array($type, $validTypes)) {
                                return $fail("The selected $attribute is invalid. Allowed values are Desktop, Mobile, Tablet.");
                            }
                        }
                        if (count($uniqueTypes) < count($types)) {
                            return $fail("The $attribute must be unique. Duplicates are not allowed.");
                        }
                    }
            ],
            'device_os' => [
                'required',
                 function ($attribute, $value, $fail) {
                        $devicetype = request()->get('device_type');
                        $deviceos = request()->get('device_os');
                        $validOS = $this->deviceTypeOs($devicetype, $deviceos);
                        $types = explode(',', $value);
                        $uniqueTypes = array_unique(array_map('trim', $types));
                        foreach ($uniqueTypes as $type) {
                            if (!in_array($type, $validOS)) {
                                return $fail("Please choose a valid OS for your device type.");
                            }
                        }
                        if (count($uniqueTypes) < count($types)) {
                            return $fail("The $attribute must be unique. Duplicates are not allowed.");
                        }
                    }
              ],
            'target_url' => [
                'required',
                'url',
                function ($attribute, $value, $fail) {
                    if (parse_url($value, PHP_URL_SCHEME) !== 'https') {
                        $fail('The target URL must be a valid secure URL (https).');
                    }
                },
            ],
            'countries' => [
                'required',
                function ($attribute, $value, $fail) {
                    $countries = request()->get('countries');
                    if ($countries !== 'All') {
                    $this->validateCountries($attribute, $value, $fail);
                    }
                }
            ],
            'daily_budget' => 'required|integer|min:15',
            'cpc_amt' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    $websiteCategory = request()->get('website_category');
                    $countries = request()->get('countries');
                    $pricing_model = request()->get('pricing_model');
                    $this->validateCpcAmount($attribute, $value, $fail, $websiteCategory ,$countries, $pricing_model);
                }
            ],
        ];
    }
    public function StoreUpdateSocialValidation(): array
    {
        return [
            'uid' => 'required',
            'campaign_name' => ['required','string','max:100','regex:/^[a-zA-Z0-9\s]+$/'],
            'social_ad_type' => 'required|numeric|min:1|max:2',
            'ad_type' => ['required', 'string', Rule::in(['social'])],
            'campaign_type' => ['required', 'string', Rule::in(['sales', 'lead', 'web', 'branding', 'social', 'nogoal'])],
            'website_category' => 'required|integer|exists:categories,id',
           'device_type' => [
                'required',
                    function ($attribute, $value, $fail) {
                        $validTypes = ['Desktop', 'Mobile', 'Tablet'];
                        $types = explode(',', $value);
                        $uniqueTypes = array_unique(array_map('trim', $types));
                        foreach ($uniqueTypes as $type) {
                            if (!in_array($type, $validTypes)) {
                                return $fail("The selected $attribute is invalid. Allowed values are Desktop, Mobile, Tablet.");
                            }
                        }
                        if (count($uniqueTypes) < count($types)) {
                            return $fail("The $attribute must be unique. Duplicates are not allowed.");
                        }
                    }
            ],
            'device_os' => [
                'required',
                 function ($attribute, $value, $fail) {
                        $devicetype = request()->get('device_type');
                        $deviceos = request()->get('device_os');
                        $validOS = $this->deviceTypeOs($devicetype, $deviceos);
                        $types = explode(',', $value);
                        $uniqueTypes = array_unique(array_map('trim', $types));
                        foreach ($uniqueTypes as $type) {
                            if (!in_array($type, $validOS)) {
                                return $fail("Please choose a valid OS for your device type.");
                            }
                        }
                        if (count($uniqueTypes) < count($types)) {
                            return $fail("The $attribute must be unique. Duplicates are not allowed.");
                        }
                    }
              ],
             'target_url' => [
                'required',
                'url',
                function ($attribute, $value, $fail) {
                    if (parse_url($value, PHP_URL_SCHEME) !== 'https') {
                        $fail('The target URL must be a valid secure URL (https).');
                    }
                },
            ],
            'countries' => [
                'required',
                function ($attribute, $value, $fail) {
                    $countries = request()->get('countries');
                    if ($countries !== 'All') {
                    $this->validateCountries($attribute, $value, $fail);
                    }
                }
            ],
            'ad_title' => 'required|string|max:32',
            'ad_description' => 'required|string|max:80',
            'pricing_model' => 'required|in:CPC,CPM',
            'daily_budget' => 'required|integer|min:15',
            'cpc_amt' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    $websiteCategory = request()->get('website_category');
                    $countries = request()->get('countries');
                    $pricing_model = request()->get('pricing_model');
                    $this->validateCpcAmount($attribute, $value, $fail, $websiteCategory ,$countries, $pricing_model);
                }
            ],
        ];
    }
    public function StoreUpdateNativeValidation(): array
    {
        return [
            'uid' => 'required',
            'campaign_name' => ['required','string','max:100','regex:/^[a-zA-Z0-9\s]+$/'],
            'ad_type' => ['required', 'string', Rule::in(['native'])],
            'campaign_type' => ['required', 'string', Rule::in(['sales', 'lead', 'web', 'branding', 'social', 'nogoal'])],
            'website_category' => 'required|integer|exists:categories,id',
            'device_type' => [
                'required',
                    function ($attribute, $value, $fail) {
                        $validTypes = ['Desktop', 'Mobile', 'Tablet'];
                        $types = explode(',', $value);
                        $uniqueTypes = array_unique(array_map('trim', $types));
                        foreach ($uniqueTypes as $type) {
                            if (!in_array($type, $validTypes)) {
                                return $fail("The selected $attribute is invalid. Allowed values are Desktop, Mobile, Tablet.");
                            }
                        }
                        if (count($uniqueTypes) < count($types)) {
                            return $fail("The $attribute must be unique. Duplicates are not allowed.");
                        }
                    }
             ],
            'device_os' => [
                'required',
                 function ($attribute, $value, $fail) {
                        $devicetype = request()->get('device_type');
                        $deviceos = request()->get('device_os');
                        $validOS = $this->deviceTypeOs($devicetype, $deviceos);
                        $types = explode(',', $value);
                        $uniqueTypes = array_unique(array_map('trim', $types));
                        foreach ($uniqueTypes as $type) {
                            if (!in_array($type, $validOS)) {
                                return $fail("Please choose a valid OS for your device type.");
                            }
                        }
                        if (count($uniqueTypes) < count($types)) {
                            return $fail("The $attribute must be unique. Duplicates are not allowed.");
                        }
                    }
              ],
             'target_url' => [
                'required',
                'url',
                function ($attribute, $value, $fail) {
                    if (parse_url($value, PHP_URL_SCHEME) !== 'https') {
                        $fail('The target URL must be a valid secure URL (https).');
                    }
                },
            ],
            'countries' => [
                'required',
                function ($attribute, $value, $fail) {
                    $countries = request()->get('countries');
                    if ($countries !== 'All') {
                    $this->validateCountries($attribute, $value, $fail);
                    }
                }
            ],
            'ad_title' => 'required|string|max:50',
            'pricing_model' => 'required|in:CPC,CPM',
            'daily_budget' => 'required|integer|min:15',
            'cpc_amt' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    $websiteCategory = request()->get('website_category');
                    $countries = request()->get('countries');
                    $pricing_model = request()->get('pricing_model');
                    $this->validateCpcAmount($attribute, $value, $fail, $websiteCategory ,$countries, $pricing_model);
                }
            ],
        ];
    }
    public function messages()
    {
        return [
            'cpc_amt.numeric'      => 'The cpc_amt field must be a number.',
            'cpc_amt.gte'          => 'The cpc_amt field must be greater than or equal to 0.0001.',
            'device_type.required' => 'The device type field is required.',
            'device_os.required'   => 'The device operating system field is required.',
            'target_url.required'  => 'The target URL is required.',
            'target_url.url'       => 'The target URL must be a valid URL format.',
            'pricing_model.in' => 'The selected pricing model is invalid. Allowed values are CPC and CPM.',
        ];
    }
    private function validateCountries($attribute, $value, $fail)
    {
        if (is_array($value)) {
            $jsonCountries  = json_encode($value);
            $countries = json_decode($jsonCountries, true);
            foreach ($countries as $country) {
                $existCountry = Country::where('name', $country)
                    ->where('status', 1)
                    ->where('trash', 1)
                    ->exists();
    
                if (!$existCountry) {
                    $fail('Each country must have a valid value, label, and phonecode.');
                    return;
                }
            }
        }else{
            $countries = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('The countries field must be a valid JSON.');
                return;
            }
            foreach ($countries as $country) {
                $existCountry = Country::where('id', $country['value'])
                    ->where('name', $country['label'])
                    ->where('phonecode', $country['phonecode'])
                    ->where('status', 1)
                    ->where('trash', 1)
                    ->exists();
                if (!$existCountry) {
                    $fail('Each country must have a valid value, label, and phonecode.');
                    return;
                }
    
                // if (!isset($country['value']) || !is_int($country['value']) || !isset($country['label']) || !is_string($country['label']) || !isset($country['phonecode']) || !is_int($country['phonecode'])) {
                //     $fail('Each country must have a valid "value" (integer), "label" (string), and "phonecode" (integer).');
                //     return;
                // }
            }
        }
    }
    private function validateCpcAmount($attribute, $value, $fail , $websiteCategory,$countries , $pricing_model)
    {
        $catid = Category::select('cat_name')->where('id', $websiteCategory)->where('status', 1)->where('trash', 0)->first();
        if(empty($catid)){
             $fail('Error! Invalid Bidding Amount.');
             return;
        }
        $result = onchangecpcValidation($pricing_model, $catid->cat_name, $countries);
        $area = json_decode($result, true);
        if($area['code'] == 101){
             $fail($area['message']);
             return;
        }
        $arrResult = json_decode($result);
        $base_amt = $arrResult->base_amt;

        if (($countries === 'All' && $value < $base_amt) || ($countries !== 'All' && $value < $base_amt)) {
            $fail('Error! Invalid Bidding Amount.');
        }
    }
    private function deviceTypeOs($dtype, $dos)
    {
        $dtype = explode(',', $dtype);
        $dos = explode(',', $dos);
        return deviceValidating($dtype,$dos);
    }
}
