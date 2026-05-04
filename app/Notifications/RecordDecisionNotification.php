<?php

namespace App\Notifications;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class RecordDecisionNotification extends Notification implements ShouldBroadcast
{
    public function __construct(
        public readonly Model $record,
        public readonly string $recordType,
        public readonly string $decision,
        public readonly ?string $rejectionReason = null
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastOn(mixed $notifiable = null): PrivateChannel
    {
        return new PrivateChannel('user.'.($notifiable?->id ?? 0));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $recordNo = $this->resolveRecordNo();
        $typeLabel = strtoupper($this->recordType);
        $decisionLabel = $this->decision === 'approved' ? 'approved' : 'rejected';

        $message = "Your {$typeLabel} {$recordNo} was {$decisionLabel}.";

        if ($this->decision === 'rejected' && $this->rejectionReason) {
            $message .= ' Reason: '.$this->rejectionReason;
        }

        return [
            'type' => $this->recordType,
            'record_no' => $recordNo,
            'record_id' => $this->record->id,
            'decision' => $this->decision,
            'rejection_reason' => $this->rejectionReason,
            'view_url' => $this->resolveViewUrl(),
            'message' => $message,
        ];
    }

    private function resolveRecordNo(): string
    {
        return match ($this->recordType) {
            'ofi' => $this->record->ofi_no ?: ('OFI #'.$this->record->id),
            'car' => $this->record->car_no ?: ('CAR #'.$this->record->id),
            'dcr' => $this->record->dcr_no ?: ('DCR #'.$this->record->id),
            default => '#'.$this->record->id,
        };
    }

    private function resolveViewUrl(): string
    {
        return match ($this->recordType) {
            'ofi' => '/ofi-form?record='.$this->record->id,
            'car' => '/car?record='.$this->record->id,
            'dcr' => '/dcr?record='.$this->record->id,
            default => '/',
        };
    }
}
