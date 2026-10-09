/**
 * BV Map — journey map frontend.
 *
 * This file was previously committed Babel output with hand-edits layered on
 * top; no build config exists, so it is now plain source. Mapbox GL v2 already
 * requires an ES6-capable browser, so there is nothing to transpile for.
 *
 * Two globals are exported deliberately:
 *   window.map               the mapboxgl.Map instance
 *   window.bvVarMapGeoJson   the live FeatureCollection
 * The active theme's assets/js/list-stories.js reads both (it calls
 * map.fitBounds()/map.flyTo() and searches bvVarMapGeoJson.features). Do not
 * scope them away without updating the theme.
 */

( function ( $ ) {
	'use strict';

	var cfg = window.bvVarMap;

	// Bail quietly rather than throwing when a dependency or the container is
	// absent. `window.map` doubles as the already-initialised guard, which is
	// what the original `if (!map)` wrapper relied on via var hoisting.
	if ( ! cfg || window.map ) {
		return;
	}

	if ( typeof mapboxgl === 'undefined' || typeof turf === 'undefined' ) {
		return;
	}

	if ( ! document.getElementById( 'map' ) ) {
		return;
	}

	var MAP_WIDTH_WIDESCREEN = 0.45;
	var MAP_HEIGHT_NONWIDESCREEN = 0.4;
	var RESIZE_THROTTLE_MS = 100;

	var bvVarMapGeoJson = {
		type: 'FeatureCollection',
		crs: {
			type: 'name',
			properties: { name: 'urn:ogc:def:crs:OGC:1.3:CRS84' }
		},
		features: []
	};
	window.bvVarMapGeoJson = bvVarMapGeoJson;

	var routeData = {
		type: 'FeatureCollection',
		features: [ {
			type: 'Feature',
			geometry: { type: 'LineString', coordinates: [] }
		} ]
	};

	var shouldLoadThumbnails = true;
	var boxToLoad = null;

	/* ---------------------------------------------------------------------
	 * Viewport
	 * ------------------------------------------------------------------ */

	function checkWindowDimensions() {
		var width = window.innerWidth;
		var height = window.innerHeight;

		if ( width > height ) {
			$( 'body' ).addClass( 'wide' );

			if ( window.map ) {
				$( '.mapboxgl-canvas' ).attr( 'height', height );
				$( '.mapboxgl-canvas' ).attr( 'width', width * MAP_WIDTH_WIDESCREEN );
			}
		} else {
			$( 'body' ).removeClass( 'wide' );

			if ( window.map ) {
				$( '.mapboxgl-canvas' ).attr( 'height', height * MAP_HEIGHT_NONWIDESCREEN );
				$( '.mapboxgl-canvas' ).attr( 'width', width );
			}
		}
	}

	checkWindowDimensions();

	var shouldListenResize = true;
	window.addEventListener( 'resize', function () {
		if ( ! shouldListenResize ) {
			return;
		}

		checkWindowDimensions();
		shouldListenResize = false;
		window.setTimeout( function () {
			shouldListenResize = true;
		}, RESIZE_THROTTLE_MS );
	} );

	/* ---------------------------------------------------------------------
	 * Map construction
	 * ------------------------------------------------------------------ */

	mapboxgl.accessToken = cfg.mapbox_api_key;

	// bv_userlocation() can return null; the transpiled `?? [1,-1]` fallback sat
	// on an array literal, which is never nullish, so it could never fire.
	var userLocation = cfg.user_location || {};
	var center = [
		isFinite( userLocation.longitude ) ? Number( userLocation.longitude ) : 1,
		isFinite( userLocation.latitude ) ? Number( userLocation.latitude ) : -1
	];

	function isCoordinatePair( value ) {
		return Array.isArray( value ) && value.length >= 2 &&
			isFinite( value[ 0 ] ) && isFinite( value[ 1 ] );
	}

	var mapOptions = {
		container: 'map',
		style: cfg.mapStyle,
		center: center,
		minZoom: parseFloat( cfg.minZoom ),
		maxZoom: parseFloat( cfg.maxZoom ),
		fitBoundsOptions: {
			padding: Math.round( Math.min( window.innerHeight, window.innerWidth ) / 5 ),
			duration: 2000
		}
	};

	// turf.multiPoint() throws on an empty array, and first_locations is empty
	// whenever the query found no geotagged posts.
	var firstLocations = Array.isArray( cfg.first_locations )
		? cfg.first_locations.filter( isCoordinatePair )
		: [];

	if ( firstLocations.length ) {
		mapOptions.bounds = turf.bbox( turf.multiPoint( firstLocations ) );
	}

	var map = new mapboxgl.Map( mapOptions );
	window.map = map;

	/* ---------------------------------------------------------------------
	 * Geometry helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Every distinct journey id present in a set of features.
	 *
	 * Replaces a turf.featureReduce() call that had two defects: it used the PHP
	 * function array() as a fallback, and with no seed value it returned the
	 * feature itself rather than an array when exactly one feature was present.
	 *
	 * @param {Array} features GeoJSON features.
	 * @return {Array} Journey identifiers.
	 */
	function collectJourneys( features ) {
		var seen = [];

		( features || [] ).forEach( function ( feature ) {
			var journeys = ( feature && feature.properties && feature.properties.journeys ) || [];

			journeys.forEach( function ( journey ) {
				if ( seen.indexOf( journey ) === -1 ) {
					seen.push( journey );
				}
			} );
		} );

		return seen;
	}

	/**
	 * Sample a cubic bezier.
	 *
	 * @param {Array}  p1        Start point.
	 * @param {Array}  p2        First control point.
	 * @param {Array}  p3        Second control point.
	 * @param {Array}  p4        End point.
	 * @param {number} precision Segment count.
	 * @return {Array|null} Positions along the curve.
	 */
	function createBezierCurve( p1, p2, p3, p4, precision ) {
		if ( ! p1 || ! p2 || ! p3 || ! p4 || precision <= 0 ) {
			return null;
		}

		var points = [];

		for ( var t = 0; t <= 1; t += 1 / precision ) {
			var mt = 1 - t;
			var x = Math.pow( mt, 3 ) * p1[ 0 ] +
				3 * Math.pow( mt, 2 ) * t * p2[ 0 ] +
				3 * mt * Math.pow( t, 2 ) * p3[ 0 ] +
				Math.pow( t, 3 ) * p4[ 0 ];
			var y = Math.pow( mt, 3 ) * p1[ 1 ] +
				3 * Math.pow( mt, 2 ) * t * p2[ 1 ] +
				3 * mt * Math.pow( t, 2 ) * p3[ 1 ] +
				Math.pow( t, 3 ) * p4[ 1 ];

			points.push( [ x, y ] );
		}

		return points;
	}

	/**
	 * Derive a bezier control point from three consecutive points.
	 *
	 * @param {Array}   explodedGLine2 Three point features, ordered [ p3, p2, p1 ].
	 * @param {boolean} back           Project backwards from the middle point.
	 * @param {number}  factor         Control arm shortening factor.
	 * @return {Array|null} Position, or null when the input is incomplete.
	 */
	function ctrlPtBezier( explodedGLine2, back, factor ) {
		back = back !== false;
		factor = factor || 2.4;

		if ( ! Array.isArray( explodedGLine2 ) ) {
			return null;
		}

		var p1 = explodedGLine2[ 2 ];
		var p2 = explodedGLine2[ 1 ];
		var p3 = explodedGLine2[ 0 ];

		// A line with fewer than three vertices leaves holes here, and turf
		// throws on undefined input.
		if ( ! p1 || ! p2 || ! p3 ) {
			return null;
		}

		if ( turf.booleanEqual( p2, p1 ) || turf.booleanEqual( p2, p3 ) ) {
			return p2.geometry.coordinates;
		}

		var bearingS = turf.bearing( p1.geometry.coordinates, p2.geometry.coordinates );
		var bearingT = turf.bearing( p2.geometry.coordinates, p3.geometry.coordinates );
		var dBearing = bearingT - bearingS;

		dBearing = dBearing > 180 ? dBearing - 360 : dBearing;
		dBearing = dBearing < -180 ? dBearing + 360 : dBearing;

		var lenS = turf.distance( p2, p1, { units: 'degrees' } );
		var lenT = turf.distance( p2, p3, { units: 'degrees' } );
		var bearingM = bearingS + dBearing * lenS / ( lenS + lenT );

		var distance = back ? -lenS / factor : lenT / factor;

		return turf.destination( p2.geometry.coordinates, distance, bearingM, { units: 'degrees' } )
			.geometry.coordinates;
	}

	/**
	 * Build a smoothed path through a journey's features.
	 *
	 * @param {number|string} journey   Journey identifier.
	 * @param {Array}         jfeatures Features belonging to that journey.
	 * @param {boolean}       gline2    Use stored geom_line2__ geometry as control hints.
	 * @return {Object|null} A LineString feature, or null when there is nothing to draw.
	 */
	function buildJourneyBezier( journey, jfeatures, gline2 ) {
		gline2 = gline2 !== false;

		if ( ! Array.isArray( jfeatures ) || jfeatures.length < 2 ) {
			return null;
		}

		var ptsBezier = [];

		for ( var i = 1; i < jfeatures.length; i++ ) {
			var previous = jfeatures[ i - 1 ];
			var current = jfeatures[ i ];
			var startCtrl;
			var endCtrl;

			if ( gline2 ) {
				var prevLine = previous.properties[ 'geom_line2__' + journey ];
				var currLine = current.properties[ 'geom_line2__' + journey ];

				startCtrl = prevLine
					? turf.explode( prevLine ).features
					: [ current, previous, jfeatures[ Math.max( i - 2, 0 ) ] ];

				endCtrl = currLine
					? turf.explode( currLine ).features
					: [ jfeatures[ Math.min( i + 1, jfeatures.length - 1 ) ], current, previous ];
			} else {
				startCtrl = [ current, previous, jfeatures[ Math.max( i - 2, 0 ) ] ];

				var lookahead;
				if ( i + 1 < jfeatures.length ) {
					lookahead = jfeatures[ i + 1 ];
				} else if ( current.properties[ 'geom_line2__' + journey ] ) {
					lookahead = turf.explode( current.properties[ 'geom_line2__' + journey ] ).features[ 0 ];
				} else {
					lookahead = jfeatures[ jfeatures.length - 1 ];
				}

				endCtrl = [ lookahead, current, previous ];
			}

			var segment = createBezierCurve(
				previous.geometry.coordinates,
				ctrlPtBezier( startCtrl, false ),
				ctrlPtBezier( endCtrl, true ),
				current.geometry.coordinates,
				gline2 ? 20 : 10
			);

			// createBezierCurve returns null on incomplete control points;
			// spreading that threw.
			if ( segment ) {
				ptsBezier.push.apply( ptsBezier, segment );
			}
		}

		// turf.lineString() requires at least two positions.
		if ( ptsBezier.length < 2 ) {
			return null;
		}

		var journeyPath = turf.lineString( ptsBezier );
		journeyPath.properties.journey = journey;

		return journeyPath;
	}

	/* ---------------------------------------------------------------------
	 * Colour
	 * ------------------------------------------------------------------ */

	function clampChannel( value ) {
		return Math.max( 0, Math.min( 255, Math.round( value ) ) );
	}

	function RGBToHex( r, g, b ) {
		return '#' + [ r, g, b ].map( function ( channel ) {
			var hex = clampChannel( channel ).toString( 16 );

			return hex.length === 1 ? '0' + hex : hex;
		} ).join( '' );
	}

	function randomRGB( i, startAt ) {
		startAt = startAt === undefined ? 11 : startAt;

		var defaultColors = [
			[ 31, 119, 180 ], [ 255, 127, 14 ], [ 44, 160, 44 ], [ 214, 39, 40 ],
			[ 148, 103, 189 ], [ 140, 86, 75 ], [ 227, 119, 194 ], [ 127, 127, 127 ],
			[ 188, 189, 34 ], [ 23, 190, 207 ]
		].reverse();

		// A non-numeric journey id produced NaN here, indexing past the array and
		// spreading undefined into RGBToHex.
		var index = Number( i );
		if ( ! isFinite( index ) ) {
			index = 0;
		}

		var selectColor = defaultColors[ Math.abs( index ) % defaultColors.length ];

		if ( index < startAt ) {
			return selectColor;
		}

		var pertR = Math.floor( Math.random() * -21 );
		var pertB = Math.floor( Math.random() * 20 );
		var pertG = pertB + pertR;

		return [
			selectColor[ 0 ] + pertR,
			selectColor[ 1 ] + pertG,
			selectColor[ 2 ] + pertB
		];
	}

	function colorFor( feature, index ) {
		return RGBToHex.apply( null, randomRGB( Number( feature.properties.journey ) + 7 * index ) );
	}

	/* ---------------------------------------------------------------------
	 * Markers
	 * ------------------------------------------------------------------ */

	/**
	 * Scroll a story into view, loading it first when it is not on the page.
	 *
	 * @param {number|string} featureId Post id.
	 */
	function scrollToStory( featureId ) {
		var $post = $( '#post_' + featureId );

		if ( $post.length ) {
			$post[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'center' } );
			$post.parent().removeClass( 'hide' );

			return;
		}

		// pv is published by the theme (digital-nomad-child). Without it there is
		// nowhere to fetch the missing story from, so do nothing rather than
		// throw on an undefined global.
		if ( ! window.pv || ! window.pv.template_path ) {
			return;
		}

		$.ajax( {
			url: window.pv.template_path + '/inc/ajax.php',
			type: 'POST',
			data: {
				data: featureId,
				action: 'jumpto_post',
				journey_id: $( '#journey_id' ).val() || ''
			},
			success: function ( response ) {
				var data = typeof response === 'string' ? JSON.parse( response ) : response;

				// Reset the theme's infinite-scroll flags.
				window.load_more_top = true;
				window.load_more_bottom = true;

				$( '#content .story-list' ).replaceWith( '<div class="story-list">' + data.html + '</div>' );
				window.initCoords = data.coords;

				var $loaded = $( '#post_' + featureId );
				if ( $loaded.length ) {
					$loaded[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'center' } );
					$loaded.parent().removeClass( 'hide' );
				}
			}
		} );
	}

	/**
	 * Thumbnail URL for a feature id.
	 *
	 * @param {number|string} id Attachment id.
	 * @return {string|null} URL, or null when unknown.
	 */
	function getImageById( id ) {
		if ( ! id || ! bvVarMapGeoJson.features.length ) {
			return null;
		}

		var feature = bvVarMapGeoJson.features.find( function ( candidate ) {
			return candidate.properties.id === id || candidate.properties.id == id; // eslint-disable-line eqeqeq
		} );

		return feature ? feature.properties.thumbnail : null;
	}

	/**
	 * Build the HTML marker for a story or cluster.
	 *
	 * @param {Object} feature GeoJSON feature.
	 * @return {HTMLElement}
	 */
	function createThumbnail( feature ) {
		var src = getImageById( feature.properties.id );
		var extraClasses = '';

		// getImageById returns null for an unknown id; .includes() threw on it.
		if ( src && src.indexOf( '225x300' ) !== -1 ) {
			extraClasses = ' vertical';
		}

		var html = '<div class="cluster-img-wrapper">';
		if ( src ) {
			html += '<img src="' + src + '" style="position:relative;width:100%;height:100%" ' +
				'class="img-on-map' + extraClasses + '">';
		}
		html += '</div>';

		if ( feature.properties.point_count ) {
			html += '<span class="point_count">' + feature.properties.point_count + '</span>';
		}

		var el = document.createElement( 'div' );
		el.innerHTML = html;
		el.className = 'marker';

		el.onclick = function () {
			scrollToStory( feature.id );
		};

		return el;
	}

	/* ---------------------------------------------------------------------
	 * Style load
	 * ------------------------------------------------------------------ */

	map.on( 'style.load', function () {
		var markers = {};
		var markersOnScreen = {};
		var nonClusterMarkersOnScreen = {};

		var scale = new mapboxgl.ScaleControl( { maxWidth: 120, unit: 'imperial' } );
		map.addControl( scale );
		scale.setUnit( 'metric' );

		map.addSource( 'stories', {
			type: 'geojson',
			data: bvVarMapGeoJson,
			cluster: true,
			clusterMaxZoom: 10,
			clusterRadius: 50,
			clusterProperties: {
				id: [ 'max', [ 'get', 'id' ], '' ]
			}
		} );

		map.addSource( 'trace', { type: 'geojson', data: routeData } );

		map.addLayer( {
			id: 'trace',
			type: 'line',
			source: 'trace',
			paint: {
				'line-color': [ 'get', 'color' ],
				'line-opacity': 0.8,
				'line-width': 7,
				'line-blur': 1
			},
			layout: {
				'line-join': 'round',
				'line-cap': 'round'
			}
		} );

		/* -----------------------------------------------------------------
		 * Waypoints / itineraries
		 *
		 * Dead in production: bv_get_geom_futureevents() returns an empty array,
		 * so bvVarMap.waypoints is null and none of this runs. Carried over
		 * unchanged by decision, with only the fixes needed for strict mode.
		 * -------------------------------------------------------------- */

		if ( cfg.waypoints ) {
			var waypointsData = {
				type: 'FeatureCollection',
				features: cfg.waypoints.filter( function ( x ) {
					return x.properties.posttype === 'tribe_events';
				} )
			};

			var itineraries = waypointsData.features
				.map( function ( x ) {
					return x.properties.journeys[ 0 ] !== undefined ? x.properties.journeys[ 0 ] : null;
				} )
				.filter( function ( el, index, self ) {
					return el !== null && self.indexOf( el ) === index;
				} )
				.reverse();

			var itinData = {
				type: 'FeatureCollection',
				features: []
			};

			var extendWaypoints = function ( journey, jFeatures ) {
				var waypoints = jFeatures.filter( function ( y ) {
					return y.properties.journeys[ 0 ] === journey;
				} );

				if ( ! waypoints.length ) {
					return null;
				}

				var wBefore;
				var wAfter;
				var pBefore = waypoints[ 0 ].properties.journeys.concat();
				var pAfter = waypoints[ 0 ].properties.journeys.concat();

				do {
					pBefore.shift();
					wBefore = jFeatures.filter( function ( y ) {
						var end = y.properties.datetime_end !== undefined
							? y.properties.datetime_end
							: y.properties.datetime;

						return y.properties.journeys[ 0 ] === pBefore[ 0 ] &&
							end < waypoints[ waypoints.length - 1 ].properties.datetime_start;
					} )[ 0 ];
				} while ( wBefore === undefined && pBefore.length );

				do {
					pAfter.shift();
					wAfter = jFeatures.filter( function ( y ) {
						return y.properties.journeys[ 0 ] === pAfter[ 0 ] &&
							y.properties.datetime_start > waypoints[ 0 ].properties.datetime_end;
					} ).pop();
				} while ( wAfter === undefined && pAfter.length );

				if ( wBefore ) {
					waypoints.push( wBefore );
				}

				if ( wAfter ) {
					waypoints.unshift( wAfter );
				}

				return waypoints;
			};

			itinData.features = itineraries
				.map( function ( x ) {
					return buildJourneyBezier( x, extendWaypoints( x, cfg.waypoints ), false );
				} )
				.filter( function ( el ) {
					return el !== null && el !== undefined;
				} );

			itinData.features.forEach( function ( feature, i ) {
				feature.properties.color = colorFor( feature, i );
			} );

			map.addSource( 'itinerary', { type: 'geojson', data: itinData } );

			map.addLayer( {
				id: 'itinerary',
				type: 'line',
				source: 'itinerary',
				paint: {
					'line-color': [ 'get', 'color' ],
					'line-opacity': 0.9,
					'line-width': 3,
					'line-dasharray': [ 2, 1.5 ],
					'line-blur': 0
				},
				layout: {
					'line-join': 'miter',
					'line-cap': 'butt'
				}
			} );

			map.addSource( 'waypooints', { type: 'geojson', data: waypointsData } );

			map.addLayer( {
				id: 'waypoint-circles',
				type: 'circle',
				source: 'waypooints',
				paint: {
					'circle-radius': 6,
					'circle-color': '#bbb'
				},
				filter: [ '==', '$type', 'Point' ]
			} );

			map.addLayer( {
				id: 'poi-labels',
				type: 'symbol',
				source: 'waypooints',
				layout: {
					'icon-image': 'harbor_icon',
					'text-field': [
						'format',
						[ 'get', 'date' ], { 'font-scale': 1, 'text-color': '#fff' },
						'\n', {},
						[ 'get', 'title' ], {
							'text-font': [ 'literal', [ 'DIN Offc Pro Italic' ] ],
							'font-scale': 0.8,
							'text-color': '#fff'
						},
						'\n', {},
						[ 'get', 'datetime_delta' ], {
							'text-font': [ 'literal', [ 'DIN Offc Pro Italic' ] ],
							'font-scale': 0.8,
							'text-color': '#fff'
						}
					]
				},
				paint: {
					'text-color': '#fff',
					'text-halo-color': '#333',
					'text-halo-width': 1.2,
					'text-halo-blur': 0
				}
			} );

			waypointsData.features.forEach( function ( waypoint ) {
				var el = document.createElement( 'div' );
				el.className = 'marker';

				$( el ).on( 'click', function () {
					scrollToStory( waypoint.id );
				} );

				new mapboxgl.Marker( el )
					.setLngLat( waypoint.geometry.coordinates )
					.addTo( map );
			} );
		}

		/* -----------------------------------------------------------------
		 * Marker sync
		 * -------------------------------------------------------------- */

		function updateMarkers() {
			var newMarkers = {};
			var features = map.querySourceFeatures( 'stories' );

			if ( bvVarMapGeoJson.features.length > 0 ) {
				var journeys = collectJourneys( bvVarMapGeoJson.features );

				routeData.features = journeys
					.map( function ( journey ) {
						var members = bvVarMapGeoJson.features.filter( function ( feature ) {
							return feature.properties.journeys &&
								feature.properties.journeys.indexOf( journey ) !== -1;
						} );

						return buildJourneyBezier( journey, members, true );
					} )
					.filter( function ( el ) {
						return el !== null && el !== undefined;
					} );

				routeData.features.forEach( function ( feature, i ) {
					feature.properties.color = colorFor( feature, i );
				} );

				map.getSource( 'trace' ).setData( routeData );
			}

			if ( ! features ) {
				return;
			}

			for ( var i = 0; i < features.length; i++ ) {
				var feature = features[ i ];
				var id = feature.id !== undefined && feature.id !== null
					? feature.id
					: feature.properties.id;
				var marker = markers[ id ];

				if ( ! marker ) {
					marker = markers[ id ] = new mapboxgl.Marker( {
						element: createThumbnail( feature )
					} ).setLngLat( feature.geometry.coordinates );
				}

				newMarkers[ id ] = marker;

				// The original set a `cluster` flag from "any marker currently on
				// screen", which made this branch unreachable after the first
				// pass. It now tracks un-clustered features as intended.
				if ( ! feature.properties.point_count &&
					feature.properties.thumbnail &&
					! nonClusterMarkersOnScreen[ id ] ) {
					marker.addTo( map );
					nonClusterMarkersOnScreen[ id ] = marker;
				}

				if ( ! markersOnScreen[ id ] ) {
					marker.addTo( map );
				}
			}

			Object.keys( markersOnScreen ).forEach( function ( id ) {
				if ( ! newMarkers[ id ] && markersOnScreen[ id ] ) {
					markersOnScreen[ id ].remove();
					delete nonClusterMarkersOnScreen[ id ];
				}
			} );

			markersOnScreen = newMarkers;
		}

		/* -----------------------------------------------------------------
		 * Loading
		 * -------------------------------------------------------------- */

		/**
		 * Bounding box around the current view, buffered by a share of its diagonal.
		 *
		 * @param {number} factor Buffer multiplier.
		 * @return {Object} A GeoJSON polygon.
		 */
		function bboxBuffer( factor ) {
			var bounds = map.getBounds();
			var sw = [ bounds.getWest(), bounds.getSouth() ];
			var ne = [ bounds.getEast(), bounds.getNorth() ];
			var diagDist = turf.distance( sw, ne, { units: 'degrees' } );

			return turf.buffer( turf.bboxPolygon( sw.concat( ne ) ), diagDist * factor, {
				units: 'degrees',
				steps: 4
			} );
		}

		function fetchPostsInView() {
			if ( ! shouldLoadThumbnails ) {
				return;
			}

			if ( boxToLoad && turf.booleanWithin( bboxBuffer( 0 ), boxToLoad ) ) {
				return;
			}

			shouldLoadThumbnails = false;
			boxToLoad = bboxBuffer( 0.1 );

			var queried = cfg.queried_obj || {};

			$.ajax( {
				url: cfg.rest_url,
				type: 'POST',
				dataType: 'json',
				contentType: 'application/json; charset=utf-8',
				beforeSend: function ( xhr ) {
					if ( cfg.rest_nonce ) {
						xhr.setRequestHeader( 'X-WP-Nonce', cfg.rest_nonce );
					}
				},
				// The old endpoint also took a `member` flag from the client and
				// used it to decide whether to lift the private-post filter.
				// Membership is now resolved server-side only.
				data: JSON.stringify( {
					box_to_load: boxToLoad,
					term_id: queried.term_id || 0,
					taxonomy: queried.taxonomy || '',
					slug: queried.slug || ''
				} ),
				success: function ( data ) {
					shouldLoadThumbnails = true;
					bvVarMapGeoJson.features = Array.isArray( data ) ? data : [];
					window.bvVarMapGeoJson = bvVarMapGeoJson;

					map.getSource( 'stories' ).setData( bvVarMapGeoJson );
					window.setTimeout( updateMarkers, 100 );
				},
				error: function () {
					shouldLoadThumbnails = true;
				}
			} );
		}

		/* -----------------------------------------------------------------
		 * Handlers
		 *
		 * `data` fires on every tile and every setData(). The original bound
		 * move/moveend from inside that handler, so listeners accumulated for the
		 * lifetime of the page and a single pan ran updateMarkers — and fired a
		 * request — once per accumulated listener. Bind exactly once.
		 * -------------------------------------------------------------- */

		var handlersBound = false;

		map.on( 'data', function ( e ) {
			if ( e.sourceId !== 'stories' || handlersBound ) {
				return;
			}

			handlersBound = true;
			map.on( 'move', updateMarkers );
			map.on( 'moveend', fetchPostsInView );
			updateMarkers();
		} );

		fetchPostsInView();
	} );
}( jQuery ) );
