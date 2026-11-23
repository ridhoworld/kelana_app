<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('city', 100)->nullable();
            $table->string('country', 100);
            $table->char('country_code', 2)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('category', 30)->nullable();
            $table->unsignedTinyInteger('priority')->default(3);
            $table->string('status', 20)->default('wishlist');
            $table->string('difficulty', 10)->nullable();
            $table->unsignedBigInteger('cost_min')->nullable();
            $table->unsignedBigInteger('cost_max')->nullable();
            $table->char('cost_currency', 3)->default('IDR');
            $table->text('notes')->nullable();
            $table->date('visited_on')->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'priority'], 'idx_destinations_user_status');
            $table->index(['latitude', 'longitude'], 'idx_destinations_coords');
        });

        Schema::create('destination_best_months', function (Blueprint $table) {
            $table->foreignUuid('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->primary(['destination_id', 'month']);
        });

        Schema::create('destination_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->string('url', 2000);
            $table->string('label', 200)->nullable();
            $table->string('link_type', 20)->default('artikel');
            $table->timestamp('added_at')->useCurrent();

            $table->index('destination_id', 'idx_destination_links_dest');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 50);
            $table->unique(['user_id', 'name'], 'uq_tags_user_name');
        });

        Schema::create('destination_tags', function (Blueprint $table) {
            $table->foreignUuid('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['destination_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('destination_links');
        Schema::dropIfExists('destination_best_months');
        Schema::dropIfExists('destinations');
    }
};
