<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ErrorClassification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForwardedErrorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ErrorClassification $error,
        private readonly User $forwardedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->error->ai_title ?? $this->error->exception_class;

        $severityLabel = match ($this->error->severity) {
            'critical' => '🔴 Kritisch',
            'high'     => '🟠 Hoch',
            'medium'   => '🟡 Mittel',
            'low'      => '🟢 Niedrig',
            default    => '',
        };

        return (new MailMessage())
            ->subject("[anyPIM] Fehler weitergeleitet: {$title}")
            ->greeting('Hallo,')
            ->line("**{$this->forwardedBy->name}** hat einen Fehler zur Bearbeitung weitergeleitet.")
            ->line('')
            ->line("**{$severityLabel} {$title}**")
            ->when(
                $this->error->ai_description,
                fn ($mail) => $mail->line($this->error->ai_description)
            )
            ->line('')
            ->line('| Feld | Wert |')
            ->line('|------|------|')
            ->line('| Exception | ' . $this->error->exception_class . ' |')
            ->line('| Datei | ' . $this->error->file . ':' . $this->error->line . ' |')
            ->line('| Häufigkeit | ' . $this->error->occurrence_count . 'x |')
            ->line('| Zuletzt gesehen | ' . ($this->error->last_seen_at?->format('d.m.Y H:i') ?? '–') . ' |')
            ->when(
                $this->error->ai_hint,
                fn ($mail) => $mail->line('')->line('**Hinweis:** ' . $this->error->ai_hint)
            )
            ->when(
                $this->error->notes,
                fn ($mail) => $mail->line('')->line('**Notiz des Admins:** ' . $this->error->notes)
            )
            ->action('Im PIM öffnen', rtrim(config('app.url'), '/') . '/errors')
            ->line('Diese Nachricht wurde durch "An Entwicklung weiterleiten" ausgelöst.');
    }
}
