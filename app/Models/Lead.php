<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Lead extends Model
{
    protected $fillable = [
        'leadscaptain_id',
        'first_name',
        'last_name',
        'company_name',
        'position_title',
        'email_status',
        'linkedin_url',
    ];
}