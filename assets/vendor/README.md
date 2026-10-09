# Vendored libraries

These are served from this domain rather than a CDN. The site runs a consent
manager (`complianz-gdpr`), and loading assets from `unpkg.com` /
`api.mapbox.com` sends every visitor's IP address to a third party before
consent is collected.

| File | Version | Source |
| --- | --- | --- |
| `mapbox-gl.js` | 3.32.0 | `https://api.mapbox.com/mapbox-gl-js/v3.32.0/mapbox-gl.js` |
| `mapbox-gl.css` | 3.32.0 | `https://api.mapbox.com/mapbox-gl-js/v3.32.0/mapbox-gl.css` |
| `turf.min.js` | 7.4.0 | `https://unpkg.com/@turf/turf@7.4.0/turf.min.js` |

The pinned versions are also recorded as `BV_MAP_MAPBOX_VER` and
`BV_MAP_TURF_VER` in `index.php`. Mapbox GL JS v3 is served from
`api.mapbox.com`; `api.tiles.mapbox.com` was the v2 host.

## Why Mapbox was stuck on 2.4.0 until now

For years this plugin pinned Mapbox GL JS to 2.4.0, with a commented-out
`//define('MAPBOX_VER','v3.13.0')` beside it — the fossil of an upgrade that
was reverted. Newer versions made the map thumbnails misbehave while the camera
moved: scrolling the story column makes the theme call `map.flyTo()`, and
markers would flicker or vanish.

The cause was in this plugin, not in Mapbox. `updateMarkers()` was bound to the
map's `move` event and rebuilt the entire HTML marker set from
`map.querySourceFeatures( 'stories' )`. That function only reports features from
tiles that are **currently rendered**, so the marker set was coupled to tile
lifecycle. Mid-flight, fewer tiles are resident, the query returns less, and
markers were removed as a result.

Two things make this unnecessary:

- `mapboxgl.Marker` registers its own `move` handler in `addTo()` and
  repositions itself every frame. The `move` binding was never doing the
  positioning.
- `idle` fires once the camera has stopped **and** every requested tile has
  loaded — the only moment `querySourceFeatures()` gives a complete answer.

So `updateMarkers()` is now bound to `idle`, with a guard that treats an empty
query result as "ask again later" rather than "remove everything" whenever the
source still holds features.

### Measured

Mid-flight churn = the number of times the live marker count changes between
consecutive in-flight samples across three `flyTo` legs. Legitimate change
happens when the camera *settles* at a new zoom; change during flight is
markers being dropped. Measured in Chrome against the live site, all four
combinations under identical conditions:

| mid-flight churn | `move`-bound (old) | `idle`-bound (new) |
| --- | --- | --- |
| Mapbox 2.4.0 | 5 | **0** |
| Mapbox 3.32.0 | 3 | **0** |

Position drift between a marker's rendered position and
`map.project( marker.getLngLat() )` was 0px in every configuration — markers
were never mispositioned, only removed.

Note the churn was never specific to v3: 2.4.0 churned too. The fix removes it
on both, which is what made the upgrade safe.

## Turf upgrade notes (6.5.0 → 7.4.0)

- **Turf 7 corrected its `degrees` conversion.** `distance()` and
  `destination()` return values roughly 0.12% different from v6 — e.g.
  `distance(P1, P2, {units:'degrees'})` goes from `0.26299` to `0.26330`. That
  feeds the bezier control arms in `ctrlPtBezier()` and the buffer radius in
  `bboxBuffer()`. The journey path vertex count is unchanged (60 points on the
  homepage, 40 on a journey page), so the effect is sub-pixel.
- All eleven Turf functions this plugin calls still exist with the same
  signatures: `bbox`, `multiPoint`, `explode`, `bearing`, `distance`,
  `destination`, `booleanEqual`, `booleanWithin`, `buffer`, `bboxPolygon`,
  `lineString`.

## Mapbox v3 notes

- **Projection stays `mercator`.** v3 ships the globe projection, but a classic
  style (`mapbox://styles/mapbox/outdoors-v11`) keeps Mercator, so framing and
  `getBounds()` behaviour are unchanged. Moving to Mapbox's Standard style would
  change that and must be re-tested.
- `mapbox-gl.js` roughly doubled, 832KB → 1.8MB. It is footer-loaded and not
  render-blocking, but it is the largest asset the plugin ships.

## Licensing

- **Mapbox GL JS 3.x** is not open source. It is covered by the
  [Mapbox Terms of Service](https://www.mapbox.com/legal/tos); self-hosting the
  bundle is permitted when it is used with Mapbox services and a valid access
  token, which is how this plugin uses it. Map loads are still billed to the
  account behind `MAPBOX_TOKEN`.
- **Turf.js** is MIT licensed.

## Updating

Re-download at the same path, bump the version in the table and in `index.php`,
and re-test. `bv_map_asset_version()` keys the cache on `filemtime()`, so a
replaced file busts caches on its own.

Turf is imported as the full bundle for roughly eleven functions. Trimming it to
per-function `@turf/*` packages would save several hundred KB but needs a
bundler, which this project deliberately does not have.
