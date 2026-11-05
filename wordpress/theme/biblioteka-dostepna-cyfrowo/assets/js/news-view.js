// Grid / list switch on the news list (.bdc-view-switch). The choice is kept in
// localStorage and applied to <html data-bdc-news-view> before the first paint by
// an inline script in <head> (functions.php), so this only wires up the buttons.
// Without JavaScript the switch stays hidden and the grid shows.
(function () {
	var root = document.documentElement;

	document.querySelectorAll('[data-bdc-view-switch]').forEach(function (group) {
		var buttons = group.querySelectorAll('[data-bdc-view]');

		function sync() {
			var current = root.getAttribute('data-bdc-news-view') || 'grid';
			buttons.forEach(function (button) {
				button.setAttribute('aria-pressed', String(button.getAttribute('data-bdc-view') === current));
			});
		}

		buttons.forEach(function (button) {
			button.addEventListener('click', function () {
				var view = button.getAttribute('data-bdc-view');
				root.setAttribute('data-bdc-news-view', view);
				try {
					localStorage.setItem('bdc-news-view', view);
				} catch (e) {}
				sync();
			});
		});

		sync();
		group.hidden = false;
	});
})();
