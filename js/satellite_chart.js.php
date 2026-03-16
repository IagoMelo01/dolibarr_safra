<script>
    (function () {
        if (window.satelliteChartRendererInitialized) {
            return;
        }
        window.satelliteChartRendererInitialized = true;

        const charts = {};

        function toDate(value) {
            if (!value) return null;
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? null : date;
        }

        function formatDate(date) {
            if (!date) return '';
            try {
                return date.toLocaleDateString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' });
            } catch (error) {
                return date.toISOString().split('T')[0];
            }
        }

        function formatRange(from, to) {
            const start = toDate(from);
            const end = toDate(to);
            if (!start && !end) {
                return '';
            }
            if (start && end) {
                const startLabel = start.toLocaleDateString(undefined, { month: '2-digit', day: '2-digit' });
                const endLabel = end.toLocaleDateString(undefined, { month: '2-digit', day: '2-digit' });
                return `${startLabel} - ${endLabel}`;
            }
            return formatDate(start || end);
        }

        function formatLabel(template, value) {
            if (!template || !value) return '';
            return template.replace('%s', value);
        }

        function parseValue(value) {
            if (typeof value === 'number' && Number.isFinite(value)) {
                return value;
            }
            if (typeof value === 'string') {
                const parsed = parseFloat(value);
                if (Number.isFinite(parsed)) {
                    return parsed;
                }
            }
            return null;
        }

        function hasNumericValue(values) {
            return Array.isArray(values) && values.some(value => value !== null && Number.isFinite(value));
        }

        function showEmpty(entry, data) {
            const options = entry.options || {};
            const canvas = document.getElementById(entry.canvasId);
            const emptyElement = entry.emptyId ? document.getElementById(entry.emptyId) : null;
            const metaElement = entry.metaId ? document.getElementById(entry.metaId) : null;

            if (canvas) {
                canvas.style.display = 'none';
            }
            if (emptyElement) {
                emptyElement.style.display = 'block';
                emptyElement.textContent = (data && data.message) || options.emptyMessage || '';
            }
            if (metaElement) {
                metaElement.textContent = data && data.message ? data.message : '';
                metaElement.style.display = data && data.message ? 'block' : 'none';
            }
            if (canvas && charts[entry.canvasId]) {
                charts[entry.canvasId].destroy();
                delete charts[entry.canvasId];
            }
        }

        function createGradient(ctx, colorStops) {
            const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas ? ctx.canvas.height : 280);
            if (Array.isArray(colorStops) && colorStops.length >= 2) {
                gradient.addColorStop(0, colorStops[0]);
                gradient.addColorStop(1, colorStops[1]);
            } else {
                gradient.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
                gradient.addColorStop(1, 'rgba(37, 99, 235, 0.05)');
            }
            return gradient;
        }

        function buildTimelineFromSeries(seriesList) {
            const timelineMap = {};
            (seriesList || []).forEach(series => {
                const points = Array.isArray(series.points) ? series.points : [];
                points.forEach(point => {
                    const from = point && point.from ? String(point.from) : '';
                    const to = point && point.to ? String(point.to) : '';
                    if (!from && !to) {
                        return;
                    }
                    const key = `${from}|${to}`;
                    if (!timelineMap[key]) {
                        timelineMap[key] = { key: key, from: from, to: to };
                    }
                });
            });

            return Object.keys(timelineMap)
                .sort()
                .map(key => timelineMap[key]);
        }

        function buildPointLookup(points) {
            const lookup = {};
            (points || []).forEach(point => {
                const from = point && point.from ? String(point.from) : '';
                const to = point && point.to ? String(point.to) : '';
                if (!from && !to) {
                    return;
                }
                lookup[`${from}|${to}`] = point;
            });

            return lookup;
        }

        function updateMeta(entry, data, metaElement) {
            if (!metaElement) {
                return;
            }

            const options = entry.options || {};
            const updated = formatDate(toDate(data.generatedAt));
            const nextUpdate = formatDate(toDate(data.validUntil));
            const parts = [];
            if (updated) {
                parts.push(formatLabel(options.updatedLabel, updated));
            }
            if (nextUpdate) {
                parts.push(formatLabel(options.nextLabel, nextUpdate));
            }

            metaElement.textContent = parts.filter(Boolean).join(' - ');
            metaElement.style.display = parts.length ? 'block' : 'none';
        }

        function buildDefaultTooltipCallbacks(defaultDecimals) {
            return {
                label: context => {
                    const dataset = context.dataset || {};
                    const prefix = dataset.tooltipLabel || dataset.label || '';
                    const decimals = typeof dataset.tooltipDecimals === 'number' ? dataset.tooltipDecimals : defaultDecimals;
                    const unit = dataset.tooltipUnit || '';
                    const raw = context.parsed && typeof context.parsed.y === 'number' ? context.parsed.y : NaN;
                    if (!Number.isFinite(raw)) {
                        return prefix ? `${prefix}: --` : '--';
                    }
                    const value = Number(raw).toFixed(decimals);
                    return prefix ? `${prefix}: ${value}${unit}` : `${value}${unit}`;
                },
            };
        }

        function renderSingleSeries(entry, data, canvas, metaElement) {
            const options = entry.options || {};
            const points = Array.isArray(data.points) ? data.points : [];
            if (!points.length) {
                showEmpty(entry, data);
                return;
            }

            const labels = points.map(point => formatRange(point.from, point.to));
            const decimals = typeof options.decimals === 'number' ? options.decimals : 2;
            const values = points.map(point => parseValue(point.mean));
            if (!hasNumericValue(values)) {
                showEmpty(entry, data);
                return;
            }

            const minValues = points.map(point => parseValue(point.min));
            const maxValues = points.map(point => parseValue(point.max));
            const hasRange = hasNumericValue(minValues) && hasNumericValue(maxValues);

            const ctx = canvas.getContext('2d');
            const datasetColor = options.color || '#2563eb';
            const background = createGradient(ctx, options.gradient);
            const datasets = [];

            if (hasRange) {
                const rangeFillColor = options.rangeFillColor || 'rgba(37, 99, 235, 0.12)';
                const rangeLineColor = options.rangeLineColor || 'rgba(148, 163, 184, 0.55)';
                const minLabel = options.minLabel || '';
                const maxLabel = options.maxLabel || '';

                datasets.push({
                    label: minLabel,
                    data: minValues,
                    borderColor: rangeLineColor,
                    borderWidth: 1,
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    hitRadius: 10,
                    spanGaps: true,
                    tooltipLabel: options.tooltipMinLabel || minLabel,
                    tooltipDecimals: decimals,
                    tooltipUnit: '',
                });

                datasets.push({
                    label: maxLabel,
                    data: maxValues,
                    borderColor: rangeLineColor,
                    borderWidth: 1,
                    backgroundColor: rangeFillColor,
                    fill: '-1',
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    hitRadius: 10,
                    spanGaps: true,
                    tooltipLabel: options.tooltipMaxLabel || maxLabel,
                    tooltipDecimals: decimals,
                    tooltipUnit: '',
                });
            }

            datasets.push({
                label: options.label || '',
                data: values,
                borderColor: datasetColor,
                borderWidth: 2,
                backgroundColor: background,
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointHoverRadius: 5,
                pointBackgroundColor: datasetColor,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                spanGaps: true,
                tooltipLabel: options.tooltipMeanLabel || options.tooltipLabel || options.label || '',
                tooltipDecimals: decimals,
                tooltipUnit: options.valueUnit ? ` ${options.valueUnit}` : '',
            });

            if (charts[entry.canvasId]) {
                charts[entry.canvasId].destroy();
            }

            charts[entry.canvasId] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { maxRotation: 0, autoSkip: true },
                        },
                        y: {
                            beginAtZero: false,
                            suggestedMin: options.range && typeof options.range.min === 'number' ? options.range.min : undefined,
                            suggestedMax: options.range && typeof options.range.max === 'number' ? options.range.max : undefined,
                            ticks: {
                                callback: value => Number(value).toFixed(decimals),
                            },
                            grid: { color: 'rgba(148, 163, 184, 0.2)' },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: buildDefaultTooltipCallbacks(decimals),
                        },
                    },
                },
            });

            updateMeta(entry, data, metaElement);
        }

        function renderMultiSeries(entry, data, canvas, metaElement) {
            const options = entry.options || {};
            const seriesList = Array.isArray(data.series) ? data.series : [];
            if (!seriesList.length) {
                showEmpty(entry, data);
                return;
            }

            const timeline = buildTimelineFromSeries(seriesList);
            if (!timeline.length) {
                showEmpty(entry, data);
                return;
            }

            const labels = timeline.map(point => formatRange(point.from, point.to));
            const datasets = [];
            let hasAnySeriesData = false;

            seriesList.forEach(series => {
                const points = Array.isArray(series.points) ? series.points : [];
                const lookup = buildPointLookup(points);
                const values = timeline.map(slot => {
                    const point = lookup[slot.key];
                    return point ? parseValue(point.mean) : null;
                });

                if (!hasNumericValue(values)) {
                    return;
                }

                hasAnySeriesData = true;
                const isHealthAxis = series.axis === 'health';
                const color = series.color || '#2563eb';
                const decimals = typeof series.decimals === 'number' ? series.decimals : (isHealthAxis ? 2 : 3);

                datasets.push({
                    label: series.label || (series.code ? String(series.code).toUpperCase() : ''),
                    data: values,
                    borderColor: color,
                    borderWidth: 2,
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.33,
                    pointRadius: isHealthAxis ? 4 : 3,
                    pointHoverRadius: isHealthAxis ? 5 : 4,
                    pointBackgroundColor: color,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 1.5,
                    spanGaps: true,
                    yAxisID: isHealthAxis ? 'yHealth' : 'yIndex',
                    tooltipLabel: series.label || '',
                    tooltipDecimals: decimals,
                    tooltipUnit: series.valueUnit ? ` ${series.valueUnit}` : '',
                });
            });

            if (!hasAnySeriesData) {
                showEmpty(entry, data);
                return;
            }

            if (charts[entry.canvasId]) {
                charts[entry.canvasId].destroy();
            }

            const leftAxis = options.leftAxis || {};
            const rightAxis = options.rightAxis || {};
            const leftAxisDecimals = typeof leftAxis.decimals === 'number' ? leftAxis.decimals : 3;
            const rightAxisDecimals = typeof rightAxis.decimals === 'number' ? rightAxis.decimals : 2;

            charts[entry.canvasId] = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { maxRotation: 0, autoSkip: true },
                        },
                        yIndex: {
                            position: 'left',
                            beginAtZero: false,
                            suggestedMin: typeof leftAxis.min === 'number' ? leftAxis.min : -0.5,
                            suggestedMax: typeof leftAxis.max === 'number' ? leftAxis.max : 1,
                            title: {
                                display: !!leftAxis.title,
                                text: leftAxis.title || '',
                            },
                            ticks: {
                                callback: value => Number(value).toFixed(leftAxisDecimals),
                            },
                            grid: { color: 'rgba(148, 163, 184, 0.2)' },
                        },
                        yHealth: {
                            position: 'right',
                            beginAtZero: true,
                            suggestedMin: typeof rightAxis.min === 'number' ? rightAxis.min : 0,
                            suggestedMax: typeof rightAxis.max === 'number' ? rightAxis.max : 100,
                            title: {
                                display: !!rightAxis.title,
                                text: rightAxis.title || '',
                            },
                            ticks: {
                                callback: value => Number(value).toFixed(rightAxisDecimals),
                            },
                            grid: {
                                drawOnChartArea: false,
                                color: 'rgba(148, 163, 184, 0.2)',
                            },
                        },
                    },
                    plugins: {
                        legend: { display: options.showLegend !== false },
                        tooltip: {
                            callbacks: buildDefaultTooltipCallbacks(2),
                        },
                    },
                },
            });

            updateMeta(entry, data, metaElement);
        }

        function renderEntry(entry) {
            const canvas = document.getElementById(entry.canvasId);
            if (!canvas) {
                return;
            }

            const data = entry.data || {};
            const emptyElement = entry.emptyId ? document.getElementById(entry.emptyId) : null;
            const metaElement = entry.metaId ? document.getElementById(entry.metaId) : null;

            if (emptyElement) {
                emptyElement.style.display = 'none';
            }
            canvas.style.display = 'block';

            if (Array.isArray(data.series) && data.series.length) {
                renderMultiSeries(entry, data, canvas, metaElement);
                return;
            }

            renderSingleSeries(entry, data, canvas, metaElement);
        }

        function renderCharts() {
            const instances = Array.isArray(window.satelliteChartInstances) ? window.satelliteChartInstances : [];
            instances.forEach(renderEntry);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', renderCharts);
        } else {
            renderCharts();
        }
    })();
</script>
