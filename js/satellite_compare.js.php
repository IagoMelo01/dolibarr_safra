<link rel="stylesheet" href="./css/leaflet.css" />
<script src="./js/leaflet.js"></script>

<script>
(function () {
    const config = window.safraSatelliteCompare || {};
    const mapConfigs = Array.isArray(config.maps) ? config.maps : [];
    const mapEntries = [];
    let synchronizing = false;
    const compareForm = document.getElementById('satelliteCompareForm');
    const periodInput = document.getElementById('satelliteComparePeriod');
    const comparisonReferenceDateInput = document.getElementById('comparison_reference_date');
    const previousDateRow = document.getElementById('satelliteComparePreviousDateRow');
    const customPeriodButton = document.getElementById('satelliteCompareCustomPeriod');

    if (compareForm && periodInput) {
        compareForm.querySelectorAll('.satellite-compare-shortcut[data-period]').forEach(function (button) {
            button.addEventListener('click', function () {
                periodInput.value = button.dataset.period || periodInput.value;
                if (comparisonReferenceDateInput) {
                    comparisonReferenceDateInput.value = '';
                }
                if (previousDateRow) {
                    previousDateRow.classList.remove('is-visible');
                }
                if (customPeriodButton) {
                    customPeriodButton.classList.remove('is-active');
                }
            });
        });
    }

    if (customPeriodButton && previousDateRow) {
        customPeriodButton.addEventListener('click', function () {
            compareForm.querySelectorAll('.satellite-compare-shortcut[data-period]').forEach(function (button) {
                button.classList.remove('is-active');
            });
            previousDateRow.classList.add('is-visible');
            customPeriodButton.classList.add('is-active');
            if (comparisonReferenceDateInput) {
                comparisonReferenceDateInput.focus();
            }
        });
    }

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function featureColor(feature) {
        const properties = feature && feature.properties ? feature.properties : {};
        const raw = String(properties.COLOR_HEX || '').replace('#', '').trim();
        return /^[0-9a-fA-F]{6}$/.test(raw) ? `#${raw}` : '#2563eb';
    }

    function featureValue(properties, indexCode) {
        const keys = indexCode === 'health'
            ? ['HEALTH_SCORE', 'health_score', 'SCORE', 'score']
            : [String(indexCode || '').toUpperCase(), String(indexCode || '').toLowerCase(), 'VALUE', 'value', 'INDEX', 'index', 'B0', 'b0'];

        for (let i = 0; i < keys.length; i += 1) {
            if (!Object.prototype.hasOwnProperty.call(properties, keys[i])) {
                continue;
            }
            const parsed = Number(properties[keys[i]]);
            if (Number.isFinite(parsed)) {
                return parsed;
            }
        }
        return null;
    }

    function setStatus(entryConfig, message) {
        const element = document.getElementById(entryConfig.statusId);
        if (element) {
            element.textContent = message || '';
        }
    }

    function addBoundary(map, entryConfig) {
        if (!entryConfig.talhaoGeoJson) {
            return null;
        }

        try {
            const layer = L.geoJSON(JSON.parse(entryConfig.talhaoGeoJson), {
                style: {
                    color: '#0f172a',
                    weight: 2,
                    fillColor: '#94a3b8',
                    fillOpacity: 0.1
                }
            }).addTo(map);
            if (layer.getBounds().isValid()) {
                map.fitBounds(layer.getBounds(), { padding: [12, 12] });
            }
            return layer;
        } catch (error) {
            console.error('Unable to render field boundary:', error);
            return null;
        }
    }

    function addAnalysis(map, entryConfig, boundaryLayer) {
        if (!entryConfig.url) {
            setStatus(entryConfig, entryConfig.status || config.missingMessage);
            return;
        }

        fetch(entryConfig.url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(function (geojson) {
                const features = geojson && Array.isArray(geojson.features) ? geojson.features : [];
                if (!features.length) {
                    throw new Error('Empty feature collection');
                }

                const layer = L.geoJSON({
                    type: 'FeatureCollection',
                    features: features
                }, {
                    style: function (feature) {
                        const color = featureColor(feature);
                        return {
                            color: color,
                            weight: 1,
                            fillColor: color,
                            fillOpacity: 0.95
                        };
                    },
                    onEachFeature: function (feature, featureLayer) {
                        const properties = feature.properties || {};
                        const value = featureValue(properties, entryConfig.index);
                        const decimals = entryConfig.index === 'health' ? 2 : 3;
                        const valueLabel = value === null ? '--' : value.toLocaleString(undefined, { maximumFractionDigits: decimals });
                        featureLayer.bindTooltip(
                            `<strong>${escapeHtml(entryConfig.indexLabel || String(entryConfig.index).toUpperCase())}</strong><br>`
                            + `${escapeHtml(config.labels.field)}: ${escapeHtml(entryConfig.talhaoLabel)}<br>`
                            + `${escapeHtml(config.labels.value)}: ${escapeHtml(valueLabel)}`,
                            { sticky: true }
                        );
                        featureLayer.on({
                            mouseover: function (event) {
                                event.target.setStyle({ color: '#111827', weight: 2, fillOpacity: 1 });
                                if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                                    event.target.bringToFront();
                                }
                            },
                            mouseout: function (event) {
                                layer.resetStyle(event.target);
                            }
                        });
                    }
                }).addTo(map);

                setStatus(entryConfig, entryConfig.status || config.loadedMessage);
                if (layer.getBounds().isValid()) {
                    map.fitBounds(layer.getBounds(), { padding: [12, 12] });
                } else if (boundaryLayer && boundaryLayer.getBounds().isValid()) {
                    map.fitBounds(boundaryLayer.getBounds(), { padding: [12, 12] });
                }
            })
            .catch(function (error) {
                console.error('Unable to load satellite comparison map:', error);
                setStatus(entryConfig, config.missingMessage || entryConfig.status);
            });
    }

    function createMap(entryConfig) {
        const target = document.getElementById(entryConfig.mapId);
        if (!target) {
            return null;
        }

        const map = L.map(entryConfig.mapId, { zoomControl: true }).setView([-17.047558, -46.824176], 13);
        const streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; OpenStreetMap contributors'
        });
        const satellite = L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
        });
        satellite.addTo(map);
        L.control.layers({
            'Satelite': satellite,
            'Ruas': streets
        }).addTo(map);

        const boundaryLayer = addBoundary(map, entryConfig);
        addAnalysis(map, entryConfig, boundaryLayer);

        return { map: map, config: entryConfig };
    }

    function synchronizeMaps() {
        if (synchronizing || mapEntries.length !== 2) {
            return;
        }
        const source = this;
        const target = mapEntries[0].map === source ? mapEntries[1].map : mapEntries[0].map;
        const sourceCenter = source.getCenter();
        const targetCenter = target.getCenter();
        if (source.getZoom() === target.getZoom()
            && Math.abs(sourceCenter.lat - targetCenter.lat) < 0.0000001
            && Math.abs(sourceCenter.lng - targetCenter.lng) < 0.0000001) {
            return;
        }

        synchronizing = true;
        target.setView(sourceCenter, source.getZoom(), { animate: false });
        synchronizing = false;
    }

    mapConfigs.forEach(function (entryConfig) {
        const entry = createMap(entryConfig);
        if (entry) {
            mapEntries.push(entry);
        }
    });

    if (mapEntries.length === 2) {
        mapEntries.forEach(function (entry) {
            entry.map.on('moveend zoomend', synchronizeMaps);
        });
    }

    function preparePrintableReport() {
        document.body.classList.add('safra-satellite-printing');
        mapEntries.forEach(function (entry) {
            entry.map.invalidateSize({ animate: false });
        });
        window.dispatchEvent(new Event('resize'));
    }

    function restoreScreenReport() {
        document.body.classList.remove('safra-satellite-printing');
        mapEntries.forEach(function (entry) {
            entry.map.invalidateSize({ animate: false });
        });
    }

    const exportButton = config.exportButtonId ? document.getElementById(config.exportButtonId) : null;
    if (exportButton) {
        exportButton.addEventListener('click', function () {
            exportButton.disabled = true;
            exportButton.textContent = config.preparingLabel || config.exportLabel || 'PDF';
            preparePrintableReport();

            window.setTimeout(function () {
                window.print();
                exportButton.disabled = false;
                exportButton.textContent = config.exportLabel || 'PDF';
            }, 250);
        });
    }

    window.addEventListener('beforeprint', preparePrintableReport);
    window.addEventListener('afterprint', function () {
        restoreScreenReport();
        if (exportButton) {
            exportButton.disabled = false;
            exportButton.textContent = config.exportLabel || 'PDF';
        }
    });
})();
</script>
