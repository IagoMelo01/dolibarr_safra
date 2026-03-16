<link rel="stylesheet" href="./css/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw/dist/leaflet.draw.css" />

<script src="./js/leaflet.js"></script>
<script src="./js/leaflet.draw.js"></script>
<script src="./js/wellknown.js"></script>

<script>
    const talhaoElement = document.getElementById('talhao_list');
    const indexElement = document.getElementById('sat_index');
    const formElement = document.getElementById('satellite_form');
    const consultaElement = document.getElementById('inputConsulta');
    const arquivoElement = document.getElementById('inputArquivo');
    const weekPickerElement = document.getElementById('weekPicker');
    const yearPickerElement = document.getElementById('yearPicker');
    const btnConsulta = document.getElementById('btnConsulta');
    const dateRangeElement = document.getElementById('dateRange');
    const dateRangeDisplay = document.getElementById('dateRangeDisplay');
    const selectedFieldName = document.getElementById('selectedFieldName');
    const selectedFieldArea = document.getElementById('selectedFieldArea');
    const selectedPeriod = document.getElementById('selectedPeriod');
    const selectedBandLabel = document.getElementById('selectedBandLabel');

    function formatDate(date) {
        const day = date.getDate().toString().padStart(2, '0');
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const year = date.getFullYear();

        return `${day}/${month}/${year}`;
    }

    function formatDateDisplay(date) {
        return date.toLocaleDateString('pt-BR');
    }

    function formatDisplayRange(startISO, endISO) {
        const startDate = new Date(startISO);
        const endDate = new Date(endISO);
        if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
            return `${startISO} a ${endISO}`;
        }

        return `${formatDateDisplay(startDate)} a ${formatDateDisplay(endDate)}`;
    }

    function formatAreaValue(rawValue) {
        const value = Number(rawValue);
        if (!Number.isFinite(value)) {
            return '--';
        }

        return `${value.toLocaleString('pt-BR', { maximumFractionDigits: 2 })} ha`;
    }

    function updateDateRangeDisplay(dateRange) {
        if (!dateRangeDisplay) {
            return;
        }

        if (!dateRange) {
            dateRangeDisplay.textContent = 'Selecione uma semana para ver as datas.';
            if (selectedPeriod) {
                selectedPeriod.textContent = '--';
            }
            return;
        }

        const [start, end] = dateRange.split('/');
        const readable = formatDisplayRange(start, end || start);
        dateRangeDisplay.textContent = readable;
        if (selectedPeriod) {
            selectedPeriod.textContent = readable;
        }
    }

    function updateSelectedFieldSummary() {
        if (!selectedFieldName || !talhaoElement) {
            return;
        }

        const selectedOption = talhaoElement.options[talhaoElement.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            selectedFieldName.textContent = 'Selecione um talhao';
            if (selectedFieldArea) {
                selectedFieldArea.textContent = '--';
            }
            return;
        }

        const talhaoId = selectedOption.value;
        const optionArea = selectedOption.dataset.area;
        const fallbackArea = Object.prototype.hasOwnProperty.call(talhao_area_map, talhaoId) ? talhao_area_map[talhaoId] : '';
        selectedFieldName.textContent = selectedOption.textContent || 'Talhao';
        if (selectedFieldArea) {
            selectedFieldArea.textContent = formatAreaValue(optionArea || fallbackArea);
        }
    }

    function updateSelectedBandSummary() {
        if (!selectedBandLabel || !indexElement) {
            return;
        }

        const selectedOption = indexElement.options[indexElement.selectedIndex];
        selectedBandLabel.textContent = selectedOption ? selectedOption.textContent : '--';
    }

    function getCurrentWeekNumber() {
        const now = new Date();
        const startOfYear = new Date(now.getFullYear(), 0, 1);
        const pastDays = Math.floor((now - startOfYear) / (24 * 60 * 60 * 1000));

        return Math.ceil((pastDays + startOfYear.getDay() + 1) / 7);
    }

    function getCurrentYear() {
        return new Date().getFullYear();
    }

    function populateYearPicker() {
        if (!yearPickerElement) {
            return;
        }

        yearPickerElement.innerHTML = '';
        const currentYear = getCurrentYear();
        for (let year = currentYear - 5; year <= currentYear; year++) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            yearPickerElement.appendChild(option);
        }
    }

    function populateWeeks(year) {
        if (!weekPickerElement) {
            return;
        }

        weekPickerElement.innerHTML = '';
        const janFirst = new Date(year, 0, 1);
        const firstDayOfYear = janFirst.getDay();
        const currentYear = getCurrentYear();
        const currentWeek = getCurrentWeekNumber();

        for (let week = 1; week <= 52; week++) {
            const days = (week - 1) * 7 - firstDayOfYear;
            const weekStart = new Date(year, 0, janFirst.getDate() + days);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekEnd.getDate() + 6);

            const weekStartIso = weekStart.toISOString().split('T')[0];
            const weekEndIso = weekEnd.toISOString().split('T')[0];
            const option = document.createElement('option');
            option.value = `${weekStartIso}/${weekEndIso}`;
            option.textContent = `Semana de ${formatDate(weekStart)} a ${formatDate(weekEnd)}`;
            weekPickerElement.appendChild(option);

            if (Number(year) === currentYear && week >= currentWeek) {
                break;
            }
        }
    }

    function parseFileKey() {
        const raw = (arquivoElement && arquivoElement.value) ? arquivoElement.value : '';
        const parts = raw.split('_');
        if (parts.length < 3) {
            return null;
        }

        return {
            from: parts[0],
            to: parts[1],
            talhaoId: parts[2]
        };
    }

    function applyInitialSelection() {
        const parsed = parseFileKey();
        const currentYear = getCurrentYear();
        if (!parsed) {
            yearPickerElement.value = String(currentYear);
            populateWeeks(currentYear);
            const currentWeekIndex = Math.max(0, getCurrentWeekNumber() - 1);
            if (weekPickerElement.options[currentWeekIndex]) {
                weekPickerElement.selectedIndex = currentWeekIndex;
                dateRangeElement.value = weekPickerElement.value;
                updateDateRangeDisplay(weekPickerElement.value);
            }
            if (talhao_selected) {
                talhaoElement.value = String(talhao_selected);
            }
            return;
        }

        const parsedYear = parsed.from.split('-')[0];
        if (parsedYear) {
            yearPickerElement.value = parsedYear;
            populateWeeks(parsedYear);
        } else {
            yearPickerElement.value = String(currentYear);
            populateWeeks(currentYear);
        }

        const weekRange = `${parsed.from}/${parsed.to}`;
        weekPickerElement.value = weekRange;
        dateRangeElement.value = weekRange;
        talhaoElement.value = parsed.talhaoId;
        updateDateRangeDisplay(weekRange);
    }

    function updateWeekByYear() {
        const year = yearPickerElement.value;
        populateWeeks(year);
        if (weekPickerElement.options.length > 0) {
            weekPickerElement.selectedIndex = weekPickerElement.options.length - 1;
            dateRangeElement.value = weekPickerElement.value;
            updateDateRangeDisplay(weekPickerElement.value);
        }
    }

    function buildConsultaValue() {
        const talhaoId = talhaoElement.value;
        const weekRange = weekPickerElement.value;
        const indexCode = indexElement.value;

        if (!talhaoId || !weekRange || !indexCode) {
            return null;
        }

        const [fromDate, toDate] = weekRange.split('/');
        if (!fromDate || !toDate) {
            return null;
        }

        const fileBase = `${fromDate}_${toDate}_${talhaoId}`;
        arquivoElement.value = fileBase;
        dateRangeElement.value = weekRange;

        return `${fileBase}_${indexCode}`;
    }

    function submitConsulta() {
        const consultaValue = buildConsultaValue();
        if (!consultaValue) {
            return;
        }

        consultaElement.value = consultaValue;
        formElement.submit();
    }

    if (weekPickerElement) {
        weekPickerElement.addEventListener('change', function () {
            dateRangeElement.value = weekPickerElement.value || '';
            updateDateRangeDisplay(weekPickerElement.value || '');
        });
    }

    if (yearPickerElement) {
        yearPickerElement.addEventListener('change', updateWeekByYear);
    }

    if (talhaoElement) {
        talhaoElement.addEventListener('change', function () {
            updateSelectedFieldSummary();
            loadMapData();
        });
    }

    if (indexElement) {
        indexElement.addEventListener('change', function () {
            updateSelectedBandSummary();
            loadMapData();
        });
    }

    if (btnConsulta) {
        btnConsulta.addEventListener('click', submitConsulta);
    }

    populateYearPicker();
    applyInitialSelection();
    updateSelectedFieldSummary();
    updateSelectedBandSummary();
    updateDateRangeDisplay(dateRangeElement.value || '');
