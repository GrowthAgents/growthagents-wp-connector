# GrowthAgents Connector

Connects a WordPress site to [GrowthAgents](https://growthagents.ai) through a
dedicated, revocable connection instead of a shared Application Password.

**v0.1 — connection only.** No tracking pixel, no publish-specific REST
namespace. Once paired, GrowthAgents authenticates against WordPress core's
own `/wp/v2/*` REST endpoints.

## Install

1. Download the plugin ZIP.
2. In WordPress admin: **Plugins → Add New → Upload Plugin**, choose the ZIP,
   click **Install Now**, then **Activate**.

## Pair

1. In your GrowthAgents workspace: **Settings → Integrations → WordPress →
   Plugin**, click **Get a pairing code**. The code is single-use and
   short-lived — generate it right before you need it.
2. In WordPress admin: **Settings → GrowthAgents**, paste the code into
   **Pairing code**, click **Connect**.
3. On success the page shows which WordPress user GrowthAgents will publish
   as (a dedicated `growthagents` user, created automatically with the
   Editor role if it didn't already exist). On failure, the page shows the
   exact reason the pairing server gave — nothing is stored locally when
   pairing fails.

## Disconnect

**Settings → GrowthAgents → Disconnect.** This removes the stored connection
token immediately — GrowthAgents can no longer authenticate to this site
until you pair again. The `growthagents` WordPress user is kept, so posts it
already published stay correctly attributed; deleting that user would orphan
them.

## Uninstalling

Deleting the plugin through WordPress admin removes its two stored options
(the connection token and the paired user id) via `uninstall.php`. It does
**not** delete the `growthagents` user or anything that user published.

## Troubleshooting: Authorization header missing (Apache)

Some Apache/PHP-FPM setups strip the `Authorization` header before PHP ever
sees it, which breaks pairing's authentication check even though the token
is correct. If GrowthAgents reports every request as unauthenticated after a
successful pair, add this to your site's `.htaccess` (or vhost config) to
forward the header through as `HTTP_AUTHORIZATION`:

```apache
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
```

Nginx and most managed WordPress hosts pass the header through by default
and do not need this.
