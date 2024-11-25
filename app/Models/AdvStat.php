<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvStat extends Model
{
    protected $table = 'adv_stats';
    use HasFactory;
    protected $fillable = [
        'advertiser_code',
        'uni_imp_id',
        'camp_id',
        'impressions',
        'clicks',
        'imp_amount',
        'click_amount',
        'ad_type',
        'amount',
        'device_type',
        'device_os',
        'country',
        'udate',
    ];
}
