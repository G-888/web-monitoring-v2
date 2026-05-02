<?php

namespace App\Providers;

use App\Models\EmailSetting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

class EmailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Check if we have active email settings in database
        $emailSetting = EmailSetting::getActive();

        if ($emailSetting) {
            // Override mail configuration with database settings
            Config::set('mail.default', $emailSetting->mailer);
            Config::set('mail.from.address', $emailSetting->from_address);
            Config::set('mail.from.name', $emailSetting->from_name);

            if ($emailSetting->mailer === 'smtp') {
                Config::set('mail.mailers.smtp.host', $emailSetting->host);
                Config::set('mail.mailers.smtp.port', $emailSetting->port);
                Config::set('mail.mailers.smtp.encryption', $emailSetting->encryption);
                Config::set('mail.mailers.smtp.username', $emailSetting->username);
                Config::set('mail.mailers.smtp.password', $emailSetting->password);
            }
        }
    }
}
