<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ErrorClassification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CriticalErrorAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ErrorClassification $error,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $severityLabel = match ($this->error->severity) {
            'critical' => '🔴 KRITISCH',
            'high'     => '🟠 HOCH',
            default    => '🟡 MITTEL',
        };

        $classificationLabel = match ($this->error->classification) {
            'real_bug'   => 'Echter Bug (Code-Fix nötig)',
            'user_error' => 'Nutzerfehler',
            default      => 'Unklar',
        };

        $subject = "[anyPIM] {$severityLabel}: " . ($this->error->ai_title ?? $this->error->exception_class);

        return (new MailMessage())
            ->subject($subject)
            ->greeting('Hallo ' . $notifiable->name . ',')
            ->line('Ein neuer Fehler wurde im anyPIM-System klassifiziert und erfordert Ihre Aufmerksamkeit.')
            ->line('')
            ->line('**' . ($this->error->ai_title ?? $this->error->exception_class) . '**')
            ->line($this->error->ai_description ?? $this->error->message)
            ->line('')
            ->line('| Feld | Wert |')
            ->line('|------|------|')
            ->line('| Schweregrad | ' . $this->error->severity . ' |')
            ->line('| Klassifikation | ' . $classificationLabel . ' |')
            ->line('| Häufigkeit | ' . $this->error->occurrence_count . 'x |')
            ->line('| Zuerst gesehen | ' . $this->error->first_seen_at?->format('d.m.Y H:i') . ' |')
            ->line('| Zuletzt gesehen | ' . $this->error->last_seen_at?->format('d.m.Y H:i') . ' |')
            ->when($this->error->ai_hint, fn ($mail) => $mail
                ->line('')
                ->line('**Hinweis:** ' . $this->error->ai_hint)
            )
            ->action('Im PIM öffnen', rtrim(config('app.url'), '/') . '/errors')
            ->line('Diese Nachricht wurde automatisch durch die KI-Fehlerklassifikation ausgelöst.');
    }
}
