<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminBankDetail extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'bank_id',
        'bank_name',
        'acc_name',
        'acc_number',
        'swift_code',
        'ifsc_code',
        'country',
        'acc_address',
        'status',
        'deleted_at',
    ];
}
