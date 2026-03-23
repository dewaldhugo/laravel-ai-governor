<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_usage', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->foreignId('prompt_version_id')
                  ->nullable()
                  ->constrained('prompt_versions')
                  ->nullOnDelete();
            $table->string('scope', 100)->default('global');
            $table->string('model', 100);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->string('period_key', 20); // e.g. "2026-03" or "2026-03-13"
            $table->timestamps();

            // Primary lookup path for budget enforcement.
            $table->index(['owner_type', 'owner_id', 'scope', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_usage');
    }
};
