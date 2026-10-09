# Development notes

Working notes kept from the original README, retained verbatim as implementation
reference for the incremental point/line loading in `assets/js/main.js`.

## Map move/zoom event

    // https://docs.mapbox.com/mapbox-gl-js/api/#map.event:move
    map.on('move', handler) // Use this to detect when the map is moved or zoomed in/out

## Get map bounds

    // https://docs.mapbox.com/mapbox-gl-js/api/#map#getbounds
    map.getBounds()
    returns 2 points, 1 for north-east and one for south-west

## Fetch points and connect with lines

1. Hold all the points in a variable.
2. Get all the new points after the map moves.
3. If there are new points, find the difference between 1 and 2.
4. Draw the new points and lines.
   1. By using datetime, find out if the points need to be connected with the
      first or the last point that is already drawn.
   2. Don't delete.
5. If all the points are already fetched, stop this process from happening again.
