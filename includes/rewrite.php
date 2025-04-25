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

  // Add rewrite rule
  add_rewrite_rule(
    '^' . $filename . '$',
    'index.php?shopify_sitemap=1',
    'top'
  );

  // Add query var
  add_rewrite_tag('%shopify_sitemap%', '([^&]+)');
}

// Handle sitemap requests
add_action('parse_request', 'shopify_sitemap_handle_request');
function shopify_sitemap_handle_request($wp)
{
  if (!empty($wp->query_vars['shopify_sitemap'])) {
    echo shopify_sitemap_generate_xml();
    exit;
  }
}
