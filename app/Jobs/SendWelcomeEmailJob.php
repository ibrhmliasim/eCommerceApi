<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\WelcomeEmail;

use Illuminate\Support\Facades\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function handle(): void
    {
        Mail::to($this->user->email)->send(new WelcomeEmail($this->user));
    }
}
