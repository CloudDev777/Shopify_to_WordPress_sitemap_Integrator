<?php

/**
 * Sitemap functionality for Shopify Sitemap Integrator
 */

/**
 * Generate the sitemap XML.
 */
function shopify_sitemap_generate_xml()
{
  // Debug information
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shopify Sitemap: Generating XML');
  }

  $sitemap_data = get_transient('shopify_sitemap_data');
  $is_index = get_transient('shopify_sitemap_is_index');
  $flatten = get_option('shopify_sitemap_flatten', 'no') === 'yes';

  if (empty($sitemap_data)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: No transient data found, attempting to update');
    }

    // Try to update the sitemap
    $update_result = shopify_sitemap_update();

    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: Update result: ' . ($update_result ? 'success' : 'failed'));
    }

    $sitemap_data = get_transient('shopify_sitemap_data');
    $is_index = get_transient('shopify_sitemap_is_index');

    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: After update, data exists: ' . (!empty($sitemap_data) ? 'yes' : 'no'));
      error_log('Shopify Sitemap: Is sitemap index: ' . ($is_index ? 'yes' : 'no'));
    }
  }

  header('Content-Type: application/xml; charset=UTF-8');
  ob_start();

  if ($is_index && !$flatten) {
    // Output sitemap index
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo '<!-- This is a sitemap index from your Shopify store -->' . "\n";

    if (!empty($sitemap_data) && is_array($sitemap_data)) {
      foreach ($sitemap_data as $sitemap) {
        echo "\t<sitemap>\n";
        echo "\t\t<loc>" . esc_url(isset($sitemap['loc']) ? $sitemap['loc'] : '') . "</loc>\n";

        if (!empty($sitemap['lastmod'])) {
          echo "\t\t<lastmod>" . esc_html($sitemap['lastmod']) . "</lastmod>\n";
        }

        echo "\t</sitemap>\n";
      }
    }

    echo '</sitemapindex>';
  } else {
    // Output standard sitemap
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    if (!empty($sitemap_data) && is_array($sitemap_data)) {
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Shopify Sitemap: Found ' . count($sitemap_data) . ' URLs in data');
      }

      foreach ($sitemap_data as $item) {
        echo "\t<url>\n";
        echo "\t\t<loc>" . esc_url(isset($item['loc']) ? $item['loc'] : '') . "</loc>\n";

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
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Shopify Sitemap: No sitemap data, using fallback');
      }

      // Fallback if no data
      echo "\t<url>\n";
      echo "\t\t<loc>" . esc_url(home_url()) . "</loc>\n";
      echo "\t\t<lastmod>" . esc_html(gmdate('Y-m-d')) . "</lastmod>\n";
      echo "\t\t<changefreq>daily</changefreq>\n";
      echo "\t\t<priority>1.0</priority>\n";
      echo "\t</url>\n";
    }

    echo '</urlset>';
  }

  return ob_get_clean();
}

/**
 * Update the Shopify sitemap.
 */
function shopify_sitemap_update()
{
  $domain = get_option('shopify_sitemap_domain', '');
  $path = get_option('shopify_sitemap_path', 'sitemap.xml');
  $flatten = get_option('shopify_sitemap_flatten', 'no') === 'yes';

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shopify Sitemap: Attempting to update sitemap from ' . $domain . '/' . $path);
    error_log('Shopify Sitemap: Flatten option is: ' . ($flatten ? 'enabled' : 'disabled'));
  }

  if (empty($domain)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: No domain configured');
    }
    return false;
  }

  // Fetch the sitemap
  $response = wp_remote_get('https://' . $domain . '/' . $path, array(
    'timeout' => 30,
    'user-agent' => 'WordPress/Shopify-Sitemap-Integrator'
  ));

  if (is_wp_error($response)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: WP Error fetching sitemap: ' . $response->get_error_message());
    }
    return false;
  }

  $status_code = wp_remote_retrieve_response_code($response);
  if ($status_code !== 200) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: HTTP error fetching sitemap. Status code: ' . $status_code);
    }
    return false;
  }

  $xml = wp_remote_retrieve_body($response);

  if (empty($xml)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: Empty response body from Shopify');
    }
    return false;
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shopify Sitemap: Fetched XML body length: ' . strlen($xml));
    error_log('Shopify Sitemap: First 100 chars: ' . substr($xml, 0, 100));
  }

  // First, check if this is a sitemap index or regular sitemap
  $is_index = false;
  if (is_string($xml) && strpos($xml, '<sitemapindex') !== false) {
    $is_index = true;
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: Detected sitemap index');
    }
  }

  // Store whether this is an index
  set_transient('shopify_sitemap_is_index', $is_index, DAY_IN_SECONDS);

  // Parse the XML accordingly
  if ($is_index) {
    $sitemap_data = shopify_sitemap_parse_index($xml);

    if ($flatten && !empty($sitemap_data)) {
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Shopify Sitemap: Flattening sitemap index with ' . count($sitemap_data) . ' sitemaps');
      }

      // Fetch and parse all linked sitemaps
      $flattened_urls = shopify_sitemap_flatten_index($sitemap_data);

      if (!empty($flattened_urls)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
          error_log('Shopify Sitemap: Successfully flattened to ' . count($flattened_urls) . ' URLs');
        }

        // Store flattened data
        set_transient('shopify_sitemap_data', $flattened_urls, DAY_IN_SECONDS);
        return true;
      } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
          error_log('Shopify Sitemap: Failed to flatten sitemap index');
        }
        return false;
      }
    }
  } else {
    $sitemap_data = shopify_sitemap_parse_sitemap($xml);
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shopify Sitemap: Parsed ' . count($sitemap_data) . ' entries from XML (type: ' . ($is_index ? 'index' : 'sitemap') . ')');
  }

  // Store the data
  if (!empty($sitemap_data)) {
    set_transient('shopify_sitemap_data', $sitemap_data, DAY_IN_SECONDS);
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: Successfully set transient with sitemap data');
    }
    return true;
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shopify Sitemap: Failed to set transient - no sitemap data found');
  }
  return false;
}

