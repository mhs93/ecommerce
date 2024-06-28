<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPurchaseByUser extends Model
{
    use HasFactory;
    protected $fillable = [
        'trx_id',
        'order_id',
        'master_order_id',
        'phone',
        'email',
        'client_id',
        'client_name',
        'shipping_info',
        'product_primary_details',
        'product_variations',
        'product_total_price',
        'delivery_charge',
        'grand_price',
        'payment_method',
        'payment_status',
        'note',
        'cs_response',
        'cs_note',
        'tracking',
        'note_by_delivery',
        'order_type',
        'time',
    ];
}
