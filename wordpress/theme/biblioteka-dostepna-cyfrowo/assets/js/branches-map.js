// Turns each branch list (.bdc-map[data-branches-map]) into one map with a pin per
// branch. A pin's popup copies the address and contacts from the branch section
// its link points to, so staff edit them in one place. Without JavaScript the
// list of links stays.
(function () {
	if (!window.L) return;

	var pin =
		'<svg viewBox="0 0 30 42" aria-hidden="true" focusable="false">' +
		'<path d="M15 1C7.3 1 1 7.2 1 14.9 1 25.3 15 41 15 41s14-15.7 14-26.1C29 7.2 22.7 1 15 1z" fill="currentColor" stroke="#fff" stroke-width="2"/>' +
		'<circle cx="15" cy="15" r="5.5" fill="#fff"/></svg>';

	function icon(isMain) {
		var size = isMain ? [36, 50] : [30, 42];
		return L.divIcon({
			className: 'bdc-pin' + (isMain ? ' bdc-pin--main' : ''),
			html: pin,
			iconSize: size,
			iconAnchor: [size[0] / 2, size[1]],
			popupAnchor: [0, -size[1] + 6],
		});
	}

	function link(href, text) {
		var a = document.createElement('a');
		a.href = href;
		a.textContent = text;
		return a;
	}

	function popup(name, section, lat, lon) {
		var box = document.createElement('div');
		box.className = 'bdc-map__popup';

		var title = document.createElement('p');
		title.className = 'bdc-map__name';
		title.textContent = name;
		box.appendChild(title);

		if (section) {
			section.querySelectorAll(':scope > p:not(.bdc-hours-today)').forEach(function (p) {
				box.appendChild(p.cloneNode(true));
			});
		}

		// The branch's "Dziś (…): …" line, rendered by functions.php.
		var today = section && section.querySelector('.bdc-hours-today');
		if (today) box.appendChild(today.cloneNode(true));

		var links = document.createElement('p');
		links.className = 'bdc-map__links';
		if (section) {
			// Jumps to the branch and opens its opening-hours toggle.
			var toHours = link('#' + section.id, 'Godziny otwarcia');
			toHours.addEventListener('click', function () {
				var details = section.querySelector('details');
				if (details) details.open = true;
			});
			links.appendChild(toHours);
		}
		links.appendChild(
			link('https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lon, 'Wyznacz trasę')
		);
		box.appendChild(links);
		return box;
	}

	document.querySelectorAll('.bdc-map[data-branches-map]').forEach(function (box) {
		var items = box.querySelectorAll('li[data-lat][data-lon]');
		if (!items.length) return;

		var canvas = document.createElement('div');
		canvas.className = 'bdc-map__canvas';
		box.insertBefore(canvas, box.firstChild);

		var map = L.map(canvas, {
			scrollWheelZoom: false,
			// One finger scrolls the page on phones; zoom with the buttons or two fingers.
			dragging: !L.Browser.mobile,
		});
		map.attributionControl.setPrefix(false);
		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '© <a href="https://www.openstreetmap.org/copyright">autorzy OpenStreetMap</a>',
		}).addTo(map);

		var bounds = [];
		items.forEach(function (item) {
			var lat = parseFloat(item.dataset.lat);
			var lon = parseFloat(item.dataset.lon);
			var anchor = item.querySelector('a');
			var name = (anchor || item).textContent.trim();
			var id = anchor && anchor.hash ? decodeURIComponent(anchor.hash.slice(1)) : '';
			var section = id ? document.getElementById(id) : null;
			var isMain = item.hasAttribute('data-main');

			L.marker([lat, lon], {
				icon: icon(isMain),
				title: name,
				zIndexOffset: isMain ? 1000 : 0,
				riseOnHover: true,
			})
				.bindPopup(function () {
					return popup(name, section, lat, lon);
				})
				.addTo(map);
			bounds.push([lat, lon]);
		});
		map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
		box.classList.add('is-ready');
	});
})();
