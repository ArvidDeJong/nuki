---
title: "Quick start"
nav_order: 3
description: "A complete example for darvis/nuki: a Laravel page that lists the NUKI smartlocks of an account and locks or unlocks one, with routes, controller and view."
---

# Quick start

This page builds one thing from start to finish: a page in your own application that lists the
smartlocks of your NUKI account, with a button to lock and a button to unlock each of them. It
assumes you finished [Installation](installation.md) and that the check there printed your locks.

You do not need the bundled `/nuki` pages for this; the example only uses the `Nuki` facade. A
facade is a class with static methods that Laravel forwards to an object it keeps for you; see the
[Laravel documentation on facades](https://laravel.com/docs/facades).

## 1. The routes

In `routes/web.php`:

```php
use App\Http\Controllers\DoorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/doors', [DoorController::class, 'index'])->name('doors.index');
    Route::post('/doors/{smartlockId}/lock', [DoorController::class, 'lock'])
        ->whereNumber('smartlockId')
        ->name('doors.lock');
    Route::post('/doors/{smartlockId}/unlock', [DoorController::class, 'unlock'])
        ->whereNumber('smartlockId')
        ->name('doors.unlock');
});
```

The `auth` middleware keeps the page for signed in users of your application. These buttons open
a real door, so never leave these routes public. Locking and unlocking are `POST` routes, so a
crawler or a prefetching browser cannot trigger them.

## 2. The controller

In `app/Http/Controllers/DoorController.php`:

```php
<?php

namespace App\Http\Controllers;

use Darvis\Nuki\Exceptions\NukiException;
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;

class DoorController extends Controller
{
    public function index(): View
    {
        return view('doors', [
            'locks' => Nuki::smartlocks()->all(),
        ]);
    }

    public function lock(int $smartlockId): RedirectResponse
    {
        return $this->send(fn () => Nuki::smartlocks()->lock($smartlockId), 'NUKI accepted the lock command.');
    }

    public function unlock(int $smartlockId): RedirectResponse
    {
        return $this->send(fn () => Nuki::smartlocks()->unlock($smartlockId), 'NUKI accepted the unlock command.');
    }

    private function send(callable $command, string $message): RedirectResponse
    {
        try {
            $command();
        } catch (NukiException|ConnectionException $e) {
            report($e);

            return back()->with('error', 'NUKI did not accept the command: '.$e->getMessage());
        }

        return back()->with('status', $message);
    }
}
```

`Nuki::smartlocks()->all()` asks the NUKI Web API for the locks of the account and returns a
collection of `SmartLock` objects. `lock()` and `unlock()` return nothing: when they do not throw,
NUKI accepted the command. That is not the same as "the bolt has moved", which takes a few seconds
and can still fail at the door.

Two kinds of failure are caught. `NukiException` covers a missing token and every error answer
from NUKI. `ConnectionException` is Laravel's own exception for "could not reach the server at
all"; it is not a `NukiException`, so it needs its own place in the `catch`.

## 3. The view

In `resources/views/doors.blade.php`:

{% raw %}
```blade
<h1>Doors</h1>

@if (session('status'))
    <p>{{ session('status') }}</p>
@endif

@if (session('error'))
    <p>{{ session('error') }}</p>
@endif

<ul>
    @foreach ($locks as $lock)
        <li>
            {{ $lock->name }}: {{ $lock->stateName ?? 'unknown' }}
            @if ($lock->batteryCritical)
                (battery almost empty)
            @endif

            <form method="POST" action="{{ route('doors.lock', $lock->smartlockId) }}">
                @csrf
                <button type="submit">Lock</button>
            </form>

            <form method="POST" action="{{ route('doors.unlock', $lock->smartlockId) }}">
                @csrf
                <button type="submit">Unlock</button>
            </form>
        </li>
    @endforeach
</ul>
```
{% endraw %}

`stateName` is a word such as `locked` or `unlocked`, and `null` when NUKI sends no state for the
lock. `batteryCritical` is `true` when the lock reports an almost empty battery.

## 4. Try it

Sign in to your application and open `/doors`. You see your locks with their state. Press
**Unlock**: the page reloads with "NUKI accepted the unlock command." and the door opens a moment
later. Reload the page to read the new state.

When NUKI refuses, the page shows the message of the exception, for example
`NUKI API POST /smartlock/5/action returned HTTP 404: ...`. [Troubleshooting](troubleshooting.md)
explains each message.

## What to build next

- Know that the door really opened: receive the `DEVICE_STATUS` webhook, see [Webhooks](webhooks.md).
- Show who opened a door and when: `Nuki::logs()->forSmartlock($smartlockId, ['limit' => 50])`, see
  the [API reference](api-reference.md#smartlocklogs).
- Hand out a keypad code: `Nuki::auths()->create()`, see the
  [API reference](api-reference.md#smartlockauths).
- Write a test for this controller without calling NUKI: see [Testing](testing.md).
