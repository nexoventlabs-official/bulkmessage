<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MessageLog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'message_logs';

    protected $fillable = [
        'campaign_id',
        'account_id',
        'assembly_no',
        'voter_id',
        'mobile_number',
        'whatsapp_message_id',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    const STATUS_QUEUED = 'queued';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_READ = 'read';
    const STATUS_FAILED = 'failed';

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}
