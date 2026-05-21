<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTelegramNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [30, 60, 120];

    protected string $message;

    /**
     * Create a new job instance.
     */
    public function __construct(string $message)
    {
        $this->onQueue('alerts');
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $telegramService = new TelegramService();
        $telegramService->sendMessage($this->message);
    }
}