</script>

<script>
    const map = L.map('mapIndex').setView([-17.047558, -46.824176], 13);
    const mapStatusElement = document.getElementById('mapStatus');
    let talhaoLayer = null;
    let indexLayer = null;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data &copy; OpenStreetMap contributors'
    }).addTo(map);

    const googleHybrid = L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
    });
    googleHybrid.addTo(map);

    const indexColorRanges = {
        ndvi: [
            { min: -1, max: -0.5, color: '0D0D0D' },
            { min: -0.5, max: -0.2, color: 'BFBFBF' },
            { min: -0.2, max: -0.1, color: 'DBDBDB' },
            { min: -0.1, max: 0, color: 'EBEBEB' },
            { min: 0, max: 0.025, color: 'FFFACC' },
            { min: 0.025, max: 0.05, color: 'EDE8B5' },
            { min: 0.05, max: 0.075, color: 'DED99C' },
            { min: 0.075, max: 0.1, color: 'CCC783' },
            { min: 0.1, max: 0.125, color: 'BDB86B' },
            { min: 0.125, max: 0.15, color: 'B0C261' },
            { min: 0.15, max: 0.175, color: 'A3CC59' },
            { min: 0.175, max: 0.2, color: '91BF52' },
            { min: 0.2, max: 0.25, color: '80B347' },
            { min: 0.25, max: 0.3, color: '70A340' },
            { min: 0.3, max: 0.35, color: '619636' },
            { min: 0.35, max: 0.4, color: '4F8A2E' },
            { min: 0.4, max: 0.45, color: '407D24' },
            { min: 0.45, max: 0.5, color: '306E1C' },
            { min: 0.5, max: 0.55, color: '216112' },
            { min: 0.55, max: 0.6, color: '0F540A' },
            { min: 0.6, max: 1, color: '004500' }
        ],
        ndmi: [
            { min: -1, max: -0.8, color: '800000' },
            { min: -0.8, max: -0.7, color: '990000' },
            { min: -0.7, max: -0.6, color: 'BF0000' },
            { min: -0.6, max: -0.5, color: 'E60000' },
            { min: -0.5, max: -0.4, color: 'FF0000' },
            { min: -0.4, max: -0.3, color: 'FF4000' },
            { min: -0.3, max: -0.2, color: 'FF8000' },
            { min: -0.2, max: -0.1, color: 'FFBF00' },
            { min: -0.1, max: 0, color: 'FFFF00' },
            { min: 0, max: 0.1, color: 'BFFFBF' },
            { min: 0.1, max: 0.2, color: '80FF80' },
            { min: 0.2, max: 0.3, color: '40FFBF' },
            { min: 0.3, max: 0.4, color: '00FFFF' },
            { min: 0.4, max: 0.45, color: '00DFFF' },
            { min: 0.45, max: 0.5, color: '00BFFF' },
            { min: 0.5, max: 1, color: '000080' }
        ],
        swir: [
            { min: -1, max: -0.4, color: '0A1F3D' },
            { min: -0.4, max: -0.25, color: '1A365C' },
            { min: -0.25, max: -0.1, color: '332B21' },
            { min: -0.1, max: 0, color: '594024' },
            { min: 0, max: 0.1, color: '855E29' },
            { min: 0.1, max: 0.2, color: 'A88030' },
            { min: 0.2, max: 0.3, color: 'A3A33D' },
            { min: 0.3, max: 0.4, color: '75A840' },
            { min: 0.4, max: 0.5, color: '4A993D' },
            { min: 0.5, max: 1, color: '1F7836' },
            // Compatibilidade com arquivos gerados por rampa anterior.
            { min: -1, max: -0.4, color: '0F1C30' },
            { min: -0.4, max: -0.2, color: '1F2E47' },
            { min: -0.2, max: 0, color: '573D26' },
            { min: 0, max: 0.15, color: '85572E' },
            { min: 0.15, max: 0.3, color: '8F9E38' },
            { min: 0.3, max: 0.45, color: '549438' },
            { min: 0.45, max: 1, color: '1A803B' },
            { min: 0.45, max: 1, color: '004500' }
        ],
        health: [
            { min: 0, max: 38, color: 'DC2626', classLabel: 'critica' },
            { min: 38, max: 58, color: 'F59E0B', classLabel: 'atencao' },
            { min: 58, max: 78, color: '65A30D', classLabel: 'boa' },
            { min: 78, max: 100, color: '15803D', classLabel: 'excelente' }
        ]
    };

    const hoverLegendControl = L.control({ position: 'bottomleft' });
    let hoverLegendElement = null;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeHexColor(color) {
        if (color === undefined || color === null) {
            return '';
        }

        const raw = String(color).replace('#', '').trim().toUpperCase();
        return /^[0-9A-F]{6}$/.test(raw) ? raw : '';
    }

    function hexToRgb(hex) {
        const normalized = normalizeHexColor(hex);
        if (!normalized) {
            return null;
        }

        return {
            r: parseInt(normalized.slice(0, 2), 16),
            g: parseInt(normalized.slice(2, 4), 16),
            b: parseInt(normalized.slice(4, 6), 16)
        };
    }

    function getActiveIndexCode() {
        if (indexElement && indexElement.value) {
            return String(indexElement.value).toLowerCase();
        }

        return satellite_index_selected ? String(satellite_index_selected).toLowerCase() : 'ndvi';
    }

    function parseFiniteNumber(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function getFeatureNumericValue(properties, indexCode) {
        if (!properties) {
            return null;
        }

        const keyMap = {
            ndvi: ['NDVI', 'ndvi', 'VALUE', 'value', 'INDEX', 'index', 'B0', 'b0'],
            ndmi: ['NDMI', 'ndmi', 'VALUE', 'value', 'INDEX', 'index', 'B0', 'b0'],
            swir: ['SWIR', 'swir', 'VALUE', 'value', 'INDEX', 'index', 'B0', 'b0'],
            health: ['HEALTH_SCORE', 'health_score', 'SCORE', 'score']
        };

        const keys = keyMap[indexCode] || [];
        for (let i = 0; i < keys.length; i += 1) {
            const key = keys[i];
            if (!Object.prototype.hasOwnProperty.call(properties, key)) {
                continue;
            }

            const value = parseFiniteNumber(properties[key]);
            if (value === null) {
                continue;
            }

            if (indexCode === 'health' && value >= 0 && value <= 100) {
                return value;
            }
            if (indexCode !== 'health' && value >= -2 && value <= 2) {
                return value;
            }
        }

        return null;
    }

    function findRangeByValue(indexCode, value) {
        const ranges = indexColorRanges[indexCode] || [];
        if (!ranges.length || value === null) {
            return null;
        }

        for (let i = 0; i < ranges.length; i += 1) {
            const range = ranges[i];
            const isLast = i === ranges.length - 1;
            if (value >= range.min && (value < range.max || (isLast && value <= range.max))) {
                return range;
            }
        }

        return null;
    }

    function findRangeByColor(indexCode, colorHex) {
        const ranges = indexColorRanges[indexCode] || [];
        if (!ranges.length) {
            return null;
        }

        const normalized = normalizeHexColor(colorHex);
        if (!normalized) {
            return null;
        }

        const exact = ranges.find(function (range) {
            return range.color === normalized;
        });
        if (exact) {
            return exact;
        }

        const target = hexToRgb(normalized);
        if (!target) {
            return null;
        }

        let best = null;
        ranges.forEach(function (range) {
            const paletteColor = hexToRgb(range.color);
            if (!paletteColor) {
                return;
            }

            const distance = Math.pow(target.r - paletteColor.r, 2)
                + Math.pow(target.g - paletteColor.g, 2)
                + Math.pow(target.b - paletteColor.b, 2);

            if (!best || distance < best.distance) {
                best = { range: range, distance: distance };
            }
        });

        return best && best.distance <= 2800 ? best.range : null;
    }

    function formatNumber(value, maximumFractionDigits) {
        const num = parseFiniteNumber(value);
        if (num === null) {
            return '--';
        }

        return num.toLocaleString('pt-BR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: maximumFractionDigits
        });
    }

    function formatRangeLabel(indexCode, range) {
        if (!range) {
            return 'sem intervalo mapeado';
        }

        if (indexCode === 'health') {
            const classSuffix = range.classLabel ? ` (${range.classLabel})` : '';
            return `${Math.round(range.min)} a ${Math.round(range.max)}${classSuffix}`;
        }

        return `${formatNumber(range.min, 3)} a ${formatNumber(range.max, 3)}`;
    }

    function getLegendTitle(indexCode) {
        if (satellite_index_options && satellite_index_options[indexCode] && satellite_index_options[indexCode].label) {
            return satellite_index_options[indexCode].label;
        }

        return (indexCode || '').toUpperCase();
    }

    function updateHoverLegend(feature) {
        if (!hoverLegendElement || !feature || !feature.properties) {
            return;
        }

        const indexCode = getActiveIndexCode();
        const properties = feature.properties;
        const colorHex = normalizeHexColor(properties.COLOR_HEX || '');
        const numericValue = getFeatureNumericValue(properties, indexCode);
        const rangeFromValue = findRangeByValue(indexCode, numericValue);
        const rangeFromColor = findRangeByColor(indexCode, colorHex);
        const range = rangeFromValue || rangeFromColor;
        const color = colorHex || (range ? range.color : '64748B');

        let valueLine = '';
        if (numericValue !== null) {
            const decimals = indexCode === 'health' ? 2 : 3;
            valueLine = `<div class="satellite-hover-legend__line"><strong>Valor:</strong> ${formatNumber(numericValue, decimals)}</div>`;
        }

        hoverLegendElement.innerHTML = `
            <div class="satellite-hover-legend__title">${escapeHtml(getLegendTitle(indexCode))}</div>
            <div class="satellite-hover-legend__line">
                <span class="satellite-hover-legend__chip" style="background:#${escapeHtml(color)}"></span>
                <strong>Intervalo:</strong> ${escapeHtml(formatRangeLabel(indexCode, range))}
            </div>
            ${valueLine}
        `;
    }

    function resetHoverLegend() {
        if (!hoverLegendElement) {
            return;
        }

        hoverLegendElement.innerHTML = `
            <div class="satellite-hover-legend__title">Legenda do ponto</div>
            <div class="satellite-hover-legend__line">Passe o mouse sobre o talhao para ver o intervalo.</div>
        `;
    }

    function highlightFeature(event) {
        const layer = event.target;
        layer.setStyle({
            color: '#111827',
            weight: 2,
            fillOpacity: 1
        });

        if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
            layer.bringToFront();
        }
    }

    function resetFeatureHighlight(event) {
        if (!indexLayer || !indexLayer.resetStyle) {
            return;
        }

        indexLayer.resetStyle(event.target);
    }

    hoverLegendControl.onAdd = function () {
        hoverLegendElement = L.DomUtil.create('div', 'satellite-hover-legend');
        L.DomEvent.disableClickPropagation(hoverLegendElement);
        L.DomEvent.disableScrollPropagation(hoverLegendElement);
        resetHoverLegend();

        return hoverLegendElement;
    };
    hoverLegendControl.addTo(map);

    function setMapStatus(message) {
        if (!mapStatusElement) {
            return;
        }
        mapStatusElement.textContent = message || '';
    }

    function clearLayer(layerRef) {
        if (layerRef && map.hasLayer(layerRef)) {
            map.removeLayer(layerRef);
        }
    }

    function parseSelectedTalhaoId() {
        if (talhaoElement && talhaoElement.value) {
            return String(talhaoElement.value);
        }

        const parsed = parseFileKey();
        if (parsed && parsed.talhaoId) {
            return String(parsed.talhaoId);
        }

        if (talhao_selected) {
            return String(talhao_selected);
        }

        return '';
    }

    function renderTalhaoBoundary() {
        clearLayer(talhaoLayer);
        talhaoLayer = null;

        const talhaoId = parseSelectedTalhaoId();
        if (!talhaoId || !Object.prototype.hasOwnProperty.call(talhao_geo_map, talhaoId)) {
            return;
        }

        const rawGeo = talhao_geo_map[talhaoId];
        if (!rawGeo) {
            return;
        }

        try {
            const talhaoGeoJson = JSON.parse(rawGeo);
            talhaoLayer = L.geoJSON(talhaoGeoJson, {
                style: {
                    color: '#1f2937',
                    weight: 2,
                    fillColor: '#94a3b8',
                    fillOpacity: 0.12
                }
            }).addTo(map);
        } catch (error) {
            console.error('Erro ao renderizar talhao:', error);
        }
    }

    function getDataUrl() {
        if (!arquivoElement || !arquivoElement.value || !indexElement) {
            return '';
        }

        const selectedIndex = indexElement.value;
        if (!selectedIndex || !Object.prototype.hasOwnProperty.call(satellite_index_options, selectedIndex)) {
            return '';
        }

        const folder = satellite_index_options[selectedIndex].folder;
        if (!folder) {
            return '';
        }

        return `./json/${folder}/${arquivoElement.value}.json`;
    }

    function featureStyle(feature) {
        const hexRaw = feature && feature.properties && feature.properties.COLOR_HEX ? String(feature.properties.COLOR_HEX) : '';
        const hex = hexRaw.replace('#', '');
        const color = hex ? `#${hex}` : '#2563eb';

        return {
            color: color,
            stroke: true,
            weight: 1,
            fill: true,
            fillOpacity: 0.95,
            fillColor: color
        };
    }

    function loadMapData() {
        clearLayer(indexLayer);
        indexLayer = null;
        resetHoverLegend();

        renderTalhaoBoundary();

        const dataUrl = getDataUrl();
        if (!dataUrl) {
            setMapStatus(map_choose_filters_message);
            if (talhaoLayer) {
                map.fitBounds(talhaoLayer.getBounds());
            }
            return;
        }

        fetch(dataUrl)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(function (geojson) {
                if (!geojson || !geojson.features || !geojson.features.length) {
                    setMapStatus(map_missing_file_message);
                    if (talhaoLayer) {
                        map.fitBounds(talhaoLayer.getBounds());
                    }
                    return;
                }

                indexLayer = L.geoJSON(geojson, {
                    style: featureStyle,
                    onEachFeature: function (feature, layer) {
                        layer.on({
                            mouseover: function (event) {
                                highlightFeature(event);
                                updateHoverLegend(feature);
                            },
                            mousemove: function () {
                                updateHoverLegend(feature);
                            },
                            mouseout: function (event) {
                                resetFeatureHighlight(event);
                                resetHoverLegend();
                            }
                        });
                    }
                }).addTo(map);

                setMapStatus('Mapa carregado com sucesso.');
                if (indexLayer.getBounds && indexLayer.getBounds().isValid()) {
                    map.fitBounds(indexLayer.getBounds());
                } else if (talhaoLayer && talhaoLayer.getBounds) {
                    map.fitBounds(talhaoLayer.getBounds());
                }
            })
            .catch(function (error) {
                console.error('Erro ao carregar geojson:', error);
                setMapStatus(map_missing_file_message);
                if (talhaoLayer && talhaoLayer.getBounds) {
                    map.fitBounds(talhaoLayer.getBounds());
                }
            });
    }

    loadMapData();
</script>
