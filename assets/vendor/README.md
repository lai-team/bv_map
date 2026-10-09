# Vendored libraries

These are served from this domain rather than a CDN. The site runs a consent
manager (`complianz-gdpr`), and loading assets from `unpkg.com` /
`api.tiles.mapbox.com` sends every visitor's IP address to a third party before
consent is collected.

| File | Version | Source |
| --- | --- | --- |
| `mapbox-gl.js` | 2.4.0 | `https://api.tiles.mapbox.com/mapbox-gl-js/v2.4.0/mapbox-gl.js` |
| `mapbox-gl.css` | 2.4.0 | `https://api.tiles.mapbox.com/mapbox-gl-js/v2.4.0/mapbox-gl.css` |
| `turf.min.js` | 6.5.0 | `https://unpkg.com/@turf/turf@6.5.0/turf.min.js` |

The pinned versions are also recorded as `BV_MAP_MAPBOX_VER` and
`BV_MAP_TURF_VER` in `index.php`.

## Licensing

- **Mapbox GL JS 2.x** is not open source. It is covered by the
  [Mapbox Terms of Service](https://www.mapbox.com/legal/tos); self-hosting the
  bundle is permitted when it is used with Mapbox services and a valid access
  token, which is how this plugin uses it. Map loads are still billed to the
  account behind `MAPBOX_TOKEN`.
- **Turf.js** is MIT licensed.

## Updating

Re-download at the same path, bump the version in the table and in `index.php`,
and re-test. `bv_map_asset_version()` keys the cache on `filemtime()`, so a
replaced file busts caches on its own.

Turf is imported as the full bundle for roughly eight functions
(`bbox`, `multiPoint`, `explode`, `bearing`, `distance`, `destination`,
`booleanEqual`, `booleanWithin`, `buffer`, `bboxPolygon`, `lineString`). Trimming
it to per-function `@turf/*` packages would save ~550KB but needs a bundler,
which this project deliberately does not have.
