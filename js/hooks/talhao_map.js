// safra/js/hooks/talhao_map.js
(function () {
  // ---- DEBUG helper ----
  function log(){ try{ console.log.apply(console, ['[SafraMap]'].concat([].slice.call(arguments))); }catch(e){} }
  function err(){ try{ console.error.apply(console, ['[SafraMap]'].concat([].slice.call(arguments))); }catch(e){} }

  var cfg = (window.SAFRA || {});
  var ajaxUrl   = cfg.ajaxTalhaoUrl;      // setado pelo PHP
  var mapHint   = cfg.mapHint || 'Selecione um talhão para visualizar o polígono.';
  var messages  = cfg.mapMessages || {};
  var selector  = 'select[id="options_fk_talhao"]';
  var container = cfg.mapContainerSelector || '#safra-map';
  var wrapper   = cfg.mapWrapperSelector || '#safra-map-wrapper';

  // ---- Carregar Leaflet 1x (ou usar local se CDN bloqueado) ----
  function loadLeaflet(cb) {
    if (window.L && window.L.map) { cb(); return; }

    var leafletCssLocal = cfg.leafletCssLocal || cfg.leafletCss;
    var leafletJsLocal  = cfg.leafletJsLocal || cfg.leafletJs;
    var leafletCssCdn   = cfg.leafletCssCdn || 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    var leafletJsCdn    = cfg.leafletJsCdn || 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

    function injectCss(url) {
      if (!url) return;
      var link = document.querySelector('link[data-safra-leaflet]');
      if (!link) {
        link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url;
        link.setAttribute('data-safra-leaflet', '1');
        document.head.appendChild(link);
      }
    }

    function injectScript(url, triedLocal) {
      if (!url) return;

      var existing = document.querySelector('script[data-safra-leaflet]');
      if (existing) {
        if (window.L && window.L.map) {
          cb();
        } else {
          var t = setInterval(function(){
            if (window.L && window.L.map) {
              clearInterval(t);
              cb();
            }
          }, 50);
        }
        return;
      }

      var script = document.createElement('script');
      script.src = url;
      script.defer = true;
      script.setAttribute('data-safra-leaflet', '1');
      script.onload = function(){ log('Leaflet carregado a partir de', url); cb(); };
      script.onerror = function(){
        err('Falha ao carregar Leaflet em', url);
        if (!triedLocal && url !== leafletJsCdn) {
          injectScript(leafletJsCdn, true);
          injectCss(leafletCssCdn);
        }
      };
      document.head.appendChild(script);
    }

    injectCss(leafletCssLocal || leafletCssCdn);
    injectScript(leafletJsLocal || leafletJsCdn, leafletJsLocal && leafletJsLocal !== leafletJsCdn);
  }

  function loadWellknown(cb) {
    if (window.wellknown && typeof window.wellknown.parse === 'function') { cb(); return; }

    var url = cfg.wellknownJs || '/safra/js/wellknown.js';
    var existing = document.querySelector('script[data-safra-wellknown]');

    if (existing) {
      var t = setInterval(function(){
        if (window.wellknown && typeof window.wellknown.parse === 'function') {
          clearInterval(t);
          cb();
        }
      }, 50);
      return;
    }

    var script = document.createElement('script');
    script.src = url;
    script.defer = true;
    script.setAttribute('data-safra-wellknown', '1');
    script.onload = function(){ cb(); };
    script.onerror = function(){ err('Não foi possível carregar wellknown.js em', url); cb(); };
    document.head.appendChild(script);
  }

  // ---- Mapa ----
  var map = null;
  var layer = null;
  var currentSelect = null;
  var selectObserver = null;
  var fetchCounter = 0;
  var currentFetchController = null;

  function ensureMap() {
    var el = document.querySelector(container);
    if (!el) { err('container do mapa não encontrado:', container); return null; }
    if (map) return map;

    // altura de fallback se CSS faltar
    if (!el.style.height) {
      var computedHeight = window.getComputedStyle(el).height;
      if (!computedHeight || computedHeight === '0px') {
        el.style.height = '380px';
      }
    }

    map = L.map(el, { attributionControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 22 }).addTo(map);
    map.setView([-15.78, -47.93], 4); // Brasil
    log('mapa criado');
    return map;
  }

  function invalidateLater() {
    // útil quando o mapa está em tabs/accordions
    setTimeout(function(){ if (map) map.invalidateSize(); }, 50);
    setTimeout(function(){ if (map) map.invalidateSize(); }, 300);
  }

  function clearLayer() {
    if (layer && map) {
      map.removeLayer(layer);
      layer = null;
    }
  }

  function setHint(text, isError) {
    var el = document.getElementById('safra-map-hint');
    if (!el) return;
    el.textContent = text || mapHint;
    el.classList.toggle('opacitymedium', !isError);
    el.style.color = isError ? '#a00' : '';
  }

  function drawGeoJSON(geojson) {
    clearLayer();
    if (!geojson) return;

    try {
      layer = L.geoJSON(geojson, { style: function(){ return { weight:2, opacity:1, fillOpacity:0.25 }; } }).addTo(map);
      var b = layer.getBounds && layer.getBounds();
      if (b && b.isValid && b.isValid()) map.fitBounds(b, { padding:[20,20] });
    } catch (e) {
      err('Falha ao renderizar o GeoJSON', e);
      setHint(messages.error || 'Erro ao carregar o polígono.', true);
      return;
    }

    invalidateLater();
  }

  function fetchGeo(id) {
    if (!ajaxUrl) { err('ajaxTalhaoUrl não definido'); return; }

    if (currentFetchController) {
      currentFetchController.abort();
    }

    if (!id) {
      clearLayer();
      setHint(mapHint, false);
      currentFetchController = null;
      return;
    }

    currentFetchController = new AbortController();
    var signal = currentFetchController.signal;
    var requestId = ++fetchCounter;

    setHint(messages.loading || 'Carregando polígono...', false);

    fetch(ajaxUrl + '?id=' + encodeURIComponent(id) + '&_ts=' + Date.now(), {
      credentials: 'same-origin',
      cache: 'no-store',
      signal: signal
    })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (requestId !== fetchCounter) return;
        currentFetchController = null;

        if (!data || data.error) {
          setHint((data && data.error) || messages.error || 'Erro ao carregar o polígono.', true);
          clearLayer();
          return;
        }

        var geometry = parseGeometryResponse(data);
        if (!geometry) {
          setHint(messages.empty || 'Talhão sem polígono cadastrado.', true);
          clearLayer();
          return;
        }

        drawGeoJSON(geometry);
        setHint(''); // volta ao padrão
      })
      .catch(function(e){
        if (signal && signal.aborted) return;
        currentFetchController = null;
        err(e);
        setHint(messages.fetchError || messages.error || 'Falha na requisição do polígono.', true);
        clearLayer();
      });
  }

  function bindSelect(select) {
    if (!select || select === currentSelect) return;

    if (currentSelect) {
      currentSelect.removeEventListener('change', onSelectChange);
      delete currentSelect.dataset.safraMapBound;
    }

    currentSelect = select;
    currentSelect.dataset.safraMapBound = '1';
    currentSelect.addEventListener('change', onSelectChange);

    if (currentSelect.value) {
      fetchGeo(currentSelect.value);
    } else {
      clearLayer();
      setHint(mapHint, false);
    }
  }

  function onSelectChange(e) {
    var val = e.target.value || '';
    log('change -> id', val);
    fetchGeo(val);
  }

  function bindCurrentSelect() {
    var select = document.querySelector(selector);
    if (!select) {
      if (currentSelect) {
        currentSelect.removeEventListener('change', onSelectChange);
        delete currentSelect.dataset.safraMapBound;
        currentSelect = null;
      }
      clearLayer();
      setHint(mapHint, false);
      return;
    }

    bindSelect(select);
  }

  function init() {
    var wrap   = document.querySelector(wrapper);

    if (!wrap)   { log('wrapper não encontrado:', wrapper); return; }

    loadLeaflet(function(){
      loadWellknown(function(){
        ensureMap();
        if (!map) return;

        setHint(mapHint, false);
        bindCurrentSelect();

        if (!selectObserver) {
          selectObserver = new MutationObserver(function(){
            bindCurrentSelect();
          });
          selectObserver.observe(document.body, { childList: true, subtree: true });
        }

        // se o mapa estiver em tab Dolibarr, tente invalidar em eventos comuns
        document.addEventListener('shown.bs.tab', invalidateLater, true);
        document.addEventListener('click', function(ev){
          // heurística simples: clicou num título de bloco/accordion
          if ((ev.target.className||'').toString().match(/(tabs|tab|accordion|arearef)/i)) invalidateLater();
        }, true);

        invalidateLater();
      });
    });
  }

  // roda quando DOM estiver pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function parseGeometry(raw) {
    if (!raw) return null;

    if (typeof raw === 'object') {
      return raw;
    }

    var text = ('' + raw).trim();
    if (!text) return null;

    try {
      return JSON.parse(text);
    } catch (jsonError) {
      if (window.wellknown && typeof window.wellknown.parse === 'function') {
        try {
          return window.wellknown.parse(text) || null;
        } catch (wktError) {
          err('Erro ao interpretar WKT', wktError);
        }
      }
      err('Geometria inválida recebida', jsonError);
    }

    return null;
  }

  function parseGeometryResponse(data) {
    if (!data) return null;

    if (data.geojson) {
      return parseGeometry(data.geojson);
    }

    if (data.geometry) {
      return parseGeometry(data.geometry);
    }

    return parseGeometry(data);
  }
})();
