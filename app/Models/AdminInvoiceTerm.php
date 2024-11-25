<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminInvoiceTerm extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'terms',
        'status',
        'deleted_at',
    ];
}
