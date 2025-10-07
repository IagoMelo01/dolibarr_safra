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
                ['limit', 200]
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
})();
