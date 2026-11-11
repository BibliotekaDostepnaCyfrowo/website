/**
 * Hamburger menu as an accordion. Core marks every submenu as expanded while the
 * hamburger menu is open and lists them all; here each first-level item with a
 * panel starts closed and opens on its own button. The state lives in the
 * button's aria-expanded, which style.css reads, so without this script the
 * menu stays fully listed.
 */
( function () {
	document.querySelectorAll( '.bdc-header .wp-block-navigation' ).forEach( ( nav ) => {
		const overlay = nav.querySelector( '.wp-block-navigation__responsive-container' );
		const toggles = Array.from( nav.querySelectorAll( '.wp-block-navigation-submenu__toggle' ) );
		if ( ! overlay || ! toggles.length ) {
			return;
		}

		const open = new Set();
		let wasOpen = false;

		// Core writes aria-expanded="true" again whenever it re-renders, so the
		// wanted value is put back each time something changes.
		const sync = () => {
			const isOpen = overlay.classList.contains( 'is-menu-open' );
			if ( isOpen && ! wasOpen ) {
				// A freshly opened menu shows only the section of the current page.
				open.clear();
				toggles.forEach( ( toggle ) => {
					if ( toggle.parentElement.querySelector( '[aria-current="page"]' ) ) {
						open.add( toggle );
					}
				} );
			}
			wasOpen = isOpen;
			if ( ! isOpen ) {
				return;
			}
			toggles.forEach( ( toggle ) => {
				const wanted = String( open.has( toggle ) );
				if ( toggle.getAttribute( 'aria-expanded' ) !== wanted ) {
					toggle.setAttribute( 'aria-expanded', wanted );
				}
			} );
		};

		// Caught on the way down and stopped, so core doesn't also record the panel
		// as open: it would still be open when the window grows back into a bar.
		nav.addEventListener(
			'click',
			( event ) => {
				const toggle = event.target.closest( '.wp-block-navigation-submenu__toggle' );
				if ( ! toggle || ! overlay.classList.contains( 'is-menu-open' ) ) {
					return;
				}
				event.stopPropagation();
				if ( ! open.delete( toggle ) ) {
					open.add( toggle );
				}
				sync();
			},
			true
		);

		new MutationObserver( sync ).observe( nav, {
			subtree: true,
			attributes: true,
			attributeFilter: [ 'class', 'aria-expanded' ],
		} );
	} );
} )();
