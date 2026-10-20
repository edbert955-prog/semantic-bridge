<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SemanticInferenceLog;
use App\Services\FusekiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SemanticSparqlApiController extends Controller
{
    public function health(FusekiClient $fuseki)
    {
        $health = $fuseki->health();
        $status = $health['status'] === 'ready' ? 200 : ($health['status'] === 'not_configured' ? 409 : 503);
        return response()->json(['success' => $status === 200, 'fuseki' => $health], $status);
    }

    public function evaluation(string $uuid, FusekiClient $fuseki)
    {
        $log = SemanticInferenceLog::where('request_uuid', $uuid)->firstOrFail();
        if (empty($log->rdf_graph_uri)) {
            return response()->json(['success' => false, 'message' => 'Graph URI belum tercatat.'], 404);
        }

        try {
            $graph = $this->iri((string) $log->rdf_graph_uri);
            $query = 'SELECT ?subject ?predicate ?object WHERE { GRAPH '.$graph.' { ?subject ?predicate ?object } } ORDER BY ?subject ?predicate LIMIT 500';
            return response()->json([
                'success' => true,
                'request_uuid' => $uuid,
                'graph_uri' => $log->rdf_graph_uri,
                'sparql' => $query,
                'data' => $fuseki->query($query),
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Query SPARQL gagal. Pastikan Fuseki aktif dan graph sudah dipublikasikan.'], 503);
        }
    }

    public function query(Request $request, FusekiClient $fuseki)
    {
        $validator = Validator::make($request->all(), [
            'query' => ['required', 'string', 'max:10000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $query = trim((string) $request->input('query'));
        if (!$this->isReadOnlyQuery($query)) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint ini hanya menerima SELECT atau ASK. SPARQL Update tidak diizinkan dari query publik ini.',
            ], 422);
        }

        try {
            return response()->json(['success' => true, 'data' => $fuseki->query($query)]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Query SPARQL gagal. Periksa syntax query dan status Fuseki.'], 503);
        }
    }

    private function isReadOnlyQuery(string $query): bool
    {
        if (preg_match('/\b(INSERT|DELETE|LOAD|CLEAR|DROP|CREATE|MOVE|COPY|ADD|WITH|SERVICE)\b/i', $query)) {
            return false;
        }
        return (bool) preg_match('/^(?:PREFIX\s+[^\n]+\s+|BASE\s+<[^>]+>\s*)*(SELECT|ASK)\b/is', $query);
    }

    private function iri(string $value): string
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('Invalid graph IRI.');
        }
        return '<'.$value.'>';
    }
}
