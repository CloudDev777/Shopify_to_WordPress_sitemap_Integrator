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
define('SHOPIFY_SITEMAP_DEFAULT_OUTPUT', 'store.xml');

/**
 * Add rewrite rules for the sitemap.
 */
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

/**
 * Handle sitemap requests.
 */
function shopify_sitemap_handle_request($wp)
{
  if (!empty($wp->query_vars['shopify_sitemap'])) {
    echo shopify_sitemap_generate_xml();
    exit;
  }
}

/**
 * Generate the sitemap XML.
 */
function shopify_sitemap_generate_xml()
{
  $sitemap_data = get_transient('shopify_sitemap_data');

  if (empty($sitemap_data)) {
    // Try to update the sitemap
    shopify_sitemap_update();
    $sitemap_data = get_transient('shopify_sitemap_data');
  }

  header('Content-Type: application/xml; charset=UTF-8');

  ob_start();
  echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

  if (!empty($sitemap_data) && is_array($sitemap_data)) {
    foreach ($sitemap_data as $item) {
      echo "\t<url>\n";
      echo "\t\t<loc>" . esc_url($item['loc']) . "</loc>\n";

      if (!empty($item['lastmod'])) {
        echo "\t\t<lastmod>" . esc_html($item['lastmod']) . "</lastmod>\n";
      }

      if (!empty($item['changefreq'])) {
        echo "\t\t<changefreq>" . esc_html($item['changefreq']) . "</changefreq>\n";
      }

      if (!empty($item['priority'])) {
        echo "\t\t<priority>" . esc_html($item['priority']) . "</priority>\n";
      }

      echo "\t</url>\n";
    }
  } else {
    // Fallback if no data
    echo "\t<url>\n";
    echo "\t\t<loc>" . esc_url(home_url()) . "</loc>\n";
    echo "\t\t<lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "\t\t<changefreq>daily</changefreq>\n";
    echo "\t\t<priority>1.0</priority>\n";
    echo "\t</url>\n";
  }

  echo '</urlset>';
  return ob_get_clean();
}

/**
 * Update the Shopify sitemap.
 */
function shopify_sitemap_update()
{
  $domain = get_option('shopify_sitemap_domain', '');
  $path = get_option('shopify_sitemap_path', 'sitemap.xml');

  if (empty($domain)) {
    return false;
  }

  // Fetch the sitemap
  $response = wp_remote_get('https://' . $domain . '/' . $path, array(
    'timeout' => 30,
  ));

  if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
    return false;
  }

  $xml = wp_remote_retrieve_body($response);
  $sitemap_data = shopify_sitemap_parse_xml($xml);

  // Store the data
  if (!empty($sitemap_data)) {
    set_transient('shopify_sitemap_data', $sitemap_data, DAY_IN_SECONDS);
    return true;
  }

  return false;
}

/**
 * Parse sitemap XML.
 */
function shopify_sitemap_parse_xml($xml)
{
  if (empty($xml)) {
    return array();
  }

  libxml_use_internal_errors(true);

  $urls = array();
  $dom = new DOMDocument();

  if (@$dom->loadXML($xml)) {
    $url_nodes = $dom->getElementsByTagName('url');

    foreach ($url_nodes as $url_node) {
      $loc_nodes = $url_node->getElementsByTagName('loc');

      if ($loc_nodes->length > 0) {
        $url = array(
          'loc' => $loc_nodes->item(0)->nodeValue,
          'lastmod' => '',
          'changefreq' => '',
          'priority' => '',
        );

        $lastmod_nodes = $url_node->getElementsByTagName('lastmod');
        if ($lastmod_nodes->length > 0) {
          $url['lastmod'] = $lastmod_nodes->item(0)->nodeValue;
        }

        $changefreq_nodes = $url_node->getElementsByTagName('changefreq');
        if ($changefreq_nodes->length > 0) {
          $url['changefreq'] = $changefreq_nodes->item(0)->nodeValue;
        }

        $priority_nodes = $url_node->getElementsByTagName('priority');
        if ($priority_nodes->length > 0) {
          $url['priority'] = $priority_nodes->item(0)->nodeValue;
        }

        $urls[] = $url;
      }
    }
  }

  libxml_clear_errors();

  return $urls;
}

/**
 * Admin settings page.
 */
function shopify_sitemap_admin_menu()
{
  add_options_page(
    'Shopify Sitemap Settings',
    'Shopify Sitemap',
    'manage_options',
    'shopify-sitemap',
    'shopify_sitemap_settings_page'
  );
}

/**
 * Settings page content.
 */
