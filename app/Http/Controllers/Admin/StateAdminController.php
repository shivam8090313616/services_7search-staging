<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\State;
use Illuminate\Support\Facades\Validator;


class StateAdminController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'country_id'    => 'required',
                'name'          => 'required',
            ]
        );
        if ($validator->fails()) {
            $return['code']    = 101;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error!';
            return json_encode($return);
        }
        $country_id = $request->country_id;
        $countryData = Country::where('id',$country_id)->first();
        if(empty($countryData)) {
            $return['code']    = 101;
            $return['message'] = 'Country Data Not Found !';
            return json_encode($return);
        }
        $stateData = State::where('country_id',$country_id)->where('name',$request->name)->count();
        if($stateData > 0) {
            $return['code']    = 101;
            $return['message'] = 'State Allready available!';
            return json_encode($return);
        }

        $state                = new State();
        $state->country_id    = $country_id;
        $state->country_iso   = $countryData->iso;
        $state->country_name  = $countryData->name;
        $state->name          = $request->name;
        if ($state->save()) {
            $return['code']    = 200;
            $return['data']    = $state;
            $return['message'] = 'State added successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }


    public function update(Request $request){
        $validator = Validator::make(
            $request->all(),
            [
                'country_id'    => 'required',
                'name'          => 'required',
            ]
        );
        if ($validator->fails()) {
            $return['code']    = 101;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error!';
            return json_encode($return);
        }
        $country_id = $request->country_id;
        $countryData = Country::where('id',$country_id)->first();
        if(empty($countryData)) {
            $return['code']    = 101;
            $return['message'] = 'Country Data Not Found !';
            return json_encode($return);
        }
        $state = State::where('id', $request->state_id)->first(); 
        if(empty($state)) {
            $return['code']    = 101;
            $return['message'] = 'State Data Not Found !';
            return json_encode($return);
        }
        $state->country_id    = $country_id;
        $state->country_iso   = $countryData->iso;
        $state->country_name  = $countryData->name;
        $state->name          = $request->name;
        if ($state->save()) {
            $return['code']    = 200;
            $return['data']    = $state;
            $return['message'] = 'State Updated successfully!';
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
            $country = State::select('id', 'country_id', 'country_iso', 'country_name', 'name', 'status')->where('trash', 1)->whereRaw('concat(ss_states.name) like ?', "%{$src}%");
            $row = $country->count();
            $data = $country->offset($start)->limit($limit)->get()->toArray();
        } else {
    
            $country = State::select('id', 'country_id', 'country_iso', 'country_name', 'name', 'status')->where('trash', 1);
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
                'state_id'    => 'required',
                'status'          => 'required',
            ]
        );
        if ($validator->fails()) {
            $return['code']    = 101;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error!';
            return json_encode($return);
        }
        $states  = State::where('id', $request->state_id)->first();
        if(empty($states)) {
            $return['code']    = 101;
            $return['message'] = 'State Data Not Found !';
            return json_encode($return);
        }
        $states->status = $request->status;
        if ($states->update()) {
            $return['code']    = 200;
            $return['message'] = 'State Status updated!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }

}
