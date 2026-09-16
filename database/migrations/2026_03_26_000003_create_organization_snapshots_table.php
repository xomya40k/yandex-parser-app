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
        Schema::create('organization_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('rating', 4, 2)->nullable();
            $table->unsignedInteger('total_ratings')->default(0);
            $table->unsignedInteger('total_reviews')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['organization_id', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_snapshots');
    }
};
