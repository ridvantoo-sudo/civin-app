<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('geohash', 12)->index();
            $table->unsignedTinyInteger('geohash_precision')->default(6);
            $table->boolean('share_location')->default(true);
            $table->boolean('show_distance')->default(true);
            $table->string('nearby_visibility', 20)->default('everyone');
            $table->timestamp('located_at')->nullable();
            $table->timestamps();

            $table->index(['share_location', 'geohash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_locations');
    }
};
