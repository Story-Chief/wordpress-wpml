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
            $sitepress->set_element_language_details($post_ID, 'post_post',
              $src_trid, $post_language);
        }
    }

    public static function saveCategories($story)
    {
        global $sitepress;
        if (isset($story['categories']['data'])) {
            $categories = [];
            foreach ($story['categories']['data'] as $category) {
                if (!$cat_ID = self::findTermLocalized($category['name'],
                  $sitepress->get_current_language(), 'category')) {
                    // try to find the category ID for cat with name X in language Y
                    // if it does not exist. create that sucker
                    if (!function_exists('wp_insert_category')) {
                        require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
                    }
                    $cat_ID = wp_insert_category([
                      'cat_name'          => $category['name'],
                      'category_nicename' => $category['name'] . ' ' . $sitepress->get_current_language(),
                    ]);
                }
                $categories[] = $cat_ID;
            }

            wp_set_post_terms($story['external_id'], $categories, 'category',
              $append = false);
        }
    }

    public static function saveTags($story)
    {
        global $sitepress;

        if (isset($story['tags']['data'])) {
            $tags = [];
            foreach ($story['tags']['data'] as $tag) {
                if (!$tag_ID = self::findTermLocalized($tag['name'],
                  $sitepress->get_current_language(), 'post_tag')) {
                    // try to find the tag ID for tag with name X in language Y
                    // if it does not exist. create that sucker

                    if (!function_exists('wp_insert_term')) {
                        require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
                    }
                    $tag = wp_insert_term($tag['name'], 'post_tag', [
                      'slug' => $tag['name'] . ' ' . $sitepress->get_current_language(),
                    ]);
                    $tag_ID = isset($tag['term_id']) ? $tag['term_id'] : null;
                }
                $tags[] = $tag_ID;
            }

            wp_set_post_terms($story['external_id'], $tags, 'post_tag',
              $append = false);
        }
    }

    private static function findTermLocalized($name, $lang, $taxonomy)
    {
        $args = [
          'get'                    => 'all',
          'name'                   => $name,
          'number'                 => 0,
          'taxonomy'               => $taxonomy,
          'update_term_meta_cache' => false,
          'orderby'                => 'none',
          'suppress_filter'        => true,
          'lang'                   => $lang,
        ];
        $terms = get_terms($args);
        if (is_wp_error($terms) || empty($terms)) {
            return false;
        }
        $term = array_shift($terms);

        return $term->term_id;
    }

}
