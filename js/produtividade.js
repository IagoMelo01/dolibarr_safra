(function () {
    'use strict';

    const config = window.safraProdutividadeConfig || {};
    const endpoint = config.endpoint || '';
    const labels = config.labels || {};
    const selected = config.selected || {};

    const culturaSelect = document.getElementById('idCultura');
    const cultivarHidden = document.getElementById('idCultivar');
    const cultivarInput = document.getElementById('cultivarSearch');
    const cultivarDatalist = document.getElementById('cultivarSuggestions');
    const cultivarStatus = document.getElementById('cultivarStatus');
    const municipioInput = document.getElementById('municipioSearch');
    const municipioHidden = document.getElementById('codigoIBGE');
    const municipioDatalist = document.getElementById('municipioSuggestions');
    const chartContainers = document.querySelectorAll('.productivity-chart');

    const CULTIVAR_MIN_CHARS = 2;
    const numericPattern = /^[0-9]+$/;

    let initialCulturaId = selected.cultura;
    if ((!initialCulturaId || initialCulturaId <= 0) && culturaSelect) {
        const parsed = parseInt(culturaSelect.value, 10);
        if (Number.isFinite(parsed) && parsed > 0) {
            initialCulturaId = parsed;
        }
    }
    if (initialCulturaId && (!selected.cultura || selected.cultura !== initialCulturaId)) {
        selected.cultura = initialCulturaId;
    }

    const initialCultivarOption = selected.cultivarOption || null;

    const cultivarState = {
        culturaId: initialCulturaId && initialCulturaId > 0 ? initialCulturaId : null,
        cache: new Map(),
        controller: null,
        token: 0,
        lastQuery: ''
    };

    let cultivarFetchTimeout = null;

    function buildUrl(params) {
        if (!endpoint) {
            return '';
        }
        const url = new URL(endpoint, window.location.origin);
        params.forEach(function (value, key) {
            if (value !== undefined && value !== null && value !== '') {
                url.searchParams.append(key, value);
            }
        });
        return url.toString();
    }

    function formatCultivarDisplay(item) {
        if (!item) {
            return '';
        }
        const label = item.label || '';
        const ref = item.ref && item.ref !== label ? item.ref : '';
        return ref ? label + ' (' + ref + ')' : label;
    }

    function clearCultivarSuggestions() {
        if (cultivarDatalist) {
            cultivarDatalist.innerHTML = '';
        }
    }

    function populateCultivarSuggestions(items) {
        if (!cultivarDatalist) {
            return;
        }
        clearCultivarSuggestions();
        if (!Array.isArray(items) || !items.length) {
            return;
        }
        items.forEach(function (item) {
            if (!item || !item.id) {
                return;
            }
            const option = document.createElement('option');
            const display = formatCultivarDisplay(item);
            option.value = display;
            option.dataset.id = item.id;
            if (item.label) {
                option.dataset.label = item.label;
            }
            if (item.ref) {
                option.dataset.ref = item.ref;
            }
            if (item.cultura) {
                option.dataset.cultura = item.cultura;
            }
            if (item.embrapa) {
                option.dataset.embrapa = item.embrapa;
            }
            cultivarDatalist.appendChild(option);
        });
    }

    function setCultivarStatus(message) {
        if (!cultivarStatus) {
            return;
        }
        if (typeof message === 'string') {
            cultivarStatus.textContent = message;
            return;
        }
        if (!selected.cultura) {
            cultivarStatus.textContent = labels.placeholder || '';
            return;
        }
        cultivarStatus.textContent = labels.cultivarSearchHelp || '';
    }

    function cacheKey(culturaId, term) {
        return String(culturaId) + '::' + term.trim().toLowerCase();
    }

    function abortCultivarFetch() {
        if (cultivarState.controller && typeof cultivarState.controller.abort === 'function') {
            try {
                cultivarState.controller.abort();
            } catch (error) {
                // Ignore abort issues
            }
        }
        cultivarState.controller = null;
        if (cultivarInput) {
            cultivarInput.classList.remove('is-loading');
        }
    }

    function commitCultivarSelection(option, updateInput) {
        const shouldUpdate = updateInput !== false;
        if (option && option.id) {
            const numericId = parseInt(option.id, 10);
            if (cultivarHidden) {
                cultivarHidden.value = option.id;
            }
            selected.cultivar = Number.isFinite(numericId) ? numericId : null;
            const optionLabel = option.label || option.ref || String(option.id);
            selected.cultivarOption = {
                id: option.id,
                label: optionLabel,
                ref: option.ref || '',
                cultura: option.cultura || selected.cultura || null,
                embrapa: option.embrapa || ''
            };
            if (shouldUpdate && cultivarInput) {
                cultivarInput.value = formatCultivarDisplay(selected.cultivarOption);
            }
        } else {
            if (cultivarHidden) {
                cultivarHidden.value = '';
            }
            selected.cultivar = null;
            selected.cultivarOption = null;
        }
    }

    function findCultivarOptionByValue(value) {
        if (!cultivarDatalist || !value) {
            return null;
        }
        const options = Array.prototype.slice.call(cultivarDatalist.options || []);
        const normalized = value.toLowerCase();
        for (let index = 0; index < options.length; index++) {
            const option = options[index];
            const optionValue = (option.value || '').toLowerCase();
            if (optionValue === normalized && option.dataset.id) {
                return {
                    id: option.dataset.id,
                    label: option.dataset.label || option.value || value,
                    ref: option.dataset.ref || '',
                    cultura: option.dataset.cultura ? parseInt(option.dataset.cultura, 10) : selected.cultura,
                    embrapa: option.dataset.embrapa || ''
                };
            }
        }
        return null;
    }

    function resetCultivarSelection(clearValue) {
        commitCultivarSelection(null, clearValue);
        if (clearValue && cultivarInput) {
            cultivarInput.value = '';
        }
        clearCultivarSuggestions();
    }

    function fetchCultivarSuggestions(query) {
        const culturaId = selected.cultura;
        const cleanQuery = (query || '').trim();
        if (!culturaId || !cleanQuery) {
            return;
        }
        const normalizedKey = cacheKey(culturaId, cleanQuery);
        if (cultivarState.lastQuery === normalizedKey) {
            return;
        }
        abortCultivarFetch();
        if (!endpoint) {
            return;
        }
        const params = new Map([
            ['action', 'cultivares'],
            ['idCultura', culturaId],
            ['term', cleanQuery],
            ['limit', 30]
        ]);
        const url = buildUrl(params);
        if (!url) {
            return;
        }
        cultivarState.lastQuery = normalizedKey;
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        cultivarState.controller = controller;
        const token = ++cultivarState.token;
        if (cultivarInput) {
            cultivarInput.classList.add('is-loading');
        }
        setCultivarStatus(labels.loading || '...');
        const fetchOptions = {credentials: 'same-origin'};
        if (controller) {
            fetchOptions.signal = controller.signal;
        }
        fetch(url, fetchOptions)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (payload) {
                if (cultivarState.token !== token) {
                    return;
                }
                cultivarState.controller = null;
                if (cultivarInput) {
                    cultivarInput.classList.remove('is-loading');
                }
                const received = payload && Array.isArray(payload.items) ? payload.items : [];
                cultivarState.cache.set(normalizedKey, received);
                if (!received.length) {
                    clearCultivarSuggestions();
                    setCultivarStatus(labels.cultivarNoResults || labels.empty || '');
                    return;
                }
                populateCultivarSuggestions(received);
                setCultivarStatus();
            })
            .catch(function (error) {
                if (controller && error && error.name === 'AbortError') {
                    return;
                }
                console.error('Unable to search cultivars', error);
                if (cultivarState.token === token) {
                    cultivarState.controller = null;
                    if (cultivarInput) {
                        cultivarInput.classList.remove('is-loading');
                    }
                    if (cultivarState.lastQuery === normalizedKey) {
                        cultivarState.lastQuery = '';
                    }
                    setCultivarStatus(labels.empty || '');
                }
            });
    }

    function scheduleCultivarFetch(query) {
        if (cultivarFetchTimeout) {
            clearTimeout(cultivarFetchTimeout);
        }
        cultivarFetchTimeout = setTimeout(function () {
            cultivarFetchTimeout = null;
            fetchCultivarSuggestions(query);
        }, 220);
    }

    function handleCultivarInput() {
        if (!cultivarInput) {
            return;
        }
        const rawValue = cultivarInput.value || '';
        const value = rawValue.trim();
        if (!selected.cultura) {
            resetCultivarSelection(false);
            setCultivarStatus();
            return;
        }

        const match = findCultivarOptionByValue(rawValue);
        if (match) {
            commitCultivarSelection(match, false);
        } else {
            commitCultivarSelection(null, false);
        }

        if (!value) {
            clearCultivarSuggestions();
            setCultivarStatus();
            abortCultivarFetch();
            if (cultivarFetchTimeout) {
                clearTimeout(cultivarFetchTimeout);
                cultivarFetchTimeout = null;
            }
            return;
        }

        if (value.length < CULTIVAR_MIN_CHARS && !numericPattern.test(value)) {
            clearCultivarSuggestions();
            setCultivarStatus(labels.cultivarTypeMore || labels.cultivarSearchHelp || '');
            abortCultivarFetch();
            if (cultivarFetchTimeout) {
                clearTimeout(cultivarFetchTimeout);
                cultivarFetchTimeout = null;
            }
            return;
        }

        const key = cacheKey(selected.cultura, value);
        if (cultivarState.cache.has(key)) {
            const cached = cultivarState.cache.get(key) || [];
            if (cached.length) {
                populateCultivarSuggestions(cached);
                setCultivarStatus();
            } else {
                clearCultivarSuggestions();
                setCultivarStatus(labels.cultivarNoResults || labels.empty || '');
            }
            return;
        }

        scheduleCultivarFetch(value);
    }

    function formatMunicipioOption(item) {
        const parts = [];
        if (item.label) {
            parts.push(item.label);
        }
        if (item.uf) {
            parts.push(item.uf);
        }
        const left = parts.join(' / ');
        const suffix = item.code ? ' - ' + item.code : '';
        return left + suffix;
    }

    function populateMunicipios(items) {
        if (!municipioDatalist) {
            return;
        }
        municipioDatalist.innerHTML = '';
        if (!items || !items.length) {
            return;
        }
        items.forEach(function (item) {
            const option = document.createElement('option');
            option.value = formatMunicipioOption(item);
            option.dataset.code = item.code || '';
            municipioDatalist.appendChild(option);
        });
    }

    function updateMunicipioCodeFromInput() {
        if (!municipioInput || !municipioHidden) {
            return;
        }
        const value = municipioInput.value.trim();
        if (!value) {
            municipioHidden.value = '';
            return;
        }
        const options = municipioDatalist ? Array.prototype.slice.call(municipioDatalist.options) : [];
        const match = options.find(function (option) {
            return option.value === value;
        });
        if (match && match.dataset.code) {
            municipioHidden.value = match.dataset.code;
            return;
        }
        const digits = value.replace(/\D+/g, '');
        if (digits.length >= 6) {
            municipioHidden.value = digits;
            return;
        }
        municipioHidden.value = '';
    }

    let municipioFetchToken = 0;
    async function fetchMunicipios(query) {
        if (!query || query.length < 3) {
            populateMunicipios([]);
            return;
        }
        const currentToken = ++municipioFetchToken;
        try {
            const url = buildUrl(new Map([
                ['action', 'municipios'],
                ['term', query],
                ['limit', 20]
            ]));
            if (!url) {
                return;
            }
            const response = await fetch(url, {credentials: 'same-origin'});
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const payload = await response.json();
            if (currentToken !== municipioFetchToken) {
                return;
            }
            populateMunicipios(payload && payload.items ? payload.items : []);
        } catch (error) {
            if (currentToken === municipioFetchToken) {
                populateMunicipios([]);
            }
            console.error('Unable to search municipios', error);
        }
    }

    if (culturaSelect) {
        culturaSelect.addEventListener('change', function () {
            const parsed = parseInt(this.value, 10);
            const culturaId = Number.isFinite(parsed) && parsed > 0 ? parsed : null;
            selected.cultura = culturaId;
            cultivarState.culturaId = culturaId;
            cultivarState.cache.clear();
            cultivarState.lastQuery = '';
            if (cultivarFetchTimeout) {
                clearTimeout(cultivarFetchTimeout);
                cultivarFetchTimeout = null;
            }
            abortCultivarFetch();
            resetCultivarSelection(true);
            if (cultivarInput) {
                cultivarInput.disabled = !culturaId;
                if (!culturaId) {
                    setCultivarStatus();
                } else {
                    setCultivarStatus(labels.cultivarTypeMore || labels.cultivarSearchHelp || '');
                    cultivarInput.focus();
                }
            } else if (cultivarStatus) {
                setCultivarStatus();
            }
        });
    }

    if (cultivarInput) {
        if (selected.cultura) {
            cultivarInput.disabled = false;
        }
        cultivarInput.addEventListener('input', handleCultivarInput);
        cultivarInput.addEventListener('change', function () {
            const match = findCultivarOptionByValue(this.value);
            if (match) {
                commitCultivarSelection(match, true);
                setCultivarStatus();
            } else {
                commitCultivarSelection(null, false);
                handleCultivarInput();
            }
        });
        cultivarInput.addEventListener('focus', function () {
            if (!selected.cultura) {
                setCultivarStatus();
                return;
            }
            if (this.value && this.value.trim().length >= CULTIVAR_MIN_CHARS) {
                handleCultivarInput();
            } else {
                setCultivarStatus(labels.cultivarTypeMore || labels.cultivarSearchHelp || '');
            }
        });
        cultivarInput.addEventListener('blur', function () {
            const match = findCultivarOptionByValue(this.value);
            if (match) {
                commitCultivarSelection(match, true);
            }
        });

        if (initialCultivarOption && (!initialCultivarOption.cultura || !selected.cultura || initialCultivarOption.cultura === selected.cultura)) {
            populateCultivarSuggestions([initialCultivarOption]);
            commitCultivarSelection(initialCultivarOption, true);
        }

        setCultivarStatus();
    } else if (cultivarStatus) {
        setCultivarStatus();
    }

    if (municipioInput) {
        municipioInput.addEventListener('input', function () {
            const value = this.value.trim();
            fetchMunicipios(value);
            updateMunicipioCodeFromInput();
        });
        municipioInput.addEventListener('change', updateMunicipioCodeFromInput);
        municipioInput.addEventListener('blur', updateMunicipioCodeFromInput);

        if (selected.municipio && selected.municipio.label) {
            municipioInput.value = selected.municipio.label;
        }
        if (selected.municipio && selected.municipio.code && municipioHidden) {
            municipioHidden.value = selected.municipio.code;
        }
    }

    const chartPalette = ['#2563eb', '#db2777', '#16a34a', '#f97316', '#8b5cf6', '#0ea5e9', '#facc15', '#fb7185'];

    function parseChartConfig(node) {
        if (!node) {
            return null;
        }
        const datasetValue = node.dataset.chart;
        if (!datasetValue && Array.isArray(window.safraProdutividadeCharts)) {
            const chartId = node.dataset.chartId;
            return window.safraProdutividadeCharts.find(function (item) {
                return item && item.id && item.id === chartId;
            }) || null;
        }
        if (!datasetValue) {
            return null;
        }
        try {
            return JSON.parse(datasetValue);
        } catch (error) {
            console.error('Unable to parse chart config', error);
        }
        return null;
    }

    function fitCanvas(canvas) {
        if (!canvas) {
            return null;
        }
        const rect = canvas.getBoundingClientRect();
        const pixelRatio = window.devicePixelRatio || 1;
        const width = rect.width || canvas.clientWidth || 480;
        const height = rect.height || canvas.clientHeight || 240;
        canvas.width = width * pixelRatio;
        canvas.height = height * pixelRatio;
        const context = canvas.getContext('2d');
        if (!context) {
            return null;
        }
        context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        context.clearRect(0, 0, width, height);
        return {
            ctx: context,
            width: width,
            height: height
        };
    }

    function collectBounds(series) {
        let min = Number.POSITIVE_INFINITY;
        let max = Number.NEGATIVE_INFINITY;
        series.forEach(function (dataset) {
            dataset.values.forEach(function (value) {
                if (value === null || Number.isNaN(value)) {
                    return;
                }
                if (value < min) {
                    min = value;
                }
                if (value > max) {
                    max = value;
                }
            });
        });
        if (!Number.isFinite(min) || !Number.isFinite(max)) {
            min = 0;
            max = 0;
        }
        if (min === max) {
            const delta = Math.abs(min) > 1 ? Math.abs(min) * 0.1 : 1;
            min -= delta;
            max += delta;
        }
        return {min: min, max: max};
    }

    function valueFormatter(value) {
        if (!Number.isFinite(value)) {
            return '';
        }
        try {
            return new Intl.NumberFormat(undefined, {
                maximumFractionDigits: Math.abs(value) >= 100 ? 0 : 2
            }).format(value);
        } catch (error) {
            return value.toFixed(2);
        }
    }

    function renderLegend(listNode, chart, palette) {
        if (!listNode) {
            return;
        }
        listNode.innerHTML = '';
        chart.series.forEach(function (dataset, index) {
            const item = document.createElement('li');
            item.className = 'productivity-chart__legend-item';
            const bullet = document.createElement('span');
            bullet.className = 'productivity-chart__legend-bullet';
            bullet.style.backgroundColor = palette[index % palette.length];
            item.appendChild(bullet);
            const label = document.createElement('span');
            label.className = 'productivity-chart__legend-label';
            label.textContent = dataset.name;
            item.appendChild(label);
            listNode.appendChild(item);
        });
    }

    function drawChart(canvas, chart, palette) {
        const dimensions = fitCanvas(canvas);
        if (!dimensions) {
            return null;
        }
        const ctx = dimensions.ctx;
        const width = dimensions.width;
        const height = dimensions.height;
        const padding = {top: 16, right: 24, bottom: 36, left: 56};
        const plotWidth = Math.max(10, width - padding.left - padding.right);
        const plotHeight = Math.max(10, height - padding.top - padding.bottom);
        const categories = Array.isArray(chart.categories) && chart.categories.length ? chart.categories : chart.series[0].values.map(function (_, index) {
            return String(index + 1);
        });
        const bounds = collectBounds(chart.series);
        const valueRange = bounds.max - bounds.min;
        const safeRange = valueRange === 0 ? 1 : valueRange;
        const seriesCount = chart.series.length;
        const tooltipPoints = categories.map(function () {
            return [];
        });

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);

        ctx.save();
        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.rect(padding.left, padding.top, plotWidth, plotHeight);
        ctx.stroke();
        ctx.restore();

        const horizontalSteps = 4;
        ctx.strokeStyle = '#f3f4f6';
        ctx.fillStyle = '#6b7280';
        ctx.font = '12px "Open Sans", "Helvetica Neue", Arial, sans-serif';
        ctx.textBaseline = 'middle';

        for (let step = 0; step <= horizontalSteps; step++) {
            const value = bounds.min + (valueRange * step) / horizontalSteps;
            const y = padding.top + plotHeight - (plotHeight * step) / horizontalSteps;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(padding.left + plotWidth, y);
            ctx.stroke();
            const label = valueFormatter(value);
            ctx.fillText(label, padding.left - 12, y);
        }

        const pointCount = categories.length;
        const categoryStep = pointCount > 1 ? plotWidth / (pointCount - 1) : 0;
        const pointX = function (index) {
            if (pointCount === 1) {
                return padding.left + plotWidth / 2;
            }
            return padding.left + categoryStep * index;
        };
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        const labelLimit = Math.min(pointCount, 6);
        const labelStep = labelLimit > 1 ? Math.max(1, Math.floor((pointCount - 1) / (labelLimit - 1))) : 1;

        for (let index = 0; index < pointCount; index++) {
            const x = pointX(index);
            if (labelLimit === pointCount || index % labelStep === 0 || index === pointCount - 1) {
                const label = categories[index];
                ctx.fillText(label, x, padding.top + plotHeight + 8);
            }
        }

        function toY(value) {
            if (!Number.isFinite(value)) {
                return padding.top + plotHeight;
            }
            return padding.top + plotHeight - ((value - bounds.min) / safeRange) * plotHeight;
        }

        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';

        chart.series.forEach(function (dataset, index) {
            const color = palette[index % palette.length];
            ctx.strokeStyle = color;
            ctx.beginPath();
            let started = false;
            const datasetPoints = [];
            dataset.values.forEach(function (value, idx) {
                if (value === null || Number.isNaN(value)) {
                    started = false;
                    return;
                }
                const x = pointX(idx);
                const y = toY(value);
                if (!started) {
                    ctx.moveTo(x, y);
                    started = true;
                } else {
                    ctx.lineTo(x, y);
                }
                const pointInfo = {
                    seriesIndex: index,
                    pointIndex: idx,
                    value: value,
                    x: x,
                    y: y,
                    name: dataset.name,
                    color: color
                };
                datasetPoints.push(pointInfo);
                if (tooltipPoints[idx]) {
                    tooltipPoints[idx].push(pointInfo);
                }
            });
            ctx.stroke();

            datasetPoints.forEach(function (point) {
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.arc(point.x, point.y, 3.5, 0, Math.PI * 2);
                ctx.fill();
                ctx.strokeStyle = color;
                ctx.stroke();
            });
        });

        const threshold = pointCount > 1 ? Math.max(12, Math.min(48, categoryStep / 2)) : plotWidth / 2;

        return {
            categories: categories,
            pointsByIndex: tooltipPoints,
            threshold: threshold,
            unit: chart.unit || ''
        };
    }

    function ensureChartTooltip(container) {
        if (!container) {
            return null;
        }
        let tooltip = container.querySelector('.productivity-chart__tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'productivity-chart__tooltip';
            container.appendChild(tooltip);
        }
        return tooltip;
    }

    function hideChartTooltip(tooltip) {
        if (!tooltip) {
            return;
        }
        tooltip.classList.remove('is-visible');
    }

    function buildChartTooltip(tooltip, chartState, index) {
        if (!tooltip || !chartState || index < 0 || !Array.isArray(chartState.pointsByIndex) || index >= chartState.pointsByIndex.length) {
            return;
        }
        const points = chartState.pointsByIndex[index];
        tooltip.innerHTML = '';
        if (!points || !points.length) {
            return;
        }
        const categoryLabel = chartState.categories && chartState.categories[index] ? chartState.categories[index] : '';
        if (categoryLabel) {
            const title = document.createElement('div');
            title.className = 'productivity-chart__tooltip-title';
            title.textContent = labels.tooltipCategory ? (labels.tooltipCategory + ': ' + categoryLabel) : categoryLabel;
            tooltip.appendChild(title);
        }
        const list = document.createElement('ul');
        list.className = 'productivity-chart__tooltip-list';
        points.forEach(function (point) {
            const item = document.createElement('li');
            item.className = 'productivity-chart__tooltip-item';
            const color = document.createElement('span');
            color.className = 'productivity-chart__tooltip-color';
            color.style.backgroundColor = point.color;
            item.appendChild(color);
            const name = document.createElement('span');
            name.className = 'productivity-chart__tooltip-name';
            name.textContent = point.name || '';
            item.appendChild(name);
            const value = document.createElement('span');
            value.className = 'productivity-chart__tooltip-value';
            let formatted = valueFormatter(point.value);
            if (chartState.unit) {
                formatted += ' ' + chartState.unit;
            }
            value.textContent = (labels.tooltipValue ? labels.tooltipValue + ': ' : '') + formatted;
            item.appendChild(value);
            list.appendChild(item);
        });
        tooltip.appendChild(list);
    }

    function positionChartTooltip(container, canvas, tooltip, anchor) {
        if (!container || !canvas || !tooltip || !anchor) {
            return;
        }
        const containerRect = container.getBoundingClientRect();
        const canvasRect = canvas.getBoundingClientRect();
        const offsetX = canvasRect.left - containerRect.left;
        const offsetY = canvasRect.top - containerRect.top;
        let left = offsetX + anchor.x;
        let top = offsetY + anchor.y;
        const tooltipWidth = tooltip.offsetWidth;
        const tooltipHeight = tooltip.offsetHeight;
        left -= tooltipWidth / 2;
        if (left < 8) {
            left = 8;
        }
        const maxLeft = containerRect.width - tooltipWidth - 8;
        if (left > maxLeft) {
            left = maxLeft;
        }
        top -= tooltipHeight + 12;
        if (top < 8) {
            top = offsetY + anchor.y + 12;
        }
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
        tooltip.classList.add('is-visible');
    }

    function findNearestCategoryIndex(chartState, relativeX) {
        if (!chartState || !Array.isArray(chartState.pointsByIndex)) {
            return -1;
        }
        let nearestIndex = -1;
        let nearestDistance = Infinity;
        chartState.pointsByIndex.forEach(function (points, index) {
            if (!points || !points.length) {
                return;
            }
            const anchorX = points[0].x;
            const distance = Math.abs(anchorX - relativeX);
            if (distance < nearestDistance) {
                nearestDistance = distance;
                nearestIndex = index;
            }
        });
        if (nearestIndex === -1) {
            return -1;
        }
        if (!Number.isFinite(chartState.threshold) || nearestDistance <= chartState.threshold) {
            return nearestIndex;
        }
        return -1;
    }

    function initialiseCharts() {
        if (!chartContainers || !chartContainers.length) {
            return;
        }
        const palette = chartPalette;
        chartContainers.forEach(function (container) {
            const chart = parseChartConfig(container);
            if (!chart || !chart.series || !chart.series.length) {
                return;
            }
            const canvas = container.querySelector('canvas');
            const legend = container.querySelector('.productivity-chart__legend');
            renderLegend(legend, chart, palette);
            const tooltip = ensureChartTooltip(container);
            let chartState = null;

            const draw = function () {
                chartState = drawChart(canvas, chart, palette);
                if (!chartState) {
                    hideChartTooltip(tooltip);
                }
            };

            draw();

            if (canvas) {
                const handlePointerMove = function (event) {
                    if (!chartState || !canvas) {
                        hideChartTooltip(tooltip);
                        return;
                    }
                    const canvasRect = canvas.getBoundingClientRect();
                    const relativeX = event.clientX - canvasRect.left;
                    const index = findNearestCategoryIndex(chartState, relativeX);
                    if (index === -1) {
                        hideChartTooltip(tooltip);
                        return;
                    }
                    const points = chartState.pointsByIndex[index];
                    if (!points || !points.length) {
                        hideChartTooltip(tooltip);
                        return;
                    }
                    buildChartTooltip(tooltip, chartState, index);
                    const anchorY = Math.min.apply(null, points.map(function (point) { return point.y; }));
                    positionChartTooltip(container, canvas, tooltip, {x: points[0].x, y: anchorY});
                };

                const handlePointerLeave = function () {
                    hideChartTooltip(tooltip);
                };

                canvas.addEventListener('pointermove', handlePointerMove);
                canvas.addEventListener('pointerdown', handlePointerMove);
                canvas.addEventListener('pointerleave', handlePointerLeave);
                canvas.addEventListener('pointercancel', handlePointerLeave);
                canvas.addEventListener('pointerup', handlePointerLeave);
            }

            if (window.ResizeObserver) {
                const observer = new ResizeObserver(function () {
                    draw();
                });
                observer.observe(container);
            } else {
                window.addEventListener('resize', draw);
            }
        });
    }

    initialiseCharts();
})();
