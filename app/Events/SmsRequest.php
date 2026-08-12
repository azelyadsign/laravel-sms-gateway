<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SmsRequest implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string[]  $deviceTypes
     */
    public function __construct(
        public string $phone,
        public string $message,
        public string $userId,
        public array $deviceTypes,
        public ?string $smsLogId = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (string $deviceType) => new PrivateChannel("sms.{$deviceType}.{$this->userId}"),
            $this->deviceTypes,
        );
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'sms.request';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'phone' => $this->phone,
            'message' => $this->message,
            'sent_at' => now()->toIso8601String(),
            'sms_log_id' => $this->smsLogId,
        ];
    }
}
