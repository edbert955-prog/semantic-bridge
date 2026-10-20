<?php

return [
    'api_token' => env('SEMANTIC_API_TOKEN'),

    'connections' => [
        'metadata' => env('DB_CONNECTION', 'pgsql'),
        'system_a' => 'system_a',
        'system_b' => 'system_b',
    ],

    'ontology' => [
        'base_uri' => 'https://unila.ac.id/ontology/',
        'workflow_uri' => 'https://unila.ac.id/ontology/workflow#',
        'siakadu_uri' => 'https://unila.ac.id/ontology/siakadu#',
        'alignment_uri' => 'https://unila.ac.id/ontology/alignment#',
        'directory' => storage_path('app/ontologies'),
        'version' => '2.0.0',
    ],

    'rdf' => [
        'graph_base_uri' => env('SEMANTIC_GRAPH_BASE_URI', 'https://unila.ac.id/graph/'),
        'resource_base_uri' => env('SEMANTIC_RESOURCE_BASE_URI', 'https://unila.ac.id/resource'),
        'directory' => storage_path('app/semantic-graphs'),
    ],

    'reasoner' => [
        'enabled' => filter_var(
            env('SEMANTIC_REASONER_ENABLED', true),
            FILTER_VALIDATE_BOOL
        ),

        'jar_path' => env('SEMANTIC_REASONER_JAR')
            ?: base_path('semantic-reasoner/target/semantic-owl2rl-reasoner.jar'),

        'java_binary' => env('SEMANTIC_JAVA_BINARY')
            ?: 'C:/Program Files/Common Files/Oracle/Java/javapath/java.exe',

        'timeout' => (int) (env('SEMANTIC_REASONER_TIMEOUT') ?: 90),
    ],

    'fuseki' => [
        'enabled' => filter_var(env('FUSEKI_ENABLED', false), FILTER_VALIDATE_BOOL),
        'query_url' => env('FUSEKI_QUERY_URL', 'http://127.0.0.1:3030/siakadu/query'),
        'update_url' => env('FUSEKI_UPDATE_URL', 'http://127.0.0.1:3030/siakadu/update'),
        'data_url' => env('FUSEKI_DATA_URL', 'http://127.0.0.1:3030/siakadu/data'),
        'username' => env('FUSEKI_USERNAME', ''),
        'password' => env('FUSEKI_PASSWORD', ''),
        'timeout' => (int) env('FUSEKI_TIMEOUT', 10),
    ],

    'services' => [
        'SKL' => [
            'label' => 'Surat Keterangan Lulus',
            // Rule values for this coursework prototype; verify against academic policy before production.
            'min_sks_lulus' => 144,
            'min_ipk' => 2.75,
            'requires_plagiarism_clearance' => true,
            'requires_skripsi_grade_a' => true,
        ],
    ],

    'inference' => [
        'missing_evidence_policy' => 'needs_manual_review',
        'store_evidence' => true,
        'store_trace' => true,
    ],

        
];
