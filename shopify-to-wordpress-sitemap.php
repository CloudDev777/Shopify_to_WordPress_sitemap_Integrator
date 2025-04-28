<?php

/**
 * Plugin Name: Shopify Sitemap Integrator
 * Description: Fetches a Shopify sitemap and adds it to your WordPress site.
 * Version: 1.0.0
 * Author: Gabriel Kanev
 * Author URI: https://gkanev.com
 * Plugin URI: https://github.com/MrGKanev/Shopify-to-WordPress-sitemap
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}

// Define plugin constants
define('SHOPIFY_SITEMAP_DIR', plugin_dir_path(__FILE__));
define('SHOPIFY_SITEMAP_DEFAULT_OUTPUT', 'store.xml');

// Include required files
require_once SHOPIFY_SITEMAP_DIR . 'includes/admin.php';
require_once SHOPIFY_SITEMAP_DIR . 'includes/sitemap.php';
require_once SHOPIFY_SITEMAP_DIR . 'includes/rewrite.php';

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'shopify_sitemap_activate');
register_deactivation_hook(__FILE__, 'shopify_sitemap_deactivate');

// Plugin activation
function shopify_sitemap_activate()
{
  // Default settings
  add_option('shopify_sitemap_path', 'sitemap.xml');
  add_option('shopify_sitemap_output_filename', SHOPIFY_SITEMAP_DEFAULT_OUTPUT);

  // Add rewrite rules
  shopify_sitemap_add_rewrite_rules();

  // Flush rewrite rules
  flush_rewrite_rules();

  // Set transient for admin notice
  set_transient('shopify_sitemap_flush_notice', true, 60);

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shopify Sitemap: Plugin activated, rewrite rules flushed');
  }
}

// Plugin deactivation
function shopify_sitemap_deactivate()
{
  flush_rewrite_rules();
  delete_transient('shopify_sitemap_data');
  delete_transient('shopify_sitemap_is_index');
  delete_transient('shopify_sitemap_flush_notice');

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shopify Sitemap: Plugin deactivated, transients deleted');
  }
}

/**
 * Show admin notice after activation to refresh permalinks
 */
function shopify_sitemap_admin_notice()
{
  if (get_transient('shopify_sitemap_flush_notice')) {
?>
    <div class="notice notice-info is-dismissible">
      <p><?php esc_html_e('Shopify Sitemap Integrator: For the sitemap to work correctly, please <a href="options-permalink.php">visit the Permalinks page</a> and click "Save Changes" to refresh your site\'s rewrite rules.', 'shopify-to-wordpress-sitemap'); ?></p>
    </div>
  <?php
    delete_transient('shopify_sitemap_flush_notice');
  }

  // Show update status message if applicable
  if (isset($_GET['page']) && $_GET['page'] === 'shopify-sitemap' && isset($_GET['updated'])) {
    $success = $_GET['updated'] === 'true';
  ?>
    <div class="notice notice-<?php echo $success ? 'success' : 'error'; ?> is-dismissible">
      <p><?php echo $success
            ? esc_html__('Shopify sitemap updated successfully!', 'shopify-to-wordpress-sitemap')
            : esc_html__('Failed to update Shopify sitemap. Please check your domain and sitemap path settings.', 'shopify-to-wordpress-sitemap'); ?></p>
    </div>
<?php
  }
}
add_action('admin_notices', 'shopify_sitemap_admin_notice');

/**
 * Show info about sitemap URL on settings page
 */
function shopify_sitemap_display_url()
{
  $output_filename = get_option('shopify_sitemap_output_filename', SHOPIFY_SITEMAP_DEFAULT_OUTPUT);
  $sitemap_url = home_url('/' . $output_filename);

  $status_html = '';

  // Check if the sitemap transient exists
  $has_data = get_transient('shopify_sitemap_data') !== false;
  $is_index = get_transient('shopify_sitemap_is_index');

  if ($has_data) {
    if ($is_index) {
      $status_html = '<span style="color: green;">✓ Sitemap index data is available</span>';
    } else {
      $status_html = '<span style="color: green;">✓ Sitemap data is available</span>';
    }
  } else {
    $status_html = '<span style="color: orange;">⚠ No sitemap data yet. Try clicking "Update Sitemap Now" below.</span>';
  }

  return sprintf(
    '<div class="notice notice-info">
    <p>Your sitemap is available at: <a href="%1$s" target="_blank">%1$s</a><br>
    Status: %2$s</p>
    <p><strong>Note:</strong> Your Shopify site is using a sitemap index file which contains links to multiple sitemaps.</p>
    <p><strong>Troubleshooting:</strong> If your sitemap doesn\'t work, try these steps:</p>
    <ol>
      <li>Make sure your Shopify domain and sitemap path are correct</li>
      <li>Visit the <a href="%3$s">Permalinks page</a> and click "Save Changes" to refresh rewrite rules</li>
      <li>Click "Update Sitemap Now" below to manually fetch the sitemap</li>
    </ol>
  </div>',
    esc_url($sitemap_url),
    $status_html,
    admin_url('options-permalink.php')
  );
}
add_action('shopify_sitemap_after_settings', 'shopify_sitemap_display_url');

// Register an action to flush rewrite rules when permalink settings are updated
add_action('permalink_structure_changed', 'shopify_sitemap_flush_rules');
function shopify_sitemap_flush_rules()
{
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shopify Sitemap: Permalink structure changed, flushing rules');
  }
  flush_rewrite_rules();
}
