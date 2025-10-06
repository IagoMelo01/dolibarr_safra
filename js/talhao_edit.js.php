<link rel="stylesheet" href="./css/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw/dist/leaflet.draw.css" />

<script src="./js/leaflet.js"></script>
<script src="./js/leaflet.draw.js"></script>
<script src="./js/wellknown.js"></script>
<script src="./js/turf.js"></script>



<script>
<?php $sentinelHubId = getDolGlobalString('SAFRA_API_SENTINELHUB'); ?>
const SENTINELHUB_ID = '<?php echo addslashes($sentinelHubId); ?>';
const MAP_INITIAL_CENTER = [-17.047558, -46.824176];
const MAP_INITIAL_ZOOM = 13;
const CIRCLE_STEPS = 128;

var map = L.map('mapCRUD').setView(MAP_INITIAL_CENTER, MAP_INITIAL_ZOOM);

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
L.control.scale({position: 'bottomright', metric: true, imperial: false}).addTo(map);

var drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

customizeDrawTexts();

var drawControl = new L.Control.Draw({
        edit: {
                featureGroup: drawnItems,
                remove: true
        },
        draw: {
                polygon: {
                        allowIntersection: false,
                        showArea: true,
                        shapeOptions: defaultShapeOptions()
                },
                rectangle: false,
                polyline: false,
                marker: false,
                circlemarker: false,
                circle: {
                        showRadius: true,
                        shapeOptions: defaultShapeOptions()
                }
        }
});
map.addControl(drawControl);

var drawnPolygon = renderGeoJSON();

if (drawnPolygon) {
        focusOnDrawing();
}

map.on(L.Draw.Event.CREATED, function (event) {
        var layer = event.layer;

        if (event.layerType === 'circle') {
                layer = convertCircleToPolygon(layer) || layer;
        }

        applySingleLayer(layer);
        focusOnDrawing();
});

map.on('draw:edited', function (e) {
        e.layers.eachLayer(function (layer) {
                drawnPolygon = layer;
                if (layer.setStyle) {
                        layer.setStyle(defaultShapeOptions());
                }
                createAreaTooltip(layer);
                updateInputs(layer);
        });
        focusOnDrawing();
});

map.on('draw:deleted', function () {
        resetInputs();
        drawnPolygon = null;
        focusOnDrawing();
});

var fitButton = document.getElementById('fit-drawing');
if (fitButton) {
        fitButton.addEventListener('click', function () {
                focusOnDrawing();
        });
}

var clearButton = document.getElementById('clear-drawing');
if (clearButton) {
        clearButton.addEventListener('click', function () {
                handleClearDrawing();
        });
}

window.addEventListener('resize', function () {
        map.invalidateSize();
});

map.whenReady(function () {
        setTimeout(function () {
                map.invalidateSize();
                focusOnDrawing();
        }, 150);
});

function renderGeoJSON() {
        var geojsonInput = document.getElementById('geo_json');
        if (!geojsonInput || !geojsonInput.value) {
                return null;
        }

        try {
                var geojsonObject = JSON.parse(geojsonInput.value);
                var polygonLayers = L.geoJSON(geojsonObject).getLayers();
                if (polygonLayers.length) {
                        var layer = polygonLayers[0];
                        return applySingleLayer(layer);
                }
        } catch (error) {
                console.error('Erro ao carregar o GeoJSON do talhão', error);
        }

        return null;
}

function customizeDrawTexts() {
        if (!L.drawLocal || !L.drawLocal.draw) {
                return;
        }

        var buttons = L.drawLocal.draw.toolbar.buttons || {};
        buttons.polygon = 'Desenhar polígono';
        buttons.circle = 'Desenhar pivô (círculo)';
        buttons.rectangle = 'Desenhar retângulo';
        L.drawLocal.draw.toolbar.buttons = buttons;

        if (L.drawLocal.draw.handlers && L.drawLocal.draw.handlers.circle && L.drawLocal.draw.handlers.circle.tooltip) {
                L.drawLocal.draw.handlers.circle.tooltip.start = 'Clique e arraste para desenhar o pivô circular.';
        }
        if (L.drawLocal.draw.handlers && L.drawLocal.draw.handlers.polygon && L.drawLocal.draw.handlers.polygon.tooltip) {
                L.drawLocal.draw.handlers.polygon.tooltip.start = 'Clique para adicionar vértices ao polígono.';
        }
}

function defaultShapeOptions() {
        return {
                color: '#0b6fa4',
                weight: 2,
                fillColor: '#3ba1d7',
                fillOpacity: 0.25
        };
}

function applySingleLayer(layer) {
        if (!layer) {
                return null;
        }

        drawnItems.clearLayers();
        if (layer.setStyle) {
                layer.setStyle(defaultShapeOptions());
        }
        drawnItems.addLayer(layer);
        drawnPolygon = layer;
        createAreaTooltip(layer);
        updateInputs(layer);
        return layer;
}

function handleClearDrawing() {
        drawnItems.clearLayers();
        resetInputs();
        drawnPolygon = null;
        map.setView(MAP_INITIAL_CENTER, MAP_INITIAL_ZOOM);
}

