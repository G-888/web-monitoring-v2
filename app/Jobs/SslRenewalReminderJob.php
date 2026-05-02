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

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $expiringSoon = Monitor::whereNotNull('ssl_expires_at')
            ->where('ssl_expires_at', '<=', Carbon::now()->addDays(60))
            ->where('ssl_expires_at', '>', Carbon::now())
            ->get();

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

        // Send email alerts
        $emails = is_array($monitor->alert_emails) && count($monitor->alert_emails) > 0
            ? $monitor->alert_emails
            : ['suhailmajemi@gmail.com']; // TODO: Remove hardcoded

        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($email)->send(new SslCertificateExpiring($monitor, $daysLeft));
            }
        }

        // Send advanced alerts (Slack/Discord)
        if ($user->hasDirectPermission('module.advanced_alerts')) {
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

        Log::info('SSL renewal reminder sent for monitor: ' . $monitor->name . ', expires: ' . $monitor->ssl_expires_at);
    }
}
