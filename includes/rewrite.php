<?php

/**
 * Rewrite rules for Shopify Sitemap Integrator
 */

// Add rewrite rules
add_action('init', 'shopify_sitemap_add_rewrite_rules');
function shopify_sitemap_add_rewrite_rules()
{
  // Get the filename from options or use default
  $filename = get_option('shopify_sitemap_output_filename', SHOPIFY_SITEMAP_DEFAULT_OUTPUT);

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shopify Sitemap: Setting up rewrite rule for ' . $filename);
  }

  // Add rewrite rule - PHP 8.1 safe with more explicit type checking
  if (!empty($filename) && is_string($filename)) {
    add_rewrite_rule(
      '^' . preg_quote($filename) . '$',
      'index.php?shopify_sitemap=1',
      'top'
    );
  }

  // Add query var
  add_filter('query_vars', 'shopify_sitemap_add_query_vars');
}

// Add custom query var
function shopify_sitemap_add_query_vars($vars)
{
  if (!is_array($vars)) {
    $vars = array();
  }

  $vars[] = 'shopify_sitemap';
  return $vars;
}

// Handle sitemap requests
add_action('template_redirect', 'shopify_sitemap_handle_request', 5);
function shopify_sitemap_handle_request()
{
  global $wp_query;

  if (defined('WP_DEBUG') && WP_DEBUG) {
    $query_var = isset($wp_query->query_vars['shopify_sitemap']) ? $wp_query->query_vars['shopify_sitemap'] : 'not set';
    error_log('Shopify Sitemap: Template redirect check - shopify_sitemap var: ' . $query_var);
  }

  if (isset($wp_query->query_vars['shopify_sitemap']) && $wp_query->query_vars['shopify_sitemap'] == '1') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: Handling sitemap request');
    }

    // Check if the function exists to prevent PHP errors
    if (function_exists('shopify_sitemap_generate_xml')) {
      // Output the XML
      $xml = shopify_sitemap_generate_xml();

      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Shopify Sitemap: XML generated, length: ' . strlen($xml));
      }

      // Set the header
      header('Content-Type: application/xml; charset=UTF-8');

      // Output the XML and exit
      echo $xml;
      exit;
    } else {
      // If function doesn't exist, log an error
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Shopify Sitemap: ERROR - shopify_sitemap_generate_xml function not found');
      }

      // Output a basic error XML
      header('Content-Type: application/xml; charset=UTF-8');
      echo '<?xml version="1.0" encoding="UTF-8"?>';
      echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
      echo '<!-- Error: Sitemap generation function not available -->';
      echo '</urlset>';
      exit;
    }
  }
}
