<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateInvalidDemoEvent extends Command
{
    protected $signature =
        'demo:invalid-event';

    protected $description =
        'Create a local event with unsupported schema version to test retries and DLQ';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error(
                'This command is available only locally.'
            );

            return self::FAILURE;
        }

        $event = new OutboxEvent();

        $event->id =
            (string) Str::uuid();

        $event->event_type =
            'transfer.completed';

        /*
         * Consumer currently supports v1 only.
         * This is an intentional test fixture,
         * not a production bug.
         */
        $event->schema_version = 999;

        $event->occurred_at = now();

        $event->aggregate_type =
            'transfer';

        $event->aggregate_id = 0;

        $event->payload = [
            'test' => true,
        ];

        $event->save();

        $this->info(
            "Invalid demo event created: {$event->id}"
        );

        return self::SUCCESS;
    }
}