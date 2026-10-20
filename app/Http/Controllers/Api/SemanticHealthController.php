<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FusekiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SemanticHealthController extends Controller
{
    public function __invoke(FusekiClient $fuseki)
    {
        $definitions = [
            'metadata' => ['connection' => config('semantic.connections.metadata'), 'tables' => ['semantic_mappings', 'semantic_inference_logs']],
            'system_a' => ['connection' => 'system_a', 'tables' => ['users', 'jenis_layanan', 'status_pengajuan', 'pengajuan']],
            'system_b' => ['connection' => 'system_b', 'tables' => ['mahasiswa', 'dosen', 'mata_kuliah', 'krs', 'krs_detail', 'pembayaran_ukt']],
        ];
        $results = [];

        foreach ($definitions as $key => $definition) {
            $connectionName = $definition['connection'];
            try {
                $connection = DB::connection($connectionName);
                $db = $connection->selectOne('SELECT current_database() AS database_name');
                $tables = [];
                foreach ($definition['tables'] as $table) {
                    $tables[$table] = Schema::connection($connectionName)->hasTable($table);
                }
                $results[$key] = [
                    'connected' => true,
                    'database' => $db->database_name ?? null,
                    'tables' => $tables,
                    'tables_ready' => !in_array(false, $tables, true),
                ];
            } catch (Throwable $e) {
                report($e);
                $results[$key] = [
                    'connected' => false,
                    'database' => null,
                    'tables' => [],
                    'tables_ready' => false,
                    'error' => 'Koneksi atau pemeriksaan tabel gagal. Periksa konfigurasi database.',
                ];
            }
        }

        // Fuseki is optional for local RDF-file generation; its state is reported separately.
        $healthy = collect($results)->every(fn ($result) => $result['connected'] && $result['tables_ready']);
        return response()->json([
            'success' => $healthy,
            'service' => 'SIAKADU Semantic Bridge',
            'status' => $healthy ? 'healthy' : 'degraded',
            'connections' => $results,
            'fuseki' => $fuseki->health(),
        ], $healthy ? 200 : 503);
    }
}
