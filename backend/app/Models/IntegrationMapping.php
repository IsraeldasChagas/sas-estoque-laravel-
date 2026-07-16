<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationMapping extends Model
{
    protected $table = 'integration_mappings';

    protected $fillable = [
        'integration_id',
        'entity_type',
        'local_id',
        'external_id',
        'external_uuid',
        'unidade_id',
        'meta_json',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
