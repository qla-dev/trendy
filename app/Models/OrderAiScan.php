<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderAiScan extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'document_profile',
        'extraction_method',
        'status',
        'processing_step',
        'progress_current',
        'progress_total',
        'source_file_name',
        'source_file_path',
        'source_mime_type',
        'source_file_size',
        'source_origin',
        'source_email_subject',
        'source_email_from',
        'source_email_message_id',
        'source_email_uid',
        'source_email_received_at',
        'source_attachment_index',
        'source_attachment_total',
        'page_count',
        'billed_tokens',
        'provider_task_id',
        'request_prompt',
        'raw_extracted_text',
        'extraction_payload',
        'validation_warnings',
        'validation_errors',
        'normalized_payload',
        'pantheon_transfer_payload',
        'raw_provider_response',
        'openrouter_payload',
        'parser_payload',
        'credits_spent',
        'confidence_score',
        'extraction_duration_ms',
        'ai_duration_ms',
        'validation_duration_ms',
        'pantheon_order_key',
        'pantheon_order_view',
        'pantheon_order_qid',
        'error_message',
        'processed_at',
        'transfer_started_at',
        'transferred_at',
        'completed_at',
    ];

    protected $casts = [
        'normalized_payload' => 'array',
        'extraction_payload' => 'array',
        'pantheon_transfer_payload' => 'array',
        'raw_provider_response' => 'array',
        'openrouter_payload' => 'array',
        'parser_payload' => 'array',
        'validation_warnings' => 'array',
        'validation_errors' => 'array',
        'credits_spent' => 'float',
        'confidence_score' => 'float',
        'extraction_duration_ms' => 'integer',
        'ai_duration_ms' => 'integer',
        'validation_duration_ms' => 'integer',
        'source_email_received_at' => 'datetime',
        'source_attachment_index' => 'integer',
        'source_attachment_total' => 'integer',
        'page_count' => 'integer',
        'billed_tokens' => 'integer',
        'processed_at' => 'datetime',
        'transfer_started_at' => 'datetime',
        'transferred_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'transferred', 'failed'], true);
    }
}
