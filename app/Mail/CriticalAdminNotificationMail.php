<?php

namespace App\Mail;

use App\Models\AdminNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CriticalAdminNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdminNotification $notification
    ) {
    }

public function build(): self
{
    return $this
        ->subject('[Critical Alert] ' . $this->notification->title)
        ->view('emails.critical-admin-notification', [
            'notification' => $this->notification,
        ]);
}
}
