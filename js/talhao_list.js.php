<link rel="stylesheet" href="./css/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw/dist/leaflet.draw.css" />

<script src="./js/leaflet.js"></script>
<script src="./js/leaflet.draw.js"></script>
<script src="./js/wellknown.js"></script>

<script>
    var map = L.map('mapList').setView([-17.047558, -46.824176], 13);

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


var drawnPolygon
// drawnPolygon = renderGeoJSON();

if(json_pol){
	console.log(json_pol);
	json_pol.forEach((data) => {
		renderGeoJSON(data);
	});
}


function renderGeoJSON(data) {
	// var geojsonInput = document.getElementsByClassName("fieldname_geo_json")[1].children[0].innerHTML;
    // console.log(geojsonInput);
	if (data) {
		var geojsonObject = JSON.parse(data);
		var geojsonLayer = L.geoJSON(geojsonObject);
		drawnItems.addLayer(geojsonLayer);
		map.fitBounds(geojsonLayer.getBounds());
		return geojsonLayer;
	}
	return null;
}
</script>