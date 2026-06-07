// safra/js/analisesolo_location.js
(function () {
  var cfg = window.SAFRA_ANALISESOLO_MAP || {};
  var map = null;
  var talhaoLayer = null;
  var marker = null;
  var ajaxUrl = cfg.ajaxTalhaoUrl || '';
  var readonly = !!cfg.readonly;
  var currentTalhaoSelect = null;
  var talhaoSelectObserver = null;
  var talhaoPollTimer = null;
  var lastTalhaoValue = null;

  function loadCss(url) {
    if (!url || document.querySelector('link[data-safra-analisesolo-leaflet]')) return;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = url;
    link.setAttribute('data-safra-analisesolo-leaflet', '1');
    document.head.appendChild(link);
  }

  function loadScript(url, attr, cb) {
    if (!url) {
      cb();
      return;
    }
    if (attr && document.querySelector('script[' + attr + ']')) {
      waitForLeaflet(cb);
      return;
    }
    var script = document.createElement('script');
    script.src = url;
    script.defer = true;
    if (attr) script.setAttribute(attr, '1');
    script.onload = cb;
    script.onerror = cb;
    document.head.appendChild(script);
  }

  function waitForLeaflet(cb) {
    if (window.L && window.L.map) {
      cb();
      return;
    }
    var attempts = 0;
    var timer = setInterval(function () {
      attempts++;
      if ((window.L && window.L.map) || attempts > 80) {
        clearInterval(timer);
        cb();
      }
    }, 50);
  }

  function ready(cb) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', cb);
    } else {
      cb();
    }
  }

  function init() {
    var container = document.getElementById(cfg.containerId || 'safra-analisesolo-map');
    if (!container) return;

    loadCss(cfg.leafletCss || '/custom/safra/css/leaflet.css');
    loadScript(cfg.leafletJs || '/custom/safra/js/leaflet.js', 'data-safra-analisesolo-leaflet', function () {
      waitForLeaflet(function () {
        if (!(window.L && window.L.map)) {
          setHint(cfg.messages && cfg.messages.leafletError ? cfg.messages.leafletError : 'Nao foi possivel carregar o mapa.', true);
          return;
        }
        loadScript(cfg.wellknownJs || '/custom/safra/js/wellknown.js', 'data-safra-analisesolo-wellknown', function () {
          buildMap(container);
        });
      });
    });
  }

  function buildMap(container) {
    map = L.map(container, { attributionControl: true });
    L.tileLayer((cfg.tileLayer && cfg.tileLayer.url) || 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 20,
      attribution: (cfg.tileLayer && cfg.tileLayer.attribution) || 'Tiles (c) Esri'
    }).addTo(map);

    map.setView([-15.78, -47.93], 4);

    var initialLat = parseFloat(cfg.initialLat || '');
    var initialLng = parseFloat(cfg.initialLng || '');
    if (isFinite(initialLat) && isFinite(initialLng)) {
      setMarker(initialLat, initialLng, false);
      map.setView([initialLat, initialLng], 16);
    }

    if (!readonly) {
      map.on('click', function (event) {
        setMarker(event.latlng.lat, event.latlng.lng, true);
      });
      bindInputs();
    }

    bindTalhaoSelect();
    startTalhaoWatcher();
    loadCurrentTalhao();

    setTimeout(function () { map.invalidateSize(); }, 100);
    setTimeout(function () { map.invalidateSize(); }, 400);
  }

  function bindInputs() {
    var latInput = findField('latitude');
    var lngInput = findField('longitude');
    if (latInput && lngInput && latInput.value && lngInput.value) {
      var lat = parseFloat(('' + latInput.value).replace(',', '.'));
      var lng = parseFloat(('' + lngInput.value).replace(',', '.'));
      if (isFinite(lat) && isFinite(lng)) {
        setMarker(lat, lng, false);
      }
    }

    [latInput, lngInput].forEach(function (input) {
      if (!input) return;
      input.addEventListener('change', function () {
        var lat = parseFloat(('' + (latInput ? latInput.value : '')).replace(',', '.'));
        var lng = parseFloat(('' + (lngInput ? lngInput.value : '')).replace(',', '.'));
        if (isFinite(lat) && isFinite(lng)) {
          setMarker(lat, lng, false);
        }
      });
    });
  }

  function findTalhaoSelect() {
    return document.querySelector('select[name="fk_talhao"], select#fk_talhao, select[name="options_fk_talhao"], select#options_fk_talhao, input[type="hidden"][name="fk_talhao"], input[type="hidden"]#fk_talhao, input[type="hidden"][name="options_fk_talhao"], input[type="hidden"]#options_fk_talhao');
  }

  function findField(name) {
    return document.querySelector('[name="' + name + '"], #' + name);
  }

  function setMarker(lat, lng, writeInputs) {
    var latlng = [lat, lng];
    if (!marker) {
      marker = L.marker(latlng, { draggable: !readonly }).addTo(map);
      if (!readonly) {
        marker.on('dragend', function () {
          var pos = marker.getLatLng();
          writePosition(pos.lat, pos.lng);
        });
      }
    } else {
      marker.setLatLng(latlng);
    }

    if (writeInputs) {
      writePosition(lat, lng);
    }
  }

  function writePosition(lat, lng) {
    var fixedLat = Number(lat).toFixed(8);
    var fixedLng = Number(lng).toFixed(8);
    var latInput = findField('latitude');
    var lngInput = findField('longitude');
    var locationInput = findField('localizacao');

    if (latInput) latInput.value = fixedLat;
    if (lngInput) lngInput.value = fixedLng;
    if (locationInput) locationInput.value = fixedLat + ', ' + fixedLng;
  }

  function bindTalhaoSelect() {
    var select = findTalhaoSelect();
    if (!select || select === currentTalhaoSelect) return !!select;

    if (currentTalhaoSelect) {
      currentTalhaoSelect.removeEventListener('change', onTalhaoSelectChange);
      currentTalhaoSelect.removeEventListener('input', onTalhaoSelectChange);
      if (window.jQuery) {
        window.jQuery(currentTalhaoSelect).off('.safraAnaliseSoloMap');
      }
    }

    currentTalhaoSelect = select;
    currentTalhaoSelect.addEventListener('change', onTalhaoSelectChange);
    currentTalhaoSelect.addEventListener('input', onTalhaoSelectChange);

    if (window.jQuery) {
      window.jQuery(currentTalhaoSelect).on('change.safraAnaliseSoloMap select2:select.safraAnaliseSoloMap select2:clear.safraAnaliseSoloMap', onTalhaoSelectChange);
    }

    return true;
  }

  function onTalhaoSelectChange() {
    setTimeout(loadCurrentTalhao, 0);
  }

  function startTalhaoWatcher() {
    if (!talhaoSelectObserver && window.MutationObserver) {
      talhaoSelectObserver = new MutationObserver(function () {
        bindTalhaoSelect();
      });
      talhaoSelectObserver.observe(document.body, { childList: true, subtree: true });
    }

    if (!talhaoPollTimer) {
      talhaoPollTimer = setInterval(function () {
        bindTalhaoSelect();
        var value = readTalhaoValue();
        if (value !== lastTalhaoValue) {
          loadCurrentTalhao();
        }
      }, 600);
    }
  }

  function readTalhaoValue() {
    var select = findTalhaoSelect();
    if (select && typeof select.value !== 'undefined') {
      return String(select.value || '');
    }

    return cfg.initialTalhaoId ? String(cfg.initialTalhaoId) : '';
  }

  function loadCurrentTalhao() {
    var hasSelect = !!findTalhaoSelect();
    var value = readTalhaoValue();
    if (!value && !hasSelect && cfg.initialTalhaoId) {
      value = String(cfg.initialTalhaoId);
    }

    if (value === lastTalhaoValue) return;
    lastTalhaoValue = value;

    if (value) {
      fetchTalhao(value);
    } else {
      clearTalhaoLayer();
      setHint(cfg.messages && cfg.messages.clickMap ? cfg.messages.clickMap : '', false);
    }
  }

  function getSelectedTalhaoLabel(id) {
    var select = findTalhaoSelect();
    if (!select) return '';

    if (select.tagName && select.tagName.toLowerCase() === 'select' && select.selectedIndex >= 0 && String(select.value) === String(id)) {
      return select.options[select.selectedIndex].textContent || '';
    }

    if (select.id) {
      var select2Container = document.getElementById('select2-' + select.id + '-container');
      if (select2Container) {
        return select2Container.textContent || '';
      }
    }

    return '';
  }

  function fetchTalhao(id) {
    if (!ajaxUrl || !id) {
      clearTalhaoLayer();
      return;
    }

    var selectedLabel = getSelectedTalhaoLabel(id);

    setHint(cfg.messages && cfg.messages.loading ? cfg.messages.loading : 'Carregando talhao...', false);
    fetch(ajaxUrl + '?id=' + encodeURIComponent(id) + '&label=' + encodeURIComponent(selectedLabel) + '&_ts=' + Date.now(), {
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data || data.error) {
          clearTalhaoLayer();
          setHint((data && data.error) || 'Nao foi possivel carregar o talhao.', true);
          return;
        }

        var geometry = parseGeometry(data.geojson || data.geometry || data);
        if (!geometry) {
          clearTalhaoLayer();
          setHint(cfg.messages && cfg.messages.empty ? cfg.messages.empty : 'Talhao sem poligono cadastrado.', true);
          return;
        }

        drawTalhao(geometry);
        setHint(cfg.messages && cfg.messages.clickMap ? cfg.messages.clickMap : '', false);
      })
      .catch(function () {
        clearTalhaoLayer();
        setHint(cfg.messages && cfg.messages.error ? cfg.messages.error : 'Falha ao carregar o talhao.', true);
      });
  }

  function drawTalhao(geometry) {
    clearTalhaoLayer();
    talhaoLayer = L.geoJSON(geometry, {
      style: function () {
        return { color: '#176b52', weight: 2, opacity: 1, fillColor: '#2f8f6b', fillOpacity: 0.22 };
      }
    }).addTo(map);

    var bounds = talhaoLayer.getBounds && talhaoLayer.getBounds();
    if (bounds && bounds.isValid && bounds.isValid()) {
      map.fitBounds(bounds, { padding: [24, 24] });
    }
  }

  function clearTalhaoLayer() {
    if (talhaoLayer && map) {
      map.removeLayer(talhaoLayer);
    }
    talhaoLayer = null;
  }

  function parseGeometry(raw) {
    if (!raw) return null;
    if (typeof raw === 'object') return raw;
    var text = ('' + raw).trim();
    if (!text) return null;
    try {
      return JSON.parse(text);
    } catch (jsonError) {
      if (window.wellknown && typeof window.wellknown.parse === 'function') {
        try {
          return window.wellknown.parse(text);
        } catch (wktError) {
          return null;
        }
      }
    }
    return null;
  }

  function setHint(text, isError) {
    var hint = document.getElementById(cfg.hintId || 'safra-analisesolo-map-hint');
    if (!hint) return;
    hint.textContent = text || (readonly ? (cfg.messages && cfg.messages.viewHint || '') : (cfg.messages && cfg.messages.clickMap || ''));
    hint.classList.toggle('safra-map-error', !!isError);
  }

  ready(init);
})();
