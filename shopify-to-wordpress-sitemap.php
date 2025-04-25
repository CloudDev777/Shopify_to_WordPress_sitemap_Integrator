<?php

/**
 * Plugin Name: Shopify Sitemap Integrator
 * Description: Fetches a Shopify sitemap and adds it to your WordPress site.
 * Version: 1.0.0
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
}

// Plugin deactivation
function shopify_sitemap_deactivate()
{
  flush_rewrite_rules();
  delete_transient('shopify_sitemap_data');
}
