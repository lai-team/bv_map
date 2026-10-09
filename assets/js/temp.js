if (!map) {
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
    };
    console.log(routeData.features[0].geometry.coordinates);
    var shouldLoadThumbnails = true;



    //console.log(bvVarMapGeoJson);
    var map = new mapboxgl.Map({
        container: "map", // container id
        style: bvVarMap.mapStyle, // stylesheet location
        center: bvVarMap.center, // s
    });
    map.on('style.load', function() {
        // Later on, we'll add layers to differentiate our marker clusters
        // by the number of markers they represent. We'll store the breaks
        // between each category here so we can change them easily. 
        let highCount = 75,
            lowCount = 15;

        // Add a new source from our GeoJSON data and set the 
        // 'cluster' option to true. 
        map.addSource("stories", {
            type: "geojson",
            data: bvVarMapGeoJson,
            cluster: true,
            clusterMaxZoom: 10, // Max zoom to cluster points on
            clusterRadius: 80, // Radius of each cluster when clustering points (defaults to 400)
            clusterProperties: {
                // get the highest id  of the clustered points
                "id": ["max", ["get", "id"], ''],
            },
        });
        // Source that is used for drawing the connections betweeen points
        map.addSource('trace', {
            type: 'geojson',
            data: routeData
        });
        // Layer that draws the connections between points
        map.addLayer({
            'id': 'trace',
            'type': 'line',
            'source': 'trace',
            'paint': {
                'line-color': 'white',
                'line-opacity': 1,
                'line-width': 5
            },
            'layout': {
                'line-join': 'round',
                'line-cap': 'round'
            },
        });

        let markers = {};
        let markersOnScreen = {};
        let nonClustermarkersOnScreen = {};

        // Function that adds thumbnails on top of the map as HTML objects
        function createThumbnail(props) {
            let html ='<div class="cluster-img-wrapper">';            
            html+='<img src="' +
                getImageById(props.id) +
                '" style="position:relative;width:100%;height:100%" class="img-on-map">';            
            html+='</div>';

            // If there is a cluster this will display the number of images clustered
            if(props.point_count){
                html+= "<span class='point_count'>"+ props.point_count + "</span>" 
            }

            let el = document.createElement("div");
            el.innerHTML = html;
            el.className = "marker";

            // Event handler which scrolls to the story post which holds the image we click
            el.onclick = () => {
                console.log(props.id);
                //console.log(props.id);
                $("#post_" + props.id)[0].scrollIntoView({
                    behavior: "smooth", // or "auto" or "instant"
                    block: "start" // or "end"
                });
            };
            return el;
        }


        // Get the thumbnail from the bvVarMapGeoJson.features for a given ID
        function getImageById(id) {
            if (!bvVarMapGeoJson.features) return;
            return bvVarMapGeoJson.features.find(({
                properties
            }) => properties.id == id).properties.thumbnail;
        }

        

        // Updates the markers on the map 
        // Shows only what is inside the map bounding box
        function updateMarkers() {
            let newMarkers = {};
            cluster = false;
            // Get the images and clusters from the source 'stories"

            let features = map.querySourceFeatures('stories');
            for (id in markersOnScreen) {
                cluster = true;
                break;
            }

            if (bvVarMapGeoJson.features) {
                // Get the coordinates of all the rendered points
                points = bvVarMapGeoJson.features.map(feature => {
                    return feature.geometry.coordinates;
                });
                points_bezier = [];

                // generate bezier curve for the given points
                if(points.length <3) points_bezier = points;
                else 
                    for(let i = 0; i < points.length - 1;i++){

                        if(i == 0)
                                points_bezier.push(...createBezierCurve(points[i],  points[i], controlPointCalc(points[i+1],points[i+2],true), points[i+1],100));
                        else if(i == points.length-2)
                                points_bezier.push(...createBezierCurve(points[i],  controlPointCalc(points[i],points[i-1],false), points[i+1], points[i+1],100));
                        else 
                                points_bezier.push(...createBezierCurve(points[i],  controlPointCalc(points[i],points[i-1],false), controlPointCalc(points[i+1],points[i+2],true), points[i+1],100));
                    }
                routeData.features[0].geometry.coordinates = points_bezier;
                // console.log(points_bezier);
                
                map.getSource('trace').setData(routeData);
            }
            if (!features) {
                return
            };
            // for every cluster on the screen, create an HTML marker for it (if we didn't yet),
            // and add it to the map if it's not there already
            for (let i = 0; i < features.length; i++) {
                let id = features[i].id ?? features[i].properties.id;

                let marker = markers[id];
                if (!marker) {
                    let coords = features[i].geometry.coordinates;
                    let el = createThumbnail(features[i].properties);
                    marker = markers[id] = new mapboxgl.Marker({
                        element: el
                    }).setLngLat(coords);
                    cluster = true;
                }
                newMarkers[id] = marker;
                if (!cluster && features[i].properties.thumbnail && !nonClustermarkersOnScreen[features[i].properties.id]) {
                    let coords = features[i]['geometry'].coordinates;
                    let el = createThumbnail(features[i].properties);
                    marker = markers[id] = new mapboxgl.Marker({
                        element: el
                    }).setLngLat(coords);
                    marker.addTo(map);
                    nonClustermarkersOnScreen[features[i].properties.id] = marker;


                }

                if (!markersOnScreen[id]) marker.addTo(map);
            }
            // for every marker we've added previously, remove those that are no longer visible
            for (id in markersOnScreen) {
                if (!newMarkers[id]) markersOnScreen[id].remove();
                if (!newMarkers[id]) markersOnScreen[id] = null;
            }
            markersOnScreen = newMarkers;
        }
        // map handlers
        // after the GeoJSON data is loaded, update markers on the screen and do so on every map move/moveend
        map.on('data', function(e) { 

            if (e.sourceId !== 'stories') return;
            map.on('move', updateMarkers);
            map.on('moveend', fetchPostsInView);
            map.on('load', updateMarkers);
            // updateMarkers();
        });

        // Cluster categories
        // Finally, add a layer for the clusters' count labels
        map.addLayer({
            "id": "cluster-count",
            "type": "symbol",
            "source": "stories"
        });

        // Generate a bezier curve
        let createBezierCurve = (p1,p2,p3,p4,precision) =>{
            // console.log({p1,p2,p3,p4})
            // p1 is the starting point p4 is the end point
            // p2 and p3 are control points
            // precision is the number of segments that will be used for the cruve
            let points = [];
            // if(precision<=0)return;
            for(let t = 0 ; t <= 1; t+= (1 / precision)){

              let point_at_t_x = Math.pow((1-t),3)*p1[0] + 3*Math.pow((1-t),2) *t*p2[0] +3 * (1-t)*Math.pow(t,2)* p3[0] + Math.pow(t,3)*p4[0];
              
              let point_at_t_y = Math.pow((1-t),3)*p1[1] + 3*Math.pow((1-t),2) *t*p2[1] +3 * (1-t)*Math.pow(t,2)* p3[1] + Math.pow(t,3)*p4[1];
              
              points.push([point_at_t_x, point_at_t_y]);
            }
            return points;
          }
          function getBaseLog(x, y) {
            if(x==0)x=0.01;
            if(y==0)y=0.01;
            return Math.log(y) / Math.log(x);
          }
        // Calculate the coordinates for the control points based on an adjacent point
        let controlPointCalc = (p1,p2,direction) => {
            let smoothness = 3;
            if(p1.length != p2.length)return;
            
            return p1.map(function (num, idx) {
                let isPositive = p2[idx]>=0;
                let newP2 = getBaseLog(Math.abs(p2[idx]) , smoothness ) ;
                if(!isPositive)newP2*=-1;
                if(!direction)newP2*=-1;
                return num + newP2 ;
            }); 
        }
        
        // Sends ajax request to get all the posts inside the boundin box
        function fetchPostsInView() {
            if (shouldLoadThumbnails) {
                shouldLoadThumbnails = false;
                let bounds = map.getBounds();
                let sw = [bounds._sw.lng, bounds._sw.lat];
                let ne = [bounds._ne.lng, bounds._ne.lat];
                $.ajax({
                    url: bvVarMap.plugin_dir + '/includes/ajax.php',
                    type: "POST",
                    'dataType': 'json',
                    data: {
                        mapboxjs_bounding_box: {
                            ne: ne,
                            sw: sw,
                        }
                    },
                    success: function(data) {
                        shouldLoadThumbnails = true;
                        bvVarMapGeoJson.features = data;
                        // Update the stories source with the new data
                        map.getSource('stories').setData(bvVarMapGeoJson);
                        setTimeout(updateMarkers, 100);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        shouldLoadThumbnails = true;
                    }
                })
            }
        }
        fetchPostsInView();
    });
}
