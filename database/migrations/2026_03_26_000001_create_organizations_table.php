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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('yandex_maps_url', 2048)->unique();
            $table->decimal('rating', 4, 2)->nullable();
            $table->unsignedInteger('total_ratings')->default(0);
            $table->unsignedInteger('total_reviews')->default(0);
            $table->timestamp('last_parsed_at')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
