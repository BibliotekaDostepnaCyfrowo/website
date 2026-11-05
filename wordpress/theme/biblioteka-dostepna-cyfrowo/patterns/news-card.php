<?php
/**
 * Title: Karta aktualności
 * Slug: biblioteka-dostepna-cyfrowo/news-card
 * Inserter: no
 * Description: One news item in a query loop: image, title, excerpt, "Czytaj dalej".
 */
?>
<!-- wp:group {"className":"bdc-card","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group bdc-card">
	<!-- wp:post-featured-image {"isLink":false,"aspectRatio":"16/9","height":"","sizeSlug":"medium_large"} /-->

	<!-- wp:group {"className":"bdc-card__body","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","orientation":"vertical"}} -->
	<div class="wp-block-group bdc-card__body">
		<!-- wp:post-date {"format":"j F Y"} /-->

		<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"large","style":{"typography":{"fontWeight":"800"}}} /-->

		<!-- wp:post-excerpt {"moreText":"Czytaj dalej","showMoreOnNewLine":true,"excerptLength":22} /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
