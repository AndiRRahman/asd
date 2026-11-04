<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email untuk pembeli yang menekan "Kabari saya" saat produk habis (bagian 4.3).
 */
class BackInStock extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Product $product) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'id';
        $name = $this->product->getTranslation('name', $locale);

        return (new MailMessage)
            ->subject(__('notif.restock.subject', ['name' => $name], $locale))
            ->line(__('notif.restock.body', ['name' => $name], $locale))
            ->action(__('notif.view_product', [], $locale), url('/produk/'.$this->product->slug));
    }

    public function toArray(object $notifiable): array
    {
        return ['event' => 'restock', 'product' => $this->product->slug];
    }
}
