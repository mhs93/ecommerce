<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'main_image',
        'sub_image',
        'price',
        'old_price',
        'discount_price',
        'category',
        'sub_category',
        'has_group_variation',
        'group',
        'variation',
        'total_stock',
        'seo',
        'tag',
        'description',
        'delivery_charge',
        'links',
        'rating',
        'inserted_by',
        'status',
    ];
}
