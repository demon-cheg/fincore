<?php

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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('initiated_by_user_id')
                ->constrained('users');

            $table->foreignId('source_account_id')
                ->constrained('accounts');

            $table->foreignId('destination_account_id')
                ->constrained('accounts');

            $table->unsignedBigInteger('amount_minor');

            $table->char('currency', 3);

            $table->string('status', 20)
                ->default('completed');

            $table->uuid('idempotency_key');

            $table->timestamps();

            $table->unique([
                'initiated_by_user_id',
                'idempotency_key',
            ]);

            $table->index([
                'source_account_id',
                'created_at',
            ]);

            $table->index([
                'destination_account_id',
                'created_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
