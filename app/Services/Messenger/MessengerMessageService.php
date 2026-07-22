<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\MessengerMessageAttachment;
use App\Models\WatchlistItem;

/**
 * Zentrale Stelle zum Anlegen und Formatieren von Messenger-Nachrichten -- wird sowohl
 * von MessengerConversationController::store() (Fan-out an mehrere Empfänger) als auch
 * von MessengerMessageController::store() (Antwort in bestehender Konversation) genutzt,
 * damit die Anhang-Expansion ("Merkliste anhängen" -> Snapshot der aktuellen Watchlist)
 * an einer einzigen Stelle passiert.
 */
class MessengerMessageService
{
    public function send(
        MessengerConversation $conversation,
        string $senderId,
        ?string $body,
        ?string $replyToMessageId,
        array $attachments
    ): MessengerMessage {
        // Anhänge VOR dem Anlegen der Nachricht auflösen: eine 'merkliste'-Anhang-Anfrage
        // kann bei leerer Watchlist des Senders zu null tatsächlichen Anhang-Zeilen
        // expandieren -- die "Text oder Anhang"-Pflicht der Controller prüft nur die
        // rohe Anfrage, nicht das Expansionsergebnis, daher hier nochmal nach der
        // Auflösung geprüft, damit keine wortlos leere Nachricht entstehen kann.
        $resolvedAttachments = $this->resolveAttachments($attachments, $senderId);

        if (($body === null || $body === '') && empty($resolvedAttachments)) {
            abort(422, 'Nachricht benötigt Text oder Anhang.');
        }

        $message = MessengerMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $senderId,
            'body' => $body,
            'reply_to_message_id' => $replyToMessageId,
        ]);

        foreach ($resolvedAttachments as $resolved) {
            MessengerMessageAttachment::create([
                'message_id' => $message->id,
                'attachable_type' => 'product',
                'attachable_id' => $resolved['product_id'],
                'attached_via' => $resolved['attached_via'],
            ]);
        }

        $conversationUpdates = ['last_message_at' => $message->created_at];

        // Ein als erledigt quittierter Chat gilt bei neuer Aktivität wieder als offen --
        // sonst blieben neue Nachrichten in einem "erledigten" Chat unbemerkt liegen.
        if ($conversation->status === 'done') {
            $conversationUpdates['status'] = 'open';
            $conversationUpdates['resolved_at'] = null;
            $conversationUpdates['resolved_by'] = null;
        }

        $conversation->update($conversationUpdates);

        return $message->load(['sender:id,name', 'attachments.attachable', 'replyTo:id,body,sender_id']);
    }

    /**
     * @return array<int, array{product_id: string, attached_via: string}>
     */
    private function resolveAttachments(array $attachments, string $senderId): array
    {
        $resolved = [];

        foreach ($attachments as $attachment) {
            if ($attachment['type'] === 'product') {
                $resolved[] = ['product_id' => $attachment['product_id'], 'attached_via' => 'product'];
                continue;
            }

            // 'merkliste': Snapshot der aktuellen Watchlist des Senders zum Sendezeitpunkt --
            // WatchlistItem ist strikt nutzergebunden, daher kein Live-Verweis auf fremde
            // Zeilen, sondern je eine eigene Anhang-Zeile pro Produkt der Merkliste.
            $productIds = WatchlistItem::where('user_id', $senderId)->pluck('product_id');
            foreach ($productIds as $productId) {
                $resolved[] = ['product_id' => $productId, 'attached_via' => 'merkliste'];
            }
        }

        return $resolved;
    }

    public function format(MessengerMessage $message): array
    {
        $message->loadMissing(['sender:id,name', 'attachments.attachable', 'replyTo:id,body,sender_id']);

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender' => $message->sender ? [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
            ] : null,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'reply_to' => $message->replyTo ? [
                'id' => $message->replyTo->id,
                'body' => $message->replyTo->body,
                'sender_id' => $message->replyTo->sender_id,
            ] : null,
            'attachments' => $message->attachments->map(fn (MessengerMessageAttachment $a) => [
                'id' => $a->id,
                'attached_via' => $a->attached_via,
                'product' => $a->attachable ? [
                    'id' => $a->attachable->id,
                    'sku' => $a->attachable->sku,
                    'name' => $a->attachable->name,
                    'status' => $a->attachable->status,
                ] : null,
            ])->values(),
        ];
    }
}
