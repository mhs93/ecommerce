<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'emergency_phone',
        'contact_person',
        'contact_person_phone',
        'address',
        'city',
        'district',
        'division',
        'delivery_method',
    ];
}
