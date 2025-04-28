<?php

/**
 * Admin settings for Shopify Sitemap Integrator
 */

// Add admin menu
add_action('admin_menu', 'shopify_sitemap_admin_menu');
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

// Settings page content
function shopify_sitemap_settings_page()
{
  // Get sitemap URL for the view button
  $output_filename = get_option('shopify_sitemap_output_filename', SHOPIFY_SITEMAP_DEFAULT_OUTPUT);
  $sitemap_url = home_url('/' . $output_filename);
?>
  <div class="wrap">
    <h1>Shopify Sitemap Integrator</h1>
    <div class="shopify-sitemap-buttons" style="margin: 10px 0 20px;">
      <a href="<?php echo esc_url($sitemap_url); ?>" class="button" target="_blank"><?php _e('View Sitemap', 'shopify-to-wordpress-sitemap'); ?></a>
    </div>

    <form method="post" action="options.php">
      <?php
      settings_fields('shopify_sitemap_settings');
      do_settings_sections('shopify_sitemap_settings');
      submit_button();
      ?>
    </form>

    <?php do_action('shopify_sitemap_after_settings'); ?>

    <h2>Manual Update</h2>
    <p>Click the button below to update the sitemap immediately.</p>
    <div class="shopify-sitemap-buttons">
      <a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=shopify-sitemap&action=update'), 'shopify_sitemap_update')); ?>" class="button button-primary">Update Sitemap Now</a>
      <a href="<?php echo esc_url($sitemap_url); ?>" class="button" target="_blank" style="margin-left: 10px;"><?php _e('View Sitemap', 'shopify-to-wordpress-sitemap'); ?></a>
    </div>
  </div>
<?php
}

// Register settings
add_action('admin_init', 'shopify_sitemap_register_settings');
function shopify_sitemap_register_settings()
{
  register_setting(
    'shopify_sitemap_settings',
    'shopify_sitemap_domain',
    array(
      'sanitize_callback' => 'sanitize_text_field',
      'default' => '',
    )
  );

  register_setting(
    'shopify_sitemap_settings',
    'shopify_sitemap_path',
    array(
      'sanitize_callback' => 'sanitize_text_field',
      'default' => 'sitemap.xml',
    )
  );

  register_setting(
    'shopify_sitemap_settings',
    'shopify_sitemap_output_filename',
    array(
      'sanitize_callback' => 'sanitize_file_name',
      'default' => SHOPIFY_SITEMAP_DEFAULT_OUTPUT,
    )
  );

  register_setting(
    'shopify_sitemap_settings',
    'shopify_sitemap_flatten',
    array(
      'sanitize_callback' => 'sanitize_text_field',
      'default' => 'no',
    )
  );

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

  add_settings_field(
    'shopify_sitemap_flatten',
    'Flatten Sitemap',
    'shopify_sitemap_flatten_field',
    'shopify_sitemap_settings',
    'shopify_sitemap_general'
  );
}

// Section text
function shopify_sitemap_section_text()
{
  echo '<p>Configure the Shopify sitemap integration settings below.</p>';
}

// Domain field
function shopify_sitemap_domain_field()
{
  $domain = get_option('shopify_sitemap_domain', '');
  echo '<input type="text" name="shopify_sitemap_domain" value="' . esc_attr($domain) . '" class="regular-text" />';
  echo '<p class="description">Enter the Shopify domain without http:// or https:// (e.g., your-store.myshopify.com)</p>';
}

// Path field
function shopify_sitemap_path_field()
{
  $path = get_option('shopify_sitemap_path', 'sitemap.xml');
  echo '<input type="text" name="shopify_sitemap_path" value="' . esc_attr($path) . '" class="regular-text" />';
  echo '<p class="description">The path to the sitemap file (default: sitemap.xml)</p>';
}

// Output filename field
function shopify_sitemap_output_field()
{
  $filename = get_option('shopify_sitemap_output_filename', SHOPIFY_SITEMAP_DEFAULT_OUTPUT);
  echo '<input type="text" name="shopify_sitemap_output_filename" value="' . esc_attr($filename) . '" class="regular-text" />';
  echo '<p class="description">The filename for the generated sitemap (default: store.xml)</p>';
}

