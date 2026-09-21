---
title: "Home"
nav_order: 1
description: "darvis/nuki is a Laravel package for the NUKI Web API: list, lock and unlock smartlocks, read logs, manage keypad codes, receive webhooks, with a Livewire UI."
permalink: /
---

# NUKI for Laravel

`darvis/nuki` lets a Laravel application talk to the [NUKI Web API](https://developer.nuki.io/):
list the smartlocks of a NUKI account, lock and unlock them, read the activity log and manage
keypad codes. It also ships a webhook receiver, a set of Livewire pages and, optionally, its own
users with permissions per lock.

It is for developers who build access control into a Laravel application: an office, a rental
home, a workshop, or a platform with a NUKI account per customer.

## What the package does not do

- It does not talk to a lock or a bridge directly. Every call goes to the NUKI Web API over the
  internet, so the lock has to be available in NUKI Web.
- It does not tell you that the bolt moved. `lock()` and `unlock()` return nothing; a call that
  did not throw means NUKI accepted the command. Read the state again or listen for a webhook.
- It registers no OAuth callback route. With OAuth your application builds the route that NUKI
  redirects to. See [API authentication](nuki-api-authentication.md#the-callback-route-is-yours-to-build).
- It writes nothing about its own users and permissions back to the NUKI account.

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13
- Livewire 3.5 or higher, or Livewire 4
- Flux 2 (the free edition is enough)
- A NUKI account with access to NUKI Web, and an API token or an OAuth application for it

Livewire and Flux are Composer requirements of the package, so they are installed with it, also
when you only use the facade.

## Install

```bash
composer require darvis/nuki
php artisan vendor:publish --tag=nuki-config
php artisan migrate
```

[Installation](installation.md) has the full steps and a way to check that it works.

## The pages

### Start here

- [Installation](installation.md): the steps from `composer require` to a first answer from NUKI,
  and how to check that it works.
- [Quick start](quickstart.md): one complete example, a page that lists the locks and locks or
  unlocks one.
- [Configuration](configuration.md): every `config/nuki.php` key and `NUKI_*` variable with its
  default.

### The NUKI Web API

- [API authentication](nuki-api-authentication.md): a personal API token or OAuth 2.0, one
  account or many, and the callback route you build yourself.
- [API reference](api-reference.md): every public method on the resources, the objects they
  return, the console commands and the exceptions.

### The bundled UI and its users

- [UI and localization](ui-and-localization.md): who may open the pages, the components, the
  layout and the four languages.
- [Users and permissions](users-and-permissions.md): the optional `darvis-nuki` guard, main and
  sub users, and permissions per lock.
- [Auth routes](auth-routes.md): every route the package registers and where a guest is sent.

### Running it

- [Webhooks](webhooks.md): the receiver, the signature check, duplicates and the event you listen
  for.
- [Demo mode](demo-mode.md): the whole UI on made up data, without a NUKI account.
- [Testing](testing.md): test your own code without calling NUKI.
- [Troubleshooting](troubleshooting.md): symptom, cause and fix, with the literal error messages.
- [FAQ](faq.md): the short answers.
