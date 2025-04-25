<?php

/**
 * Plugin Name: Shopify Sitemap Integrator
 * Plugin URI: https://example.com/plugins/shopify-sitemap-integrator
 * Description: Fetches a Shopify sitemap and adds it to your WordPress site.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: shopify-sitemap-integrator
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}

// Define plugin constants
define('SHOPIFY_SITEMAP_INTEGRATOR_VERSION', '1.0.0');
define('SHOPIFY_SITEMAP_INTEGRATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SHOPIFY_SITEMAP_INTEGRATOR_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The core plugin class.
 */
class Shopify_Sitemap_Integrator
{

  /**
   * Initialize the plugin.
   */
  public function __construct()
  {
    add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
    add_action('admin_init', array($this, 'register_settings'));
    add_action('init', array($this, 'init_sitemap_integration'));
    add_action('shopify_sitemap_integrator_cron', array($this, 'update_sitemap'));
    add_action('admin_notices', array($this, 'admin_notices'));

    // Handle admin actions
    add_action('admin_init', array($this, 'handle_admin_actions'));
  }

  /**
   * Initialize sitemap integration.
   */
  public function init_sitemap_integration()
  {
    // Only register sitemap provider if WP core sitemaps are enabled
    if (function_exists('wp_sitemaps_get_server')) {
      add_filter('wp_sitemaps_add_provider', array($this, 'add_sitemap_provider'));
    }
  }

  /**
   * Add sitemap provider to WordPress.
   */
  public function add_sitemap_provider($providers)
  {
    require_once SHOPIFY_SITEMAP_INTEGRATOR_PLUGIN_DIR . 'class-shopify-sitemap-provider.php';
    $providers['shopify'] = new Shopify_Sitemap_Provider();
    return $providers;
  }

  /**
   * Add plugin admin menu.
   */
  public function add_plugin_admin_menu()
  {
    add_options_page(
      __('Shopify Sitemap Integrator Settings', 'shopify-sitemap-integrator'),
      __('Shopify Sitemap', 'shopify-sitemap-integrator'),
      'manage_options',
      'shopify-sitemap-integrator',
      array($this, 'display_plugin_admin_page')
    );
  }

  /**
   * Display the admin page content.
   */
  public function display_plugin_admin_page()
  {
?>
    <div class="wrap">
      <h1><?php echo esc_html__('Shopify Sitemap Integrator', 'shopify-sitemap-integrator'); ?></h1>
      <form method="post" action="options.php">
        <?php
        settings_fields('shopify_sitemap_integrator_options');
        do_settings_sections('shopify_sitemap_integrator_settings');
        submit_button();
        ?>
      </form>

      <h2><?php echo esc_html__('Manual Update', 'shopify-sitemap-integrator'); ?></h2>
      <p><?php echo esc_html__('Click the button below to update the sitemap immediately.', 'shopify-sitemap-integrator'); ?></p>

      <?php
      $nonce_url = wp_nonce_url(
        add_query_arg(
          array(
            'page' => 'shopify-sitemap-integrator',
            'action' => 'update_sitemap',
          ),
          admin_url('options-general.php')
        ),
        'shopify_sitemap_update_nonce'
      );
      ?>

      <a href="<?php echo esc_url($nonce_url); ?>" class="button button-primary">
        <?php echo esc_html__('Update Sitemap Now', 'shopify-sitemap-integrator'); ?>
      </a>
    </div>
  <?php
  }

  /**
   * Register plugin settings.
   */
  public function register_settings()
  {
    register_setting(
      'shopify_sitemap_integrator_options',
      'shopify_sitemap_domain',
      array(
        'sanitize_callback' => array($this, 'sanitize_domain'),
      )
    );

    register_setting(
      'shopify_sitemap_integrator_options',
      'shopify_sitemap_frequency',
      array(
        'sanitize_callback' => 'sanitize_text_field',
      )
    );

    register_setting(
      'shopify_sitemap_integrator_options',
      'shopify_sitemap_path',
      array(
        'sanitize_callback' => array($this, 'sanitize_path'),
        'default' => 'sitemap.xml',
      )
    );

    add_settings_section(
      'shopify_sitemap_integrator_general_settings',
      __('General Settings', 'shopify-sitemap-integrator'),
      array($this, 'display_general_section'),
      'shopify_sitemap_integrator_settings'
    );

    add_settings_field(
      'shopify_sitemap_domain',
      __('Shopify Domain', 'shopify-sitemap-integrator'),
      array($this, 'display_domain_field'),
      'shopify_sitemap_integrator_settings',
      'shopify_sitemap_integrator_general_settings'
    );

    add_settings_field(
      'shopify_sitemap_path',
      __('Sitemap Path', 'shopify-sitemap-integrator'),
      array($this, 'display_path_field'),
      'shopify_sitemap_integrator_settings',
      'shopify_sitemap_integrator_general_settings'
    );

    add_settings_field(
      'shopify_sitemap_frequency',
      __('Update Frequency', 'shopify-sitemap-integrator'),
      array($this, 'display_frequency_field'),
      'shopify_sitemap_integrator_settings',
      'shopify_sitemap_integrator_general_settings'
    );
  }

