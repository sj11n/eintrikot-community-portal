<?php
/**
 * Stories (public posts): fixed categories, category filter, placeholder for posts without an image.
 * The layout itself (cards, story page) lives in the theme templates and in the Stories page.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

/** The five story categories in display order: slug => [label, tone]. */
function story_categories() {
    return [
        'menschen' => ['Menschen', 'pink'],
        'teams' => ['Teams', 'green'],
        'treffen' => ['Treffen', 'gold'],
        'verein' => ['Verein', 'blue'],
        'hockeywelt' => ['Hockeywelt', 'mix']
    ];
}

/** Category chips above the story list. One click per category, "Alle" resets. */
add_shortcode('eintrikot_stories_filter', function () {
    $active = directory_param('rubrik');
    $base = get_permalink() ?: home_url('/');
    $chips =
        '<a class="chip' .
        ($active === '' ? ' on' : '') .
        '" href="' .
        esc_url($base) .
        '"' .
        ($active === '' ? ' aria-current="true"' : '') .
        '>Alle</a>';
    foreach (story_categories() as $slug => [$label]) {
        $term = get_term_by('slug', $slug, 'category');
        if (!$term || (int) $term->count < 1) {
            continue;
        }
        $on = $active === $slug;
        $chips .=
            '<a class="chip' .
            ($on ? ' on' : '') .
            '" href="' .
            esc_url(add_query_arg('rubrik', $slug, $base)) .
            '"' .
            ($on ? ' aria-current="true"' : '') .
            '>' .
            esc_html($label) .
            '</a>';
    }
    return '<nav class="story-filter" aria-label="Rubriken">' . $chips . '</nav>';
});

/**
 * Story list filtered by ?rubrik=; "Mehr Stories" leaves out the story that is open. The blocks that build the
 * query (post template, paging, "no results") carry the class story-list or story-more, which is read here.
 */
add_filter(
    'query_loop_block_query_vars',
    function ($vars, $block) {
        $class = (string) ($block->parsed_block['attrs']['className'] ?? '');
        if (str_contains($class, 'story-list')) {
            $slug = directory_param('rubrik');
            if ($slug !== '' && isset(story_categories()[$slug])) {
                $vars['category_name'] = $slug;
            }
        }
        if (str_contains($class, 'story-more')) {
            $vars['post__not_in'] = [get_queried_object_id()];
        }
        return $vars;
    },
    10,
    2
);

/** Tone of a story from its first known category; "mix" is the brand gradient. */
function story_tone($post_id) {
    $known = story_categories();
    foreach (get_the_category((int) $post_id) as $term) {
        if (isset($known[$term->slug])) {
            return $known[$term->slug][1];
        }
    }
    return 'mix';
}

/** No featured image: a brand surface in the tone of the category instead of an empty gap. */
add_filter(
    'render_block_core/post-featured-image',
    function ($html, $parsed, $block) {
        if ($html !== '' || is_admin()) {
            return $html;
        }
        $id = (int) ($block->context['postId'] ?? 0);
        if (!$id) {
            return $html;
        }
        return '<figure class="wp-block-post-featured-image et-story-noimg tone-' .
            esc_attr(story_tone($id)) .
            '" aria-hidden="true"></figure>';
    },
    10,
    3
);

/** The archive part of the Stories page (filter, cards, paging). Same markup as the theme pattern. */
function stories_archive_markup() {
    return '<!-- wp:group {"tagName":"section","className":"section news-archive","layout":{"type":"default"}} --><section class="wp-block-group section news-archive"><!-- wp:shortcode -->[eintrikot_stories_filter]<!-- /wp:shortcode -->
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
</section><!-- /wp:group -->';
}
