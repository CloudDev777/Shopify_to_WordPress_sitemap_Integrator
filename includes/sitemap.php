<?php

/**
 * Sitemap functionality for Shopify Sitemap Integrator
 */

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
