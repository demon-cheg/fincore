<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            
            $table->id();

            $table->uuid('event_id')
                ->unique();

            $table->string('event_type', 100);

            $table->json('payload');

            $table->timestamp('received_at');

            $table->timestamps();

            $table->index([
                'event_type',
                'received_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};