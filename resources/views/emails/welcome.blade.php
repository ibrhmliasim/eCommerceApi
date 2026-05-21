<h1>Welcome, {{ $user->first_name }}</h1>

<p>Thanks for registering.</p>

<a href="{{ $verificationUrl }}" style="padding:10px 20px; background:#000; color:#fff;">
    Verify Email
</a>