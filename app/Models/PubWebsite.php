<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PubAdunit;
use Illuminate\Support\Facades\DB;

class PubWebsite extends Model
{
    use HasFactory;
    protected $fillable = [
        'web_name',
        'site_url',
    ];
    public function adunitList()
    {
        return $this->hasMany(PubAdunit::class, 'web_code', 'web_code')
        ->where('pub_adunits.trash', 0)
        ->orderBy('pub_adunits.id', 'DESC')
        ->leftJoin('pub_stats', 'pub_adunits.ad_code', '=', 'pub_stats.adunit_id')
        ->select('pub_adunits.id','pub_adunits.ad_code','pub_adunits.web_code','pub_adunits.ad_name','pub_adunits.ad_type','pub_adunits.erotic_ads','pub_adunits.created_at as created','pub_adunits.site_url','pub_adunits.status',
            DB::raw('COALESCE(SUM(ss_pub_stats.impressions), 0) as impressions'),DB::raw('COALESCE(SUM(ss_pub_stats.clicks), 0) as clicks'))
        ->groupBy('pub_adunits.ad_code');
    }
    
    public function user()
    {
        return $this->hasMany(User::class,'uid','uid');
    }
    public function category()
    {
        return $this->belongsTo(Category::class, 'website_category');
    }
    public function pubAdUnits()
    {
        return $this->hasMany(PubAdunit::class, 'web_code', 'web_code')->where('trash', 0)->groupBy('web_code');
    }
}
