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

	// Active plugins first, then alphabetical within each group.
	usort( $plugins, function ( $a, $b ) {
		if ( $a['active'] !== $b['active'] ) {
			return $a['active'] ? -1 : 1;
		}
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

	$github_prs = [
		'woocommerce-bookings'              => 'https://github.com/woocommerce/woocommerce-bookings/pulls',
		'woocommerce-accommodation-bookings'=> 'https://github.com/woocommerce/woocommerce-accommodation-bookings/pulls',
		'woocommerce-square'                => 'https://github.com/woocommerce/woocommerce-square/pulls',
		'woocommerce-bookings-availability' => 'https://github.com/woocommerce/woocommerce-bookings-availability/pulls',
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

		/* Plugin grid */
		.plugin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
		.plugin-card { display: flex; flex-direction: row; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border: 1px solid #dcdcde; border-radius: 6px; cursor: pointer; user-select: none; transition: border-color .15s, background .15s; width: 100%; box-sizing: border-box; }
		.plugin-card:hover { border-color: #2271b1; background: #f8fbff; }
		.plugin-card.is-active { border-color: #2271b1; background: #f0f6fc; }
		.plugin-card.is-busy { opacity: .6; pointer-events: none; }
		.plugin-card-top { display: none; }
		.plugin-card-info { flex: 1; min-width: 0; }
		.plugin-name { font-weight: 600; font-size: 12px; line-height: 1.4; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
		.plugin-ver { color: #757575; font-size: 11px; display: block; margin-top: 2px; }
		.plugin-filter { margin-bottom: 14px; }
		.plugin-filter input { width: 100%; padding: 6px 10px; border: 1px solid #dcdcde; border-radius: 4px; font-size: 13px; box-sizing: border-box; }

		/* Toggle pill on card */
		.plugin-card-toggle { flex-shrink: 0; width: 34px; height: 19px; background: #ccc; border-radius: 19px; position: relative; transition: background .2s; margin-top: 1px; }
		.plugin-card-toggle::after { content: ""; position: absolute; width: 13px; height: 13px; background: #fff; border-radius: 50%; top: 3px; left: 3px; transition: transform .2s; }
		.plugin-card.is-active .plugin-card-toggle { background: #2271b1; }
		.plugin-card.is-active .plugin-card-toggle::after { transform: translateX(15px); }

		/* Quick links */
		.quick-links a { display: block; padding: 8px 12px; margin-bottom: 4px; color: #2271b1; text-decoration: none; border-radius: 4px; font-size: 13px; transition: background .15s; }
		.quick-links a:hover { background: #f0f6fc; }
		.quick-links-divider { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #999; padding: 12px 12px 4px; }
		.quick-links-github a { color: #1f2328; }
		.quick-links-github a svg { vertical-align: middle; margin-right: 6px; flex-shrink: 0; }
		.quick-links-github a { display: flex; align-items: center; }

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
					<div class="plugin-grid" id="plugin-list">
						<?php foreach ( $plugins as $p ) : ?>
							<div class="plugin-card <?php echo $p['active'] ? 'is-active' : ''; ?>"
								data-slug="<?php echo esc_attr( $p['slug'] ); ?>"
								data-name="<?php echo esc_attr( strtolower( $p['name'] ) ); ?>"
								role="button" tabindex="0" aria-pressed="<?php echo $p['active'] ? 'true' : 'false'; ?>">
								<div class="plugin-card-info">
									<span class="plugin-name"><?php echo esc_html( $p['name'] ); ?></span>
									<span class="plugin-ver"><?php echo esc_html( $p['ver'] ) ?: '—'; ?></span>
								</div>
								<div class="plugin-card-toggle" aria-hidden="true"></div>
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
					<div class="quick-links-divider">GitHub PRs</div>
					<div class="quick-links quick-links-github">
						<?php foreach ( $github_prs as $label => $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank">
								<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27s1.36.09 2 .27c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg>
								<?php echo esc_html( $label ); ?>
							</a>
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

		// Plugin toggle — entire card is clickable
		function togglePlugin(card) {
			const slug   = card.dataset.slug;
			const active = card.classList.contains('is-active');
			const action = active ? 'deactivate' : 'activate';

			card.classList.add('is-busy');

			const form = new FormData();
			form.append('action', 'dev_dashboard_toggle_plugin');
			form.append('nonce', nonce);
			form.append('plugin', slug);
			form.append('toggle', action);

			fetch(ajaxUrl, { method: 'POST', body: form })
				.then(r => r.json())
				.then(res => {
					if (res.success) {
						card.classList.toggle('is-active', !active);
						card.setAttribute('aria-pressed', String(!active));
					} else {
						alert('Error: ' + (res.data || 'Unknown error'));
					}
				})
				.catch(() => { alert('Network error.'); })
				.finally(() => { card.classList.remove('is-busy'); });
		}

		document.getElementById('plugin-list').addEventListener('click', function(e) {
			const card = e.target.closest('.plugin-card');
			if (card) togglePlugin(card);
		});

		document.getElementById('plugin-list').addEventListener('keydown', function(e) {
			if (e.key === 'Enter' || e.key === ' ') {
				const card = e.target.closest('.plugin-card');
				if (card) { e.preventDefault(); togglePlugin(card); }
			}
		});

		// Plugin filter
		document.getElementById('plugin-search').addEventListener('input', function() {
			const q = this.value.toLowerCase();
			document.querySelectorAll('.plugin-card').forEach(card => {
				card.style.display = card.dataset.name.includes(q) ? '' : 'flex';
				if (!card.dataset.name.includes(q)) card.style.display = 'none';
			});
		});

	})();
	</script>
	<?php
}
