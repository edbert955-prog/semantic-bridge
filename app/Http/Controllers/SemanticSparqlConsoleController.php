<?php

namespace App\Http\Controllers;

use App\Services\FusekiClient;
use Illuminate\Http\Request;
use Throwable;

class SemanticSparqlConsoleController extends Controller
{
    private const DEFAULT_QUERY = <<<'SPARQL'
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX alignment: <https://unila.ac.id/ontology/alignment#>
SELECT ?evaluation ?npm ?service ?status
WHERE {
  GRAPH ?graph {
    ?evaluation a alignment:ServiceEligibilityResult ;
                alignment:applicantNpm ?npm ;
                alignment:serviceCode ?service ;
                alignment:resultStatus ?status .
  }
}
ORDER BY DESC(?evaluation)
LIMIT 50
SPARQL;

    public function index(FusekiClient $fuseki)
    {
        return view('semantic.sparql', [
            'fusekiStatus' => $fuseki->health(),
            'queryText' => session('sparql_query', self::DEFAULT_QUERY),
            'result' => session('sparql_result'),
            'errorMessage' => session('sparql_error'),
        ]);
    }

    public function execute(Request $request, FusekiClient $fuseki)
    {
        $request->validate(['query' => ['required', 'string', 'max:10000']]);
        $query = trim((string) $request->input('query'));

        if (!$this->isReadOnlyQuery($query)) {
            return redirect()->route('semantic.sparql')
                ->withInput()
                ->with('sparql_query', $query)
                ->with('sparql_error', 'Console hanya menerima query SELECT atau ASK. Query yang mengubah data ditolak.');
        }

        try {
            $result = $fuseki->query($query);
            return redirect()->route('semantic.sparql')
                ->with('sparql_query', $query)
                ->with('sparql_result', $result)
                ->with('success', 'Query SPARQL berhasil dijalankan.');
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('semantic.sparql')
                ->withInput()
                ->with('sparql_query', $query)
                ->with('sparql_error', 'Query gagal. Pastikan Fuseki aktif, endpoint benar, dan query valid.');
        }
    }

    private function isReadOnlyQuery(string $query): bool
    {
        if (preg_match('/\b(INSERT|DELETE|LOAD|CLEAR|DROP|CREATE|MOVE|COPY|ADD|WITH|SERVICE)\b/i', $query)) {
            return false;
        }
        return (bool) preg_match('/^(?:PREFIX\s+[^\n]+\s+|BASE\s+<[^>]+>\s*)*(SELECT|ASK)\b/is', $query);
    }
}
