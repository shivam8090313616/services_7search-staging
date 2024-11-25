<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminRemovedPaymentResctrictionLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'uid',
        'remark',
        'removed_by',
    ];
}
