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

  function ensureMap() {
    var el = document.querySelector(container);
    if (!el) { err('container do mapa não encontrado:', container); return null; }
    if (map) return map;

    // altura de fallback se CSS faltar
    if (!el.style.height) el.style.height = '380px';

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
    if (!id) { clearLayer(); setHint(mapHint, false); return; }
    if (!ajaxUrl) { err('ajaxTalhaoUrl não definido'); return; }
    setHint('Carregando polígono...', false);

    fetch(ajaxUrl + '?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
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
        err(e);
        setHint('Falha na requisição do polígono.', true);
        clearLayer();
      });
  }

  function init() {
    var select = document.querySelector(selector);
    var wrap   = document.querySelector(wrapper);

    if (!select) { log('select não encontrado:', selector); return; }
    if (!wrap)   { log('wrapper não encontrado:', wrapper); return; }

    loadLeaflet(function(){
      ensureMap();
      if (!map) return;

      // listener
      select.addEventListener('change', function(e){
        var val = e.target.value || '';
        log('change -> id', val);
        fetchGeo(val);
      });

      // se já tiver valor (modo edição)
      if (select.value) {
        fetchGeo(select.value);
      } else {
        setHint(mapHint, false);
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
