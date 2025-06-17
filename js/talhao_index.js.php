<link rel="stylesheet" href="./css/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw/dist/leaflet.draw.css" />

<script src="./js/leaflet.js"></script>
<script src="./js/leaflet.draw.js"></script>
<script src="./js/wellknown.js"></script>

<script>
    var map = L.map('mapIndex').setView([-17.047558, -46.824176], 13);
    var loader = document.getElementById('boxLoading');

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

    map.whenReady(function () {
        if (loader) loader.classList.remove('display');
    });


    var drawnPolygon
    // drawnPolygon = renderGeoJSON();
    let i = 0;

    if (json) {
        // console.log(json);
        json.forEach((data) => {
            let json_layer = renderGeoJSON(data);
            console.log(json_layer);
            createAreaTooltip(json_layer, i);
            i++;
        });
    }


    function renderGeoJSON(data) {
        // var geojsonInput = document.getElementsByClassName("fieldname_geo_json")[1].children[0].innerHTML;
        // console.log(geojsonInput);
        if (data) {
            var geojsonObject = JSON.parse(data);
            var geojsonLayer = L.geoJSON(geojsonObject);
            drawnItems.addLayer(geojsonLayer);
            // createAreaTooltip(geojsonLayer);
            map.fitBounds(geojsonLayer.getBounds());
            return geojsonLayer;
        }
        return null;
    }


    function createAreaTooltip(layer, i) {
        if (layer.areaTooltip) {
            updateAreaTooltip(layer);
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
            updateAreaTooltip(layer, i);
            layer.areaTooltip.addTo(map);
        }
    }

    function updateAreaTooltip(layer) {
        var area = area_array[i]
        var readableArea = L.GeometryUtil.readableArea(area, true);
        var latlng = layer.getCenter();

        layer.areaTooltip
            .setContent(readableArea)
            .setLatLng(latlng);
    }
</script>