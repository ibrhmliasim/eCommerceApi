<?php
namespace App\Action;

use App\Models\User;
use App\Services\Auth\PendingRegistrationServiceStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Notifications\WelcomeNotification;

final class VerifyRegistrationAction
{
    public function __construct(
        private readonly PendingRegistrationServiceStore $pendingRegistrationServiceStore,
    ) {}

    public function execute(string $rawToken): User
    {
        $pending = $this->pendingRegistrationServiceStore->consume($rawToken);

        if (!$pending) {
            abort(410, 'Registration link has expired or already been used.');
        }

        try {
            $user = DB::transaction(function () use ($pending) {
                return User::create([
                    'email' => $pending->email,
                    'password' => $pending->password,
                    'first_name' => $pending->firstName,
                    'last_name' => $pending->lastName,
                    'phone' => $pending->phone,
                    'email_verified_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw $exception;
        }

        $this->pendingRegistrationServiceStore
            ->forgetEmailIndex($pending->email);

        $user->notify(new WelcomeNotification());

        return $user;
    }
}
