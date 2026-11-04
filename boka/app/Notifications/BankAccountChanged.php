<?php

namespace App\Notifications;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Semua Super Admin menerima email setiap ada perubahan rekening tujuan (11.1 T4).
 */
class BankAccountChanged extends Notification
{
    use Queueable;

    public function __construct(public BankAccount $bank, public User $actor, public string $action) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[Boka] Rekening tujuan {$this->action}")
            ->line("{$this->actor->name} {$this->action} rekening {$this->bank->bank_name} {$this->bank->account_number} a.n. {$this->bank->account_holder}.")
            ->line('Rekening baru tampil di checkout mulai '.$this->bank->active_from->timezone('Asia/Makassar')->format('d M Y H:i').' WITA.')
            ->line('Jika perubahan ini bukan dari tim Anda, segera nonaktifkan rekening tersebut dan ganti password akun yang terlibat.');
    }

    public function toArray(object $notifiable): array
    {
        return ['bank_id' => $this->bank->id, 'action' => $this->action, 'actor' => $this->actor->name];
    }
}
