<?php

namespace App\Jobs;

use App\Models\Monitor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\SslCertificateExpiring;
use Carbon\Carbon;

class SslRenewalReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('alerts');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $expiringSoon = Monitor::whereNotNull('ssl_expires_at')
            ->where('ssl_expires_at', '>', Carbon::now())
            ->whereNotNull('ssl_alert_threshold_days')
            ->get()
            ->filter(function (Monitor $monitor) {
                $daysLeft = (int) floor(Carbon::now()->diffInDays($monitor->ssl_expires_at, false));

                return $daysLeft <= (int) $monitor->ssl_alert_threshold_days;
            });

        foreach ($expiringSoon as $monitor) {
            $this->sendSslReminder($monitor);
        }
    }

    private function sendSslReminder(Monitor $monitor): void
    {
        $user = $monitor->user;
        if (!$user) {
            return;
        }

        $daysLeft = Carbon::now()->diffInDays($monitor->ssl_expires_at, false);

        foreach ($monitor->alertEmailRecipients() as $email) {
            Mail::to($email)->send(new SslCertificateExpiring($monitor, $daysLeft));
        }

        // Send advanced alerts (Slack/Discord)
        if ($user->can('module.advanced_alerts')) {
            $channels = $user->alertChannels()->where('is_active', true)->get();
            foreach ($channels as $channel) {
                if ($channel->type === 'slack' || $channel->type === 'discord') {
                    try {
                        Http::timeout(3)->post($channel->endpoint, [
                            'content' => '⚠️ SSL Certificate Expiring Soon: ' . $monitor->name . ' (' . $monitor->url . ') - Expires in ' . $daysLeft . ' days (' . $monitor->ssl_expires_at->format('Y-m-d') . ')',
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to send SSL reminder to ' . $channel->type . ' for monitor ' . $monitor->id . ': ' . $e->getMessage());
                    }
                }
            }
        }

        Log::info('SSL renewal reminder processed for monitor: ' . $monitor->name . ', expires: ' . $monitor->ssl_expires_at);
    }
}
