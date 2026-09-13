<?php

namespace App\Console\Commands;

use App\Infrastructure\Messaging\RabbitMqTopology;
use Illuminate\Console\Command;

class SetupRabbitMqTopology extends Command
{
    protected $signature = 'rabbitmq:setup';

    protected $description =
        'Declare FinCore RabbitMQ exchanges and queues';

    public function handle(
        RabbitMqTopology $topology,
    ): int {
        $topology->declare();

        $this->info(
            'RabbitMQ topology declared successfully.'
        );

        return self::SUCCESS;
    }
}