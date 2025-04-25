<?php

/**
 * Custom sitemap provider for Shopify sitemap.
 */
class Shopify_Sitemap_Provider extends WP_Sitemaps_Provider
{

  /**
   * Provider name.
   *
   * @var string
   */
  public $name = 'shopify';

  /**
   * Constructor.
   */
  public function __construct()
  {
    $this->object_subtype_route_base = 'sitemap';

    // Custom sitemap name
    add_filter('wp_sitemaps_shopify_filename', array($this, 'custom_sitemap_filename'));
  }

  /**
   * Gets a URL list for a sitemap.
   *
   * @param int    $page_num       Page of results.
   * @param string $object_subtype Optional. Not applicable for this provider.
   * @return array Array of URLs for a sitemap.
   */
  public function get_url_list($page_num, $object_subtype = '')
  {
    $sitemap_data = get_transient('shopify_sitemap_data');

    if (!$sitemap_data || !is_array($sitemap_data)) {
      return array();
    }

    // Split the sitemap data into chunks for pagination
    $max_urls = $this->get_max_urls();
    $offset = ($page_num - 1) * $max_urls;

    if ($offset >= count($sitemap_data)) {
      return array();
    }

    $chunk = array_slice($sitemap_data, $offset, $max_urls);
    $url_list = array();

    foreach ($chunk as $item) {
      $entry = array(
        'loc' => $item['loc'],
      );

      if (!empty($item['lastmod'])) {
        $entry['lastmod'] = $item['lastmod'];
      }

      if (!empty($item['changefreq'])) {
        $entry['changefreq'] = $item['changefreq'];
      }

      if (!empty($item['priority'])) {
        $entry['priority'] = $item['priority'];
      }

      $url_list[] = $entry;
    }

    return $url_list;
  }

  /**
   * Gets the max number of URLs for a sitemap.
   *
   * @return int Maximum number of URLs.
   */
  public function get_max_urls()
  {
    /**
     * Filters the maximum number of URLs included in a sitemap page.
     *
     * @param int    $max_urls The maximum number of URLs included in a sitemap page.
     * @param string $provider_name The name of the sitemap provider.
     */
    return apply_filters('wp_sitemaps_max_urls', 2000, $this->name);
  }

  /**
   * Query for determining the number of pages in the sitemap.
   *
   * @param string $object_subtype Optional. Not applicable for this provider.
   * @return int Total number of pages.
   */
  public function get_max_num_pages($object_subtype = '')
  {
    $sitemap_data = get_transient('shopify_sitemap_data');

    if (!$sitemap_data || !is_array($sitemap_data)) {
      return 0;
    }

    $max_urls = $this->get_max_urls();

    return (int) ceil(count($sitemap_data) / $max_urls);
  }

  /**
   * Return the list of supported object subtypes.
   *
   * @return array Empty array as we don't use subtypes.
   */
  public function get_object_subtypes()
  {
    return array();
  }

  /**
   * Filter to customize the sitemap filename.
   *
   * @param string $filename Default filename.
   * @return string Custom filename.
   */
  public function custom_sitemap_filename($filename)
  {
    $custom_filename = get_option('shopify_sitemap_output_filename', SHOPIFY_SITEMAP_INTEGRATOR_DEFAULT_OUTPUT);

    if (!empty($custom_filename)) {
      return $custom_filename;
    }

    return $filename;
  }
}
