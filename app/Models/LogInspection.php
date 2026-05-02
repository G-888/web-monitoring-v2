<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogInspection extends Model
{
    protected $fillable = [
        'user_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'source_type',
        'size_bytes',
        'total_lines',
        'critical_count',
        'error_count',
        'warning_count',
        'info_count',
        'highlights',
        'ai_status',
        'ai_provider',
        'ai_model',
        'ai_summary',
        'ai_findings',
        'ai_analyzed_at',
        'inspected_at',
    ];

    protected $casts = [
        'highlights' => 'array',
        'ai_findings' => 'array',
        'ai_analyzed_at' => 'datetime',
        'inspected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
