<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedBigInteger('budget_amount')->nullable();
            $table->char('budget_currency', 3)->default('IDR');
            $table->string('status', 20)->default('rencana');
            $table->boolean('is_template')->default(false);
            $table->uuid('source_trip_id')->nullable();
            $table->timestamps();

            $table->foreign('source_trip_id')
                ->references('id')
                ->on('trips')
                ->nullOnDelete();

            $table->index(['user_id', 'start_date'], 'idx_trips_user');
        });

        Schema::create('trip_destinations', function (Blueprint $table) {
            $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignUuid('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->date('arrival_date')->nullable();
            $table->date('departure_date')->nullable();

            $table->primary(['trip_id', 'destination_id']);
            $table->unique(['trip_id', 'position'], 'uq_trip_dest_pos');
            $table->index('destination_id', 'idx_trip_destinations_dest');
        });

        Schema::create('itinerary_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->date('day_date');
            $table->foreignUuid('destination_id')->nullable()->constrained('destinations')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->unique(['trip_id', 'day_number'], 'uq_days_trip_num');
            $table->unique(['trip_id', 'day_date'], 'uq_days_trip_date');
        });

        Schema::create('itinerary_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('day_id')->constrained('itinerary_days')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('category', 20)->default('aktivitas');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location', 300)->nullable();
            $table->unsignedBigInteger('estimated_cost')->nullable();
            $table->string('booking_ref', 100)->nullable();
            $table->text('notes')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_done')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['day_id', 'position'], 'idx_items_day');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignUuid('item_id')->nullable()->constrained('itinerary_items')->nullOnDelete();
            $table->string('description', 200);
            $table->string('category', 20)->default('lainnya');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('IDR');
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['trip_id', 'paid_at'], 'idx_expenses_trip');
        });

        Schema::create('packing_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('category', 50)->default('umum');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->boolean('is_packed')->default(false);
            $table->integer('position')->default(0);

            $table->index(['trip_id', 'category'], 'idx_packing_trip');
        });

        Schema::create('trip_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->string('storage_key', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_attachments');
        Schema::dropIfExists('packing_items');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('itinerary_items');
        Schema::dropIfExists('itinerary_days');
        Schema::dropIfExists('trip_destinations');
        Schema::dropIfExists('trips');
    }
};
