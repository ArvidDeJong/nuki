## darvis/nuki

Wraps the [NUKI Web API](https://developer.nuki.io) for smartlocks, activity logs and authorizations, receives NUKI webhooks, and ships a Livewire/Flux UI for all of it. Optionally it also brings its own users and per smartlock permissions.

- Everything goes through the `Nuki` facade (`Darvis\Nuki\Facades\Nuki`), which returns resource objects: `Nuki::smartlocks()`, `logs()`, `auths()`, `webhooks()`, `oauth()` and `account()`. Don't build HTTP calls to api.nuki.io yourself; add a method to the resource class instead.
- `Nuki::as('some-account')` returns a clone scoped to another stored token. That is the multi account path, used with `auth` set to `oauth`; with `auth` set to `token` there is one account and you can leave it alone.
- Config lives under the key `nuki` (file `config/nuki.php`). The authentication method (`auth`), the token resolver (`token_resolver`), the webhook route and the whole UI are settings, not code paths to fork.
- Read a setting through `Darvis\Nuki\Support\NukiConfig` (`NukiConfig::uiPrefix()`, `NukiConfig::webhookRoute()`, `NukiConfig::apiToken()`), never with `config('nuki.…')`. Every default is written there once, so a `?? 'fallback'` next to a `config()` call is a second opinion waiting to go stale. A test fails the build if anything in the package reaches past it.
- Responses come back as DTOs in `Darvis\Nuki\DTOs` (`SmartLock`, `LogEntry`, `Authorization`, `AccountInfo`, `WebhookSubscription`, `NukiToken`), not as arrays. A new field from the API means adding a property there, so the shape stays checkable.
- A failing call throws `ApiException`; an authentication problem throws `AuthenticationException`. Both extend `NukiException`, so catch that when you don't care which.
- Webhooks arrive on the route from `webhook.route`. The body is checked with HMAC-SHA256 against `webhook.secret`; **without a secret every request is rejected with `401`**, so set one. Accepting unsigned calls takes an explicit `webhook.verify_signature` set to `false`. Act on a webhook by listening for `Darvis\Nuki\Events\NukiWebhookReceived` (`type`, `payload`, `accountKey`), never by adding your own controller to that route.
- The bundled UI is registered as Livewire components named `nuki.*` (`nuki.dashboard`, `nuki.smartlocks-index`, `nuki.smartlock-show`, `nuki.webhooks-index`, and with `auth_users` enabled also `nuki.auth.*`, `nuki.profile` and `nuki.sub-users-index`). Render those by name; the layout comes from `ui.layout`.
- `auth_users` is off by default. Switching it on registers its own `darvis-nuki` auth guard with the `NukiUser` model, puts every bundled route behind it, and enables sub users with per smartlock permissions. **Those permissions are local to your application; nothing is written back to the NUKI account.**
- Never call the real API in a test. Use `Http::fake()`, and `Darvis\Nuki\Support\DemoFixtures` for payloads shaped like the real responses. `demo.enabled` serves those same fixtures in the UI, which is how you show the package without a lock.

@verbatim
<code-snippet name="React to a lock that was opened" lang="php">
use Darvis\Nuki\Events\NukiWebhookReceived;
use Illuminate\Support\Facades\Event;

Event::listen(NukiWebhookReceived::class, function (NukiWebhookReceived $event) {
    // $event->type is the NUKI event name, $event->payload the raw body.
    Log::channel('access')->info('NUKI webhook '.$event->type, $event->payload);
});
</code-snippet>
@endverbatim
