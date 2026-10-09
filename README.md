# BV Map

A WordPress plugin that renders geotagged posts on a Mapbox GL map. Posts whose
featured image carries geo metadata are plotted as markers and joined into
journey lines, with points loaded incrementally as the visitor pans and zooms.

Built for the beauVoyage travel-story site.

## Requirements

- WordPress with a theme that supports shortcodes/widgets
- PHP 7.4+
- [WP-GeoUtil](https://github.com/cimburadotcom/WP-GeoMeta-Lib) — the plugin
  calls `WP_GeoUtil::*` and relies on `ST_INTERSECTS` meta queries, so a spatial
  meta layer (WP GeoMeta Lib) must be active
- A MySQL/MariaDB database with spatial support
- A Mapbox account and access token

## Installation

1. Copy or clone this directory into `wp-content/plugins/bv_map`.
2. Define the required constants (see below) in `wp-config.php`.
3. Activate **BV Map** from the WordPress plugins screen.

## Configuration

The plugin reads two constants that are **not** defined in this repository.
Define them in `wp-config.php` so no credentials live in version control:

```php
define( 'MAPBOX_TOKEN', 'pk.your_mapbox_public_token' );
define( 'MAPBOX_STYLE_OUTDOOR', 'your-account/your-style-id' );
```

`MAPBOX_STYLE_OUTDOOR` is appended to `mapbox://styles/`, so pass only the
`account/style-id` portion.

Library versions are pinned in `index.php`:

| Constant      | Default   | Purpose                   |
| ------------- | --------- | ------------------------- |
| `MAPBOX_VER`  | `v2.4.0`  | mapbox-gl-js from the CDN |
| `TURF_VER`    | `6.5.0`   | Turf.js from unpkg        |

## Usage

### Shortcodes

```
[bv_map_shortcode]                          Render the map
[bv_map_shortcode post_id="123" height=600] Scope to a post, set pixel height
[bv_list_shortcode]                         Render the story list view
```

### Widget

Registers **beauVoyage Map**, which simply outputs `[bv_map_shortcode]`.

### Block editor

Registers the `bv-map/map-block` Gutenberg block ("Map Block"), which emits the
`#map` container the frontend script mounts onto.

## Post data model

Each mapped post is expected to carry:

| Meta key              | Holds                                                  |
| --------------------- | ------------------------------------------------------ |
| `geom_point`          | GeoJSON point for the post or its featured image        |
| `geom_line2__<catID>` | GeoJSON line joining the post to others in that category |
| `datetime`            | Timestamp on the attachment, used to order journey legs |
| `route_style`         | Per-post route styling                                  |
| `small_map_image_size`| Marker size in rem                                      |

Categories act as journeys; `$GLOBALS['special_categories']` marks categories
that are excluded from journey grouping and must be set by the theme.

## Layout

```
index.php                  Plugin bootstrap: shortcodes, widget, block, asset enqueue
map.php                    Marker sizing partial
list-stories-view.php      Story list template ([bv_list_shortcode])
includes/spatialquery.php  Spatial WP_Query helpers (bv_query_rectangle, bv_bufferbox)
includes/ajax.php          Bounding-box endpoint returning points in view
includes/ajax_geo.php      Bounding-box endpoint, category/journey aware
assets/js/main.js          Map setup, incremental loading, line drawing
assets/js/block.js         Gutenberg block registration
assets/css/style.css       Frontend map styles
docs/NOTES.md              Development notes on the incremental loading strategy
```

## Known rough edges

- `includes/ajax.php` and `includes/ajax_geo.php` bootstrap WordPress by
  path-munging `__DIR__` to locate `wp-load.php` rather than going through
  `admin-ajax.php` or the REST API, and they consume `$_POST` without
  sanitisation or a nonce check. Worth migrating to a registered REST route.
- `assets/js/temp.js` and `assets/js/elementor-class.js` are scratch files and
  are not enqueued.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Credits

Originally authored by Gentrit Biba; maintained by the Lai Consulting Team.
Derived in part from [digfish/geotagged-media](https://github.com/digfish/geotagged-media).
