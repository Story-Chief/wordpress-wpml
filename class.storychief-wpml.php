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

    public static function saveCategories($story)
    {
        if (isset($story['categories']['data'])) {
            $categories = self::mapTerms($story['categories']['data'], 'category', $story, \Storychief\Settings\get_sc_option('category_create'));
            wp_set_post_categories($story['external_id'], $categories, false);
        }
    }

    public static function saveTags($story)
    {
        if (isset($story['tags']['data'])) {
            $tags = self::mapTerms($story['tags']['data'], 'post_tag', $story, \Storychief\Settings\get_sc_option('tag_create'));
            wp_set_post_tags($story['external_id'], $tags, false);
        }
    }

    private static function mapTerms($termsPayload, $taxonomy, $payload, $createIfMissing = false)
    {
        global $sitepress;

        $termIds = [];
        $sourceLang = isset($payload['source']['data']['language']) ? $payload['source']['data']['language'] : null;

        foreach ($termsPayload as $termPayload) {

            $termId = self::findTermLocalized($termPayload['name'], $sitepress->get_current_language(), $taxonomy);
            if ($termId) {
                $termIds[] = $termId;
                continue;
            }


            if($sourceLang) {
                $sourceTermId = self::findTermLocalized($termPayload['name'], $sourceLang, $taxonomy);
                $termId = apply_filters( 'wpml_object_id', $sourceTermId, $taxonomy, false, $sitepress->get_current_language()  );
                if ($termId) {
                    $termIds[] = $termId;
                    continue;
                }
            }

            if($createIfMissing) {
                if (!function_exists('wp_insert_category')) {
                    require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
                }
                $termId = wp_insert_category(
                  [
                    'cat_name'          => $termPayload['name'],
                    'category_nicename' => $termPayload['name'] . ' ' . $sitepress->get_current_language(),
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


        if($current_lang !== $lang){
            $sitepress->switch_lang($lang);
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
        if (is_wp_error($terms) || empty($terms)) {
            return false;
        }
        $term = array_shift($terms);

        if($current_lang !== $lang){
            $sitepress->switch_lang($current_lang);
        }

        return $term->term_id;
    }

}
