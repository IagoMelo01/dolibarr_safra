<link rel="stylesheet" href="./css/leaflet.css" />
<link rel="stylesheet" href="./css/leaflet-geoman.css" />

<script src="./js/leaflet.js"></script>
<script src="./js/leaflet-geoman.min.js"></script>
<script src="./js/turf.js"></script>
<script src="./js/wellknown.js"></script>



<script>
    
var map = L.map('mapCRUD').setView([-17.047558, -46.824176], 13);

map.pm.setLang('pt');

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Map data &copy; OpenStreetMap contributors'
}).addTo(map);

var drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

googleHybrid = L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
});
googleHybrid.addTo(map);

// add leaflet-geoman controls with some options to the map
map.pm.addControls({
		position: 'topleft',
		drawMarker: false,
		drawCircleMarker: false,
		drawPolyline: false,
		drawRectangle: false,
		drawCircle: false,
	})

    // enable polygon draw mode
	map.pm.enableDraw('Polygon', {
		layerGroup: map,
		snappable: true,
		snapDistance: 10,
	})

	map.pm.disableDraw()

map.on('pm:create', function (event) {
    var layer = event.layer;
    drawnItems.addLayer(layer);
    var bounds = layer.getBounds();
    var bbox = bounds.toBBoxString();
    var geojson = layer.toGeoJSON();
    var wkt = wellknown.stringify(geojson);
    var encondedWKT = encodeURIComponent(wkt);
    var input_geojson = document.getElementById("geo_json")
    input_geojson.value = JSON.stringify(geojson);
    var input_wkt = document.getElementById("wkt")
    input_wkt.value = encondedWKT;
    var input_bbox = document.getElementById("bbox")
    input_bbox.value = bbox;

    createAreaTooltip(layer);
    var area = L.GeometryUtil.geodesicArea(layer.getLatLngs()[0]); // Get polygon area
    // console.log(area + 'm2');
    var areaHa = area / 10000; // Convert area to hectares
    var input_area = document.getElementById("area")
    input_area.value = areaHa;
    // fetchNDVIData(layer);
    
        layer.on('pm:update', function (event) {
            var layer = event.layer;
            drawnItems.addLayer(layer);
            var bounds = layer.getBounds();
            var bbox = bounds.toBBoxString();
            var geojson = layer.toGeoJSON();
            var wkt = wellknown.stringify(geojson);
            var encondedWKT = encodeURIComponent(wkt);
            var input_geojson = document.getElementById("geo_json")
            input_geojson.value = JSON.stringify(geojson);
            var input_wkt = document.getElementById("wkt")
            input_wkt.value = encondedWKT;
            var input_bbox = document.getElementById("bbox")
            input_bbox.value = bbox;
            
            createAreaTooltip(layer);
            var area = L.GeometryUtil.geodesicArea(layer.getLatLngs()[0]); // Get polygon area
            // console.log(area + 'm2');
            var areaHa = area / 10000; // Convert area to hectares
            var input_area = document.getElementById("area")
            input_area.value = areaHa;
            // fetchNDVIData(layer);
        });
    });
    
    function fetchNDVIData(layer) {
        var wkt = wellknown.stringify(layer.toGeoJSON());
        var encodedWKT = encodeURIComponent(wkt);
        var url = `https://services.sentinel-hub.com/ogc/wms/3f380032-35a2-468e-83b2-0363da66b000?service=WMS&request=GetMap&layers=NDVI&styles=&format=application/json&transparent=true&RESX=10m&RESY=10m&srs=CRS:84&geometry=${encodedWKT}/`;
        console.log(url);
        
    // Fetch NDVI data in GeoJSON
    fetch(url)
        .then(response => response.json())
        .then(data => {
            let counter = 0;
            var geoJsonLayer = L.geoJSON(data, {
                onEachFeature: function (feature, layer) {
                    layer.setStyle({
                        color: `#${feature.properties.COLOR_HEX}`,
                        stroke: true,
                        weight: 1,
                        fill: true,
                        fillOpacity: 1,
                        fillColor: `#${feature.properties.COLOR_HEX}`
                    });
                    counter++;
                }
            }).addTo(map);
            map.fitBounds(geoJsonLayer.getBounds());
        })
        .catch(error => console.error('Error fetching NDVI data:', error));
}


function createAreaTooltip(layer) {
            if (layer.areaTooltip) {
                return;
            }

            layer.areaTooltip = L.tooltip({
                permanent: true,
                direction: 'center',
                className: 'area-tooltip'
            });

            layer.on('remove', function(event) {
                layer.areaTooltip.remove();
            });

            layer.on('add', function(event) {
                updateAreaTooltip(layer);
                layer.areaTooltip.addTo(map);
            });

            if (map.hasLayer(layer)) {
                updateAreaTooltip(layer);
                layer.areaTooltip.addTo(map);
            }
        }

        function updateAreaTooltip(layer) {
            var area = L.GeometryUtil.geodesicArea(layer.getLatLngs()[0]);
            var readableArea = L.GeometryUtil.readableArea(area, true);
            var latlng = layer.getCenter();

            layer.areaTooltip
                .setContent(readableArea)
                .setLatLng(latlng);
        }


</script>


