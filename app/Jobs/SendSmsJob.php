<?php

namespace App\Jobs;

use App\Events\SmsRequest;
use App\Exceptions\SmsNotDeliveredException;
use App\Models\SmsLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSmsJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public array $backoff = [2, 5, 10];

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $smsLogId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = SmsLog::findOrFail($this->smsLogId);

        // Phone already reported status between retries — nothing to do.
        if ($log->status !== 'pending') {
            return;
        }

        // Broadcast the SMS request to the phone via Reverb.
        SmsRequest::dispatch(
            phone: $log->phone,
            message: $log->message,
            userId: $log->user_id,
            smsLogId: $log->id,
        );

        // Poll the database until the phone reports back or timeout.
        $status = $this->waitForStatus($this->smsLogId);

        if ($status === 'pending') {
            throw new SmsNotDeliveredException($this->smsLogId);
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(\Throwable $e): void
    {
        SmsLog::query()->whereKey($this->smsLogId)->update(['status' => 'failed']);
    }

    /**
     * Poll the database until the status is no longer 'pending' or the
     * timeout window elapses. Returns the resolved status, or 'pending'
     * on timeout.
     */
    private function waitForStatus(string $smsLogId): string
    {
        $deadline = microtime(true) + (float) config('sms.polling.timeout_seconds');
        $intervalMs = (int) config('sms.polling.poll_interval_ms');

        while (microtime(true) < $deadline) {
            $status = SmsLog::query()->whereKey($smsLogId)->value('status');

            if ($status !== 'pending') {
                return (string) $status;
            }

            usleep($intervalMs * 1000);
        }

        return 'pending';
    }
}
