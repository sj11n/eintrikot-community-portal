<?php
/**
 * Title: EINTRIKOT – news
 * Slug: eintrikot/mvp-news
 * Categories: eintrikot
 */
?>
<!-- wp:cover {"overlayColor":"blue","dimRatio":100,"isUserOverlayColor":true,"className":"section page-intro et-mvp-page-hero"} --><div class="wp-block-cover section page-intro et-mvp-page-hero"><span aria-hidden="true" class="wp-block-cover__background has-blue-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p>Aus dem Netzwerk</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Stories.</h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Geschichten, Interviews und Beiträge von EINTRIKOT.</p><!-- /wp:paragraph -->
</div></div><!-- /wp:cover -->
<!-- wp:group {"tagName":"section","className":"section news-archive","layout":{"type":"default"}} --><section class="wp-block-group section news-archive"><!-- wp:shortcode -->[eintrikot_stories_filter]<!-- /wp:shortcode -->
<!-- wp:query {"queryId":23,"query":{"perPage":9,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"className":"news-query story-list"} --><div class="wp-block-query news-query story-list">
<!-- wp:post-template {"className":"story-list","layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/10"} /-->
<!-- wp:group {"className":"story-meta","layout":{"type":"flex","flexWrap":"wrap"}} --><div class="wp-block-group story-meta"><!-- wp:post-terms {"term":"category"} /--><!-- wp:post-date {"format":"d.m.Y"} /--></div><!-- /wp:group -->
<!-- wp:post-title {"isLink":true,"level":3} /-->
<!-- wp:post-excerpt {"moreText":"","excerptLength":22} /-->
<!-- /wp:post-template -->
<!-- wp:query-no-results {"className":"story-list"} --><!-- wp:paragraph --><p>Hier erscheinen die nächsten Geschichten, Interviews und Beiträge aus unserem Netzwerk.</p><!-- /wp:paragraph -->
<!-- /wp:query-no-results -->
<!-- wp:query-pagination --><!-- wp:query-pagination-previous {"label":"← Neuere Beiträge","className":"story-list"} /-->
<!-- wp:query-pagination-numbers {"className":"story-list"} /-->
<!-- wp:query-pagination-next {"label":"Ältere Beiträge →","className":"story-list"} /-->
<!-- /wp:query-pagination -->
</div><!-- /wp:query -->
</section><!-- /wp:group -->
<!-- wp:group {"tagName":"section","className":"section story-tip","layout":{"type":"default"}} --><section class="wp-block-group section story-tip"><!-- wp:paragraph --><p>Du bist schneller als wir und hast von Neuigkeiten gehört? Oder du weißt, dass eines unserer Mitglieder etwas Tolles geleistet hat? Dann berichte uns doch davon!</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><a class="text-link" href="mailto:info@eintrikot.de">info@eintrikot.de ↗</a></p><!-- /wp:paragraph --></section><!-- /wp:group -->
