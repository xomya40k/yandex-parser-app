<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parse_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('processed_reviews')->default(0);
            $table->unsignedInteger('total_reviews')->nullable();
            $table->unsignedSmallInteger('processed_pages')->default(0);
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->unsignedSmallInteger('max_attempts');
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parse_runs');
    }
};
