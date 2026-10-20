<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SemanticDashboardController;
use App\Http\Controllers\SemanticSparqlConsoleController;

Route::get('/', [SemanticDashboardController::class, 'index'])->name('semantic.dashboard');
Route::get('/mappings', [SemanticDashboardController::class, 'mappings'])->name('semantic.mappings');
Route::get('/inference-logs', [SemanticDashboardController::class, 'logs'])->name('semantic.logs');
Route::get('/ontologies', [SemanticDashboardController::class, 'ontologies'])->name('semantic.ontologies');
Route::get('/ontologies/{name}/download', [SemanticDashboardController::class, 'downloadOntology'])
    ->where('name', 'workflow|siakadu|alignment')
    ->name('semantic.ontology.download');
Route::get('/sparql-console', [SemanticSparqlConsoleController::class, 'index'])->name('semantic.sparql');
Route::post('/sparql-console', [SemanticSparqlConsoleController::class, 'execute'])->name('semantic.sparql.execute');
Route::get('/rdf-graphs/{uuid}', [SemanticDashboardController::class, 'downloadGraph'])
    ->where('uuid', '[0-9a-fA-F-]{36}')
    ->name('semantic.graph.download');
