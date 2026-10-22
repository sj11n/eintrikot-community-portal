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

/** Head picture settings of a story: own picture (attachment id) and whether to show none at all. */
function story_head_state($post_id) {
    $id = (int) get_post_meta($post_id, '_et_head_image', true);
    return [
        'hide' => get_post_meta($post_id, '_et_head_hide', true) === '1',
        'id' => $id && wp_attachment_is_image($id) ? $id : 0
    ];
}

/** "is-wide" for large, wide pictures (banner across the page), else "is-column" (in the text column, stays sharp). */
function story_head_class($attachment_id) {
    $meta = wp_get_attachment_metadata((int) $attachment_id);
    $w = (int) ($meta['width'] ?? 0);
    $h = (int) ($meta['height'] ?? 0);
    return $w >= 1400 && $h > 0 && $w / $h >= 1.5 ? 'is-wide' : 'is-column';
}

/**
 * Picture blocks of the stories. On the story page (class story-banner) the head picture is the own picture of
 * the story, else the featured image; the checkbox can switch it off. In lists the featured image is used. Without
 * any picture a brand surface in the tone of the category stands in instead of an empty gap.
 */
add_filter(
    'render_block_core/post-featured-image',
    function ($html, $parsed, $block) {
        $post_id = (int) ($block->context['postId'] ?? 0);
        if (is_admin() || !$post_id) {
            return $html;
        }
        $head = str_contains((string) ($parsed['attrs']['className'] ?? ''), 'story-banner');
        if ($head) {
            $state = story_head_state($post_id);
            if ($state['hide']) {
                return '';
            }
            if ($state['id']) {
                $alt = (string) get_post_meta($state['id'], '_wp_attachment_image_alt', true);
                $html =
                    '<figure class="wp-block-post-featured-image story-banner">' .
                    wp_get_attachment_image($state['id'], 'full', false, [
                        'alt' => $alt !== '' ? $alt : get_the_title($post_id),
                        'loading' => 'eager'
                    ]) .
                    '</figure>';
            }
            $attachment = $state['id'] ?: (int) get_post_thumbnail_id($post_id);
            if ($html !== '') {
                return preg_replace('/class="/', 'class="' . story_head_class($attachment) . ' ', $html, 1);
            }
        }
        if ($html !== '') {
            return $html;
        }
        return '<figure class="wp-block-post-featured-image et-story-noimg' .
            ($head ? ' story-banner is-wide' : '') .
            ' tone-' .
            esc_attr(story_tone($post_id)) .
            '" aria-hidden="true"></figure>';
    },
    10,
    3
);

/** Box "Kopfbild der Story" on the edit screen of a post: own head picture and the switch to show none. */
add_action('add_meta_boxes_post', function () {
    add_meta_box(
        'et_story_head',
        'Kopfbild der Story',
        function ($post) {
            wp_nonce_field('et_story_head', 'et_story_head_nonce');
            $state = story_head_state($post->ID);
            echo '<div data-et-head><p class="description">Optional. Steht oben auf der Beitragsseite. Ohne Kopfbild steht dort das Beitragsbild. Die Kachel auf der Stories-Seite zeigt immer das Beitragsbild.</p>' .
                '<input type="hidden" name="et_head_image" value="' .
                esc_attr((string) $state['id']) .
                '">' .
                '<div data-et-head-preview>' .
                ($state['id']
                    ? wp_get_attachment_image($state['id'], 'medium', false, [
                        'style' => 'max-width:100%;height:auto'
                    ])
                    : '') .
                '</div>' .
                '<p><button type="button" class="button" data-et-head-pick>Kopfbild wählen</button> ' .
                '<button type="button" class="button-link button-link-delete" data-et-head-remove' .
                ($state['id'] ? '' : ' hidden') .
                '>Entfernen</button></p>' .
                '<p class="description">Am besten breit: 2400 × 900 Pixel (Motiv in der Mitte).</p>' .
                '<p><label><input type="checkbox" name="et_head_hide" value="1"' .
                checked($state['hide'], true, false) .
                '> Oben kein Bild zeigen</label></p></div>';
        },
        'post',
        'side'
    );
});
add_action('admin_enqueue_scripts', function ($hook) {
    if (in_array($hook, ['post.php', 'post-new.php'], true) && get_post_type() === 'post') {
        wp_enqueue_media();
        wp_enqueue_script(
            'eintrikot-story-head',
            plugins_url('stories-head.js', __FILE__),
            [],
            asset_version('stories-head.js'),
            true
        );
    }
});
add_action('save_post_post', function ($id) {
    if (
        wp_is_post_revision($id) ||
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
        !current_user_can('edit_post', $id) ||
        empty($_POST['et_story_head_nonce'])
    ) {
        return;
    }
    $nonce = $_POST['et_story_head_nonce']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- this is the nonce itself, checked on the next line
    if (!is_string($nonce) || !wp_verify_nonce($nonce, 'et_story_head')) {
        return;
    }
    $attachment = absint(post_text('et_head_image', '', 12));
    if ($attachment && wp_attachment_is_image($attachment)) {
        update_post_meta($id, '_et_head_image', $attachment);
    } else {
        delete_post_meta($id, '_et_head_image');
    }
    if (isset($_POST['et_head_hide'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is verified above
        update_post_meta($id, '_et_head_hide', '1');
    } else {
        delete_post_meta($id, '_et_head_hide');
    }
});

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
