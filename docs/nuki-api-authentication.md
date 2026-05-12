# NUKI API authentication

[← Documentation index](README.md)

This page is about authenticating **the package against the NUKI Web API**. For
the package's own end-user login system, see
[Users and permissions](users-and-permissions.md).

The package supports two strategies, selected by the `NUKI_AUTH` env var (the
`nuki.auth` config key):

| Mode | When to use |
|---|---|
| `token` | Single account, or a fixed list of accounts you manage internally. You generate one personal API token per customer on [web.nuki.io](https://web.nuki.io/). Simplest setup. |
| `oauth` | Multi-account SaaS. Customers consent through NUKI's OAuth Authorization Code flow and your app stores access + refresh tokens per account. |

Both modes go through the same surface: [Contracts\Authenticator](../src/Contracts/Authenticator.php),
which gets a fresh `PendingRequest` and an `accountKey` and attaches whatever
header is appropriate. Every outbound call from a [resource](api-reference.md)
goes through [HttpClient](../src/Http/HttpClient.php), which calls the
authenticator before sending.

## Token mode (`NUKI_AUTH=token`)

[TokenAuthenticator](../src/Auth/TokenAuthenticator.php) attaches
`Authorization: Bearer …` on every request. The token comes from an
[ApiTokenResolver](../src/Contracts/ApiTokenResolver.php) keyed by
`accountKey`. Two resolvers ship:

### Config resolver (`NUKI_TOKEN_RESOLVER=config`)

[ConfigApiTokenResolver](../src/Auth/ConfigApiTokenResolver.php) returns
`config('nuki.token')` for **every** account key. Use this when you only
manage one NUKI account — there is no database table, nothing to seed, nothing
to encrypt.

```dotenv
NUKI_AUTH=token
NUKI_TOKEN_RESOLVER=config
NUKI_API_TOKEN=abc123def456
```

### Database resolver (`NUKI_TOKEN_RESOLVER=database`)

[DatabaseApiTokenResolver](../src/Auth/DatabaseApiTokenResolver.php) looks the
token up on `nuki_accounts.api_token` by `account_key`. The column is encrypted
via Eloquent's `encrypted` cast (see
[NukiAccount](../src/Models/NukiAccount.php)), so your `APP_KEY` is required to
decrypt it.

It falls back to `config('nuki.token')` for the literal `default` account key
when no row matches — convenient for development.

Add accounts via the bundled `/nuki/accounts` UI or directly:

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

[OAuthAuthenticator](../src/Auth/OAuthAuthenticator.php) reads a stored
[NukiToken](../src/DTOs/NukiToken.php) for the given `accountKey` from a
[TokenStore](../src/Contracts/TokenStore.php), refreshes it when expired (with
a **30-second leeway** — see [NukiToken::isExpired()](../src/DTOs/NukiToken.php))
and attaches `Authorization: Bearer …`. If no token is stored and you call a
resource, `AuthenticationException` is thrown with a helpful message pointing
at `nuki:oauth-authorize`.

### Application registration

1. Sign up on the [NUKI Developer Portal](https://developer.nuki.io/).
2. Register an OAuth application; set the redirect URL to the public
   `oauth.redirect_url` for your app.
3. Set the credentials:

```dotenv
NUKI_AUTH=oauth
NUKI_OAUTH_CLIENT_ID=...
NUKI_OAUTH_CLIENT_SECRET=...
NUKI_OAUTH_REDIRECT_URL=https://yourapp.example/nuki/oauth/callback
NUKI_TOKEN_STORE=database   # or "cache" for single-account
```

Default scopes (override in `config/nuki.php`):
`account`, `notification`, `smartlock`, `smartlock.readOnly`,
`smartlock.action`, `smartlock.auth`.

### CLI flow — `php artisan nuki:oauth-authorize`

[NukiOAuthAuthorizeCommand](../src/Console/Commands/NukiOAuthAuthorizeCommand.php)
walks you through the authorization-code dance from the terminal:

```bash
php artisan nuki:oauth-authorize --account=tenant-42
```

The command prints an `authorize_url` to open in a browser, you consent,
NUKI redirects back to your `redirect_url` with `?code=…`, paste the code,
and the resulting token is persisted under `account_key = 'tenant-42'`.

`--code=…` skips the interactive prompt and exchanges a code you already
captured.

### In-app flow

For end-user consent in your app's UI, use [OAuth](../src/Resources/OAuth.php)
directly:

```php
use Darvis\Nuki\Facades\Nuki;

// 1. Redirect the user to NUKI
return redirect(Nuki::oauth()->authorizationUrl(state: $state));

// 2. Handle the callback
$token = Nuki::oauth()->exchangeCode($request->code, accountKey: (string) $user->id);

// 3. Use the API
$locks = Nuki::as((string) $user->id)->smartlocks()->all();

// 4. Optional: refresh (the authenticator does this automatically when
//    `expiresAt` is within 30s of now, but you can force it).
Nuki::oauth()->refresh((string) $user->id);

// 5. Disconnect
Nuki::oauth()->revoke((string) $user->id);
```

### Token storage

Selected by `nuki.oauth.token_store`:

| Driver | Where tokens live | When to pick it |
|---|---|---|
| `cache` (default) | Laravel cache, prefix `nuki:oauth:` | Single account, or a few accounts where cache eviction is acceptable. |
| `database` | `nuki_oauth_tokens` table — one row per `account_key` with encrypted `access_token` / `refresh_token` columns | Multi-account SaaS. Survives cache flushes. |

The implementations are
[CacheTokenStore](../src/Auth/CacheTokenStore.php) and
[DatabaseTokenStore](../src/Auth/DatabaseTokenStore.php). Both implement
[TokenStore](../src/Contracts/TokenStore.php) (`get`, `put`, `forget`).

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

All exceptions inherit from [NukiException](../src/Exceptions/NukiException.php):

- [AuthenticationException](../src/Exceptions/AuthenticationException.php) —
  no token configured, OAuth refresh failed, expired token cannot be refreshed.
- [ApiException](../src/Exceptions/ApiException.php) — non-success HTTP
  response from NUKI; carries the status code and parsed body.

`HttpClient` already retries connection failures, HTTP 429 and 5xx responses
with exponential backoff (`http.retry_sleep` × 2^attempt ms). You only need to
catch these explicitly for cases where the retries are exhausted.
