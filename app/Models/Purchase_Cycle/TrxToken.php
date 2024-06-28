<?php

namespace App\Models\Purchase_Cycle;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrxToken extends Model
{
    use HasFactory;
    protected $fillable = [
        'trx_id',
        'serial',
        'client_details',
        'buyable_products',
        'stockout_products',
        'grand_total',
        'status',
        'trx_history',
        'time'
    ];
}
