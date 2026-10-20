
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
                if (!Schema::hasTable('semantic_mappings')) {
            throw new \RuntimeException(
                'Tabel semantic_mappings belum tersedia. ' .
                'Periksa migration sebelumnya.'
            );
        }

        Schema::table('semantic_mappings', function (Blueprint $table) {
            // Identitas sumber
            if (!Schema::hasColumn('semantic_mappings', 'source_system')) {
                $table->string('source_system', 32)->nullable();
            }

            if (!Schema::hasColumn('semantic_mappings', 'source_table')) {
                $table->string('source_table', 100)->nullable();
            }

            if (!Schema::hasColumn('semantic_mappings', 'source_column')) {
                $table->string('source_column', 100)->nullable();
            }

            // Identitas target
            if (!Schema::hasColumn('semantic_mappings', 'target_system')) {
                $table->string('target_system', 32)->nullable();
            }

            if (!Schema::hasColumn('semantic_mappings', 'target_entity')) {
                $table->string('target_entity', 100)->nullable();
            }

            if (!Schema::hasColumn('semantic_mappings', 'target_property')) {
                $table->string('target_property', 150)->nullable();
            }

            // Informasi aturan pemetaan
            if (!Schema::hasColumn('semantic_mappings', 'predicate_uri')) {
                $table->string('predicate_uri', 255)->nullable();
            }

            if (!Schema::hasColumn('semantic_mappings', 'mapping_type')) {
                $table->string('mapping_type', 50)
                    ->default('exact-match');
            }

            if (!Schema::hasColumn('semantic_mappings', 'condition_expression')) {
                $table->text('condition_expression')->nullable();
            }

            if (!Schema::hasColumn('semantic_mappings', 'description')) {
                $table->text('description')->nullable();
            }

            if (!Schema::hasColumn('semantic_mappings', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }

            // Timestamp untuk Eloquent
            if (!Schema::hasColumn('semantic_mappings', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (!Schema::hasColumn('semantic_mappings', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Kolom tidak dihapus saat rollback agar data lama
        // pada tabel semantic_mappings tetap aman.
    }
};
