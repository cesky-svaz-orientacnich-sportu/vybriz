const mapyCZ = (userOptions) => {
	const API_KEY = 'wOqTujqQYvTnCpGwTMooBXOxk4lLLOHhPFVS5PLEo5I'; // TODO: .env
	const DEFAULT_OPTIONS = {
		id: "mapa",
		center: [49.83, 15.45],
		zoom: 6
	}
	const options = Object.assign({}, DEFAULT_OPTIONS, userOptions);

	const map = L.map(options.id).setView(options.center, options.zoom);
	initLayers();
	initAttributionLogo();

	function initLayers() {
		const tileLayers = {
			'Základní': L.tileLayer(`https://api.mapy.cz/v1/maptiles/basic/256/{z}/{x}/{y}?apikey=${API_KEY}`, {
				minZoom: 5,
				maxZoom: 15,
				attribution: '<a href="https://api.mapy.cz/copyright" target="_blank">&copy; Seznam.cz a.s. a další</a>',
			}),
			'Turistická': L.tileLayer(`https://api.mapy.cz/v1/maptiles/outdoor/256/{z}/{x}/{y}?apikey=${API_KEY}`, {
				minZoom: 5,
				maxZoom: 15,
				attribution: '<a href="https://api.mapy.cz/copyright" target="_blank">&copy; Seznam.cz a.s. a další</a>',
			}),
			'Letecká': L.tileLayer(`https://api.mapy.cz/v1/maptiles/aerial/256/{z}/{x}/{y}?apikey=${API_KEY}`, {
				minZoom: 5,
				maxZoom: 15,
				attribution: '<a href="https://api.mapy.cz/copyright" target="_blank">&copy; Seznam.cz a.s. a další</a>',
			}),
		};
		tileLayers['Turistická'].addTo(map);
		L.control.layers(tileLayers).addTo(map);
	}

	function initAttributionLogo() {
		const LogoControl = L.Control.extend({
			options: {
				position: 'bottomleft',
			},

			onAdd: function (map) {
				const container = L.DomUtil.create('div');
				const link = L.DomUtil.create('a', '', container);

				link.setAttribute('href', 'http://mapy.cz/');
				link.setAttribute('target', '_blank');
				link.innerHTML = '<img src="https://api.mapy.cz/img/api/logo.svg" />';
				L.DomEvent.disableClickPropagation(link);

				return container;
			},
		});
		new LogoControl().addTo(map);
	}

	return map;
}
