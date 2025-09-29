// safra/js/hooks/talhao_map.js
(function () {
  // ---- DEBUG helper ----
  function log(){ try{ console.log.apply(console, ['[SafraMap]'].concat([].slice.call(arguments))); }catch(e){} }
  function err(){ try{ console.error.apply(console, ['[SafraMap]'].concat([].slice.call(arguments))); }catch(e){} }

  var cfg = (window.SAFRA || {});
  var ajaxUrl   = cfg.ajaxTalhaoUrl;      // setado pelo PHP
  var mapHint   = cfg.mapHint || 'Selecione um talhão para visualizar o polígono.';
//   var selector  = cfg.selectSelector || 'select[id="options_fk_talhao"]';
  var selector  = 'select[id="options_fk_talhao"]';
  var container = cfg.mapContainerSelector || '#safra-map';
  var wrapper   = cfg.mapWrapperSelector || '#safra-map-wrapper';

  // ---- Carregar Leaflet 1x (ou usar local se CDN bloqueado) ----
  function loadLeaflet(cb) {
    if (window.L && window.L.map) { cb(); return; }

    // Se quiser forçar local: comente CDN e descomente local
    var leafletCss = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    var leafletJs  = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

    // var leafletCss = cfg.leafletCssLocal; // ex: '/custom/safra/css/vendor/leaflet.css'
    // var leafletJs  = cfg.leafletJsLocal;  // ex: '/custom/safra/js/vendor/leaflet.js'

    var link = document.querySelector('link[data-safra-leaflet]');
    if (!link) {
      link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = leafletCss;
      link.setAttribute('data-safra-leaflet', '1');
      document.head.appendChild(link);
    }

    var s = document.querySelector('script[data-safra-leaflet]');
    if (!s) {
      s = document.createElement('script');
      s.src = leafletJs;
      s.defer = true;
      s.setAttribute('data-safra-leaflet', '1');
      s.onload = function(){ log('Leaflet carregado'); cb(); };
      s.onerror = function(){ err('Falha ao carregar Leaflet'); };
      document.head.appendChild(s);
    } else {
      // se já existe, dá um tempinho pra garantir onload
      var t = setInterval(function(){
        if (window.L && window.L.map){ clearInterval(t); cb(); }
      }, 50);
    }
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
    layer = L.geoJSON(geojson, { style: function(){ return { weight:2, opacity:1, fillOpacity:0.25 }; } }).addTo(map);
    try {
      var b = layer.getBounds && layer.getBounds();
      if (b && b.isValid()) map.fitBounds(b, { padding:[20,20] });
    } catch (e) {}
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

    setHint('Carregando polígono...', false);

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
          setHint(data && data.error ? data.error : 'Erro ao carregar o polígono.', true);
          clearLayer();
          return;
        }
        if (!data.geojson) {
          setHint('Talhão sem GeoJSON.', true);
          clearLayer();
          return;
        }
        drawGeoJSON(data.geojson);
        setHint(''); // volta ao padrão
      })
      .catch(function(e){
        if (signal && signal.aborted) return;
        currentFetchController = null;
        err(e);
        setHint('Falha na requisição do polígono.', true);
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
  }

  // roda quando DOM estiver pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
