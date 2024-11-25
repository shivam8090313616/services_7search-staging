<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\State;

class CityStateController extends Controller
{

    /* ######## State List Using Country ID ########### */

    public function stateList(Request $request)
    {
        $getstate =[];
        $getstate = State::select('id as value', 'name as label')->where('status', 1)
            ->where('trash', 1)->where('country_id', $request->cid)
            ->orderBy('name', 'asc')->get()->toArray();
        if ($getstate) {
            $return = $getstate;
        } else {
            $return = $getstate;
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }

   /* ######## City List Using Country ID ########### */

    public function cityList(Request $request)
    {
        $country =[];
        $country = DB::table("cities")->select('id as value', 'name as label')->where('status',1)
        ->where('trash',1)->where('country_id', $request->cid)
        ->orderBy('name', 'asc')->get();
        if ($country) {
            $return = $country;
        } else {
            $return = $country;
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }




}
