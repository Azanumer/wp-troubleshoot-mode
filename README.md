# WP Troubleshoot Mode

One-click safe mode for diagnosing plugin conflicts — loads WordPress with every plugin disabled except this one, **for your browser only**. No more renaming plugin folders over FTP to find out which plugin broke your site.

## How it works

1. Click **Safe Mode: OFF** in the admin bar (or **Tools → Troubleshoot Mode → Enable safe mode**).
2. Your browser now loads the site with all plugins disabled. Other visitors still see the normal site.
3. If the problem is gone, a plugin is the cause. Exit safe mode and re-enable plugins one at a time until the problem returns — the last one you enabled is the culprit.

Safe mode turns off automatically after 2 hours, or the moment you click **Exit safe mode**.

## Why it's safe

- The safe-mode cookie is **signed with an HMAC** keyed on your site's `NONCE_KEY`, and is only issued to admins after a nonce-checked toggle. Nobody can forge it or enable it for someone else.
- Safe mode is per-browser: it never affects other visitors, search engines, or logged-out users.
- The plugin stores no options and no database tables.

## Features

- Admin-bar toggle available on every screen.
- Tools page with status and a 3-step conflict-hunting guide.
- Developers: the `wtsm_safe_mode_plugins` filter lets you keep extra plugins (e.g. a debugging plugin) active during safe mode.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it from **Plugins**.
3. Use the admin-bar toggle or **Tools → Troubleshoot Mode**.

## Requirements

- WordPress 5.0+
- PHP 7.4+

## License

MIT — see [LICENSE](LICENSE).
