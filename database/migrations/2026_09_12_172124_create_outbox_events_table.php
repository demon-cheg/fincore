<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('event_type', 100);

            $table->string('aggregate_type', 50);
            $table->unsignedBigInteger('aggregate_id');

            $table->json('payload');

            $table->unsignedInteger('attempts')->default(0);

            $table->timestamp('published_at')->nullable();

            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['published_at', 'created_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
