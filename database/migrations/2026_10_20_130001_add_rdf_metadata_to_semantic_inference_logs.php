<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('semantic_inference_logs')) {
            throw new \RuntimeException('Tabel semantic_inference_logs belum tersedia. Jalankan migration awal Semantic Bridge terlebih dahulu.');
        }

        Schema::table('semantic_inference_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('semantic_inference_logs', 'rdf_graph_uri')) {
                $table->text('rdf_graph_uri')->nullable();
            }
            if (!Schema::hasColumn('semantic_inference_logs', 'rdf_file_path')) {
                $table->string('rdf_file_path', 255)->nullable();
            }
            if (!Schema::hasColumn('semantic_inference_logs', 'rdf_triple_count')) {
                $table->unsignedInteger('rdf_triple_count')->nullable();
            }
            if (!Schema::hasColumn('semantic_inference_logs', 'rdf_status')) {
                $table->string('rdf_status', 32)->nullable();
            }
            if (!Schema::hasColumn('semantic_inference_logs', 'sparql_verified')) {
                $table->boolean('sparql_verified')->nullable();
            }
            if (!Schema::hasColumn('semantic_inference_logs', 'reasoner_status')) {
                $table->string('reasoner_status', 32)->nullable();
            }
            if (!Schema::hasColumn('semantic_inference_logs', 'reasoner_triple_count')) {
                $table->unsignedInteger('reasoner_triple_count')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally retain RDF metadata during rollback so existing audit data is not lost.
    }
};
