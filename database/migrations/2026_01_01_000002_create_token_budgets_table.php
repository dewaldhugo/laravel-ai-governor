<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_budgets', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('scope', 100)->default('global');
            $table->string('period', 20);
            $table->unsignedInteger('limit');
            $table->boolean('hard_limit')->default(true);
            $table->timestamps();

            // One budget definition per owner + scope + period combination.
            $table->unique(['owner_type', 'owner_id', 'scope', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_budgets');
    }
};
