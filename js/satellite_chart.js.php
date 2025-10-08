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
            const canvas = document.getElementById(entry.canvasId);
            const emptyElement = entry.emptyId ? document.getElementById(entry.emptyId) : null;
            const metaElement = entry.metaId ? document.getElementById(entry.metaId) : null;

            if (canvas) {
                canvas.style.display = 'none';
            }
            if (emptyElement) {
                emptyElement.style.display = 'block';
                emptyElement.textContent = (data && data.message) || entry.options.emptyMessage || '';
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

        function renderEntry(entry) {
            const canvas = document.getElementById(entry.canvasId);
            if (!canvas) {
                return;
            }

            const data = entry.data || {};
            const points = Array.isArray(data.points) ? data.points : [];
            if (!points.length) {
                showEmpty(entry, data);
                return;
            }

            const emptyElement = entry.emptyId ? document.getElementById(entry.emptyId) : null;
            const metaElement = entry.metaId ? document.getElementById(entry.metaId) : null;

            if (emptyElement) {
                emptyElement.style.display = 'none';
            }
            canvas.style.display = 'block';

            const labels = points.map(point => formatRange(point.from, point.to));
            const decimals = typeof entry.options.decimals === 'number' ? entry.options.decimals : 2;
            const values = points.map(point => parseValue(point.mean));
            const minValues = points.map(point => parseValue(point.min));
            const maxValues = points.map(point => parseValue(point.max));
            const hasRange = hasNumericValue(minValues) && hasNumericValue(maxValues);

            const ctx = canvas.getContext('2d');
            const datasetColor = entry.options.color || '#2563eb';
            const background = createGradient(ctx, entry.options.gradient);
            const datasets = [];

            if (hasRange) {
                const rangeFillColor = entry.options.rangeFillColor || 'rgba(37, 99, 235, 0.12)';
                const rangeLineColor = entry.options.rangeLineColor || 'rgba(148, 163, 184, 0.55)';
                const minLabel = entry.options.minLabel || '';
                const maxLabel = entry.options.maxLabel || '';

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
                    tooltipLabel: entry.options.tooltipMinLabel || minLabel,
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
                    tooltipLabel: entry.options.tooltipMaxLabel || maxLabel,
                });
            }

            datasets.push({
                label: entry.options.label || '',
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
                tooltipLabel: entry.options.tooltipMeanLabel || entry.options.tooltipLabel || entry.options.label || '',
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
                            suggestedMin: entry.options.range && typeof entry.options.range.min === 'number' ? entry.options.range.min : undefined,
                            suggestedMax: entry.options.range && typeof entry.options.range.max === 'number' ? entry.options.range.max : undefined,
                            ticks: {
                                callback: value => Number(value).toFixed(decimals),
                            },
                            grid: { color: 'rgba(148, 163, 184, 0.2)' },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: context => {
                                    const dataset = context.dataset || {};
                                    const prefix = dataset.tooltipLabel || entry.options.tooltipLabel || '';
                                    const unit = entry.options.valueUnit ? ` ${entry.options.valueUnit}` : '';
                                    const value = Number(context.parsed.y).toFixed(decimals);
                                    return prefix ? `${prefix}: ${value}${unit}` : `${value}${unit}`;
                                },
                            },
                        },
                    },
                },
            });

            if (metaElement) {
                const updated = formatDate(toDate(data.generatedAt));
                const nextUpdate = formatDate(toDate(data.validUntil));
                const parts = [];
                if (updated) {
                    parts.push(formatLabel(entry.options.updatedLabel, updated));
                }
                if (nextUpdate) {
                    parts.push(formatLabel(entry.options.nextLabel, nextUpdate));
                }
                metaElement.textContent = parts.filter(Boolean).join(' · ');
                metaElement.style.display = parts.length ? 'block' : 'none';
            }
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