function focusOnDrawing() {
        if (drawnPolygon) {
                map.fitBounds(drawnPolygon.getBounds(), {padding: [40, 40]});
        } else {
                map.setView(MAP_INITIAL_CENTER, MAP_INITIAL_ZOOM);
        }
}

function convertCircleToPolygon(circleLayer) {
        var center = circleLayer.getLatLng();
        var polygonGeo = turf.circle([center.lng, center.lat], circleLayer.getRadius() / 1000, {
                steps: CIRCLE_STEPS,
                units: 'kilometers'
        });

        var polygonLayers = L.geoJSON(polygonGeo).getLayers();
        if (polygonLayers.length) {
                var polygonLayer = polygonLayers[0];
                if (circleLayer.options && polygonLayer.setStyle) {
                        polygonLayer.setStyle(circleLayer.options);
                } else if (polygonLayer.setStyle) {
                        polygonLayer.setStyle(defaultShapeOptions());
                }
                return polygonLayer;
        }

        return null;
}

function getPrimaryLatLngs(layer) {
        if (!layer || typeof layer.getLatLngs !== 'function') {
                return [];
        }

        return extractFirstRing(layer.getLatLngs());
}

function extractFirstRing(latLngs) {
        if (!Array.isArray(latLngs)) {
                return [];
        }
        if (!latLngs.length) {
                return [];
        }
        if (Array.isArray(latLngs[0])) {
                return extractFirstRing(latLngs[0]);
        }
        return latLngs;
}

function updateInputs(layer) {
        if (!layer) {
                return;
        }

        var bounds = layer.getBounds();
        if (bounds && typeof bounds.isValid === 'function' && !bounds.isValid()) {
                return;
        }

        var bbox = bounds.toBBoxString();
        var geojson = layer.toGeoJSON();
        var wkt = wellknown.stringify(geojson);
        var encodedWKT = encodeURIComponent(wkt);

        var inputGeoJson = document.getElementById('geo_json');
        if (inputGeoJson) {
                inputGeoJson.value = JSON.stringify(geojson);
        }

        var inputWkt = document.getElementById('wkt');
        if (inputWkt) {
                inputWkt.value = encodedWKT;
        }

        var inputBbox = document.getElementById('bbox');
        if (inputBbox) {
                inputBbox.value = bbox;
        }

        var latLngs = getPrimaryLatLngs(layer);
        if (latLngs.length && L.GeometryUtil && L.GeometryUtil.geodesicArea) {
                var area = L.GeometryUtil.geodesicArea(latLngs);
                var inputArea = document.getElementById('area');
                if (inputArea) {
                        inputArea.value = area / 10000;
                }
        }

        updateAreaTooltip(layer);
}

function resetInputs() {
        ['geo_json', 'wkt', 'bbox', 'area'].forEach(function (fieldId) {
                var input = document.getElementById(fieldId);
                if (input) {
                        input.value = '';
                }
        });
}

function createAreaTooltip(layer) {
        if (!layer || typeof layer.getLatLngs !== 'function') {
                return;
        }

        if (layer.areaTooltip) {
                updateAreaTooltip(layer);
                return;
        }

        layer.areaTooltip = L.tooltip({
                permanent: true,
                direction: 'center',
                className: 'area-tooltip'
        });

        layer.on('remove', function () {
                if (layer.areaTooltip) {
                        layer.areaTooltip.remove();
                }
        });

        layer.on('add', function () {
                updateAreaTooltip(layer);
                if (layer.areaTooltip) {
                        layer.areaTooltip.addTo(map);
                }
        });

        if (map.hasLayer(layer) && layer.areaTooltip) {
                updateAreaTooltip(layer);
                layer.areaTooltip.addTo(map);
        }
}

function updateAreaTooltip(layer) {
        if (!layer || !layer.areaTooltip) {
                return;
        }

        var latLngs = getPrimaryLatLngs(layer);
        if (!latLngs.length || !L.GeometryUtil || !L.GeometryUtil.geodesicArea) {
                return;
        }

        var area = L.GeometryUtil.geodesicArea(latLngs);
        var readableArea = L.GeometryUtil.readableArea(area, true);
        var center = layer.getBounds().getCenter();

        layer.areaTooltip
                .setContent(readableArea)
                .setLatLng(center);
}

function fetchNDVIData(layer) {
        var wkt = wellknown.stringify(layer.toGeoJSON());
        var encodedWKT = encodeURIComponent(wkt);
        var url = `https://services.sentinel-hub.com/ogc/wms/${SENTINELHUB_ID}?service=WMS&request=GetMap&layers=NDVI&styles=&format=application/json&transparent=true&RESX=10m&RESY=10m&srs=CRS:84&geometry=${encodedWKT}/`;

        fetch(url)
                .then(response => response.json())
                .then(data => {
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
                                }
                        }).addTo(map);
                        map.fitBounds(geoJsonLayer.getBounds());
                })
                .catch(error => console.error('Error fetching NDVI data:', error));
}
</script>
