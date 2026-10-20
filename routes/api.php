<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SemanticHealthController;
use App\Http\Controllers\Api\SemanticMappingController;
use App\Http\Controllers\Api\SemanticOntologyController;
use App\Http\Controllers\Api\SemanticEligibilityController;
use App\Http\Controllers\Api\SemanticGraphController;
use App\Http\Controllers\Api\SemanticSparqlApiController;
use App\Http\Middleware\VerifySemanticApiToken;

Route::prefix('v1')->middleware(VerifySemanticApiToken::class)->group(function () {
    Route::get('/health', SemanticHealthController::class)->name('api.semantic.health');
    Route::get('/mappings', [SemanticMappingController::class, 'index'])->name('api.semantic.mappings');
    Route::get('/ontology/{name}', [SemanticOntologyController::class, 'show'])
        ->where('name', 'workflow|siakadu|alignment')
        ->name('api.semantic.ontology');
    Route::post('/eligibility/check', [SemanticEligibilityController::class, 'check'])->name('api.semantic.eligibility');

    Route::get('/fuseki/health', [SemanticSparqlApiController::class, 'health'])->name('api.semantic.fuseki.health');
    Route::get('/rdf/graphs/{uuid}', [SemanticGraphController::class, 'show'])
        ->where('uuid', '[0-9a-fA-F-]{36}')
        ->name('api.semantic.rdf.show');
    Route::post('/rdf/graphs/{uuid}/publish', [SemanticGraphController::class, 'publish'])
        ->where('uuid', '[0-9a-fA-F-]{36}')
        ->name('api.semantic.rdf.publish');
    Route::get('/sparql/evaluations/{uuid}', [SemanticSparqlApiController::class, 'evaluation'])
        ->where('uuid', '[0-9a-fA-F-]{36}')
        ->name('api.semantic.sparql.evaluation');
    Route::post('/sparql/query', [SemanticSparqlApiController::class, 'query'])->name('api.semantic.sparql.query');
});
