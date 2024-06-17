document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('map').setView([-17.047558, -46.824176], 13);

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

    var drawControl = new L.Control.Draw({
        edit: {
            featureGroup: drawnItems
        },
        draw: {
            polygon: true,
            polyline: false,
            rectangle: false,
            circle: false,
            marker: false
        }
    });
    map.addControl(drawControl);

    map.on(L.Draw.Event.CREATED, function(event) {
        var layer = event.layer;
        drawnItems.addLayer(layer);
        fetchNDVIData(layer);
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
                    onEachFeature: function(feature, layer) {
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
});