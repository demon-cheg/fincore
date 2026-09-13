<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('schema_version')
                ->default(1)
                ->after('event_type');

            $table->timestamp('occurred_at')
                ->nullable()
                ->after('schema_version');
        });
    }

    public function down(): void
    {
        Schema::table('outbox_events', function (Blueprint $table) {
            $table->dropColumn([
                'schema_version',
                'occurred_at',
            ]);
        });
    }
};