<?php

class Storychief_WPML
{

    private static $initiated = false;

    public static function init()
    {
        if (!self::$initiated) {
            self::$initiated = true;

            remove_action('storychief_save_categories_action', '\Storychief\Mapping\saveCategories');
            remove_action('storychief_save_tags_action', '\Storychief\Mapping\saveTags');

            add_action('storychief_before_publish_action', ['Storychief_WPML', 'setLocale'], 1);
            add_action('storychief_after_publish_action', ['Storychief_WPML', 'linkTranslations'], 1);

            add_action('storychief_save_categories_action', ['Storychief_WPML', 'saveCategories'], 1);
            add_action('storychief_save_tags_action', ['Storychief_WPML', 'saveTags'], 1);

            add_filter('upload_dir', ['Storychief_WPML', 'setUploadDir'], 999);
        }
    }

    public static function setUploadDir($upload_dir)
    {
        // WPML adds a trailingslash to the end of the upload dir.
        // This prevents attachment_url_to_postid from finding an attachment. (ex /upload/2020/05//...)
        // This action removes the trailingslash.
        // see: https://wpml.org/errata/changes-in-the-way-wpml-handles-the-trailing-slashes-in-url-conversion/
        $upload_dir['baseurl'] = untrailingslashit($upload_dir['baseurl']);
        $upload_dir['url'] = untrailingslashit($upload_dir['url']);
        return $upload_dir;
    }

    public static function setLocale($payload)
    {
        global $sitepress;
        $language = isset($payload['language']) ? $payload['language'] : $sitepress->get_default_language();
        $sitepress->switch_lang($language);
    }

    public static function linkTranslations($payload)
    {
        global $sitepress;
        $post_ID = $payload['external_id'];
        $post_language = $payload['language'];
        $src_ID = isset($payload['source']['data']['external_id']) ? $payload['source']['data']['external_id'] : null;

        // Translate Post
        if ($src_ID && $post_language && $sitepress) {
            $src_trid = $sitepress->get_element_trid($src_ID);

            $post_type = get_post_type($post_ID);

            $sitepress->set_element_language_details($post_ID, 'post_' . $post_type,
                                                     $src_trid, $post_language);
        }
    }

    public static function saveCategories($payload)
    {
        self::setLocale($payload);
        if (isset($payload['categories']['data'])) {
            $categories = self::mapTerms($payload['categories']['data'], 'category', $payload, \Storychief\Settings\get_sc_option('category_create'));
            wp_set_post_categories($payload['external_id'], $categories, false);
        }
    }

    public static function saveTags($payload)
    {
        self::setLocale($payload);
        if (isset($payload['tags']['data'])) {
            $tags = self::mapTerms($payload['tags']['data'], 'post_tag', $payload, \Storychief\Settings\get_sc_option('tag_create'));
            wp_set_post_tags($payload['external_id'], $tags, false);
        }
    }

    private static function mapTerms($termsPayload, $taxonomy, $payload, $createIfMissing = false)
    {
        $termIds = [];
        $language = $payload['language'];
        $sourceLang = isset($payload['source']['data']['language']) ? $payload['source']['data']['language'] : null;

        foreach ($termsPayload as $termPayload) {
            $termId = self::findTermLocalized($termPayload['name'], $language, $taxonomy);
            if ($termId) {
                $termIds[] = $termId;
                continue;
            }

            // Check if the term exists in the source language
            if($sourceLang) {
                $sourceTermId = self::findTermLocalized($termPayload['name'], $sourceLang, $taxonomy);
                $termId = apply_filters( 'wpml_object_id', $sourceTermId, $taxonomy, false, $language );
                if ($termId) {
                    $termIds[] = $termId;
                    continue;
                }
            }

            // Hailmary lookup the term in any language
            $sourceTermId = self::findTermLocalized($termPayload['name'], null, $taxonomy);
            $termId = apply_filters( 'wpml_object_id', $sourceTermId, $taxonomy, false, $language );

            if ($termId) {
                $termIds[] = $termId;
                continue;
            }

            if($createIfMissing) {
                if (!function_exists('wp_insert_category')) {
                    require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
                }
                $termId = wp_insert_category(
                  [
                    'cat_name'          => $termPayload['name'],
                    'category_nicename' => $termPayload['name'] . ' ' . $language,
                  ]
                );
                $termIds[] = $termId;
            }
        }

        return $termIds;
    }

    private static function findTermLocalized($name, $lang, $taxonomy)
    {
        global $sitepress;
        $current_lang = $sitepress->get_current_language();

        if($current_lang !== $lang && !is_null($lang)){
            $sitepress->switch_lang($lang);
        }

        if(is_null($lang)){
            // remove WPML term filters to search language wide
            remove_filter('get_terms_args', array($sitepress, 'get_terms_args_filter'));
            remove_filter('get_term', array($sitepress,'get_term_adjust_id'));
            remove_filter('terms_clauses', array($sitepress,'terms_clauses'));
        }

        $args = [
          'get'                    => 'all',
          'name'                   => $name,
          'number'                 => 0,
          'taxonomy'               => $taxonomy,
          'update_term_meta_cache' => false,
          'orderby'                => 'none',
          'suppress_filter'        => true,
        ];
        $terms = get_terms($args);

        if(is_null($lang)){
            // re-apply WPML term filters to search language wide
            add_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ), 10, 3 );
            add_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ), 1, 1 );
            add_filter( 'get_terms_args', array( $sitepress, 'get_terms_args_filter' ), 10, 2 );
        }

        if($current_lang !== $lang && !is_null($lang)){
            $sitepress->switch_lang($current_lang);
        }

        if (is_wp_error($terms) || empty($terms)) {
            return false;
        }

        $term = array_shift($terms);

        return $term->term_id;
    }

}
