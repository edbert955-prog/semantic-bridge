<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SemanticMapping extends Model
{
    protected $table = 'semantic_mappings';

    protected $fillable = [
        'source_system', 'source_table', 'source_column',
        'target_system', 'target_entity', 'target_property',
        'predicate_uri', 'mapping_type', 'condition_expression',
        'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
