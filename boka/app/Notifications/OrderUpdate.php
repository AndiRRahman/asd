<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email dan notifikasi di situs untuk setiap perubahan pesanan (bagian 3.7), dalam bahasa pilihan Pembeli.
 */
class OrderUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public string $event, public ?string $note = null) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'id';
        $total = 'Rp '.number_format($this->order->grand_total, 0, ',', '.');

        return (new MailMessage)
            ->subject(__("notif.{$this->event}.subject", ['code' => $this->order->code], $locale))
            ->greeting(__('notif.greeting', ['name' => $notifiable->name], $locale))
            ->line(__("notif.{$this->event}.body", ['code' => $this->order->code, 'total' => $total], $locale))
            ->when($this->note, fn ($mail) => $mail->line($this->note))
            ->action(__('notif.view_order', [], $locale), url('/pesanan/'.$this->order->code))
            ->line(__('notif.warning', [], $locale));
    }

    public function toArray(object $notifiable): array
    {
        return ['order_code' => $this->order->code, 'event' => $this->event, 'note' => $this->note];
    }
}
