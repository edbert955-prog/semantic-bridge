<?php

namespace App\Http\Controllers;

use App\Models\SemanticInferenceLog;
use App\Models\SemanticMapping;
use App\Services\FusekiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SemanticDashboardController extends Controller
{
    private function connections(): array
    {
        $definitions = [
            'metadata' => ['connection' => config('semantic.connections.metadata'), 'label' => 'Metadata Semantic Hub', 'tables' => ['semantic_mappings', 'semantic_inference_logs']],
            'system_a' => ['connection' => 'system_a', 'label' => 'System A · Pengajuan Akademik', 'tables' => ['users', 'jenis_layanan', 'status_pengajuan', 'pengajuan']],
            'system_b' => ['connection' => 'system_b', 'label' => 'System B · SIAKADU', 'tables' => ['mahasiswa', 'dosen', 'mata_kuliah', 'krs', 'krs_detail', 'pembayaran_ukt']],
        ];

        foreach ($definitions as $key => $definition) {
            try {
                $conn = DB::connection($definition['connection']);
                $db = $conn->selectOne('SELECT current_database() AS database_name');
                $tables = [];
                foreach ($definition['tables'] as $table) {
                    $tables[$table] = Schema::connection($definition['connection'])->hasTable($table);
                }
                $definitions[$key]['connected'] = true;
                $definitions[$key]['database'] = $db->database_name ?? '(unknown)';
                $definitions[$key]['tables_state'] = $tables;
                $definitions[$key]['ready'] = !in_array(false, $tables, true);
            } catch (Throwable $e) {
                report($e);
                $definitions[$key]['connected'] = false;
                $definitions[$key]['database'] = 'Not connected';
                $definitions[$key]['tables_state'] = [];
                $definitions[$key]['ready'] = false;
            }
        }
        return $definitions;
    }

    public function index(FusekiClient $fuseki)
    {
        $connections = $this->connections();
        $allReady = collect($connections)->every(fn ($c) => $c['connected'] && $c['ready']);
        $fusekiStatus = $fuseki->health();

        $stats = [
            'mappings' => SemanticMapping::count(),
            'active_mappings' => SemanticMapping::where('is_active', true)->count(),
            'evaluations' => SemanticInferenceLog::count(),
            'eligible' => SemanticInferenceLog::where('result_status', 'eligible')->count(),
            'ineligible' => SemanticInferenceLog::where('result_status', 'ineligible')->count(),
            'review' => SemanticInferenceLog::where('result_status', 'needs_manual_review')->count(),
            'rdf_graphs' => SemanticInferenceLog::whereNotNull('rdf_graph_uri')->count(),
            'rdf_verified' => SemanticInferenceLog::where('sparql_verified', true)->count(),
        ];
        $recentLogs = SemanticInferenceLog::latest()->limit(8)->get();
        $recentMappings = SemanticMapping::orderBy('source_system')->limit(8)->get();

        return view('semantic.dashboard', compact('connections', 'allReady', 'stats', 'recentLogs', 'recentMappings', 'fusekiStatus'));
    }

    public function mappings(Request $request)
    {
        $query = SemanticMapping::query();
        foreach (['source_system', 'target_system', 'source_table'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field)->toString());
            }
        }
        if ($request->filled('search')) {
            $term = trim($request->string('search')->toString());
            $query->where(function ($q) use ($term) {
                $q->where('source_column', 'ilike', "%{$term}%")
                    ->orWhere('target_entity', 'ilike', "%{$term}%")
                    ->orWhere('target_property', 'ilike', "%{$term}%")
                    ->orWhere('predicate_uri', 'ilike', "%{$term}%");
            });
        }
        $mappings = $query->orderBy('source_system')->orderBy('source_table')->paginate(20)->withQueryString();
        return view('semantic.mappings', compact('mappings'));
    }

    public function logs(Request $request)
    {
        $query = SemanticInferenceLog::query();
        if ($request->filled('status')) {
            $query->where('result_status', $request->string('status')->toString());
        }
        if ($request->filled('npm')) {
            $query->where('applicant_npm', 'ilike', '%'.trim($request->string('npm')->toString()).'%');
        }
        if ($request->filled('rdf_status')) {
            $query->where('rdf_status', $request->string('rdf_status')->toString());
        }
        $logs = $query->latest()->paginate(20)->withQueryString();
        return view('semantic.logs', compact('logs'));
    }

    public function ontologies()
    {
        $base = config('semantic.ontology.directory');
        $ontologies = [];
        foreach (['workflow', 'siakadu', 'alignment'] as $name) {
            $path = $base.DIRECTORY_SEPARATOR.$name.'.ttl';
            $ontologies[] = [
                'name' => $name,
                'path' => $path,
                'exists' => is_file($path),
                'size' => is_file($path) ? filesize($path) : 0,
                'uri' => match ($name) {
                    'workflow' => config('semantic.ontology.workflow_uri'),
                    'siakadu' => config('semantic.ontology.siakadu_uri'),
                    default => config('semantic.ontology.alignment_uri'),
                },
            ];
        }
        return view('semantic.ontologies', compact('ontologies'));
    }

    public function downloadOntology(string $name)
    {
        $allowed = ['workflow', 'siakadu', 'alignment'];
        abort_unless(in_array($name, $allowed, true), 404);

        $path = config('semantic.ontology.directory') . DIRECTORY_SEPARATOR . $name . '.ttl';
        abort_unless(is_file($path), 404, 'File ontologi tidak ditemukan.');

        return response(File::get($path), 200, [
            'Content-Type' => 'text/turtle; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="' . $name . '.ttl"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function downloadGraph(string $uuid)
    {
        abort_unless((bool) preg_match('/^[0-9a-fA-F-]{36}$/', $uuid), 404);
        $log = SemanticInferenceLog::where('request_uuid', $uuid)->firstOrFail();
        $path = storage_path('app/semantic-graphs/'.$uuid.'.ttl');
        abort_unless(File::exists($path), 404, 'File RDF untuk evaluasi ini belum tersedia.');

        return response(File::get($path), 200, [
            'Content-Type' => 'text/turtle; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="evaluation-'.$uuid.'.ttl"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
