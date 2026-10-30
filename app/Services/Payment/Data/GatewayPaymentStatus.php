<?php

declare(strict_types=1);

namespace App\Services\Payment\Data;

enum GatewayPaymentStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Pending = 'pending'; // Not settled yet, or a state we do not recognise: never credited
}
