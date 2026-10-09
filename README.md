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
excluded from journey grouping. It is published by the `bv_geotagged_media`
plugin, and read here through `bv_map_special_categories()`, which falls back to
an empty array when that plugin is inactive.

## HTTP API

`POST /wp-json/bv-map/v1/points`

Returns the GeoJSON features intersecting a viewport. Send `X-WP-Nonce` — a
logged-in member is only recognised as one when the nonce authenticates the
request, and members see posts in the restricted category tree that anonymous
callers do not.

```json
{ "box_to_load": { "type": "Feature", "geometry": { … } },
  "term_id": 42, "taxonomy": "category", "slug": "some-journey" }
```

Membership is resolved server-side from the capability `read_private_posts`;
there is deliberately no request parameter for it.

## Layout

```
index.php                   Bootstrap: constants, requires, dependency notices
includes/compat.php         Guarded wrappers for theme/ACF/GeoMeta symbols
includes/geo-query.php      Spatial WP_Query helpers (bv_query_rectangle, bv_bufferbox)
includes/geo-data.php       Posts -> GeoJSON features (bv_get_geompoint, …)
includes/rest.php           bv-map/v1/points endpoint
includes/assets.php         Script/style registration and the bvVarMap payload
includes/shortcodes.php     [bv_map_shortcode], [bv_list_shortcode]
includes/widget.php         beauVoyage Map widget
includes/block.php          Block editor assets
templates/list-stories.php  Story list markup
assets/js/main.js           Map setup, incremental loading, line drawing
assets/js/block.js          Block registration
assets/css/style.css        Frontend map styles
assets/vendor/              Self-hosted Mapbox GL + Turf (see its README)
docs/NOTES.md               Notes on the incremental loading strategy
```

## Conventions

- `index.php` must keep its filename. WordPress identifies a plugin by
  `<folder>/<file>.php`, so renaming it deactivates the installed plugin.
- Every `bv_*` function name is public API. `digital-nomad-child` calls
  `bv_get_geompoint()`, `9to5voyage` calls `bv_get_attachment_map_data()` and
  reads `BV_MAP_PLUGIN_URL`.
- `assets/js/main.js` intentionally exports two globals, `map` and
  `bvVarMapGeoJson`. The theme's `list-stories.js` calls `map.fitBounds()` /
  `map.flyTo()` and searches `bvVarMapGeoJson.features`.
- There is no build step; `main.js` is hand-maintained ES6 and ships as-is.

## Known rough edges

- The waypoints/itinerary feature is inert: `bv_get_geom_futureevents()` has had
  its body commented out for a long time, so it returns an empty array and the
  `itinerary`, `waypoint-circles` and `poi-labels` layers never receive data.
  Reviving it means restoring the `tribe_events()` query and checking it against
  the installed The Events Calendar.
- The block's `save()` emits a bare `#map` container but nothing enqueues the map
  script for it, so a page containing only the block renders an empty div. The
  shortcode and widget are the working entry points.
- `isImage()` / `isVideo()` are defined in `includes/compat.php` because the
  copies in `bv_geotagged_media` live in a `.php_gentrit` file WordPress never
  loads.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Credits

Originally authored by Gentrit Biba; maintained by the Lai Consulting Team.
Derived in part from [digfish/geotagged-media](https://github.com/digfish/geotagged-media).