// Flatten sitemap field
function shopify_sitemap_flatten_field()
{
  $flatten = get_option('shopify_sitemap_flatten', 'no');
  echo '<label><input type="radio" name="shopify_sitemap_flatten" value="yes" ' . checked('yes', $flatten, false) . ' /> Yes</label>';
  echo ' <label><input type="radio" name="shopify_sitemap_flatten" value="no" ' . checked('no', $flatten, false) . ' /> No</label>';
  echo '<p class="description">For smaller websites, you can flatten the sitemap index into a single sitemap containing all URLs. This will fetch and combine all linked sitemaps.</p>';
}

// Handle admin actions
add_action('admin_init', 'shopify_sitemap_admin_actions');
function shopify_sitemap_admin_actions()
{
  if (
    isset($_GET['page']) && $_GET['page'] === 'shopify-sitemap' &&
    isset($_GET['action']) && $_GET['action'] === 'update' &&
    isset($_GET['_wpnonce'])
  ) {
    // Sanitize and validate the nonce
    $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));

    if (wp_verify_nonce($nonce, 'shopify_sitemap_update')) {
      if (function_exists('shopify_sitemap_log')) {
        shopify_sitemap_log('Manual update requested');
      }

      // Delete existing transient data
      delete_transient('shopify_sitemap_data');
      delete_transient('shopify_sitemap_is_index');

      // Run update function from sitemap.php
      $updated = function_exists('shopify_sitemap_update') ? shopify_sitemap_update() : false;

      if (function_exists('shopify_sitemap_log')) {
        shopify_sitemap_log('Manual update result: ' . ($updated ? 'success' : 'failed'));
      }

      // Redirect back with status
      $redirect = add_query_arg(array(
        'page' => 'shopify-sitemap',
        'updated' => $updated ? 'true' : 'false'
      ), admin_url('options-general.php'));

      wp_safe_redirect($redirect);
      exit;
    }
  }
}

// Add debug tab for local environments
add_action('admin_init', 'shopify_sitemap_maybe_add_debug_tab');
function shopify_sitemap_maybe_add_debug_tab()
{
  // Check if we're on a local environment
  $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
  $is_local = (
    strpos($domain, '.local') !== false ||
    strpos($domain, 'localhost') !== false ||
    strpos($domain, '.test') !== false ||
    strpos($domain, '.dev') !== false ||
    $domain === '127.0.0.1'
  );

  // Only add the debug tab on local environments and for admin users
  if ($is_local && current_user_can('manage_options')) {
    // Register a new settings section for debugging
    add_settings_section(
      'shopify_sitemap_debug',
      'Debug Information (Local Environment Only)',
      'shopify_sitemap_debug_section_text',
      'shopify_sitemap_settings'
    );
  }
}

// Debug section text
function shopify_sitemap_debug_section_text()
{
  // Display server information
  echo '<div class="shopify-sitemap-debug-info" style="background: #f8f8f8; padding: 10px; border: 1px solid #ddd;">';
  echo '<h3>Server Environment</h3>';
  echo '<ul>';
  echo '<li><strong>PHP Version:</strong> ' . PHP_VERSION . '</li>';
  echo '<li><strong>WordPress Version:</strong> ' . get_bloginfo('version') . '</li>';
  echo '<li><strong>Plugin Version:</strong> ' . SHOPIFY_SITEMAP_VERSION . '</li>';
  echo '<li><strong>Server:</strong> ' . (isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown') . '</li>';
  echo '</ul>';

  // Display transient data status
  echo '<h3>Sitemap Data Status</h3>';
  $data_exists = get_transient('shopify_sitemap_data') !== false;
  $is_index = get_transient('shopify_sitemap_is_index');

  echo '<ul>';
  echo '<li><strong>Data in Transient:</strong> ' . ($data_exists ? 'Yes' : 'No') . '</li>';
  echo '<li><strong>Is Sitemap Index:</strong> ' . ($is_index ? 'Yes' : 'No') . '</li>';
  echo '</ul>';

  // Add manual debug button
  echo '<p><a href="' . esc_url(wp_nonce_url(admin_url('options-general.php?page=shopify-sitemap&action=debug_log'), 'shopify_sitemap_debug')) . '" class="button">Generate Debug Log</a></p>';
  echo '</div>';
}
