<?php
/**
 * Title: Przełącznik widoku: kafelki / lista
 * Slug: biblioteka-dostepna-cyfrowo/news-view-switch
 * Inserter: no
 * Description: Grid / list switch for a post list with the bdc-news class (assets/js/news-view.js).
 */
?>
<!-- wp:html -->
<div class="bdc-view-switch" role="group" aria-label="Widok wpisów" data-bdc-view-switch hidden>
	<button type="button" data-bdc-view="grid" aria-pressed="true" title="Kafelki">
		<svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false"><rect x="2" y="2" width="7" height="7" rx="1.5"/><rect x="11" y="2" width="7" height="7" rx="1.5"/><rect x="2" y="11" width="7" height="7" rx="1.5"/><rect x="11" y="11" width="7" height="7" rx="1.5"/></svg>
		<span class="screen-reader-text">Kafelki</span>
	</button>
	<button type="button" data-bdc-view="list" aria-pressed="false" title="Lista">
		<svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false"><rect x="2" y="3" width="5" height="4" rx="1"/><rect x="9" y="4" width="9" height="2" rx="1"/><rect x="2" y="8" width="5" height="4" rx="1"/><rect x="9" y="9" width="9" height="2" rx="1"/><rect x="2" y="13" width="5" height="4" rx="1"/><rect x="9" y="14" width="9" height="2" rx="1"/></svg>
		<span class="screen-reader-text">Lista</span>
	</button>
</div>
<!-- /wp:html -->
