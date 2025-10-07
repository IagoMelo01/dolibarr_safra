(function () {
    'use strict';

    const config = window.safraProdutividadeConfig || {};
    const endpoint = config.endpoint || '';
    const labels = config.labels || {};
    const selected = config.selected || {};

    const culturaSelect = document.getElementById('idCultura');
    const cultivarSelect = document.getElementById('idCultivar');
    const municipioInput = document.getElementById('municipioSearch');
    const municipioHidden = document.getElementById('codigoIBGE');
    const municipioDatalist = document.getElementById('municipioSuggestions');
    const chartContainers = document.querySelectorAll('.productivity-chart');

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

    function setCultivarOptions(options) {
        if (!cultivarSelect) {
            return;
        }
        cultivarSelect.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = labels.select || '---';
        cultivarSelect.appendChild(placeholder);

        if (!options || !options.length) {
            const message = selected.cultura ? (labels.empty || '') : (labels.placeholder || labels.empty || '');
            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = message;
            emptyOption.disabled = true;
            emptyOption.selected = true;
            cultivarSelect.appendChild(emptyOption);
            cultivarSelect.disabled = true;
            return;
        }

        options.forEach(function (item) {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.label;
            option.dataset.cultura = item.cultura;
            if (item.embrapa) {
                option.dataset.embrapa = item.embrapa;
            }
            cultivarSelect.appendChild(option);
        });

        cultivarSelect.disabled = false;

        if (selected.cultivar) {
            cultivarSelect.value = String(selected.cultivar);
        }
    }

    function showCultivarLoading() {
        if (!cultivarSelect) {
            return;
        }
        cultivarSelect.innerHTML = '';
        const loadingOption = document.createElement('option');
        loadingOption.value = '';
        loadingOption.textContent = labels.loading || '...';
        loadingOption.disabled = true;
        loadingOption.selected = true;
        cultivarSelect.appendChild(loadingOption);
        cultivarSelect.disabled = true;
    }

    async function fetchCultivares(culturaId) {
        if (!culturaId) {
            setCultivarOptions(null);
            return;
        }
        showCultivarLoading();
        try {
            const url = buildUrl(new Map([
                ['action', 'cultivares'],
                ['idCultura', culturaId],
                ['limit', 0]
            ]));
            if (!url) {
                setCultivarOptions(null);
                return;
            }
            const response = await fetch(url, {credentials: 'same-origin'});
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const payload = await response.json();
            selected.cultura = culturaId;
            setCultivarOptions(payload && payload.items ? payload.items : []);
        } catch (error) {
            console.error('Unable to load cultivares', error);
            setCultivarOptions(null);
        }
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

    if (culturaSelect && cultivarSelect) {
        culturaSelect.addEventListener('change', function () {
            selected.cultivar = null;
            const culturaId = parseInt(this.value, 10) || 0;
            if (!culturaId) {
                selected.cultura = null;
                setCultivarOptions(null);
                return;
            }
            selected.cultura = culturaId;
            fetchCultivares(culturaId);
        });

        if (selected.cultura) {
            fetchCultivares(selected.cultura);
        } else {
            setCultivarOptions(null);
        }
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
            return;
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
            });
            ctx.stroke();

            dataset.values.forEach(function (value, idx) {
                if (value === null || Number.isNaN(value)) {
                    return;
                }
                const x = pointX(idx);
                const y = toY(value);
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.arc(x, y, 3.5, 0, Math.PI * 2);
                ctx.fill();
                ctx.strokeStyle = color;
                ctx.stroke();
            });
        });
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

            const draw = function () {
                drawChart(canvas, chart, palette);
            };

            draw();

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
