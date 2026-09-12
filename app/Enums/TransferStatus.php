<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
}