<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SemanticInferenceLog;
use App\Services\FusekiClient;
use Illuminate\Support\Facades\File;
use Throwable;

class SemanticGraphController extends Controller
{
    public function show(string $uuid)
    {
        $log = SemanticInferenceLog::where('request_uuid', $uuid)->firstOrFail();
        $path = $this->graphPath($uuid);

        abort_unless(File::exists($path), 404, 'File RDF lokal belum tersedia untuk evaluasi ini.');

        return response(File::get($path), 200, [
            'Content-Type' => 'text/turtle; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="evaluation-'.$uuid.'.ttl"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function publish(string $uuid, FusekiClient $fuseki)
    {
        $log = SemanticInferenceLog::where('request_uuid', $uuid)->firstOrFail();
        $path = $this->graphPath($uuid);

        abort_unless(File::exists($path), 404, 'File RDF lokal belum tersedia. Jalankan evaluasi terlebih dahulu.');

        try {
            $published = $fuseki->publishTurtle((string) $log->rdf_graph_uri, File::get($path));
            $log->forceFill([
                'rdf_status' => 'published_verified',
                'sparql_verified' => true,
            ])->save();

            return response()->json([
                'success' => true,
                'message' => 'Graph RDF berhasil dipublikasikan dan diverifikasi melalui SPARQL.',
                'request_uuid' => $uuid,
                'graph_uri' => $log->rdf_graph_uri,
                'triple_count' => $published['triple_count'] ?? $log->rdf_triple_count,
            ]);
        } catch (Throwable $e) {
            report($e);
            $log->forceFill([
                'rdf_status' => 'publish_failed',
                'sparql_verified' => false,
            ])->save();

            return response()->json([
                'success' => false,
                'message' => 'Publikasi RDF gagal. Periksa konfigurasi Fuseki dan Laravel log.',
            ], 503);
        }
    }

    private function graphPath(string $uuid): string
    {
        abort_unless((bool) preg_match('/^[0-9a-fA-F-]{36}$/', $uuid), 404);
        return storage_path('app/semantic-graphs/'.$uuid.'.ttl');
    }
}
