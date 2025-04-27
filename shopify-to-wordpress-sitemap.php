<?php

/**
 * Plugin Name: Shopify Sitemap Integrator
 * Description: Fetches a Shopify sitemap and adds it to your WordPress site.
 * Version: 1.0.0
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

  // Flush rewrite rules
  flush_rewrite_rules();

  // Set transient for admin notice
  set_transient('shopify_sitemap_flush_notice', true, 60);
}

// Plugin deactivation
function shopify_sitemap_deactivate()
{
  flush_rewrite_rules();
  delete_transient('shopify_sitemap_data');
  delete_transient('shopify_sitemap_flush_notice');
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
}
add_action('admin_notices', 'shopify_sitemap_admin_notice');
