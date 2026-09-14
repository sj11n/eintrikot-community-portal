<?php
/**
 * Title: EINTRIKOT Header
 * Slug: eintrikot/mvp-header
 * Inserter: no
 */
?>
<!-- wp:group {"className":"header","layout":{"type":"flex","justifyContent":"space-between"}} --><div class="wp-block-group header"><!-- wp:html --><a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="EINTRIKOT Startseite"><img class="logo" src="<?php echo esc_url(get_theme_file_uri('assets/logo.svg')); ?>" alt="EINTRIKOT"></a><!-- /wp:html --><!-- wp:navigation {"className":"mvp-navigation","overlayMenu":"mobile"} --><!-- wp:navigation-link {"label": "Der Verein", "url": "/community-verein/", "kind": "custom"} /--><!-- wp:navigation-link {"label": "Engagement", "url": "/community-engagement/", "kind": "custom"} /--><!-- wp:navigation-link {"label": "News", "url": "/community-news/", "kind": "custom"} /--><!-- wp:navigation-link {"label": "Unterstützen", "url": "/community-unterstuetzen/", "kind": "custom"} /--><!-- wp:navigation-link {"className":"et-nav-join", "label": "Mitglied werden", "url": "/mitglied-werden/", "kind": "custom"} /--><!-- wp:navigation-link {"className":"et-nav-login", "label": "Anmelden", "url": "/community-portal/", "kind": "custom"} /--><!-- /wp:navigation --></div><!-- /wp:group -->