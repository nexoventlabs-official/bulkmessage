<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Assembly extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'assemblies';

    protected $fillable = [
        'ac_no',
        'constituency',
        'district',
        'zone',
        'table_name',
        'total_voters',
        'total_male',
        'total_female',
        'total_third_gender',
        'has_mobile_data',
    ];

    protected $casts = [
        'ac_no' => 'integer',
        'total_voters' => 'integer',
        'total_male' => 'integer',
        'total_female' => 'integer',
        'total_third_gender' => 'integer',
        'has_mobile_data' => 'boolean',
    ];
}
