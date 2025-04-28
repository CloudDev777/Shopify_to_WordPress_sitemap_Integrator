# Shopify Sitemap Integrator

A WordPress plugin that fetches a Shopify sitemap and integrates it with your WordPress site.

## Description

Shopify Sitemap Integrator creates a bridge between your Shopify store and WordPress website by importing your Shopify sitemap and making it available through your WordPress site. This plugin solves the common problem of maintaining consistent sitemaps across multiple platforms.

## Features

- Fetch any Shopify sitemap automatically
- Add the Shopify sitemap to your WordPress site as a custom XML file
- Configurable update frequency (hourly, twice daily, daily, weekly)
- Customizable output filename (default: store.xml)
- Support for both simple sitemaps and sitemap indexes
- Manual update option for immediate synchronization

## Installation

1. Upload the `shopify-sitemap-integrator` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Shopify Sitemap to configure
4. Visit your Permalinks settings page and click "Save Changes" to properly register the sitemap URL

## Configuration

### Required Settings

- **Shopify Domain**: Your Shopify store domain without http:// or https:// (e.g., your-store.myshopify.com)
- **Sitemap Path**: The path to your Shopify sitemap file (default: sitemap.xml)

### Optional Settings

- **Update Frequency**: How often the sitemap should be updated (default: daily)
- **Output Filename**: The filename for the generated sitemap on your WordPress site (default: store.xml)

## Usage

Once configured, your imported Shopify sitemap will be available at:

```
https://your-wordpress-site.com/store.xml
```

You can change the output filename in the plugin settings if desired.

## Troubleshooting

### "Page not found" when accessing the sitemap

If you receive a "Page not found" error when trying to access your sitemap, try:

1. Go to Settings → Permalinks
2. Click "Save Changes" without making any changes
3. This refreshes WordPress's rewrite rules and should resolve the issue

### Empty sitemap or missing products

1. Check that your Shopify domain is entered correctly
2. Verify that the sitemap path is correct
3. Try clicking "Update Sitemap Now" on the plugin settings page
4. Check your Shopify store to ensure the original sitemap is accessible

## License

This plugin is licensed under the [GPL v2 or later](http://www.gnu.org/licenses/gpl-2.0.html).

## Author

Created by [Gabriel Kanev](https://gkanev.com)

## Tested up to

WordPress 6.8
