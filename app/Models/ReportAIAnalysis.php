<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAIAnalysis extends Model
{
    protected $fillable = [
        'report_id', 'status', 'summary_json', 'summary_html',
        'model', 'files_hash', 'error', 'generated_at',
    ];

    protected $casts = [
        'summary_json' => 'array',
        'generated_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}