<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SemanticInferenceLog extends Model
{
    protected $table = 'semantic_inference_logs';

    protected $fillable = [
        'request_uuid', 'service_code', 'applicant_npm',
        'result_status', 'is_eligible', 'reasons', 'evidence',
        'source_snapshot', 'reasoning_trace', 'ontology_version',
        'evaluated_at', 'rdf_graph_uri', 'rdf_file_path', 'rdf_triple_count',
        'rdf_status', 'sparql_verified', 'reasoner_status', 'reasoner_triple_count',
    ];

    protected function casts(): array
    {
        return [
            'is_eligible' => 'boolean',
            'reasons' => 'array',
            'evidence' => 'array',
            'source_snapshot' => 'array',
            'reasoning_trace' => 'array',
            'evaluated_at' => 'datetime',
            'rdf_triple_count' => 'integer',
            'sparql_verified' => 'boolean',
            'reasoner_triple_count' => 'integer',
        ];
    }
}
