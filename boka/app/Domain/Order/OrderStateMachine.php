<?php

namespace App\Domain\Order;

use App\Domain\BusinessRuleException;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderUpdate;

/**
 * Menjaga perpindahan status. Perpindahan di luar tabel transisi ditolak, termasuk dari Super Admin.
 */
class OrderStateMachine
{
    public function transition(Order $order, OrderStatus $to, ?User $actor = null, ?string $note = null, bool $notify = true): Order
    {
        $from = $order->status;

        if (! $from->canTransitionTo($to)) {
            throw new BusinessRuleException(
                "Pesanan berstatus {$from->value} tidak bisa diubah menjadi {$to->value}.",
                'invalid_transition',
                409,
            );
        }

        $order->status = $to;
        $order->save();

        $order->histories()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);

        if ($notify) {
            $order->user?->notify(new OrderUpdate($order, $to->value, $note));
        }

        return $order;
    }
}
