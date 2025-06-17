<link rel="stylesheet" href="./css/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw/dist/leaflet.draw.css" />

<script src="./js/leaflet.js"></script>
<script src="./js/leaflet.draw.js"></script>
<script src="./js/wellknown.js"></script>
<script src="https://unpkg.com/@turf/turf/turf.min.js"></script>

<script>
    // elementos de seleção de talhão, data e arquivo
    const mensagemElement = document.getElementById('mensagem');
    const talhaoElement = document.getElementById('talhao_list');
    const consultaElement = document.getElementById('inputConsulta');
    const formElement = document.getElementById('ndvi_form');
    const arquivoElement = document.getElementById('inputArquivo');
    const weekPickerElement = document.getElementById('weekPicker');
    const yearPickerElement = document.getElementById('yearPicker');
    const btnConsulta = document.getElementById('btnConsulta');

    let consultado = arquivoElement.value.split("_");
    console.log(consultado);
    // if(consultado){
    //     weekPickerElement.value = consultado[0]+'/'+consultado[1];
    //     talhaoElement.value = consultado[2];

    // }
    let rangeSelected;

    arquivoElement.value = arquivo_post;

    function formatDate(date) {
        let day = date.getDate().toString().padStart(2, '0');
        let month = (date.getMonth() + 1).toString().padStart(2, '0');
        let year = date.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function getCurrentWeekNumber() {
        const now = new Date();
        const startOfYear = new Date(now.getFullYear(), 0, 1);
        const pastDays = Math.floor((now - startOfYear) / (24 * 60 * 60 * 1000));
        return Math.ceil((pastDays + startOfYear.getDay() + 1) / 7);
    }

    function populateYearPicker() {
        const yearPicker = document.getElementById('yearPicker');
        const currentYear = getCurrentYear();
        for (let year = currentYear - 5; year <= currentYear; year++) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            yearPicker.appendChild(option);
            if (year === currentYear) {
                option.selected = true;
            }
        }
    }

    function getCurrentYear() {
        return new Date().getFullYear();
    }

    function populateWeeks(year) {
        const weekPicker = document.getElementById('weekPicker');
        weekPicker.innerHTML = ''; // Limpa semanas antigas ao mudar o ano
        const janFirst = new Date(year, 0, 1);
        const firstDayOfYear = janFirst.getDay();

        for (let week = 1; week <= 52; week++) {
            const days = (week - 1) * 7 - firstDayOfYear;
            const weekStart = new Date(year, 0, janFirst.getDate() + days);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekEnd.getDate() + 6);

            const weekStartStr = formatDate(weekStart);
            const weekEndStr = formatDate(weekEnd);

            const weekStartStrOpt = weekStart.toISOString().split('T')[0];
            const weekEndStrOpt = weekEnd.toISOString().split('T')[0];

            const option = document.createElement('option');
            option.value = `${weekStartStrOpt}/${weekEndStrOpt}`;
            option.textContent = `Semana de ${weekStartStr} a ${weekEndStr}`;
            weekPicker.appendChild(option);
            if (year == getCurrentYear() && week == getCurrentWeekNumber()) {
                break;
            }
        }
    }

    function updateWeekPicker() {
        const year = document.getElementById('yearPicker').value;
        populateWeeks(year);
        weekPickerElement.onchange();
    }

    function getWeekDates(dateRange) {
        document.getElementById('dateRange').value = dateRange || "Selecione uma semana para ver as datas.";
        rangeSelected = dateRange;
        // consultaElement.value = dateRange + '_' + talhaoElement.value;
        if (consultaElement.value != arquivoElement.value) {
            // submitForm()
            consulta();
        }
        // consultaElement.onchange();
    }

    // Inicialização
    window.onload = function() {
        populateYearPicker();
        const currentYear = new Date().getFullYear();
        populateWeeks(currentYear);
        const currentWeekNumber = getCurrentWeekNumber() - 1; // Ajusta para índice do array
        if (arquivoElement.value == "") {
            // getWeekDates(weekSelected);
            document.getElementById('weekPicker').selectedIndex = currentWeekNumber;
            document.getElementById('weekPicker').onchange();
            consulta();
        } else {
            // weekPickerElement.value = consultado[0];
            let data = consultado[1];
            // console.log(data);
            let data_ano = data;
            data_ano = data_ano.split("-");
            // console.log(data_ano);
            let ano = data_ano[0];
            yearPickerElement.value = ano;
            populateWeeks(ano);
            // weekPickerElement.value = consultado[0];
            // talhaoElement.value = consultado[1];
            if (consultado) {
                weekPickerElement.value = consultado[0] + '/' + consultado[1];
                talhaoElement.value = consultado[2];

            }

            carregarDadosMapa();
        }
    };


    // Função para adicionar opções ao <select>
    function adicionarOpcoes(select, opcoes) {
        let i = 0;
        opcoes.forEach(opcao => {
            const optionElement = document.createElement('option');
            optionElement.value = talhao_ids[i];
            optionElement.textContent = opcao;
            select.appendChild(optionElement);
            i++;
        });
    }

    // Adiciona as opções ao <select>
    adicionarOpcoes(talhaoElement, talhao_array);



    function aoMudar(event) {
        const opcaoSelecionada = event.target.value;
        // mensagemElement.textContent = `Você selecionou: ${opcaoSelecionada}`;
        // consultaElement.value = str_replace('/', '_', weekPickerElement.value) + '_' + talhaoElement.value;
        consulta();
        // if(consultaElement.value != arquivoElement.value){
        //     submitForm()
        // }
    }

    function consulta() {
        let t = talhaoElement.value;
        let d = weekPickerElement.value;
        let val = d + '_' + t;
        // let val = consultaElement.value;
        val = val.replace("/", "_");
        consultaElement.value = val;
        formElement.submit();
    }

    function submitForm() {
        formElement.submit();
    }

    // Adiciona o ouvinte de eventos para o evento de mudança
    talhaoElement.addEventListener('change', aoMudar);
    consultaElement.addEventListener('change', consulta)
    if (btnConsulta) {
        btnConsulta.addEventListener('click', consulta);
    }
