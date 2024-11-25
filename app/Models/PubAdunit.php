<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Publisher\Pubstats;

class PubAdunit extends Model
{
    use HasFactory;
    
    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }
    public function category()
    {
        return $this->belongsTo(Category::class, 'website_category');
    }
    public function pubstats()
    {
        return $this->hasMany(Pubstats::class, 'adunit_id', 'ad_code')->groupBy('adunit_id');
    }
}