function shopify_sitemap_settings_page()
{
?>
  <div class="wrap">
    <h1>Shopify Sitemap Integrator</h1>
    <form method="post" action="options.php">
      <?php
      settings_fields('shopify_sitemap_settings');
      do_settings_sections('shopify_sitemap_settings');
      submit_button();
      ?>
    </form>
    <h2>Manual Update</h2>
    <p>Click the button below to update the sitemap immediately.</p>
    <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=shopify-sitemap&action=update'), 'shopify_sitemap_update'); ?>" class="button button-primary">Update Sitemap Now</a>
  </div>
<?php
}

/**
 * Register settings.
 */
function shopify_sitemap_register_settings()
{
  register_setting('shopify_sitemap_settings', 'shopify_sitemap_domain');
  register_setting('shopify_sitemap_settings', 'shopify_sitemap_path');
  register_setting('shopify_sitemap_settings', 'shopify_sitemap_output_filename');

  add_settings_section(
    'shopify_sitemap_general',
    'General Settings',
    'shopify_sitemap_section_text',
    'shopify_sitemap_settings'
  );

  add_settings_field(
    'shopify_sitemap_domain',
    'Shopify Domain',
    'shopify_sitemap_domain_field',
    'shopify_sitemap_settings',
    'shopify_sitemap_general'
  );

  add_settings_field(
    'shopify_sitemap_path',
    'Sitemap Path',
    'shopify_sitemap_path_field',
    'shopify_sitemap_settings',
    'shopify_sitemap_general'
  );

  add_settings_field(
    'shopify_sitemap_output_filename',
    'Output Filename',
    'shopify_sitemap_output_field',
    'shopify_sitemap_settings',
    'shopify_sitemap_general'
  );
}

/**
 * Section text.
 */
function shopify_sitemap_section_text()
{
  echo '<p>Configure the Shopify sitemap integration settings below.</p>';
}

/**
 * Domain field.
 */
function shopify_sitemap_domain_field()
{
  $domain = get_option('shopify_sitemap_domain', '');
  echo '<input type="text" name="shopify_sitemap_domain" value="' . esc_attr($domain) . '" class="regular-text" />';
  echo '<p class="description">Enter the Shopify domain without http:// or https:// (e.g., your-store.myshopify.com)</p>';
}

/**
 * Path field.
 */
function shopify_sitemap_path_field()
{
  $path = get_option('shopify_sitemap_path', 'sitemap.xml');
  echo '<input type="text" name="shopify_sitemap_path" value="' . esc_attr($path) . '" class="regular-text" />';
  echo '<p class="description">The path to the sitemap file (default: sitemap.xml)</p>';
}

/**
 * Output filename field.
 */
function shopify_sitemap_output_field()
{
  $filename = get_option('shopify_sitemap_output_filename', SHOPIFY_SITEMAP_DEFAULT_OUTPUT);
  echo '<input type="text" name="shopify_sitemap_output_filename" value="' . esc_attr($filename) . '" class="regular-text" />';
  echo '<p class="description">The filename for the generated sitemap (default: store.xml)</p>';
}

/**
 * Handle admin actions.
 */
function shopify_sitemap_admin_actions()
{
  if (
    isset($_GET['page']) && $_GET['page'] === 'shopify-sitemap' &&
    isset($_GET['action']) && $_GET['action'] === 'update' &&
    isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'shopify_sitemap_update')
  ) {

    $updated = shopify_sitemap_update();
    $redirect = add_query_arg(array(
      'page' => 'shopify-sitemap',
      'updated' => $updated ? 'true' : 'false'
    ), admin_url('options-general.php'));

    wp_redirect($redirect);
    exit;
  }
}

/**
 * Plugin activation.
 */
function shopify_sitemap_activate()
{
  // Default settings
  add_option('shopify_sitemap_path', 'sitemap.xml');
  add_option('shopify_sitemap_output_filename', SHOPIFY_SITEMAP_DEFAULT_OUTPUT);

  // Flush rewrite rules
  flush_rewrite_rules();
}

/**
 * Plugin deactivation.
 */
function shopify_sitemap_deactivate()
{
  flush_rewrite_rules();
}

// Register actions and hooks
add_action('init', 'shopify_sitemap_add_rewrite_rules');
add_action('parse_request', 'shopify_sitemap_handle_request');
add_action('admin_menu', 'shopify_sitemap_admin_menu');
add_action('admin_init', 'shopify_sitemap_register_settings');
add_action('admin_init', 'shopify_sitemap_admin_actions');

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'shopify_sitemap_activate');
register_deactivation_hook(__FILE__, 'shopify_sitemap_deactivate');
