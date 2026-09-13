<?php
/**
 * Title: EINTRIKOT – news
 * Slug: eintrikot/mvp-news
 * Categories: eintrikot
 */
?>
<!-- wp:group {"tagName":"section","className":"section page-intro","layout":{"type":"default"}} --><section class="wp-block-group section page-intro"><!-- wp:paragraph --><p>Aus dem Netzwerk</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">News.</h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Geschichten, Interviews und Beiträge von EINTRIKOT.</p><!-- /wp:paragraph -->
</section><!-- /wp:group -->
<!-- wp:group {"tagName":"section","className":"section news-archive","layout":{"type":"default"}} --><section class="wp-block-group section news-archive"><!-- wp:query {"queryId":23,"query":{"perPage":9,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"className":"news-query"} --><div class="wp-block-query news-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} --><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->
<!-- wp:post-date {"format":"d.m.Y"} /-->
<!-- wp:post-title {"isLink":true,"level":3} /-->
<!-- wp:post-excerpt {"moreText":"Beitrag lesen →"} /-->
<!-- /wp:post-template -->
<!-- wp:query-no-results --><!-- wp:paragraph --><p>Hier erscheinen die nächsten Geschichten, Interviews und Beiträge aus unserem Netzwerk.</p><!-- /wp:paragraph -->
<!-- /wp:query-no-results -->
<!-- wp:query-pagination --><!-- wp:query-pagination-previous {"label":"← Neuere Beiträge"} /-->
<!-- wp:query-pagination-numbers /-->
<!-- wp:query-pagination-next {"label":"Ältere Beiträge →"} /-->
<!-- /wp:query-pagination -->
</div><!-- /wp:query -->
</section><!-- /wp:group -->
