<?php

namespace App\Models\Purchase_Cycle;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderIDSerialIndex extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'serial',
    ];
}
