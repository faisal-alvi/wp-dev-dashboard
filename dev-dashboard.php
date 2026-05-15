<?php
/**
 * Dev Dashboard
 *
 * A lightweight must-use plugin that adds a "Dev Dashboard" page to the
 * WordPress admin. It gives you one-click plugin toggling (via AJAX, no
 * page reload) and a configurable quick-links sidebar.
 *
 * -------------------------------------------------------------------------
 * INSTALLATION
 * -------------------------------------------------------------------------
 * 1. Copy this file into your site's wp-content/mu-plugins/ directory.
 *    Create that directory if it does not exist.
 * 2. That's it — must-use plugins load automatically and cannot be
 *    deactivated from the Plugins screen.
 * 3. Visit WP Admin → Dev Dashboard (near the top of the sidebar).
 *
 * -------------------------------------------------------------------------
 * CUSTOMISING QUICK LINKS
 * -------------------------------------------------------------------------
 * Find the $quick_links array inside dev_dashboard_render() and add,
 * remove, or rename entries to suit your workflow. Each entry is:
 *
 *   'Label' => admin_url( 'path/to/page' ),
 *
 * Examples:
 *   'WooCommerce Settings' => admin_url( 'admin.php?page=wc-settings' ),
 *   'My Plugin Settings'   => admin_url( 'admin.php?page=my-plugin' ),
 *   'Posts'                => admin_url( 'edit.php' ),
 *   'Media'                => admin_url( 'upload.php' ),
 *
 * All links open in a new tab automatically.
 *
 * -------------------------------------------------------------------------
 * PLUGIN TOGGLES
 * -------------------------------------------------------------------------
 * Every regular (non-must-use) plugin is listed with a toggle switch.
 * Flipping a switch activates or deactivates the plugin instantly via AJAX —
 * no page reload required. Use the filter box at the top to quickly find a
 * plugin by name.
 *
 * Note: must-use plugins (mu-plugins/) are not listed because they cannot
 * be deactivated through WordPress at all.
 *
 * -------------------------------------------------------------------------
 * REQUIREMENTS
 * -------------------------------------------------------------------------
 * - WordPress 5.0+
 * - The logged-in user must have the manage_options and activate_plugins
 *   capabilities (i.e. an Administrator).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page(
		'Dev Dashboard',
		'Dev Dashboard',
		'manage_options',
		'dev-dashboard',
		'dev_dashboard_render',
		'dashicons-dashboard',
		2
	);
} );

add_action( 'wp_ajax_dev_dashboard_toggle_plugin', function () {
	check_ajax_referer( 'dev_dashboard_nonce', 'nonce' );

	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_send_json_error( 'Permission denied.' );
	}

	$plugin = sanitize_text_field( $_POST['plugin'] ?? '' );
	$action = sanitize_text_field( $_POST['toggle'] ?? '' );

	if ( ! $plugin || ! in_array( $action, [ 'activate', 'deactivate' ], true ) ) {
		wp_send_json_error( 'Invalid request.' );
	}

	$all_plugins = get_plugins();
	$found       = false;
	foreach ( $all_plugins as $file => $data ) {
		if ( dirname( $file ) === $plugin || $file === $plugin ) {
			$plugin = $file;
			$found  = true;
			break;
		}
	}

	if ( ! $found ) {
		wp_send_json_error( 'Plugin not found.' );
	}

	if ( $action === 'activate' ) {
		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
	} else {
		deactivate_plugins( $plugin );
	}

	wp_send_json_success( [
		'plugin' => $plugin,
		'status' => is_plugin_active( $plugin ) ? 'active' : 'inactive',
	] );
} );

function dev_dashboard_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Access denied.' );
	}

	$all_plugins    = get_plugins();
	$active_plugins = get_option( 'active_plugins', [] );
	$plugins        = [];

	foreach ( $all_plugins as $file => $data ) {
		$slug = dirname( $file );
		if ( $slug === '.' ) {
			$slug = basename( $file, '.php' );
		}
		$plugins[] = [
			'file'   => $file,
			'slug'   => $slug,
			'name'   => $data['Name'],
			'ver'    => $data['Version'],
			'active' => in_array( $file, $active_plugins, true ),
		];
	}

	usort( $plugins, function ( $a, $b ) {
		return strcasecmp( $a['name'], $b['name'] );
	} );

	$nonce     = wp_create_nonce( 'dev_dashboard_nonce' );
	$admin_url = admin_url( 'admin-ajax.php' );

	$quick_links = [
		'WooCommerce Settings'    => admin_url( 'admin.php?page=wc-settings' ),
		'Square Settings'         => admin_url( 'admin.php?page=wc-settings&tab=square' ),
		'Payment Gateways'        => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
		'Products'                => admin_url( 'edit.php?post_type=product' ),
		'Orders'                  => admin_url( 'edit.php?post_type=shop_order' ),
		'Bookings'                => admin_url( 'edit.php?post_type=wc_booking' ),
		'Posts'                   => admin_url( 'edit.php' ),
		'Pages'                   => admin_url( 'edit.php?post_type=page' ),
		'WooCommerce Status'      => admin_url( 'admin.php?page=wc-status' ),
		'WooCommerce Extensions'  => admin_url( 'admin.php?page=wc-addons' ),
	];

	?>
	<style>
		.dev-dash { max-width: 1400px; margin: 20px auto 0; }
		.dev-dash h1 { font-size: 28px; font-weight: 600; margin-bottom: 24px; }
		.dev-dash-grid { display: grid; grid-template-columns: 1fr 260px; gap: 24px; align-items: start; }
		.dev-dash-sidebar { position: sticky; top: 46px; }
		@media (max-width: 782px) { .dev-dash-grid { grid-template-columns: 1fr; } .dev-dash-sidebar { position: static; } }

		/* Panels */
		.dev-panel { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; }
		.dev-panel h2 { font-size: 16px; font-weight: 600; margin: 0 0 16px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f1; }

		/* Plugin list */
		.plugin-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f6f7f7; }
		.plugin-row:last-child { border-bottom: none; }
		.plugin-info { flex: 1; min-width: 0; }
		.plugin-name { font-weight: 500; font-size: 13px; }
		.plugin-ver { color: #757575; font-size: 12px; margin-left: 6px; }
		.plugin-filter { margin-bottom: 12px; }
		.plugin-filter input { width: 100%; padding: 6px 10px; border: 1px solid #dcdcde; border-radius: 4px; font-size: 13px; }

		/* Toggle switch */
		.toggle-switch { position: relative; width: 40px; height: 22px; flex-shrink: 0; margin-left: 12px; }
		.toggle-switch input { opacity: 0; width: 0; height: 0; }
		.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 22px; transition: .2s; }
		.toggle-slider:before { content: ""; position: absolute; height: 16px; width: 16px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .2s; }
		.toggle-switch input:checked + .toggle-slider { background: #2271b1; }
		.toggle-switch input:checked + .toggle-slider:before { transform: translateX(18px); }
		.toggle-switch input:disabled + .toggle-slider { opacity: 0.5; cursor: wait; }

		/* Quick links */
		.quick-links a { display: block; padding: 8px 12px; margin-bottom: 4px; color: #2271b1; text-decoration: none; border-radius: 4px; font-size: 13px; transition: background .15s; }
		.quick-links a:hover { background: #f0f6fc; }

	</style>

	<div class="dev-dash">
		<h1>Dev Dashboard</h1>
		<div class="dev-dash-grid">
			<div>
				<div class="dev-panel">
					<h2>Plugins</h2>
					<div class="plugin-filter">
						<input type="text" id="plugin-search" placeholder="Filter plugins...">
					</div>
					<div id="plugin-list">
						<?php foreach ( $plugins as $p ) : ?>
							<div class="plugin-row" data-slug="<?php echo esc_attr( $p['slug'] ); ?>" data-name="<?php echo esc_attr( strtolower( $p['name'] ) ); ?>">
								<div class="plugin-info">
									<span class="plugin-name"><?php echo esc_html( $p['name'] ); ?></span>
									<span class="plugin-ver"><?php echo esc_html( $p['ver'] ); ?></span>
								</div>
								<label class="toggle-switch">
									<input type="checkbox" data-plugin="<?php echo esc_attr( $p['slug'] ); ?>" <?php checked( $p['active'] ); ?>>
									<span class="toggle-slider"></span>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

			</div>

			<div class="dev-dash-sidebar">
				<div class="dev-panel">
					<h2>Quick Links</h2>
					<div class="quick-links">
						<?php foreach ( $quick_links as $label => $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script>
	(function() {
		const ajaxUrl = <?php echo wp_json_encode( $admin_url ); ?>;
		const nonce   = <?php echo wp_json_encode( $nonce ); ?>;

		// Plugin toggle
		document.getElementById('plugin-list').addEventListener('change', function(e) {
			if (e.target.type !== 'checkbox') return;
			const cb     = e.target;
			const slug   = cb.dataset.plugin;
			const action = cb.checked ? 'activate' : 'deactivate';

			cb.disabled = true;

			const form = new FormData();
			form.append('action', 'dev_dashboard_toggle_plugin');
			form.append('nonce', nonce);
			form.append('plugin', slug);
			form.append('toggle', action);

			fetch(ajaxUrl, { method: 'POST', body: form })
				.then(r => r.json())
				.then(res => {
					if (!res.success) {
						cb.checked = !cb.checked;
						alert('Error: ' + (res.data || 'Unknown error'));
					}
				})
				.catch(() => {
					cb.checked = !cb.checked;
					alert('Network error.');
				})
				.finally(() => { cb.disabled = false; });
		});

		// Plugin filter
		document.getElementById('plugin-search').addEventListener('input', function() {
			const q = this.value.toLowerCase();
			document.querySelectorAll('.plugin-row').forEach(row => {
				row.style.display = row.dataset.name.includes(q) ? '' : 'none';
			});
		});

	})();
	</script>
	<?php
}
