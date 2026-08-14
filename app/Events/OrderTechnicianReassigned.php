<?php

namespace App\Events;

use App\Models\Order;
use App\Models\Technician;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The order keeps its status when the technician is swapped, so this is a
 * separate event — reporting it as a status change would put a status the
 * order is not in into the notification payload.
 */
class OrderTechnicianReassigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Technician $previous,
        public readonly Technician $current,
    ) {
    }
}
