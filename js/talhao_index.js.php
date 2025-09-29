<script src="./js/leaflet.js"></script>
<script src="./js/leaflet.draw.js"></script>
<script src="./js/wellknown.js"></script>

<script>
    (function () {
        const talhoes = Array.isArray(window.safraTalhoes) ? window.safraTalhoes : [];
        const areaByMunicipio = Array.isArray(window.safraAreaByMunicipio) ? window.safraAreaByMunicipio : [];
        const weatherConfig = window.safraWeatherConfig || {};
        const labels = window.safraLabels || {};

        window.safraCharts = window.safraCharts || {};

        const mapElement = document.getElementById('mapIndex');
        if (!mapElement) {
            return;
        }

        const computedHeight = window.getComputedStyle(mapElement).height;
        if ((!computedHeight || computedHeight === '0px') && !mapElement.style.height) {
            mapElement.style.height = '360px';
        }

        const map = L.map('mapIndex').setView([-17.047558, -46.824176], 5);
        const loader = document.getElementById('boxLoading');
        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; OpenStreetMap contributors'
        }).addTo(map);

        const satelliteLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
            maxZoom: 19,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
        });
        satelliteLayer.addTo(map);

        let bounds = null;

        talhoes.forEach(function (talhao) {
            if (!talhao || !talhao.geo_json) return;

            try {
                const geojsonObject = JSON.parse(talhao.geo_json);
                const layerGroup = L.geoJSON(geojsonObject, {
                    onEachFeature: function (feature, layer) {
                        layer._safraData = {
                            area: parseFloat(talhao.area) || 0,
                            label: talhao.label || talhao.ref || ''
                        };
                        attachTooltip(layer);
                    }
                });

                drawnItems.addLayer(layerGroup);
                const layerBounds = layerGroup.getBounds();
                if (layerBounds.isValid()) {
                    bounds = bounds ? bounds.extend(layerBounds) : layerBounds;
                }
            } catch (error) {
                console.error('Erro ao interpretar o GeoJSON do talhão.', error);
            }
        });

        map.whenReady(function () {
            if (loader) loader.classList.remove('display');
            if (bounds && bounds.isValid()) {
                map.fitBounds(bounds.pad(0.1));
            } else {
                map.setView([-17.047558, -46.824176], talhoes.length ? 8 : 4);
            }
        });

        function attachTooltip(layer) {
            const tooltip = L.tooltip({
                permanent: true,
                direction: 'center',
                className: 'area-tooltip'
            });
            layer.areaTooltip = tooltip;

            layer.on('remove', function () {
                tooltip.remove();
            });

            layer.on('add', function () {
                updateTooltip(layer);
                tooltip.addTo(map);
            });

            if (map.hasLayer(layer)) {
                updateTooltip(layer);
                tooltip.addTo(map);
            }
        }

        function updateTooltip(layer) {
            const data = layer._safraData || {};
            const area = parseFloat(data.area) || 0;
            const decimals = area >= 10 ? 1 : 2;
            const unit = labels.areaUnit || 'ha';
            const label = data.label ? data.label + '<br>' : '';
            const center = layer.getBounds().getCenter();

            layer.areaTooltip
                .setContent(label + area.toFixed(decimals) + ' ' + unit)
                .setLatLng(center);
        }

        renderCharts(talhoes, areaByMunicipio);
        renderWeather(weatherConfig);
    })();

    function renderCharts(talhoes, areaByMunicipio) {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js não carregado.');
            return;
        }

        const registry = window.safraCharts || (window.safraCharts = {});

        function prepareCanvas(canvas) {
            if (!canvas) return null;
            const parent = canvas.parentNode;
            if (parent && parent.clientHeight) {
                canvas.height = parent.clientHeight;
            }
            return canvas.getContext('2d');
        }

        const talhaoCanvas = document.getElementById('talhaoAreaChart');
        if (talhaoCanvas && talhoes.length) {
            const ctxTalhao = prepareCanvas(talhaoCanvas);
            if (!ctxTalhao) {
                console.warn('Canvas do gráfico de talhão indisponível.');
            } else {
                if (registry.talhao) {
                    registry.talhao.destroy();
                }
                registry.talhao = new Chart(ctxTalhao, {
                    type: 'bar',
                    data: {
                        labels: talhoes.map(function (item) { return item.label || item.ref || ''; }),
                        datasets: [{
                            label: window.safraLabels && window.safraLabels.areaUnit ? window.safraLabels.areaUnit : 'ha',
                            data: talhoes.map(function (item) { return parseFloat(item.area) || 0; }),
                            backgroundColor: 'rgba(52, 152, 219, 0.35)',
                            borderColor: 'rgba(52, 152, 219, 0.9)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        }

        const municipioCanvas = document.getElementById('municipioAreaChart');
        if (municipioCanvas && areaByMunicipio.length) {
            const ctxMunicipio = prepareCanvas(municipioCanvas);
            if (!ctxMunicipio) {
                console.warn('Canvas do gráfico de município indisponível.');
            } else {
                if (registry.municipio) {
                    registry.municipio.destroy();
                }
                registry.municipio = new Chart(ctxMunicipio, {
                    type: 'doughnut',
                    data: {
                        labels: areaByMunicipio.map(function (item) { return item.label; }),
                        datasets: [{
                            data: areaByMunicipio.map(function (item) { return parseFloat(item.area) || 0; }),
                            backgroundColor: ['#1abc9c', '#3498db', '#9b59b6', '#f1c40f', '#e67e22', '#34495e', '#2ecc71']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        }
    }

    function renderWeather(config) {
        const container = document.getElementById('weather-content');
        if (!container) {
            return;
        }

        const labels = window.safraLabels || {};
        if (!config || !config.latitude || !config.longitude) {
            container.textContent = labels.weatherConfigure || '';
            return;
        }

        const url = `https://api.open-meteo.com/v1/forecast?latitude=${config.latitude}&longitude=${config.longitude}` +
            '&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m,apparent_temperature' +
            '&daily=temperature_2m_max,temperature_2m_min,precipitation_sum&timezone=auto';

        container.textContent = labels.weatherLoading || '';

        fetch(url)
            .then(function (response) { return response.json(); })
            .then(function (data) { updateWeather(container, data, config.location); })
            .catch(function () {
                container.textContent = labels.weatherError || '';
            });
    }

    function updateWeather(container, data, locationLabel) {
        const labels = window.safraLabels || {};
        if (!data || !data.current) {
            container.textContent = labels.weatherError || '';
            return;
        }

        const current = data.current;
        const daily = data.daily || {};
        const description = getWeatherDescription(current.weather_code);

        const html = [];
        html.push('<div class="safra-weather__current">');
        html.push('<div>');
        html.push(`<div class="safra-weather__temp">${Math.round(current.temperature_2m)}°C</div>`);
        html.push(`<div class="safra-weather__description">${description}</div>`);
        if (locationLabel) {
            html.push(`<div class="safra-weather__location">${locationLabel}</div>`);
        }
        html.push('</div>');
        html.push('<ul class="safra-weather__metrics">');
        html.push(`<li>${labels.weatherFeelsLike || ''}: ${Math.round(current.apparent_temperature)}°C</li>`);
        html.push(`<li>${labels.weatherHumidity || ''}: ${Math.round(current.relative_humidity_2m)}%</li>`);
        html.push(`<li>${labels.weatherWind || ''}: ${Math.round(current.wind_speed_10m)} km/h</li>`);
        html.push('</ul>');
        html.push('</div>');

        if (Array.isArray(daily.time) && daily.time.length) {
            html.push(`<div class="safra-weather__forecast-title">${labels.weatherForecast || ''}</div>`);
            const precipitationLabel = labels.weatherPrecipitation || '';
            const forecastItems = daily.time.slice(0, 3).map(function (dateStr, index) {
                const date = new Date(dateStr);
                const min = daily.temperature_2m_min ? Math.round(daily.temperature_2m_min[index]) : null;
                const max = daily.temperature_2m_max ? Math.round(daily.temperature_2m_max[index]) : null;
                const rain = daily.precipitation_sum ? daily.precipitation_sum[index] : null;
                return `<div class="safra-weather__forecast-day">
                    <div class="safra-weather__forecast-date">${date.toLocaleDateString()}</div>
                    <div class="safra-weather__forecast-temp">${min !== null ? `${min}°C` : ''} / ${max !== null ? `${max}°C` : ''}</div>
                    ${rain !== null ? `<div class="safra-weather__forecast-precip">${precipitationLabel}: ${rain.toFixed(1)} mm</div>` : ''}
                </div>`;
            }).join('');
            html.push(`<div class="safra-weather__forecast">${forecastItems}</div>`);
        }

        container.innerHTML = html.join('');
    }

    function getWeatherDescription(code) {
        const labels = window.safraLabels || {};
        if (labels.weatherDescriptions && labels.weatherDescriptions.hasOwnProperty(code)) {
            return labels.weatherDescriptions[code];
        }
        return labels.weatherUnknown || ('Código: ' + code);
    }
</script>
