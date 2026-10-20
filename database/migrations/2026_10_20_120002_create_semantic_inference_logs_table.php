<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semantic_inference_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->string('service_code', 30);
            $table->string('applicant_npm', 20)->nullable();
            $table->string('result_status', 32);
            $table->boolean('is_eligible')->nullable();
            $table->jsonb('reasons')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->jsonb('source_snapshot')->nullable();
            $table->jsonb('reasoning_trace')->nullable();
            $table->string('ontology_version', 50)->nullable();
            $table->timestampTz('evaluated_at')->nullable();
            $table->timestamps();
            $table->index(['applicant_npm', 'service_code']);
            $table->index(['result_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semantic_inference_logs');
    }
};
