<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-Mail-Alarm bei einem erkannten Sicherheitsvorfall.
 */
class SecurityAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(
        private readonly array $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $severityLabel = match ($this->event['severity'] ?? 'low') {
            'high' => '🔴 HOCH',
            'medium' => '🟠 MITTEL',
            default => '🟡 NIEDRIG',
        };

        $type = (string) ($this->event['type'] ?? 'unknown');
        $blocked = ! empty($this->event['blocked']);

        $mail = (new MailMessage)
            ->subject("[anyPIM Security] {$severityLabel}: {$type}")
            ->greeting('Sicherheitswarnung')
            ->line('Der SecurityMonitor hat ein verdaechtiges Muster erkannt.')
            ->line('')
            ->line('| Feld | Wert |')
            ->line('|------|------|')
            ->line('| Typ | '.$type.' |')
            ->line('| Schweregrad | '.($this->event['severity'] ?? '-').' |')
            ->line('| Aktion | '.($blocked ? 'BLOCKIERT' : 'nur erkannt').' |')
            ->line('| IP | '.($this->event['ip_address'] ?? '-').' |')
            ->line('| Land | '.($this->event['country'] ?? '-').' |')
            ->line('| Pfad | '.($this->event['method'] ?? '').' '.($this->event['path'] ?? '-').' |')
            ->line('| User-Agent | '.($this->event['user_agent'] ?? '-').' |');

        if (! empty($this->event['metadata'])) {
            $mail->line('| Details | '.json_encode($this->event['metadata'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).' |');
        }

        return $mail->line('')->line('Bitte pruefen Sie die Security-Events im PIM.');
    }
}