  /**
   * Display general section info.
   */
  public function display_general_section()
  {
    echo '<p>' . esc_html__('Configure the Shopify sitemap integration settings below.', 'shopify-sitemap-integrator') . '</p>';
  }

  /**
   * Display domain field.
   */
  public function display_domain_field()
  {
    $domain = get_option('shopify_sitemap_domain', '');
  ?>
    <input type="text" name="shopify_sitemap_domain" id="shopify_sitemap_domain"
      value="<?php echo esc_attr($domain); ?>" class="regular-text" />
    <p class="description">
      <?php echo esc_html__('Enter the Shopify domain without http:// or https:// (e.g., your-store.myshopify.com)', 'shopify-sitemap-integrator'); ?>
    </p>
  <?php
  }

  /**
   * Display path field.
   */
  public function display_path_field()
  {
    $path = get_option('shopify_sitemap_path', 'sitemap.xml');
  ?>
    <input type="text" name="shopify_sitemap_path" id="shopify_sitemap_path"
      value="<?php echo esc_attr($path); ?>" class="regular-text" />
    <p class="description">
      <?php echo esc_html__('The path to the sitemap file (default: sitemap.xml)', 'shopify-sitemap-integrator'); ?>
    </p>
  <?php
  }

  /**
   * Display frequency field.
   */
  public function display_frequency_field()
  {
    $frequency = get_option('shopify_sitemap_frequency', 'daily');
  ?>
    <select name="shopify_sitemap_frequency" id="shopify_sitemap_frequency">
      <option value="hourly" <?php selected($frequency, 'hourly'); ?>><?php echo esc_html__('Hourly', 'shopify-sitemap-integrator'); ?></option>
      <option value="twicedaily" <?php selected($frequency, 'twicedaily'); ?>><?php echo esc_html__('Twice Daily', 'shopify-sitemap-integrator'); ?></option>
      <option value="daily" <?php selected($frequency, 'daily'); ?>><?php echo esc_html__('Daily', 'shopify-sitemap-integrator'); ?></option>
      <option value="weekly" <?php selected($frequency, 'weekly'); ?>><?php echo esc_html__('Weekly', 'shopify-sitemap-integrator'); ?></option>
    </select>
    <p class="description">
      <?php echo esc_html__('How often should the sitemap be updated', 'shopify-sitemap-integrator'); ?>
    </p>
    <?php
  }

  /**
   * Sanitize domain input.
   */
  public function sanitize_domain($domain)
  {
    // Remove http:// or https:// if present
    $domain = preg_replace('#^https?://#', '', $domain);

    // Remove trailing slash if present
    $domain = rtrim($domain, '/');

    return sanitize_text_field($domain);
  }

  /**
   * Sanitize path input.
   */
  public function sanitize_path($path)
  {
    // Remove leading slash if present
    $path = ltrim($path, '/');

    return sanitize_text_field($path);
  }

  /**
   * Handle admin actions.
   */
  public function handle_admin_actions()
  {
    if (!isset($_GET['page']) || $_GET['page'] !== 'shopify-sitemap-integrator') {
      return;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'update_sitemap') {
      // Verify nonce
      if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'shopify_sitemap_update_nonce')) {
        wp_die(__('Security check failed.', 'shopify-sitemap-integrator'));
      }

      // Update sitemap
      $result = $this->update_sitemap();

      // Redirect back to the settings page with a status message
      $redirect_url = add_query_arg(
        array(
          'page' => 'shopify-sitemap-integrator',
          'update' => is_wp_error($result) ? 'error' : 'success',
          'message' => is_wp_error($result) ? urlencode($result->get_error_message()) : '',
        ),
        admin_url('options-general.php')
      );

