<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WhatsAppAccount extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'whatsapp_accounts';

    protected $fillable = [
        'name',
        'mobile_number',
        'meta_app_id',
        'meta_app_secret',
        'meta_access_token',
        'meta_business_id',
        'meta_phone_number_id',
        'meta_waba_id',
        'meta_verify_token',
        'whatsapp_offer_template',
        'connection_status',
        'last_checked_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    protected $hidden = [
        'meta_access_token',
        'meta_app_secret',
    ];

    public function templates()
    {
        return $this->hasMany(Template::class, 'account_id');
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class, 'account_id');
    }

    public function getDecryptedToken()
    {
        return decrypt($this->meta_access_token);
    }

    public function getDecryptedAppSecret()
    {
        return $this->meta_app_secret ? decrypt($this->meta_app_secret) : null;
    }
}
