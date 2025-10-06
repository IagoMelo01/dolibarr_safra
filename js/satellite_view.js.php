<?php
/* Copyright (C) 2024 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
?>
<script src="./js/leaflet.js"></script>
<script src="./js/leaflet.draw.js"></script>
<script src="./js/wellknown.js"></script>
<script>
(function() {
    const config = window.satelliteViewConfig || {};
    const dataset = window.satelliteDataset || {};

    const datasetFolder = config.folder || config.dataset || 'ndvi';
    const datasetName = config.name || config.title || 'NDVI';
    const tips = Array.isArray(config.tips) ? config.tips : [];
    const autoSubmitOnChange = config.autoSubmitOnChange !== false;
    const autoLoadFirst = config.autoLoadFirst !== false;

    const talhaoNames = Array.isArray(dataset.names) ? dataset.names : [];
    const talhaoIds = Array.isArray(dataset.ids) ? dataset.ids : [];
    const talhaoAreas = Array.isArray(dataset.areas) ? dataset.areas : [];
    const boundaries = Array.isArray(dataset.boundaries) ? dataset.boundaries : [];
    const arquivoInicial = dataset.arquivo || '';

    const formElement = document.getElementById('satelliteForm');
    const talhaoElement = document.getElementById('talhao_list');
    const yearPickerElement = document.getElementById('yearPicker');
    const weekPickerElement = document.getElementById('weekPicker');
    const btnConsulta = document.getElementById('btnConsulta');
    const consultaElement = document.getElementById('inputConsulta');
    const arquivoElement = document.getElementById('inputArquivo');
    const dateRangeElement = document.getElementById('dateRange');

    const metricTalhao = document.getElementById('metricTalhao');
    const metricArea = document.getElementById('metricArea');
    const metricDate = document.getElementById('metricDate');
    const metricStatus = document.getElementById('metricStatus');
    const selectionTitle = document.getElementById('selectionTitle');
    const insightList = document.getElementById('layerInsightList');
    const downloadButton = document.getElementById('downloadLayer');
    const mapStatusOverlay = document.getElementById('mapLoadingState');
    const mapStatusMessage = document.getElementById('mapLoadingMessage');
    const mapStatusSpinner = mapStatusOverlay ? mapStatusOverlay.querySelector('.satellite-map__spinner') : null;

    const talhaoLookup = talhaoIds.reduce((acc, id, index) => {
        acc[String(id)] = {
            id: id,
            name: talhaoNames[index] || ('Talhão ' + id),
            area: talhaoAreas[index] || null,
            boundary: boundaries[index] || null
        };
        return acc;
    }, {});

    let map;
    let datasetLayer = null;
    let boundaryLayer = null;

    function formatArea(value) {
        const number = Number(value);
        if (!number || Number.isNaN(number) || number <= 0) {
            return '--';
        }
        return number.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ha';
    }

    function updateMapOverlay(message, state) {
        if (!mapStatusOverlay) {
            return;
        }
        if (state === 'loading' || state === 'error') {
            mapStatusOverlay.hidden = false;
            mapStatusOverlay.classList.toggle('is-error', state === 'error');
            if (mapStatusMessage) {
                mapStatusMessage.textContent = message;
            }
            if (mapStatusSpinner) {
                mapStatusSpinner.hidden = state !== 'loading';
            }
        } else {
            mapStatusOverlay.hidden = true;
            mapStatusOverlay.classList.remove('is-error');
            if (mapStatusSpinner) {
                mapStatusSpinner.hidden = false;
            }
        }
    }

    function setStatus(message, state) {
        updateMapOverlay(message, state);
        if (!metricStatus) {
            return;
        }
        metricStatus.textContent = message;
        metricStatus.classList.remove('is-loading', 'is-positive', 'is-warning', 'is-error');
        if (state) {
            metricStatus.classList.add('is-' + state);
        }
    }

    function disableDownload() {
        if (!downloadButton) {
            return;
        }
        downloadButton.classList.add('is-disabled');
        downloadButton.setAttribute('aria-disabled', 'true');
        downloadButton.removeAttribute('href');
        downloadButton.removeAttribute('download');
    }

    function enableDownload(url, filename) {
        if (!downloadButton) {
            return;
        }
        downloadButton.classList.remove('is-disabled');
        downloadButton.removeAttribute('aria-disabled');
        downloadButton.href = url;
        if (filename) {
            downloadButton.setAttribute('download', filename);
        }
    }

    function updateInsightList(selectedTalhao) {
        if (!insightList) {
            return;
        }
        insightList.innerHTML = '';
        if (selectedTalhao && selectedTalhao.area) {
            const item = document.createElement('li');
            item.innerHTML = '<strong>Área monitorada:</strong> ' + formatArea(selectedTalhao.area);
            insightList.appendChild(item);
        }
        tips.forEach(function(tip) {
            const li = document.createElement('li');
            li.textContent = tip;
            insightList.appendChild(li);
        });
        if (!tips.length && (!selectedTalhao || !selectedTalhao.area)) {
            const fallback = document.createElement('li');
            fallback.textContent = 'Selecione um talhão e utilize o zoom para explorar a cena com mais detalhes.';
            insightList.appendChild(fallback);
        }
    }

    function updateSelectionTitle(selectedTalhao, weekText) {
        if (!selectionTitle) {
            return;
        }
        if (selectedTalhao && weekText) {
            selectionTitle.textContent = datasetName + ' para ' + selectedTalhao.name + ' · ' + weekText.replace('Semana de ', '');
        } else {
            selectionTitle.textContent = 'Escolha um talhão e um período para visualizar o índice.';
        }
    }

    function updateSelectionInfo(options) {
        const opts = options || {};
        const talhaoId = talhaoElement && !talhaoElement.disabled ? talhaoElement.value : '';
        const talhaoData = talhaoLookup[String(talhaoId)] || null;
        const weekOption = weekPickerElement && weekPickerElement.selectedIndex >= 0 ? weekPickerElement.options[weekPickerElement.selectedIndex] : null;
        const weekText = weekOption ? weekOption.textContent : '';

        if (metricTalhao) {
            metricTalhao.textContent = talhaoData ? talhaoData.name : 'Selecione um talhão';
        }
        if (metricArea) {
            metricArea.textContent = talhaoData ? formatArea(talhaoData.area) : '--';
        }
        if (metricDate) {
            metricDate.textContent = weekText || 'Selecione uma semana';
        }

        updateSelectionTitle(talhaoData, weekText);
        updateInsightList(talhaoData);

        if (!opts.skipStatus) {
            const pendingValue = buildConsultaValue();
            if (!arquivoElement || !arquivoElement.value) {
                setStatus('Selecione um talhão e uma semana para gerar o mapa.', 'idle');
            } else if (pendingValue && arquivoElement.value && pendingValue !== arquivoElement.value) {
                setStatus('Existem alterações ainda não aplicadas.', 'warning');
            }
        }
    }

    function populateTalhoes() {
        if (!talhaoElement) {
            return;
        }
        talhaoElement.innerHTML = '';
        if (!talhaoIds.length) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Nenhum talhão cadastrado';
            talhaoElement.appendChild(option);
            talhaoElement.disabled = true;
            return;
        }
        talhaoElement.disabled = false;
        talhaoIds.forEach(function(id, index) {
            const option = document.createElement('option');
            option.value = id;
            option.textContent = talhaoNames[index] || ('Talhão ' + id);
            talhaoElement.appendChild(option);
        });
    }

    function getCurrentYear() {
        return new Date().getFullYear();
    }

    function getCurrentWeekNumber() {
        const now = new Date();
        const startOfYear = new Date(now.getFullYear(), 0, 1);
        const pastDays = Math.floor((now - startOfYear) / 86400000);
        return Math.ceil((pastDays + startOfYear.getDay() + 1) / 7);
    }

    function formatDate(date) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return day + '/' + month + '/' + year;
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
            if (year === currentYear) {
                option.selected = true;
            }
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
        for (let week = 1; week <= 52; week++) {
            const days = (week - 1) * 7 - firstDayOfYear;
            const weekStart = new Date(year, 0, janFirst.getDate() + days);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekEnd.getDate() + 6);
            const option = document.createElement('option');
            option.value = weekStart.toISOString().split('T')[0] + '/' + weekEnd.toISOString().split('T')[0];
            option.textContent = 'Semana de ' + formatDate(weekStart) + ' a ' + formatDate(weekEnd);
            weekPickerElement.appendChild(option);
            if (year === getCurrentYear() && week === getCurrentWeekNumber()) {
                break;
            }
        }
        if (weekPickerElement.options.length > 0 && weekPickerElement.selectedIndex === -1) {
            weekPickerElement.selectedIndex = 0;
        }
    }

    function buildConsultaValue() {
        if (!talhaoElement || talhaoElement.disabled || !weekPickerElement) {
            return '';
        }
        const talhaoId = talhaoElement.value;
        const range = weekPickerElement.value;
        if (!talhaoId || !range) {
            return '';
        }
        return (range + '_' + talhaoId).replace(/\//g, '_');
    }

    function updateHiddenFields() {
        if (dateRangeElement && weekPickerElement) {
            dateRangeElement.value = weekPickerElement.value || '';
            dateRangeElement.disabled = !weekPickerElement.value;
        }
        if (consultaElement) {
            consultaElement.value = buildConsultaValue();
        }
    }

    function consulta() {
        if (!formElement) {
            return;
        }
        const value = buildConsultaValue();
        if (!value) {
            setStatus('Selecione um talhão e uma semana antes de consultar.', 'warning');
            return;
        }
        if (consultaElement) {
            consultaElement.value = value;
        }
        formElement.submit();
    }

    function renderBoundary(geoJsonString) {
        if (!map) {
            return null;
        }
        let geometry = geoJsonString;
        if (typeof geometry === 'string' && geometry.length) {
            try {
                geometry = JSON.parse(geometry);
            } catch (error) {
                console.error('Não foi possível interpretar o limite do talhão.', error);
                return null;
            }
        }
        if (!geometry) {
            return null;
        }
        if (boundaryLayer) {
            map.removeLayer(boundaryLayer);
        }
        boundaryLayer = L.geoJSON(geometry, {
            style: function() {
                return {
                    color: '#1d4ed8',
                    weight: 2,
                    fillOpacity: 0,
                    dashArray: '6 4'
                };
            }
        }).addTo(map);
        const bounds = boundaryLayer.getBounds();
        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [24, 24] });
        }
        return boundaryLayer;
    }

    function carregarDadosMapa() {
        if (!arquivoElement || !arquivoElement.value) {
            setStatus('Selecione um talhão e uma semana para gerar o mapa.', 'idle');
            disableDownload();
            return;
        }
        const parts = arquivoElement.value.split('_');
        if (parts.length < 3) {
            setStatus('Seleção inválida. Refaça a consulta.', 'error');
            disableDownload();
            return;
        }
        const talhaoId = parts[2];
        const talhaoData = talhaoLookup[String(talhaoId)];
        if (!talhaoData) {
            setStatus('Talhão selecionado não encontrado.', 'error');
            disableDownload();
            return;
        }

        renderBoundary(talhaoData.boundary);

        const normalizedConsulta = arquivoElement.value.replace(/\//g, '_');
        const url = new URL('./json/' + datasetFolder + '/' + normalizedConsulta + '.json', window.location.href).href;
        disableDownload();
        setStatus('Carregando camada...', 'loading');

        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Resposta inválida');
                }
                return response.json();
            })
            .then(function(data) {
                if (datasetLayer) {
                    map.removeLayer(datasetLayer);
                }
                datasetLayer = L.geoJSON(data, {
                    onEachFeature: function(feature, layer) {
                        const colorHex = feature && feature.properties && feature.properties.COLOR_HEX ? '#' + feature.properties.COLOR_HEX : '#2563eb';
                        layer.setStyle({
                            color: colorHex,
                            weight: 1,
                            fillOpacity: 0.85,
                            fillColor: colorHex
                        });
                    }
                }).addTo(map);

                const bounds = datasetLayer.getBounds();
                if (bounds.isValid()) {
                    map.fitBounds(bounds, { padding: [28, 28] });
                }

                enableDownload(url, datasetFolder + '_' + normalizedConsulta + '.json');
                updateSelectionInfo({ skipStatus: true });
                setStatus('Camada atualizada.', 'positive');
            })
            .catch(function(error) {
                console.error('Erro ao carregar dados do índice.', error);
                setStatus('Não foi possível carregar os dados do índice.', 'error');
                disableDownload();
            });
    }

    function inicializarMapa() {
        map = L.map('mapIndex', {
            zoomControl: true
        }).setView([-17.047558, -46.824176], 13);

        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contribuidores'
        }).addTo(map);

        const googleHybrid = L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
        }).addTo(map);

        L.control.layers({
            'OpenStreetMap': osm,
            'Google Satélite': googleHybrid
        }, {}, { position: 'topright' }).addTo(map);
    }

    function aplicarConsultaInicial() {
        if (!talhaoElement || talhaoElement.disabled) {
            updateSelectionInfo();
            setStatus('Cadastre um talhão para visualizar os mapas.', 'warning');
            return;
        }

        const rawValue = arquivoElement && arquivoElement.value ? arquivoElement.value : arquivoInicial;
        if (rawValue) {
            const parts = rawValue.split('_');
            if (parts.length >= 3) {
                const start = parts[0];
                const end = parts[1];
                const talhaoId = parts[2];
                const initialYear = parseInt(start.slice(0, 4), 10);
                if (!Number.isNaN(initialYear)) {
                    yearPickerElement.value = initialYear;
                    populateWeeks(initialYear);
                }
                const rangeValue = start + '/' + end;
                const weekOption = Array.from(weekPickerElement.options || []).find(function(option) {
                    return option.value === rangeValue;
                });
                if (weekOption) {
                    weekOption.selected = true;
                }
                const talhaoOption = Array.from(talhaoElement.options || []).find(function(option) {
                    return option.value === talhaoId;
                });
                if (talhaoOption) {
                    talhaoOption.selected = true;
                }
                updateHiddenFields();
                updateSelectionInfo({ skipStatus: true });
                carregarDadosMapa();
                return;
            }
        }

        const currentYear = getCurrentYear();
        yearPickerElement.value = currentYear;
        populateWeeks(currentYear);
        const weeksCount = weekPickerElement.options.length;
        if (weeksCount > 0) {
            const currentWeekIndex = Math.min(Math.max(getCurrentWeekNumber() - 1, 0), weeksCount - 1);
            weekPickerElement.selectedIndex = currentWeekIndex;
        }
        talhaoElement.selectedIndex = 0;
        updateHiddenFields();
        updateSelectionInfo({ skipStatus: true });
        if (autoLoadFirst && buildConsultaValue()) {
            consulta();
        }
    }

    function handleAutoSubmit() {
        if (!autoSubmitOnChange) {
            return;
        }
        const newValue = buildConsultaValue();
        if (newValue && arquivoElement && arquivoElement.value !== newValue) {
            consulta();
        }
    }

    function bindEvents() {
        if (btnConsulta) {
            btnConsulta.addEventListener('click', function(event) {
                event.preventDefault();
                consulta();
            });
        }
        if (formElement) {
            formElement.addEventListener('submit', function(event) {
                const value = buildConsultaValue();
                if (!value) {
                    event.preventDefault();
                    setStatus('Selecione um talhão e uma semana antes de consultar.', 'warning');
                    return;
                }
                if (consultaElement) {
                    consultaElement.value = value;
                }
            });
        }
        if (talhaoElement) {
            talhaoElement.addEventListener('change', function() {
                updateHiddenFields();
                updateSelectionInfo();
                handleAutoSubmit();
            });
        }
        if (yearPickerElement) {
            yearPickerElement.addEventListener('change', function() {
                populateWeeks(parseInt(yearPickerElement.value, 10));
                updateHiddenFields();
                updateSelectionInfo();
                handleAutoSubmit();
            });
        }
        if (weekPickerElement) {
            weekPickerElement.addEventListener('change', function() {
                updateHiddenFields();
                updateSelectionInfo();
                handleAutoSubmit();
            });
        }
    }

    function init() {
        if (!formElement || !talhaoElement || !weekPickerElement || !yearPickerElement) {
            return;
        }
        inicializarMapa();
        populateTalhoes();
        populateYearPicker();
        bindEvents();
        aplicarConsultaInicial();
    }

    document.addEventListener('DOMContentLoaded', init);

    window.addEventListener('pageshow', function() {
        if (!datasetLayer && arquivoElement && arquivoElement.value) {
            updateHiddenFields();
            updateSelectionInfo({ skipStatus: true });
            carregarDadosMapa();
        }
    });

    delete window.satelliteViewConfig;
    delete window.satelliteDataset;
})();
</script>
