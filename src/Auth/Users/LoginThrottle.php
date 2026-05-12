<?php

declare(strict_types=1);

namespace Darvis\Nuki\Auth\Users;

use Illuminate\Cache\RateLimiter;

final class LoginThrottle
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function checkSend(string $email, ?string $ip): bool
    {
        $max = (int) config('nuki.auth_users.otp.rate_limit.max_per_window', 5);
        $window = (int) config('nuki.auth_users.otp.rate_limit.window_minutes', 15) * 60;
        $key = $this->sendKey($email, $ip);

        if ($this->limiter->tooManyAttempts($key, $max)) {
            return false;
        }

        $this->limiter->hit($key, $window);

        return true;
    }

    public function sendAvailableIn(string $email, ?string $ip): int
    {
        return $this->limiter->availableIn($this->sendKey($email, $ip));
    }

    public function checkAttempt(int $userId): bool
    {
        $max = (int) config('nuki.auth_users.otp.rate_limit.max_per_window', 5);
        $window = (int) config('nuki.auth_users.otp.rate_limit.window_minutes', 15) * 60;
        $key = $this->attemptKey($userId);

        if ($this->limiter->tooManyAttempts($key, $max)) {
            return false;
        }

        $this->limiter->hit($key, $window);

        return true;
    }

    public function clearAttempts(int $userId): void
    {
        $this->limiter->clear($this->attemptKey($userId));
    }

    private function sendKey(string $email, ?string $ip): string
    {
        return 'nuki:otp:send:'.sha1(strtolower($email).'|'.($ip ?? ''));
    }

    private function attemptKey(int $userId): string
    {
        return 'nuki:otp:attempt:'.$userId;
    }
}
