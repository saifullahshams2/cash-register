<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Login extends Component
{
    /**
     * Fixed bcrypt hash verified on every failed login so that response time
     * does not reveal whether the submitted username exists.
     */
    private const DUMMY_HASH = '$2y$12$hKsVzy6r9J8aUhJ3oNCOKu6QU8T8QpsW/sj1pv.tGmWzFmg2tbShe';

    #[Rule('required|string')]
    public string $username = '';

    #[Rule('required')]
    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate();

        $throttleKey = Str::transliterate(Str::lower($this->username).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'username' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]),
            ]);
        }

        $credentials = ['username' => $this->username, 'password' => $this->password];

        if (! Auth::attempt($credentials, $this->remember)) {
            if (! Auth::attempt(['email' => $this->username, 'password' => $this->password], $this->remember)) {
                // Equalize response time for unknown usernames (timing enumeration).
                Hash::check($this->password, self::DUMMY_HASH);

                RateLimiter::hit($throttleKey, 60);

                throw ValidationException::withMessages([
                    'username' => __('auth.failed'),
                ]);
            }
        }

        RateLimiter::clear($throttleKey);

        session()->regenerate();

        if (Auth::user()->isAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('pos'));
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('components.layouts.app');
    }
}
