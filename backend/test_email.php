<?php

$user = \App\Modules\Accounts\Models\User::first();
if ($user) {
    $user->email = "wmutunga003@gmail.com";
    $user->name = "Test User";
    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\WelcomeEmail($user, "testpassword123"));
    echo "Email sent\n";
} else {
    echo "No user found\n";
}
