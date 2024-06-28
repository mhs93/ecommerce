<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientLoginSession extends Model
{
    use HasFactory;
    protected $fillable = [
        'client_id',
        'identifier',
        'access_token',
        'user_agent',
        'time_limit',
    ];
}
