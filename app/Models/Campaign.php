<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Campaign extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'campaigns';

    protected $fillable = [
        'account_id',
        'template_id',
        'name',
        'assemblies',
        'total_voters',
        'total_with_mobile',
        'total_sent',
        'total_delivered',
        'total_read',
        'total_failed',
        'total_skipped',
        'status',
        'started_at',
        'completed_at',
        'last_processed_assembly',
        'last_processed_offset',
        'error_log',
        'rate_per_second',
        'batch_size',
        'is_test',
        'test_numbers',
    ];

    protected $casts = [
        'assemblies' => 'array',
        'error_log' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_voters' => 'integer',
        'total_with_mobile' => 'integer',
        'total_sent' => 'integer',
        'total_delivered' => 'integer',
        'total_read' => 'integer',
        'total_failed' => 'integer',
        'total_skipped' => 'integer',
        'rate_per_second' => 'integer',
        'batch_size' => 'integer',
        'is_test' => 'boolean',
        'test_numbers' => 'array',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_QUEUED = 'queued';
    const STATUS_RUNNING = 'running';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'account_id');
    }

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function isRunning()
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function getProgressPercentage()
    {
        if ($this->total_with_mobile == 0) return 0;
        return round(($this->total_sent + $this->total_failed + $this->total_skipped) / $this->total_with_mobile * 100, 2);
    }
}