      wp_redirect($redirect_url);
      exit;
    }

    // Check for frequency change
    if (isset($_POST['shopify_sitemap_frequency'])) {
      $old_frequency = get_option('shopify_sitemap_frequency', 'daily');
      $new_frequency = sanitize_text_field($_POST['shopify_sitemap_frequency']);

      if ($old_frequency !== $new_frequency) {
        $this->update_cron_schedule($new_frequency);
      }
    }
  }

  /**
   * Update the cron schedule.
   */
  public function update_cron_schedule($frequency)
  {
    // Clear existing schedule
    $timestamp = wp_next_scheduled('shopify_sitemap_integrator_cron');

    if ($timestamp) {
      wp_unschedule_event($timestamp, 'shopify_sitemap_integrator_cron');
    }

    // Set new schedule
    wp_schedule_event(time(), $frequency, 'shopify_sitemap_integrator_cron');
  }

  /**
   * Display admin notices.
   */
  public function admin_notices()
  {
    if (!isset($_GET['page']) || $_GET['page'] !== 'shopify-sitemap-integrator') {
      return;
    }

    if (isset($_GET['update']) && $_GET['update'] === 'success') {
    ?>
      <div class="notice notice-success is-dismissible">
        <p><?php echo esc_html__('Sitemap updated successfully.', 'shopify-sitemap-integrator'); ?></p>
      </div>
    <?php
    } elseif (isset($_GET['update']) && $_GET['update'] === 'error') {
      $message = isset($_GET['message']) ? urldecode($_GET['message']) : __('Failed to update sitemap.', 'shopify-sitemap-integrator');
    ?>
      <div class="notice notice-error is-dismissible">
        <p><?php echo esc_html($message); ?></p>
      </div>
<?php
    }
  }

  /**
   * Update the Shopify sitemap.
   *
   * @return bool|WP_Error True on success, WP_Error on failure.
   */
  public function update_sitemap()
  {
    $domain = get_option('shopify_sitemap_domain', '');
    $path = get_option('shopify_sitemap_path', 'sitemap.xml');

    if (empty($domain)) {
      return new WP_Error(
        'empty_domain',
        __('Shopify domain is not configured.', 'shopify-sitemap-integrator')
      );
    }

    // Construct sitemap URL
    $sitemap_url = 'https://' . $domain . '/' . $path;

    // Fetch the sitemap
    $response = wp_remote_get($sitemap_url, array(
      'timeout' => 30,
      'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
    ));

    if (is_wp_error($response)) {
      error_log('Shopify Sitemap Integrator: ' . $response->get_error_message());
      return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);

    if ($response_code !== 200) {
      $error = new WP_Error(
        'fetch_error',
        sprintf(
          __('Failed to fetch sitemap. Response code: %s', 'shopify-sitemap-integrator'),
          $response_code
        )
      );
      error_log('Shopify Sitemap Integrator: ' . $error->get_error_message());
      return $error;
    }

    $xml = wp_remote_retrieve_body($response);

    // Parse and store the sitemap data
    $sitemap_data = $this->parse_sitemap_xml($xml);

    if (is_wp_error($sitemap_data)) {
      error_log('Shopify Sitemap Integrator: ' . $sitemap_data->get_error_message());
      return $sitemap_data;
    }

    // Store sitemap data in a transient
    $expiration = $this->get_transient_expiration();
    set_transient('shopify_sitemap_data', $sitemap_data, $expiration);

    return true;
  }

  /**
   * Parse sitemap XML into usable array.
   *
   * @param string $xml Sitemap XML string.
   * @return array|WP_Error Array of URLs or WP_Error on failure.
   */
  private function parse_sitemap_xml($xml)
  {
    libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    $result = $dom->loadXML($xml);

    if (!$result) {
      $errors = libxml_get_errors();
      libxml_clear_errors();

      $error_msg = __('Failed to parse sitemap XML.', 'shopify-sitemap-integrator');

      if (!empty($errors)) {
        $error_msg .= ' ' . $errors[0]->message;
      }

      return new WP_Error('parse_error', $error_msg);
    }

    $urls = array();

    // Check if this is a sitemap index
    $sitemaps = $dom->getElementsByTagName('sitemap');

    if ($sitemaps->length > 0) {
      // This is a sitemap index, fetch each individual sitemap
      foreach ($sitemaps as $sitemap) {
        $loc_nodes = $sitemap->getElementsByTagName('loc');

        if ($loc_nodes->length > 0) {
          $sitemap_url = $loc_nodes->item(0)->nodeValue;
          $sub_urls = $this->fetch_sub_sitemap($sitemap_url);

          if (!is_wp_error($sub_urls)) {
            $urls = array_merge($urls, $sub_urls);
          }
        }
      }
    } else {
      // This is a regular sitemap
      $url_nodes = $dom->getElementsByTagName('url');

      foreach ($url_nodes as $url_node) {
        $loc_nodes = $url_node->getElementsByTagName('loc');

        if ($loc_nodes->length > 0) {
          $url = $loc_nodes->item(0)->nodeValue;

          $lastmod = '';
          $lastmod_nodes = $url_node->getElementsByTagName('lastmod');
          if ($lastmod_nodes->length > 0) {
            $lastmod = $lastmod_nodes->item(0)->nodeValue;
          }

          $changefreq = '';
          $changefreq_nodes = $url_node->getElementsByTagName('changefreq');
          if ($changefreq_nodes->length > 0) {
            $changefreq = $changefreq_nodes->item(0)->nodeValue;
          }

          $priority = '';
          $priority_nodes = $url_node->getElementsByTagName('priority');
          if ($priority_nodes->length > 0) {
            $priority = $priority_nodes->item(0)->nodeValue;
          }

          $urls[] = array(
            'loc' => $url,
            'lastmod' => $lastmod,
            'changefreq' => $changefreq,
            'priority' => $priority,
          );
        }
      }
    }

    return $urls;
  }

  /**
   * Fetch a sub-sitemap from a sitemap index.
   *
   * @param string $sitemap_url URL of the sub-sitemap.
   * @return array|WP_Error Array of URLs or WP_Error on failure.
   */
  private function fetch_sub_sitemap($sitemap_url)
  {
    $response = wp_remote_get($sitemap_url, array(
      'timeout' => 30,
      'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
    ));

    if (is_wp_error($response)) {
      return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);

    if ($response_code !== 200) {
      return new WP_Error(
        'fetch_error',
        sprintf(
          __('Failed to fetch sub-sitemap. Response code: %s', 'shopify-sitemap-integrator'),
          $response_code
        )
      );
    }

    $xml = wp_remote_retrieve_body($response);

    return $this->parse_sitemap_xml($xml);
  }

  /**
   * Get the appropriate transient expiration time based on the update frequency.
   *
   * @return int Expiration time in seconds.
   */
  private function get_transient_expiration()
  {
    $frequency = get_option('shopify_sitemap_frequency', 'daily');

    switch ($frequency) {
      case 'hourly':
        return HOUR_IN_SECONDS;
      case 'twicedaily':
        return 12 * HOUR_IN_SECONDS;
      case 'weekly':
        return WEEK_IN_SECONDS;
      case 'daily':
      default:
        return DAY_IN_SECONDS;
    }
  }

  /**
   * Activate the plugin.
   */
  public static function activate()
  {
    // Setup default options if they don't exist
    if (!get_option('shopify_sitemap_domain')) {
      add_option('shopify_sitemap_domain', '');
    }

    if (!get_option('shopify_sitemap_frequency')) {
      add_option('shopify_sitemap_frequency', 'daily');
    }

    if (!get_option('shopify_sitemap_path')) {
      add_option('shopify_sitemap_path', 'sitemap.xml');
    }

    // Schedule the cron event
    if (!wp_next_scheduled('shopify_sitemap_integrator_cron')) {
      $frequency = get_option('shopify_sitemap_frequency', 'daily');
      wp_schedule_event(time(), $frequency, 'shopify_sitemap_integrator_cron');
    }

    // Run initial sitemap update
    $instance = new self();
    $instance->update_sitemap();
  }

  /**
   * Deactivate the plugin.
   */
  public static function deactivate()
  {
    // Clear scheduled events
    $timestamp = wp_next_scheduled('shopify_sitemap_integrator_cron');

    if ($timestamp) {
      wp_unschedule_event($timestamp, 'shopify_sitemap_integrator_cron');
    }

    // Delete transient data
    delete_transient('shopify_sitemap_data');
  }
}

/**
 * Initialize the plugin.
 */
function shopify_sitemap_integrator_init()
{
  $shopify_sitemap = new Shopify_Sitemap_Integrator();
}
add_action('plugins_loaded', 'shopify_sitemap_integrator_init');

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('Shopify_Sitemap_Integrator', 'activate'));
register_deactivation_hook(__FILE__, array('Shopify_Sitemap_Integrator', 'deactivate'));
