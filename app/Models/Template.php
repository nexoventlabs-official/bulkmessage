<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Template extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'templates';

    protected $fillable = [
        'account_id',
        'name',
        'language',
        'category',
        'header_type',
        'header_media_url',
        'header_media_public_id',
        'header_media_type',
        'body_text',
        'footer_text',
        'buttons',
        'meta_template_id',
        'meta_template_name',
        'status',
        'rejection_reason',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'buttons' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'account_id');
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class, 'template_id');
    }

    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
