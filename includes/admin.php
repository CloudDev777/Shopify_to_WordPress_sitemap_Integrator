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
    <a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=shopify-sitemap&action=update'), 'shopify_sitemap_update')); ?>" class="button button-primary">Update Sitemap Now</a>
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
      // Run update function from sitemap.php
      $updated = shopify_sitemap_update();

      // Redirect back with status
      $redirect = add_query_arg(array(
        'page' => 'shopify-sitemap',
        'updated' => $updated ? 'true' : 'false'
      ), admin_url('options-general.php'));

      wp_redirect($redirect);
      exit;
    }
  }
}