/**
 * Flatten a sitemap index by fetching all linked sitemaps.
 */
function shopify_sitemap_flatten_index($sitemap_entries)
{
  $all_urls = array();

  if (!is_array($sitemap_entries)) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: Invalid sitemap entries passed to flatten_index');
    }
    return $all_urls;
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shopify Sitemap: Starting flattening of ' . count($sitemap_entries) . ' sitemaps');
  }

  foreach ($sitemap_entries as $sitemap) {
    if (empty($sitemap['loc'])) {
      continue;
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: Fetching linked sitemap: ' . $sitemap['loc']);
    }

    // Fetch the linked sitemap
    $response = wp_remote_get($sitemap['loc'], array(
      'timeout' => 30,
      'user-agent' => 'WordPress/Shopify-Sitemap-Integrator'
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Shopify Sitemap: Failed to fetch linked sitemap: ' . $sitemap['loc']);
      }
      continue;
    }

    $xml = wp_remote_retrieve_body($response);

    if (empty($xml)) {
      continue;
    }

    // Parse the sitemap
    $urls = shopify_sitemap_parse_sitemap($xml);

    if (!empty($urls)) {
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Shopify Sitemap: Found ' . count($urls) . ' URLs in linked sitemap');
      }

      // Add URLs to the combined array
      $all_urls = array_merge($all_urls, $urls);
    }
  }

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shopify Sitemap: Total flattened URLs: ' . count($all_urls));
  }

  return $all_urls;
}

/**
 * Parse sitemap index XML.
 */
function shopify_sitemap_parse_index($xml)
{
  if (empty($xml) || !is_string($xml)) {
    return array();
  }

  libxml_use_internal_errors(true);

  $sitemaps = array();
  $dom = new DOMDocument();

  if (@$dom->loadXML($xml)) {
    $sitemap_nodes = $dom->getElementsByTagName('sitemap');

    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: This appears to be a sitemap index file with ' . $sitemap_nodes->length . ' sitemaps');
    }

    if ($sitemap_nodes->length > 0) {
      foreach ($sitemap_nodes as $sitemap_node) {
        $loc_nodes = $sitemap_node->getElementsByTagName('loc');

        if ($loc_nodes->length > 0) {
          $sitemap = array(
            'loc' => $loc_nodes->item(0)->nodeValue,
            'lastmod' => '',
          );

          $lastmod_nodes = $sitemap_node->getElementsByTagName('lastmod');
          if ($lastmod_nodes->length > 0) {
            $sitemap['lastmod'] = $lastmod_nodes->item(0)->nodeValue;
          }

          $sitemaps[] = $sitemap;
        }
      }
    }
  } else {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      $errors = libxml_get_errors();
      foreach ($errors as $error) {
        error_log('Shopify Sitemap XML Error: ' . $error->message);
      }
    }
  }

  libxml_clear_errors();

  return $sitemaps;
}

/**
 * Parse regular sitemap XML.
 */
function shopify_sitemap_parse_sitemap($xml)
{
  if (empty($xml) || !is_string($xml)) {
    return array();
  }

  libxml_use_internal_errors(true);

  $urls = array();
  $dom = new DOMDocument();

  if (@$dom->loadXML($xml)) {
    $url_nodes = $dom->getElementsByTagName('url');

    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('Shopify Sitemap: Found ' . $url_nodes->length . ' URL nodes in XML');
    }

    if ($url_nodes->length > 0) {
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
  } else {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      $errors = libxml_get_errors();
      foreach ($errors as $error) {
        error_log('Shopify Sitemap XML Error: ' . $error->message);
      }
    }
  }

  libxml_clear_errors();

  return $urls;
}
