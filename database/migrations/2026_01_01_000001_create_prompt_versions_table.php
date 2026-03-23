<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedSmallInteger('version');
            $table->string('model', 100);
            $table->text('system_prompt');
            $table->text('user_template');
            $table->decimal('temperature', 3, 2)->default(0.70);
            // unsignedInteger (max ~4.29 billion) instead of unsignedSmallInteger
            // (max 65,535) — modern models already allow 128k+ output tokens.
            $table->unsignedInteger('max_tokens')->default(1000);
            $table->char('checksum', 64);
            $table->string('environment', 20)->default('production');
            $table->timestamps();

            $table->unique(['name', 'version', 'environment']);
            $table->index(['name', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_versions');
    }
};
