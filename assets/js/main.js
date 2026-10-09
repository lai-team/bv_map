if (!map) {
  var _ref;

  function checkWindowDimensions() {
    let width = window.innerWidth;
    let height = window.innerHeight;
    let map_width_widescreen = 0.45;
    let map_height_nonwidescreen = 0.4;

    if (width > height) {
      $('body').addClass('wide');

      if (map) {
        $('.mapboxgl-canvas').attr('height', height);
        $('.mapboxgl-canvas').attr('width', width * map_width_widescreen);
      }
    } else {
      $('body').removeClass('wide');

      if (map) {
        $('.mapboxgl-canvas').attr('height', height * map_height_nonwidescreen);
        $('.mapboxgl-canvas').attr('width', width);
      }
    }
  }

  checkWindowDimensions();
  var shouldListenResize = true;

  window.onresize = function () {
    if (shouldListenResize) {
      checkWindowDimensions();
      shouldListenResize = false;
      setTimeout(function () {
        shouldListenResize = true;
      }, 100); //console.log(123);
    }
  };

  mapboxgl.accessToken = bvVarMap.mapbox_api_key;
  var bvVarMapGeoJson = {
    "type": "FeatureCollection",
    "crs": {
      "type": "name",
      "properties": {
        "name": "urn:ogc:def:crs:OGC:1.3:CRS84"
      }
    },
    "features": []
  };
  var routeData = {
    "type": "FeatureCollection",
    "features": [{
      type: "Feature",
      geometry: {
        type: 'LineString',
        coordinates: []
      }
    }]
  }; //console.log(routeData.features[0].geometry.coordinates);

  var shouldLoadThumbnails = true;
  var boxToLoad;
  var center = (_ref = [bvVarMap.user_location.longitude, bvVarMap.user_location.latitude]) !== null && _ref !== void 0 ? _ref : [1, -1]; //console.log(bvVarMap.user_location.longitude, 'start');
	//console.log('Ge0jsoN',bvVarMapGeoJson);

  var map = new mapboxgl.Map({
    container: "map",
    // container id
    style: bvVarMap.mapStyle,
    // stylesheet location
    //center: [bvVarMap.user_location.longitude, bvVarMap.user_location.latitude], // s
    center: center,
    // s
    minZoom: parseFloat(bvVarMap.minZoom),
    maxZoom: parseFloat(bvVarMap.maxZoom),
    //zoom: 4,
    bounds: turf.bbox(turf.multiPoint(bvVarMap.first_locations)),
    fitBoundsOptions: {
	padding: Math.round(Math.min( window.innerHeight, window.innerWidth) /5 ) ,  
      	duration: 2000
    }
  }); 
	//console.log('quiried 0bj',bvVarMap.queried_obj);

  map.on('style.load', function () {
    // Later on, we'll add layers to differentiate our marker clusters
    // by the number of markers they represent. We'll store the breaks
    // between each category here so we can change them easily. 
    let highCount = 75,
        lowCount = 15;
    let markers = {};
    let markersOnScreen = {};
    let nonClustermarkersOnScreen = {}; // Add a new source from our GeoJSON data and set the 
    // 'cluster' option to true. 
	
	var scale = new mapboxgl.ScaleControl({
	    maxWidth: 120,
	    unit: 'imperial'
	});
	map.addControl(scale);

	scale.setUnit('metric');

    map.addSource("stories", {
      type: "geojson",
      data: bvVarMapGeoJson,
      cluster: true,
      clusterMaxZoom: 10,
      // Max zoom to cluster points on
      clusterRadius: 50,
      // Radius of each cluster when clustering points (defaults to 400)
      clusterProperties: {
        // get the highest id  of the clustered points
        "id": ["max", ["get", "id"], '']
      }
    }); // Source that is used for drawing the connections betweeen points

    map.addSource('trace', {
      type: 'geojson',
      data: routeData
    }); // Layer that draws the connections between points

    map.addLayer({
      'id': 'trace',
      'type': 'line',
      'source': 'trace',
      'paint': {
        'line-color': ['get', 'color'],
        'line-opacity': 0.8,
        'line-width': 7,
        'line-blur': 1 //'line-gap-width': 10,

      },
      'layout': {
        'line-join': 'round',
        'line-cap': 'round' //'line-round-limit':0.1,

      }
    });

    function reduceUnionJourneys(prev, current, index) {
      var _prev$properties$jour, _current$properties$j;

      let prevValue = prev.length > 0 ? prev : (_prev$properties$jour = prev.properties.journeys) !== null && _prev$properties$jour !== void 0 ? _prev$properties$jour : array();
      let joinArrays = [...prevValue, ...((_current$properties$j = current.properties.journeys) !== null && _current$properties$j !== void 0 ? _current$properties$j : array())];
      return [...new Set(joinArrays)];
    }

    let createBezierCurve = (p1, p2, p3, p4, precision) => {
	if (p1 === null) return;
	if (p2 === null) return;
	if (p3 === null) return;
	if (p4 === null) return;
      // console.log({p1,p2,p3,p4})
      // p1 is the starting point p4 is the end point
      // p2 and p3 are control points
      // precision is the number of segments that will be used for the cruve
      let points = []; // if(precision<=0)return;

      for (let t = 0; t <= 1; t += 1 / precision) {
        let point_at_t_x = Math.pow(1 - t, 3) * p1[0] + 3 * Math.pow(1 - t, 2) * t * p2[0] + 3 * (1 - t) * Math.pow(t, 2) * p3[0] + Math.pow(t, 3) * p4[0];
        let point_at_t_y = Math.pow(1 - t, 3) * p1[1] + 3 * Math.pow(1 - t, 2) * t * p2[1] + 3 * (1 - t) * Math.pow(t, 2) * p3[1] + Math.pow(t, 3) * p4[1];
        points.push([point_at_t_x, point_at_t_y]);
      }

      return points;
    };

    let ctrlPtBezier = (explodedGLine2, back = true, factor = 2.4) => {
      let p1 = explodedGLine2[2];
      let p2 = explodedGLine2[1];
      let p3 = explodedGLine2[0];
      if (turf.booleanEqual(p2, p1) || turf.booleanEqual(p2, p3)) return p2.geometry.coordinates;
      let bearing_s = turf.bearing(p1.geometry.coordinates, p2.geometry.coordinates);
      let bearing_t = turf.bearing(p2.geometry.coordinates, p3.geometry.coordinates);
      let dBearing = bearing_t - bearing_s;
      dBearing = dBearing > 180 ? dBearing - 360 : dBearing;
      dBearing = dBearing < -180 ? dBearing + 360 : dBearing;
      let len_s = turf.distance(p2, p1, {
        units: 'degrees'
      });
      let len_t = turf.distance(p2, p3, {
        units: 'degrees'
      });
      let bearing_m = bearing_s + dBearing * len_s / (len_s + len_t);

      if (back) {
        const result = turf.destination(p2.geometry.coordinates, -len_s / factor, bearing_m, {
          units: 'degrees'
        });
        return result.geometry.coordinates;
      } else {
        const result = turf.destination(p2.geometry.coordinates, len_t / factor, bearing_m, {
          units: 'degrees'
        });
        return result.geometry.coordinates;
      }
    };

    if (bvVarMap.waypoints) {
      //console.log(bvVarMap.waypoints, ' waypoints');
      let waypointsData = {
        'type': 'FeatureCollection',
        'features': bvVarMap.waypoints.filter(x => x.properties.posttype == 'tribe_events')
      };
      let itineraries = [...new Set(waypointsData.features.map(x => {
        var _x$properties$journey;

        return (_x$properties$journey = x.properties.journeys[0]) !== null && _x$properties$journey !== void 0 ? _x$properties$journey : null;
      }))].filter(function (el) {
        return el != null;
      }).reverse(); //console.log(itineraries,'itins');

      /*
          let itineraries = turf.featureReduce(waypointsData, (prev, current, ind) =>{ 
          let prevValue = prev.length > 0 ? prev : prev.properties.journeys;
              let joinArrays = [...prevValue, ...current.properties.journeys[0]??[]];
                  return [...new Set(joinArrays)];
          });
      */
      //console.log(itineraries, 'itins');

      let itinData = {
        "type": "FeatureCollection",
        "features": [{
          type: "Feature",
          geometry: {
            type: 'LineString',
            coordinates: []
          }
        }]
      };

      function extendWaypoints(journey, jFeatures) {
        //jFeatures.sort(function(a,b){ return a.properties.datetime_start - b.properties.datetime_start ;});
        //console.log(journey, 'getting journey');
        var waypoints = jFeatures.filter(y => y.properties.journeys[0] == journey);
        if (!waypoints.length) return;
        //console.log(waypoints, 'subjourney');
        //console.log(jFeatures, 'full journey'); 
	//console.log(waypoints,'collected waypoints');

        let wBefore;
        let wAfter;
        let pBefore = waypoints[0].properties.journeys.concat();
        let pAfter = waypoints[0].properties.journeys.concat();
        //console.log(pBefore, 'starting checking');

        do {
          pBefore.shift();
          wBefore = jFeatures.filter(y => {
            var _y$properties$datetim;

            return y.properties.journeys[0] == pBefore[0] && ((_y$properties$datetim = y.properties.datetime_end) !== null && _y$properties$datetim !== void 0 ? _y$properties$datetim : y.properties.datetime) < waypoints[waypoints.length - 1].properties.datetime_start;
          })[0];
          //console.log(wBefore, 'wBefore in Loop');
          //console.log(pBefore, 'pBefore Looping');
        } while (wBefore === undefined && pBefore.length);

        //console.log(wBefore, 'before'); //console.log(pAfter,'any loop?');
        //console.log(pAfter,'starting checkingi again');

        do {
          pAfter.shift();
          wAfter = [...jFeatures.filter(y => y.properties.journeys[0] == pAfter[0] && y.properties.datetime_start > waypoints[0].properties.datetime_end)].pop();
          //console.log(wAfter, 'wAfter in Loop');
          //console.log(pAfter, 'pAfter Looping');
        } while (wAfter === undefined && pAfter.length);

        //console.log(wAfter, 'after');
        if (wBefore) waypoints.push(wBefore);
        if (wAfter) waypoints.unshift(wAfter);
        return waypoints;
      } //itinData.features = itineraries.map(x => buildJourneyBezier(x, bvVarMap.waypoints.filter(y => y.properties.journeys[0]==x), false)).filter(function (el) {


      itinData.features = itineraries.map(x => buildJourneyBezier(x, extendWaypoints(x, bvVarMap.waypoints), false)).filter(function (el) {
        return el != null;
      }); //console.log(itinData.features,'itinData');

      for (let i = 0; i < itinData.features.length; i++) {
        //routeData.features[i].properties.color = colors[i%routeData.features.length];
        itinData.features[i].properties.color = RGBToHex(...randomRGB(itinData.features[i].properties.journey + 7 * i));
      } //console.log(itinData.features,'bez');
      //console.log(buildJourneyBezier(4,bvVarMap.waypoints,false));
      // Source that is used for drawing the connections betweeen points


      map.addSource('itinerary', {
        type: 'geojson',
        data: itinData
      }); // Layer that draws the connections between points

      map.addLayer({
        'id': 'itinerary',
        'type': 'line',
        'source': 'itinerary',
        'paint': {
          'line-color': ['get', 'color'],
          'line-opacity': 0.9,
          'line-width': 3,
          //'line-dasharray': ['get','dasharray'],
          'line-dasharray': [2, 1.5],
          'line-blur': 0 //'line-gap-width': 10,

        },
        'layout': {
          'line-join': 'miter',
          'line-cap': 'butt' //'line-round-limit':0.1,

        }
      }); //var wcoord = bvVarMap.waypoints.map(x=>x.geometry.coordinates);
      //console.log(wcoord,'filtered');
      // Add a GeoJSON source containing place coordinates and information.

      map.addSource('waypooints', {
        'type': 'geojson',
        'data': waypointsData
      });
      map.addLayer({
        'id': 'waypoint-circles',
        'type': 'circle',
        'source': 'waypooints',
        'paint': {
          'circle-radius': 6,
          'circle-color': '#bbb'
        },
        'filter': ['==', '$type', 'Point']
      });
      map.addLayer({
        'id': 'poi-labels',
        'type': 'symbol',
        'source': 'waypooints',
        'layout': {
          "icon-image": "harbor_icon",
          "text-field": ["format", 
		  ["get", "date"], {
            				"font-scale": 1,
            				'text-color': '#fff'
          			}, // Use default formatting
          	"\n", {}, 
		  ["get", "title"],
		  		{
            				//"text-font": ["Niconne", ["DIN Offc Pro Italic"]],
            				"text-font": ["literal", ["DIN Offc Pro Italic"]],
            				"font-scale": .8,
            				'text-color': '#fff'
          			},
		"\n",{},
		  ["get","datetime_delta"],
		  		{
            				"text-font": ["literal", ["DIN Offc Pro Italic"]],
            				"font-scale": .8,
            				'text-color': '#fff'
          			}			
	  	]
        },
        "paint": {
          "text-color": "#fff",
          "text-halo-color": "#333",
          "text-halo-width": 1.2,
          "text-halo-blur": 0
        }
      });

	    /*
	map.addSource('bBox',{
		'type':'geojson',
		'data':boxToLoad
	});
	map.addLayer({
		'id':'bBox',
'type': 'fill',
'source': 'bBox', // reference the data source
'layout': {},
'paint': {
'fill-color': '#0080ff', // blue color fill
'fill-opacity': 0.5
}
	});
	*/

      function createWaypoint(feature) {
        //console.log('feature '+feature);
        let html = '<div class="map-waypoint-wrapper">';
        html += '<span class="datetime">';
        html += feature.properties.datetime;
        html += '</span>';
        html += '<span class="">';
        html += feature.properties.datetime;
        html += '</span>';
        html += '</div>'; // If there is a cluster this will display the number of images clustered

        if (feature.properties.point_count) {
          html += "<span class='point_count'>" + feature.properties.point_count + "</span>";
        }

        let el = document.createElement("div");
        el.innerHTML = html;
        el.className = "marker"; // Event handler which scrolls to the story post which holds the image we click

        el.onclick = () => {
          console.log('click id ' + feature.id); 
		//console.log(props.id);
          //$("[data-id=" + props.id + "]")[0].scrollIntoView({

          $("#post_" + feature.id)[0].scrollIntoView({
            behavior: "smooth",
            // or "auto" or "instant"
            block: "center" // or "end"

          });
          $("#post_" + feature.id).parents('.stories-wrapper').removeClass('hide');
        };

        return el;
      }

      waypointsData.features.forEach(function(marker) {
        //console.log({marker})
        // create a HTML element for each feature
        var el = document.createElement('div');
        el.className = 'marker';
        $(el).click(function(){
          $("#post_" + marker.id)[0].scrollIntoView({
            behavior: "smooth",
            // or "auto" or "instant"
            block: "center" // or "end"
  
          });
        })
      
        // make a marker for each feature and add to the map
        new mapboxgl.Marker(el)
          .setLngLat(marker.geometry.coordinates)
          .addTo(map);
      });


    } // Function that adds thumbnails on top of the map as HTML objects


    function createThumbnail(feature) {
      //console.log('feature '+feature);
      let html = '<div class="cluster-img-wrapper">';
		const src = getImageById(feature.properties.id);
		let extraClasses = "";
		if(src.includes('225x300')) extraClasses += " vertical"
      html += '<img src="' + src + '" style="position:relative;width:100%;height:100%" class="img-on-map'+extraClasses+'">';
      html += '</div>'; // If there is a cluster this will display the number of images clustered

      if (feature.properties.point_count) {
        html += "<span class='point_count'>" + feature.properties.point_count + "</span>";
      }

      let el = document.createElement("div");
      el.innerHTML = html;
      el.className = "marker"; // Event handler which scrolls to the story post which holds the image we click

      el.onclick = () => {
	if(  $("#post_" + feature.id)[0] == null){
		//console.log('does not exist');
		$.ajax({
                        url: pv.template_path + '/inc/ajax.php',
                        type: "POST",
                        data: {
                            'data'  : feature.id,
                            'action':'jumpto_post',
                            'journey_id' : $('#journey_id').val()??"",
                        },
                        success: function(data){
                            data = JSON.parse(data);
				load_more_top = true;
				load_more_bottom = true;
			  let storyList = '<div class="story-list">';
				storyList += data.html;
				storyList += '</div>';
                            $('#content .story-list').replaceWith( storyList);
                                //postCoords=data.coords.concat(postCoords);
				   initCoords=data.coords;
				//console.log(initCoords,'coorDs');
				      //console.log($("#post_" +feature.id),'poSt');
				 $("#post_" + feature.id)[0].scrollIntoView({
			          behavior: "smooth",
			          // or "auto" or "instant"
			          block: "center" // or "end"
			
			        });
			        $("#post_" + feature.id).parent().removeClass('hide');
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            shouldLoad = true
                            //console.log(errorThrown);
                        }
                    })
	}else{
	      //console.log($("#post_" +feature.id),'posT');
        //console.log('click id ' + feature.id); //console.log(props.id);
        //$("[data-id=" + props.id + "]")[0].scrollIntoView({

        $("#post_" + feature.id)[0].scrollIntoView({
          behavior: "smooth",
          // or "auto" or "instant"
          block: "center" // or "end"

        });
        $("#post_" + feature.id).parent().removeClass('hide');
	}

      };

      return el;
    } // Get the thumbnail from the bvVarMapGeoJson.features for a given ID


    function getImageById(id) {
      //console.log('collection: '+bvVarMapGeoJson);
      //console.log('image id ' + id);
      if (!id) return;
      if (!bvVarMapGeoJson.features.length > 0) return;
      let feature = bvVarMapGeoJson.features.find(({
        properties
      }) => properties.id == id); //console.log('fEature: ' + feature.id);

      return feature ? feature.properties.thumbnail : null;
    } // Updates the markers on the map 
    // Shows only what is inside the map bounding box


    function buildJourneyBezier(journey, jfeatures, gline2 = true) {
      //function buildJourneyBezier(journey, gjFeatures, gline2 = true) {
      //if (!bvVarMapGeoJson.features) return;
      //if (!bvVarMapGeoJson.features.length > 0) return;
      //console.log(bvVarMapGeoJson.features);
      //let jfeatures = bvVarMapGeoJson.features.filter(x => x.properties.journeys.includes(journey));
      //let jfeatures = gjFeatures.filter(x => x.properties.journeys.includes(journey));
      if (jfeatures.length < 2) return; // console.log(jfeatures);

      let ptsBezier = []; //if(wcoord !== undefined){
      //ptsBezier.push(...wcoord);
      //	}

      for (let i = 1; i < jfeatures.length; i++) {
		//console.log(i,'i');
        if (gline2) {
          var _turf$explode$feature, _turf$explode$feature2;
		_turf$explode$feature = jfeatures[i - 1].properties['geom_line2__' + journey];
	        _turf$explode$feature = _turf$explode$feature ? turf.explode( _turf$explode$feature ).features : [ jfeatures[i], jfeatures[i - 1], jfeatures[Math.max(i - 2, 0)] ] ;
	        _turf$explode$feature2 = jfeatures[i].properties['geom_line2__' + journey];
	        _turf$explode$feature2 = _turf$explode$feature2 ? turf.explode( _turf$explode$feature2 ).features : [jfeatures[Math.min(i + 1, jfeatures.length - 1)], jfeatures[i], jfeatures[i - 1]];

          ptsBezier.push(...createBezierCurve(
                  jfeatures[i - 1].geometry.coordinates,
                  ctrlPtBezier( _turf$explode$feature , false),
                  ctrlPtBezier( _turf$explode$feature2 , true),
                  jfeatures[i].geometry.coordinates,
                  20));
		/*
          ptsBezier.push(...createBezierCurve(
		  jfeatures[i - 1].geometry.coordinates, 
		  ctrlPtBezier(
			  (_turf$explode$feature = turf.explode(jfeatures[i - 1].properties['geom_line2__' + journey]).features) !== null && _turf$explode$feature !== void 0 ? 
			  _turf$explode$feature : 
			  [jfeatures[i], jfeatures[i - 1], jfeatures[Math.max(i - 2, 0)]],
			  false), 
		  ctrlPtBezier(
			  (_turf$explode$feature2 = turf.explode(jfeatures[i].properties['geom_line2__' + journey]).features) !== null && _turf$explode$feature2 !== void 0 ? 
			  _turf$explode$feature2 : 
			  [jfeatures[Math.min(i + 1, jfeatures.length - 1)], jfeatures[i], jfeatures[i - 1]],
			  true), 
		  jfeatures[i].geometry.coordinates, 
		  100));
		  */
		//console.log(_turf$explode$feature,'feature 1');
		//console.log(_turf$explode$feature2,'feature 2');
        } else {
          //console.log(i);
          ptsBezier.push(...createBezierCurve(
		  jfeatures[i - 1].geometry.coordinates, 
		  ctrlPtBezier([jfeatures[i], jfeatures[i - 1], jfeatures[Math.max(i - 2, 0)]], false),
		  ctrlPtBezier([//jfeatures[ Math.min(i+1,jfeatures.length-1)],
          i + 1 < jfeatures.length ? jfeatures[i + 1] : jfeatures[i].properties['geom_line2__' + journey] ? 
			  turf.explode(jfeatures[i].properties['geom_line2__' + journey]).features[0] : 
			  jfeatures[jfeatures.length - 1], jfeatures[i], jfeatures[i - 1]], 
			  true),
		  jfeatures[i].geometry.coordinates,
		  10));
        }
      }

      let journeyPath = turf.lineString(ptsBezier); 
	   //console.log(journeyPath,'bPath');

      journeyPath.properties['journey'] = journey;
      return journeyPath;
    } 
	  //console.log("its Working");


    function RGBToHex(r, g, b) {
      r = r.toString(16);
      g = g.toString(16);
      b = b.toString(16);
      if (r.length == 1) r = "0" + r;
      if (g.length == 1) g = "0" + g;
      if (b.length == 1) b = "0" + b;
      return "#" + r + g + b;
    }

    function randomRGB(i, startAt = 11) {
      const defaultColors = [[31, 119, 180], [255, 127, 14], [44, 160, 44], [214, 39, 40], [148, 103, 189], [140, 86, 75], [227, 119, 194], [127, 127, 127], [188, 189, 34], [23, 190, 207]].reverse();
      let selectColor = defaultColors[i % defaultColors.length];

      if (i < startAt) {
        return selectColor;
      } else {
        let pertR = Math.floor(Math.random() * -21);
        let pertB = Math.floor(Math.random() * 20);
        let pertG = pertB + pertR;
        let result = [selectColor[0] + pertR, selectColor[1] + pertG, selectColor[2] + pertB]; // console.log(result);

        return result;
      }
    }

    function updateMarkers() {
      let newMarkers = {};
      cluster = false; // Get the images and clusters from the source 'stories"

      let features = map.querySourceFeatures('stories');

      for (id in markersOnScreen) {
        cluster = true;
        break;
      } // original Bezier


      if (bvVarMapGeoJson.features && bvVarMapGeoJson.features.length > 0) {
        //console.log(bvVarMapGeoJson.features,'features');
        //console.log(bvVarMapGeoJson,'bvVarMapGeoJson');
        let journeys = turf.featureReduce(bvVarMapGeoJson, (prev, current, index) => reduceUnionJourneys(prev, current, index)); 
	     //console.log(journeys,'journeys');

        routeData.features = journeys.map(x => buildJourneyBezier(x, bvVarMapGeoJson.features.filter(y => y.properties.journeys.includes(x)), true)).filter(function (el) {
          return el != null;
        });
	      //console.log(routeData.features,'route');

        colors = ['#1f77b4', // muted blue
        '#ff7f0e', // safety orange
        '#2ca02c', // cooked asparagus green
        '#d62728', // brick red
        '#9467bd', // muted purple
        '#8c564b', // chestnut brown
        '#e377c2', // raspberry yogurt pink
        '#7f7f7f', // middle gray
        '#bcbd22', // curry yellow-green
        '#17becf' // blue-teal
        ];

        for (let i = 0; i < routeData.features.length; i++) {
          //routeData.features[i].properties.color = colors[i%routeData.features.length];
          routeData.features[i].properties.color = RGBToHex(...randomRGB(routeData.features[i].properties.journey + 7 * i));
        }

        map.getSource('trace').setData(routeData);
      }

      if (!features) {
        return;
      }

      ; // for every cluster on the screen, create an HTML marker for it (if we didn't yet),
      // and add it to the map if it's not there already

      for (let i = 0; i < features.length; i++) {
        var _features$i$id;

        let id = (_features$i$id = features[i].id) !== null && _features$i$id !== void 0 ? _features$i$id : features[i].properties.id;
        let marker = markers[id];

        if (!marker) {
          let coords = features[i].geometry.coordinates;
          let el = createThumbnail(features[i]);
          marker = markers[id] = new mapboxgl.Marker({
            element: el
          }).setLngLat(coords);
          cluster = true;
        }

        newMarkers[id] = marker; //if (!cluster && features[i].properties.thumbnail && !nonClustermarkersOnScreen[features[i].properties.id]) {

        if (!cluster && features[i].properties.thumbnail && !nonClustermarkersOnScreen[features[i].id]) {
          let coords = features[i]['geometry'].coordinates;
          let el = createThumbnail(features[i]);
          marker = markers[id] = new mapboxgl.Marker({
            element: el
          }).setLngLat(coords);
          marker.addTo(map);
          nonClustermarkersOnScreen[features[i].id] = marker; //nonClustermarkersOnScreen[features[i].properties.id] = marker;
        }

        if (!markersOnScreen[id]) marker.addTo(map);
      } // for every marker we've added previously, remove those that are no longer visible


      for (id in markersOnScreen) {
        if (!newMarkers[id]) markersOnScreen[id].remove();
        if (!newMarkers[id]) markersOnScreen[id] = null;
      }

      markersOnScreen = newMarkers;
    } // map handlers
    // after the GeoJSON data is loaded, update markers on the screen and do so on every map move/moveend


    map.on('data', function (e) {
      if (e.sourceId !== 'stories') return;
      map.on('move', updateMarkers);
      map.on('moveend', fetchPostsInView);
      map.on('load', updateMarkers); // updateMarkers();
    }); // Cluster categories
    // Finally, add a layer for the clusters' count labels

    map.addLayer({
      "id": "cluster-count",
      "type": "symbol",
      "source": "stories"
    }); // Generate a bezier curve

	  // Get a bounding box based on the diagonal from NE to SW
    function bboxBuffer(factor) {
      let bounds = map.getBounds();
      let sw = [bounds._sw.lng, bounds._sw.lat];
      let ne = [bounds._ne.lng, bounds._ne.lat];
      let diagDist = turf.distance(sw, ne, {
        units: 'degrees'
      });
      bboxB = turf.buffer(turf.bboxPolygon(sw.concat(ne)), diagDist * factor, {
        units: 'degrees',
        steps: 4
      });
	    //console.log(bboxB,'bboxB');
      return bboxB;
    } // Sends ajax request to get all the posts inside the boundin box


    function fetchPostsInView() {
      if (shouldLoadThumbnails && (!boxToLoad || !turf.booleanWithin(bboxBuffer(0), boxToLoad))) {
        shouldLoadThumbnails = false;
        let bounds = map.getBounds();
        let sw = [bounds._sw.lng, bounds._sw.lat];
        let ne = [bounds._ne.lng, bounds._ne.lat];
        boxToLoad = bboxBuffer(0.1);
	  //console.log(boxToLoad,'boxToLoad');
        $.ajax({
          url: bvVarMap.plugin_dir + '/includes/ajax_geo.php',
          type: "POST",
          'dataType': 'json',
          data: {
            box_to_load: boxToLoad,
            mapboxjs_bounding_box: {
              ne: ne,
              sw: sw
            },
            query: bvVarMap.queried_obj,
		member: bvVarMap.private_member
          },
          success: function (data) {
            shouldLoadThumbnails = true;
            //console.log(data,'too much Data');
            bvVarMapGeoJson.features = data; // Update the stories source with the new data

            map.getSource('stories').setData(bvVarMapGeoJson);
            setTimeout(updateMarkers, 100);
          },
          error: function (jqXHR, textStatus, errorThrown) {
            shouldLoadThumbnails = true;
          }
        });
      }
    }

    fetchPostsInView();
  });
}