</script>


<script>
    var map = L.map('mapIndex').setView([-17.047558, -46.824176], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data &copy; OpenStreetMap contributors'
    }).addTo(map);

    var drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    googleHybrid = L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
    });
    googleHybrid.addTo(map);


    var drawnPolygon
    // drawnPolygon = renderGeoJSON();


    function carregarDadosMapa() {
        let i = 0;
        let url;
        let caminho = arquivoElement.value;
        // alert(caminho);
        if (json) {
            let t = 0;
            let i = 0;
            if (talhao_selected) {
                t = talhao_selected;
            }

            // console.log(json);
            json.forEach((data) => {
                // console.log("talhao:"+talhao_ids[i])
                // console.log("selecionado:"+talhaoElement.value)
                if (talhao_ids[i] == consultado[2]) {
                    url = window.location.href.replace("ndwi_view.php", "json/ndwi/" + caminho.replace("/", "_") + ".json");
                    console.log('url consultada: ');
                    console.log(url);
                    let json_layer = renderGeoJSON(data);
                    // let ndvi_layer = renderGeoJSON(url);
                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            let counter = 0;
                            var geoJsonLayer = L.geoJSON(data, {
                                onEachFeature: function(feature, layer) {
                                    layer.setStyle({
                                        color: `#${feature.properties.COLOR_HEX}`,
                                        stroke: true,
                                        weight: 1,
                                        fill: true,
                                        fillOpacity: 1,
                                        fillColor: `#${feature.properties.COLOR_HEX}`
                                    });
                                    counter++;
                                }
                            }).addTo(map);
                            map.fitBounds(geoJsonLayer.getBounds());
                        })
                        .catch(error => console.error('Error fetching NDVI data:', error));
                    console.log(json_layer);
                    // createAreaTooltip(json_layer, i);
                }
                i++;
            });
        }
    }


    function renderGeoJSON(data) {
        // var geojsonInput = document.getElementsByClassName("fieldname_geo_json")[1].children[0].innerHTML;
        // console.log(geojsonInput);
        if (data) {
            // var geojsonObject = turf.simplify(data, { tolerance: 0.01, highQuality: true });
            var geojsonObject = JSON.parse(data);
            // var geojsonObject = JSON.parse(data);
            var geojsonLayer = L.geoJSON(geojsonObject);
            drawnItems.addLayer(geojsonLayer);
            // createAreaTooltip(geojsonLayer);
            map.fitBounds(geojsonLayer.getBounds());
            return geojsonLayer;
        }
        return null;
    }


    function createAreaTooltip(layer, i) {
        if (layer.areaTooltip) {
            updateAreaTooltip(layer);
            return;
        }

        layer.areaTooltip = L.tooltip({
            permanent: true,
            direction: 'center',
            className: 'area-tooltip'
        });

        layer.on('remove', function(event) {
            layer.areaTooltip.remove();
        });

        layer.on('add', function(event) {
            updateAreaTooltip(layer);
            layer.areaTooltip.addTo(map);
        });

        if (map.hasLayer(layer)) {
            updateAreaTooltip(layer, i);
            layer.areaTooltip.addTo(map);
        }
    }

    function updateAreaTooltip(layer) {
        var area = area_array[i]
        var readableArea = L.GeometryUtil.readableArea(area, true);
        var latlng = layer.getCenter();

        layer.areaTooltip
            .setContent(readableArea)
            .setLatLng(latlng);
    }
</script>