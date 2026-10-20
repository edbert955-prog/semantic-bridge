<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class SemanticOntologyController extends Controller
{
    public function show(string $name): Response
    {
        $allowed = [
            'workflow' => 'workflow.ttl',
            'siakadu' => 'siakadu.ttl',
            'alignment' => 'alignment.ttl',
        ];

        abort_unless(isset($allowed[$name]), 404);

        $path = config('semantic.ontology.directory')
            . DIRECTORY_SEPARATOR . $allowed[$name];

        abort_unless(is_file($path), 404, 'File ontologi tidak ditemukan.');

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/turtle; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
