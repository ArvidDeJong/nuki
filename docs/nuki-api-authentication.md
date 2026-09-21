---
title: "API authentication"
nav_order: 5
description: "How darvis/nuki signs in to the NUKI Web API: a personal API token or OAuth 2.0, one account or many with Nuki::as(), and the OAuth callback route you build."
---

# NUKI API authentication

This page is about authenticating **the package against the NUKI Web API**. For
the package's own end-user login system, see
[Users and permissions](users-and-permissions.md).

The package supports two strategies, selected by the `NUKI_AUTH` env var (the
`nuki.auth` config key):

| Mode | When to use |
|---|---|
| `token` | One account, or several accounts whose tokens you manage yourself. You create a personal API token per NUKI account on [web.nuki.io](https://web.nuki.io/). With the `database` resolver this mode handles as many accounts as you have rows. |
| `oauth` | The owner of each NUKI account gives your application access through NUKI's OAuth 2.0 authorization code flow, and the package stores an access and a refresh token per account. |

Both modes can serve more than one NUKI account; [`Nuki::as()`](#multi-account-scoping-with-nukias)
picks the account for a call.

Both modes go through the same surface: [Contracts\Authenticator](https://github.com/ArvidDeJong/nuki/blob/main/src/Contracts/Authenticator.php),
which gets a fresh `PendingRequest` and an `accountKey` and attaches whatever
header is appropriate. Every outbound call from a [resource](api-reference.md)
goes through [HttpClient](https://github.com/ArvidDeJong/nuki/blob/main/src/Http/HttpClient.php), which calls the
authenticator before sending.

## Token mode (`NUKI_AUTH=token`)

[TokenAuthenticator](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/TokenAuthenticator.php) attaches
`Authorization: Bearer …` on every request. The token comes from an
[ApiTokenResolver](https://github.com/ArvidDeJong/nuki/blob/main/src/Contracts/ApiTokenResolver.php) keyed by
`accountKey`. Two resolvers ship:

### Config resolver (`NUKI_TOKEN_RESOLVER=config`)

[ConfigApiTokenResolver](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/ConfigApiTokenResolver.php) returns
`NUKI_API_TOKEN` for the account key `default` and nothing for any other key. Use this when you
manage one NUKI account: no database table is read. `Nuki::as('another-key')` throws an
`AuthenticationException` in this mode.

```dotenv
NUKI_AUTH=token
NUKI_TOKEN_RESOLVER=config
NUKI_API_TOKEN=abc123def456
```

### Database resolver (`NUKI_TOKEN_RESOLVER=database`)

[DatabaseApiTokenResolver](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/DatabaseApiTokenResolver.php) looks the
token up on `nuki_accounts.api_token` by `account_key`, among the rows with `is_active` true.
This is the default resolver, and it runs a query on every call, so `php artisan migrate` has to
have run. The column is encrypted
via Eloquent's `encrypted` cast (see
[NukiAccount](https://github.com/ArvidDeJong/nuki/blob/main/src/Models/NukiAccount.php)), so your `APP_KEY` is required to
decrypt it.

It falls back to `NUKI_API_TOKEN` for the literal `default` account key when no row matches.

Add accounts on the bundled `/nuki/accounts` page or in code. Write the token through the model,
as below: a query builder `insert()` or `update()` skips the encryption, and the row then fails to
decrypt.

```php
use Darvis\Nuki\Models\NukiAccount;

NukiAccount::create([
    'account_key' => (string) $tenant->id,
    'name' => $tenant->name,
    'api_token' => $personalToken,
    'is_active' => true,
]);
```

Then scope subsequent calls to that key:

```php
Nuki::as((string) $tenant->id)->smartlocks()->all();
```

See [`Nuki::as()`](#multi-account-scoping-with-nukias) below.

## OAuth mode (`NUKI_AUTH=oauth`)

[OAuthAuthenticator](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/OAuthAuthenticator.php) reads a stored
[NukiToken](https://github.com/ArvidDeJong/nuki/blob/main/src/DTOs/NukiToken.php) for the given `accountKey` from a
[TokenStore](https://github.com/ArvidDeJong/nuki/blob/main/src/Contracts/TokenStore.php), refreshes it when expired (with
a **30-second leeway** — see [NukiToken::isExpired()](https://github.com/ArvidDeJong/nuki/blob/main/src/DTOs/NukiToken.php))
and attaches `Authorization: Bearer …`. If no token is stored and you call a
resource, an `AuthenticationException` is thrown: `No NUKI OAuth token stored for account [<key>].
Run nuki:oauth-authorize or complete the OAuth flow first.` When NUKI refuses a refresh, the
stored token is deleted and the exception says `Failed to refresh NUKI OAuth token: HTTP <status>
<body>`; the account then has to be connected again.

### Application registration

1. Register an OAuth application with NUKI; the [NUKI developer site](https://developer.nuki.io/)
   explains how. Give it the redirect URL of [your own callback route](#the-callback-route-is-yours-to-build).
2. Set the credentials and the same redirect URL:

```dotenv
NUKI_AUTH=oauth
NUKI_OAUTH_CLIENT_ID=...
NUKI_OAUTH_CLIENT_SECRET=...
NUKI_OAUTH_REDIRECT_URL=https://yourapp.example/nuki-connect/callback
NUKI_TOKEN_STORE=database
```

`NUKI_TOKEN_STORE` defaults to `cache`. A token in the cache is gone after
`php artisan cache:clear`, and an entry expires one day after the access token does. Use
`database` for a connection that has to last.

Default scopes (override in `config/nuki.php`):
`account`, `notification`, `smartlock`, `smartlock.readOnly`,
`smartlock.action`, `smartlock.auth`.

### CLI flow — `php artisan nuki:oauth-authorize`

[NukiOAuthAuthorizeCommand](https://github.com/ArvidDeJong/nuki/blob/main/src/Console/Commands/NukiOAuthAuthorizeCommand.php)
walks you through the authorization-code dance from the terminal:

```bash
php artisan nuki:oauth-authorize --account=tenant-42
```

The command prints the authorization URL and a random `state`. Open the URL in a browser and
give consent. NUKI then redirects the browser to your `NUKI_OAUTH_REDIRECT_URL` with `?code=…` in
the address; that page does not have to exist for this flow, the code is in the address bar. Paste
the code into the terminal and the token is stored under the account key `tenant-42`. The command
cannot see the redirect, so comparing the `state` in the address bar with the printed one is up to
you.

`--code=…` skips the question and exchanges a code you already have.

### The callback route is yours to build

The package builds the authorization URL and exchanges the code for a token. It registers **no
callback route** and it never checks the `state` parameter. In a web flow your application owns
both. The bundled `/nuki/oauth/connect` page only shows the stored token and generates an
authorization URL; the redirect from NUKI still lands on your route.

`state` is a random value you send along and get back, to make sure the redirect belongs to the
visitor who started the flow. Without the check, someone else's authorization code can be attached
to your user. In `routes/web.php`:

```php
use Darvis\Nuki\Facades\Nuki;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/nuki-connect', function () {
    session(['nuki_oauth_state' => $state = Str::random(40)]);

    return redirect()->away(Nuki::oauth()->authorizationUrl(state: $state));
})->middleware('auth');

// This URL is NUKI_OAUTH_REDIRECT_URL, and the one you registered with NUKI.
Route::get('/nuki-connect/callback', function (Request $request) {
    abort_unless(
        hash_equals((string) session()->pull('nuki_oauth_state'), (string) $request->query('state')),
        403,
    );

    Nuki::oauth()->exchangeCode((string) $request->query('code'), (string) $request->user()->id);

    return redirect('/');
})->middleware('auth');
```

The first route remembers a random `state` in the session and sends the visitor to NUKI. The
second one refuses a redirect whose `state` does not match, exchanges the code and stores the
token under the id of the signed in user as account key. From then on:

```php
$locks = Nuki::as((string) $user->id)->smartlocks()->all();

Nuki::oauth()->token((string) $user->id);    // the stored NukiToken, or null
Nuki::oauth()->refresh((string) $user->id);  // force a refresh; normally automatic
Nuki::oauth()->revoke((string) $user->id);   // forget the token locally
```

`exchangeCode()` throws an `AuthenticationException` with
`NUKI OAuth code exchange failed: HTTP <status> <body>` when NUKI refuses the code. `revoke()`
only deletes the stored token; it does not call NUKI.

### Token storage

Selected by `nuki.oauth.token_store`:

| Driver | Where tokens live | When to pick it |
|---|---|---|
| `cache` (default) | Laravel cache, prefix `nuki:oauth:`, in the store from `NUKI_TOKEN_CACHE_STORE` or your default store. Kept until one day after the access token expires. | Trying it out. Clearing the cache disconnects every account. |
| `database` | `nuki_oauth_tokens` table — one row per `account_key` with encrypted `access_token` / `refresh_token` columns | Anything that has to last. Needs your `APP_KEY` to stay the same. |

The implementations are
[CacheTokenStore](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/CacheTokenStore.php) and
[DatabaseTokenStore](https://github.com/ArvidDeJong/nuki/blob/main/src/Auth/DatabaseTokenStore.php). Both implement
[TokenStore](https://github.com/ArvidDeJong/nuki/blob/main/src/Contracts/TokenStore.php) (`get`, `put`, `forget`).

## Multi-account scoping with `Nuki::as()`

```php
Nuki::as('tenant-42')->smartlocks()->all();
Nuki::as('tenant-99')->smartlocks()->lock($id);
```

`Nuki::as($accountKey)` returns a **clone** of the manager scoped to that key.
The key flows through every resource into `HttpClient`, which hands it to the
authenticator — so the right token is picked per request, even when calls for
two accounts interleave.

The `$accountKey` is an **opaque string**. The package never touches your
`User` model; you decide what shape the key takes. Common patterns:

```php
// Per Laravel user
Nuki::as((string) $user->id);

// Per tenant / organisation
Nuki::as($tenant->uuid);

// Per package-managed NukiUser
Nuki::as((string) ($nukiUser->parent_id ?? $nukiUser->id));
```

Without `as()`, the key defaults to the literal string `'default'`.

`Nuki::currentAccount()` returns the key the current instance is scoped to —
useful when you pass the manager into a helper.

## Errors

| Situation | Exception |
|---|---|
| No token for the account, a failed or impossible OAuth refresh, incomplete OAuth settings | [AuthenticationException](https://github.com/ArvidDeJong/nuki/blob/main/src/Exceptions/AuthenticationException.php) |
| NUKI answered with a 4xx or 5xx | [ApiException](https://github.com/ArvidDeJong/nuki/blob/main/src/Exceptions/ApiException.php), with `->status`, `->body` (the raw answer as a string, not decoded) and `->endpoint` |
| The server could not be reached, also after the retries | `Illuminate\Http\Client\ConnectionException` |

The first two extend [NukiException](https://github.com/ArvidDeJong/nuki/blob/main/src/Exceptions/NukiException.php).
`ConnectionException` is Laravel's and does **not**, so catch it next to `NukiException` when a
timeout must not break the page.

A connection error, an HTTP 429 and a 5xx are tried again before you see an exception:
`http.retries` attempts in total (default 3, the first one included) with a fixed pause of
`http.retry_sleep` milliseconds (default 200) in between. [Troubleshooting](troubleshooting.md)
lists the literal messages.
