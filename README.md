# WP Dev Dashboard

A lightweight WordPress must-use plugin that adds a **Dev Dashboard** admin page — your one-stop command centre during local development.

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue) ![License](https://img.shields.io/badge/license-GPL--2.0-green)

## Features

- **Plugin toggles** — activate or deactivate any plugin with a single click via AJAX. No page reload.
- **Filter box** — type to instantly filter the plugin list by name.
- **Quick links sidebar** — sticky panel of links to the admin pages you visit most, all opening in a new tab.

## Installation

1. Download `dev-dashboard.php`.
2. Place it in your site's `wp-content/mu-plugins/` directory. Create the directory if it does not exist.
3. That's it. Must-use plugins load automatically — no activation step needed.
4. Go to **WP Admin → Dev Dashboard** (appears near the top of the sidebar).

## Customising Quick Links

Open `dev-dashboard.php` and find the `$quick_links` array inside `dev_dashboard_render()`. Add, remove, or rename entries to match your workflow:

```php
$quick_links = [
    'WooCommerce Settings' => admin_url( 'admin.php?page=wc-settings' ),
    'Products'             => admin_url( 'edit.php?post_type=product' ),
    'Orders'               => admin_url( 'edit.php?post_type=shop_order' ),
    'Posts'                => admin_url( 'edit.php' ),
    'Pages'                => admin_url( 'edit.php?post_type=page' ),
    // Add as many as you like...
];
```

Each link opens in a new tab automatically.

## Plugin Toggles

Every regular (non-must-use) plugin is listed with a toggle switch. Flipping a switch activates or deactivates the plugin instantly via AJAX — no page reload. Use the filter box to quickly find a plugin by name.

> **Note:** Must-use plugins are not listed because they cannot be deactivated through WordPress.

## Requirements

- WordPress 5.0+
- Logged-in user must have the `manage_options` and `activate_plugins` capabilities (Administrator role).

## Security

- All AJAX actions are protected with a WordPress nonce (`wp_create_nonce` / `check_ajax_referer`).
- Capability checks (`manage_options`, `activate_plugins`) are enforced on every request.
- All output is escaped with `esc_html` / `esc_url` / `wp_json_encode`.

## License

GPL-2.0-or-later — same as WordPress itself.
