<?php

/**
 * Plugin Name: Shopify Sitemap Integrator
 * Description: Fetches a Shopify sitemap and adds it to your WordPress site.
 * Version: 1.1.0
 * Author: Gabriel Kanev
 * Author URI: https://gkanev.com
 * Plugin URI: https://github.com/MrGKanev/Shopify-to-WordPress-sitemap
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}

// Define plugin constants
define('SHOPIFY_SITEMAP_DIR', plugin_dir_path(__FILE__));
define('SHOPIFY_SITEMAP_DEFAULT_OUTPUT', 'store.xml');
define('SHOPIFY_SITEMAP_VERSION', '1.1.0');

/**
 * Enhanced debug logging for local environments
 * 
 * @param string $message The message to log
 * @return void
 */
function shopify_sitemap_log($message)
{
  // Check if we're in debug mode
  if (defined('WP_DEBUG') && WP_DEBUG) {
    // Get the current domain
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

    // Check if we're on a local domain (.local, localhost, etc.)
    $is_local = (
      strpos($domain, '.local') !== false ||
      strpos($domain, 'localhost') !== false ||
      strpos($domain, '.test') !== false ||
      strpos($domain, '.dev') !== false ||
      $domain === '127.0.0.1'
    );

    // For local domains, also output to screen if WP_DEBUG_DISPLAY is enabled
    if ($is_local && defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) {
      // Only show debug output for admin users on local environments
      if (current_user_can('manage_options')) {
        // Collect debug messages to show at the bottom of the page
        global $shopify_sitemap_debug_messages;
        if (!isset($shopify_sitemap_debug_messages) || !is_array($shopify_sitemap_debug_messages)) {
          $shopify_sitemap_debug_messages = array();
        }
        $shopify_sitemap_debug_messages[] = '[Shopify Sitemap] ' . $message;
      }
    }

    // Always log to the error log
    error_log('[Shopify Sitemap] ' . $message);
  }
}

// Hook to display debug messages at the bottom of admin pages
add_action('admin_footer', 'shopify_sitemap_display_debug_messages');
function shopify_sitemap_display_debug_messages()
{
  // Only proceed if we're in debug mode and for admin users
  if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_DISPLAY') || !WP_DEBUG_DISPLAY || !current_user_can('manage_options')) {
    return;
  }

  global $shopify_sitemap_debug_messages;
  if (!empty($shopify_sitemap_debug_messages) && is_array($shopify_sitemap_debug_messages)) {
    echo '<div class="shopify-sitemap-debug" style="margin-top:20px; padding:10px; background:#f8f8f8; border:1px solid #ddd; color:#444;">';
    echo '<h3>Shopify Sitemap Debug Log</h3>';
    echo '<pre style="font-size:12px; line-height:1.5; max-height:300px; overflow:auto;">';
    foreach ($shopify_sitemap_debug_messages as $msg) {
      echo esc_html($msg) . "\n";
    }
    echo '</pre>';
    echo '</div>';
  }
}

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
  add_option('shopify_sitemap_flatten', 'no');

  // Add rewrite rules
  if (function_exists('shopify_sitemap_add_rewrite_rules')) {
    shopify_sitemap_add_rewrite_rules();
  }

  // Flush rewrite rules
  flush_rewrite_rules();

  // Set transient for admin notice
  set_transient('shopify_sitemap_flush_notice', true, 60);

  shopify_sitemap_log('Plugin activated, rewrite rules flushed');
}

// Plugin deactivation
function shopify_sitemap_deactivate()
{
  flush_rewrite_rules();
  delete_transient('shopify_sitemap_data');
  delete_transient('shopify_sitemap_is_index');
  delete_transient('shopify_sitemap_flush_notice');

  shopify_sitemap_log('Plugin deactivated, transients deleted');
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

  // Check if the sitemap transient exists - PHP 8.1 safe
  $has_data = get_transient('shopify_sitemap_data') !== false;
  $is_index = get_transient('shopify_sitemap_is_index');
  $flatten = get_option('shopify_sitemap_flatten', 'no') === 'yes';

  if ($has_data) {
    if ($is_index && !$flatten) {
      $status_html = '<span style="color: green;">✓ Sitemap index data is available</span>';
    } else {
      $status_html = '<span style="color: green;">✓ Sitemap data is available</span>';
    }
  } else {
    $status_html = '<span style="color: orange;">⚠ No sitemap data yet. Try clicking "Update Sitemap Now" below.</span>';
  }

  $note = '';
  if ($is_index && !$flatten) {
    $note = '<p><strong>Note:</strong> Your Shopify site is using a sitemap index file which contains links to multiple sitemaps.</p>';
  } elseif ($is_index && $flatten) {
    $note = '<p><strong>Note:</strong> Your Shopify site uses a sitemap index, but you\'ve chosen to flatten it into a single sitemap.</p>';
  }

  return sprintf(
    '<div class="notice notice-info">
    <p>Your sitemap is available at: <a href="%1$s" target="_blank">%1$s</a><br>
    Status: %2$s</p>
    %3$s
    <p><strong>Troubleshooting:</strong> If your sitemap doesn\'t work, try these steps:</p>
    <ol>
      <li>Make sure your Shopify domain and sitemap path are correct</li>
      <li>Visit the <a href="%4$s">Permalinks page</a> and click "Save Changes" to refresh rewrite rules</li>
      <li>Click "Update Sitemap Now" below to manually fetch the sitemap</li>
    </ol>
  </div>',
    esc_url($sitemap_url),
    $status_html,
    $note,
    admin_url('options-permalink.php')
  );
}
add_action('shopify_sitemap_after_settings', 'shopify_sitemap_display_url');

// Register an action to flush rewrite rules when permalink settings are updated
add_action('permalink_structure_changed', 'shopify_sitemap_flush_rules');
function shopify_sitemap_flush_rules()
{
  shopify_sitemap_log('Permalink structure changed, flushing rules');
  flush_rewrite_rules();
}

// Add "Settings" link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'shopify_sitemap_plugin_action_links');
function shopify_sitemap_plugin_action_links($links)
{
  if (!is_array($links)) {
    $links = array();
  }

  $settings_link = '<a href="' . admin_url('options-general.php?page=shopify-sitemap') . '">' . __('Settings', 'shopify-to-wordpress-sitemap') . '</a>';

  // Get the sitemap URL
  $output_filename = get_option('shopify_sitemap_output_filename', SHOPIFY_SITEMAP_DEFAULT_OUTPUT);
  $sitemap_url = home_url('/' . $output_filename);
  $sitemap_link = '<a href="' . esc_url($sitemap_url) . '" target="_blank">' . __('View Sitemap', 'shopify-to-wordpress-sitemap') . '</a>';

  array_unshift($links, $settings_link, $sitemap_link);
  return $links;
}
