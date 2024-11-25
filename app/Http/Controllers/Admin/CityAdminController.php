<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use Illuminate\Support\Facades\Validator;

class CityAdminController extends Controller
{
    public function getstate(Request $request){
        $validator = Validator::make(
            $request->all(),
            [
                'country_id'    => 'required',
            ]
        );
        if ($validator->fails()) {
            $return['code']    = 101;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error!';
            return json_encode($return);
        }
        $country_id = $request->country_id;
        $country = State::select('id','name')->where('country_id', $country_id)->where('trash', 1);
        $row = $country->count();
        $data = $country->get()->toArray();
        if ($row) {
            $return['code']    = 200;
            $return['data']    = $data;
            $return['row']     = $row;
            $return['message'] = 'State  successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);

    }



    public function store(Request $request)
    {
       
        $validator = Validator::make(
            $request->all(),
            [
                'country_id'    => 'required',
                'state_id'    => 'required',
                'name'          => 'required',
            ]
        );
        if ($validator->fails()) {
            $return['code']    = 101;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error!';
            return json_encode($return);
        }
        $stateDatacount = State::where('country_id',$request->country_id)->where('id',$request->state_id)->where('trash', 1)->count();
        if($stateDatacount == 0) {
            $return['code']    = 101;
            $return['message'] = 'State Data Not Found !';
            return json_encode($return);
        }

        $stateData = City::where('country_id',$request->country_id)->where('state_id',$request->state_id)->where('name',$request->name)->where('trash', 1)->count();
        if($stateData > 0) {
            $return['code']    = 101;
            $return['message'] = 'City Allready available!';
            return json_encode($return);
        }

        $getcountryData = Country::where('id',$request->country_id)->where('trash', 1)->first();
        if(empty($getcountryData)) {
            $return['code']    = 101;
            $return['message'] = 'Country Not Found!';
            return json_encode($return);
        }
        $getstateData = State::where('id',$request->state_id)->where('trash', 1)->first();
        if(empty($getstateData)) {
            $return['code']    = 101;
            $return['message'] = 'State Not Found!';
            return json_encode($return);
        }

        $city                = new City();
        $city->country_id    = $getcountryData->id;
        $city->country_name    = $getcountryData->name;
        $city->state_id      = $getstateData->id;
        $city->state_name    = $getstateData->name;
        $city->name          = $request->name;
        if ($city->save()) {
            $return['code']    = 200;
            $return['data']    = $city;
            $return['message'] = 'City added successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }


    public function update(Request $request)
    {
      
        $validator = Validator::make(
            $request->all(),
            [
                'city_id'    => 'required',
                'country_id'    => 'required',
                'state_id'    => 'required',
                'name'          => 'required',
            ]
        );
        if ($validator->fails()) {
            $return['code']    = 101;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error!';
            return json_encode($return);
        }
               
        $getcountryData = Country::where('id',$request->country_id)->where('trash', 1)->first();
        if(empty($getcountryData)) {
            $return['code']    = 101;
            $return['message'] = 'Country Not Found!';
            return json_encode($return);
        }
        $getstateData = State::where('id',$request->state_id)->where('trash', 1)->first();
        if(empty($getstateData)) {
            $return['code']    = 101;
            $return['message'] = 'State Not Found!';
            return json_encode($return);
        }

        $city                = City::where('id',$request->city_id)->first();
        if(empty($city)) {
            $return['code']    = 101;
            $return['message'] = 'City Not Found!';
            return json_encode($return);
        }
        $city->country_id    = $getcountryData->id;
        $city->country_name  = $getcountryData->name;
        $city->state_id      = $getstateData->id;
        $city->state_name    = $getstateData->name;
        $city->name          = $request->name;
        if ($city->save()) {
            $return['code']    = 200;
            $return['data']    = $city;
            $return['message'] = 'City Updated successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }


    public function list(Request $request)
    {
        $limit = $request->lim;
        $page = $request->page;
        $pg = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;
        $src =  $request->src;

        if ($src) {
            $country = City::select('id', 'country_id','country_name', 'state_id','state_name','name', 'status')->where('trash', 1)->whereRaw('concat(ss_cities.name) like ?', "%{$src}%");
            $row = $country->count();
            $data = $country->offset($start)->limit($limit)->get()->toArray();
        } else {
    
            $country = City::select('id', 'country_id','country_name', 'state_id','state_name','name', 'status')->where('trash', 1);
            $row = $country->count();
            $data = $country->offset($start)->limit($limit)->get()->toArray();
        }
        if ($row !== null) {
            $return['code']    = 200;
            $return['data']    = $data;
            $return['row']     = $row;
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }



    public function stateUpdateStatus(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'city_id'    => 'required',
                'status'          => 'required',
            ]
        );
        if ($validator->fails()) {
            $return['code']    = 101;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error!';
            return json_encode($return);
        }
        $states  = City::where('id', $request->city_id)->first();
        if(empty($states)) {
            $return['code']    = 101;
            $return['message'] = 'City Data Not Found !';
            return json_encode($return);
        }
        $states->status = $request->status;
        if ($states->update()) {
            $return['code']    = 200;
            $return['message'] = 'City Status updated!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
}
