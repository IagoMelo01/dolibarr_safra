<link rel="stylesheet" href="./css/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw/dist/leaflet.draw.css" />

<script src="./js/leaflet.js"></script>
<script src="./js/leaflet.draw.js"></script>
<script src="./js/wellknown.js"></script>

<script>
    var map = L.map('mapShow').setView([-17.047558, -46.824176], 13);

var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
    maxZoom: 20
});

var osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Map data &copy; OpenStreetMap contributors'
});

var googleHybrid = L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
});

satelliteLayer.addTo(map);

var baseLayers = {
    'Imagem de satélite (Esri)': satelliteLayer,
    'Satélite (Google)': googleHybrid,
    'Mapa de ruas (OSM)': osmLayer
};

L.control.layers(baseLayers, null, {position: 'topright'}).addTo(map);

var drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

L.control.scale({position: 'bottomright', metric: true, imperial: false}).addTo(map);


var drawnPolygon
drawnPolygon = renderGeoJSON();


function renderGeoJSON() {
	var geojsonInput = document.getElementsByClassName("fieldname_geo_json")[1].children[0].innerHTML;
    console.log(geojsonInput);
	if (geojsonInput) {
		var geojsonObject = JSON.parse(geojsonInput);
		var geojsonLayer = L.geoJSON(geojsonObject);
		drawnItems.addLayer(geojsonLayer);
		map.fitBounds(geojsonLayer.getBounds());
		return geojsonLayer;
	}
	return null;
}
</script>