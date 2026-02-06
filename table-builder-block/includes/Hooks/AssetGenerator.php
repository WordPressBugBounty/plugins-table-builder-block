<?php

namespace TableBuilder\Hooks;

defined('ABSPATH') || exit;

class AssetGenerator
{

    use \TableBuilder\Traits\Singleton;

    /**
     * Defining css
     */
    public $css = '';

    /**
     * Defining fonts
     */
    protected $fonts = array();

    /**
     * AssetGenerator class constructor.
     * private for singleton
     *
     * @return void
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('save_post', array($this, 'save_post_hook'), 10, 3);
        add_filter('render_block_data', array($this, 'set_blocks_css'), 10);
        add_filter('wp_resource_hints', array($this, 'fonts_resource_hints'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 10);
        add_action('enqueue_block_assets', array($this, 'block_assets'), 10);
    }

    /**
     * Filters an array of blocks and returns only those where the block name contains 'tablebuilder'.
     *
     * @param array $blocks An array of blocks. Each block is an associative array that must contain a 'blockName' key. Default is an empty array.
     * @return array Returns an array of blocks where the block name contains 'tablebuilder'.
     */
    public function filter_blocks($blocks = array())
    {
        $filtered_blocks = [];

        foreach ($blocks as $block) {
            if (isset($block['blockName']) && strpos($block['blockName'], 'tablebuilder') !== false) {
                $filtered_blocks[] = $block;
            }

            if (!empty($block['innerBlocks'])) {
                $filtered_blocks = array_merge($filtered_blocks, $this->filter_blocks($block['innerBlocks']));
            }
        }

        return $filtered_blocks;
    }

    /**
     * Minify CSS by condensing white spaces and removing comments.
     *
     * @param string $css The input CSS.
     * @return string Minified CSS.
     */
    public function minimize_css($css)
    {
        if (trim($css) === '') {
            return $css;
        }

        return preg_replace(
            array(
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
                '#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
                '#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
                '#(background-position):0(?=[;\}])#si',
                '#(?<=[\s:,\-])0+\.(\d+)#s',
                '#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
                '#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
                '#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
                '#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s',
            ),
            array(
                '$1',
                '$1$2$3$4$5$6$7',
                '$1',
                ':0',
                '$1:0 0',
                '.$1',
                '$1$3',
                '$1$2$4$5',
                '$1$2$3',
                '$1:0',
                '$1$2',
            ),
            $css
        );
    }

    /**
     * Fires once a post has been saved.
     *
     * @param int   $post_id The ID of the post.
     * @param WP_Post $post The post object.
     * @param bool  $update Whether this is an existing post being updated.
     * @return void
     */
    public function save_post_hook($post_id, $post, $update)
    {
        // Bail out if is draft, revision, or autosave
        if ('auto-draft' === $post->post_status || wp_is_post_revision($post_id) || defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Skip if this is an autosave or update
        if (!$update) {
            return;
        }

        $post = get_post($post_id);
        $parse_blocks = $this->filter_blocks(parse_blocks($post->post_content));

        if ($parse_blocks) {
            $fse = in_array($post->post_type, ['wp_template_part', 'wp_template']);
            if ($fse) {
                $this->set_fonts(null, $this->generate_fse_assets(), true);
            } else {
                $this->set_fonts($post_id, $parse_blocks);
            }
        }
    }

    /**
     * Generate assets for templates.
     *
     * @return array $filtered_blocks The filtered blocks for FSE templates.
     */
    protected function generate_fse_assets()
    {
        $args = [
            'post_type' => ['wp_template_part', 'wp_template'],
            'posts_per_page' => -1,
        ];

        $posts = get_posts($args);
        $merged_blocks = [];

        foreach ($posts as $post) {
            $merged_blocks = array_merge($merged_blocks, parse_blocks($post->post_content));
        }

        return $this->filter_blocks($merged_blocks);
    }

    /**
     * Set the fonts for a given post or Full Site Editing (FSE) template.
     *
     * @param int   $post_id The ID of the post or FSE template.
     * @param array $blocks An array of blocks.
     * @param bool  $fse    Whether this is an FSE template.
     * @return void
     */
    protected function set_fonts($post_id, $blocks, $fse = false)
    {
        $fonts = [];

        foreach ($blocks as $block) {
            if (isset($block['attrs'])) {
                $typographies = array_filter($block['attrs'], function ($key) {
                    return str_contains(strtolower($key), 'typography');
                }, ARRAY_FILTER_USE_KEY);

                if (!empty($typographies)) {
                    foreach ($typographies as $typography) {
                        $font_weight = !empty($typography['fontWeight']['value']) ? $typography['fontWeight']['value'] : 400;
                        if (!empty($typography['fontFamily']['value'])) {
                            $fonts[$typography['fontFamily']['value']][] = $font_weight;
                        }
                    }
                }
            }
        }

        // Update fonts
        if (!empty($fonts)) {
            if ($fse) {
                update_option('table_builder_fse_fonts', $fonts);
            } else {
                update_post_meta($post_id, 'table_builder_posts_fonts', $fonts);
            }
        } else {
            if ($fse) {
                delete_option('table_builder_fse_fonts');
            } else {
                delete_post_meta($post_id, 'table_builder_posts_fonts');
            }
        }
    }

    /**
     * Combine block assets (CSS and JS) based on the used blocks.
     *
     * @param array $parsed_block The parsed block.
     * @return string Combined CSS content.
     */
    protected function combine_blocks_assets($parsed_block = [])
    {
        $blocks_css = [];

        if (isset($parsed_block['blockName']) && strpos($parsed_block['blockName'], 'tablebuilder') !== false) {
            if (isset($parsed_block['attrs']['blocksCSS'])) {
                foreach ($parsed_block['attrs']['blocksCSS'] as $device => $css) {
                    if (!isset($blocks_css[$device])) {
                        $blocks_css[$device] = '';
                    }

                    if (is_string($css)) {
                        $blocks_css[$device] .= $css;
                    }
                }
            }

            // block typography
            $this->set_typography($parsed_block);

            // block common style
            if (isset($parsed_block['attrs']['commonStyle'])) {
                foreach ($parsed_block['attrs']['commonStyle'] as $device => $css) {
                    if (!isset($blocks_css[$device])) {
                        $blocks_css[$device] = '';
                    }

                    $blocks_css[$device] .= $css;
                }
            }
        }

        // Concatenate CSS content into a single string
        $css_content = '';
        $is_custom_styles_added = false;
        $device_list = \TableBuilder\Helpers\Utils::get_device_list();

        if (!empty($blocks_css)) {
            foreach ($device_list as $device) {
                foreach ($blocks_css as $key => $block) {
                    if (!empty($block) && trim($block) !== '') {
                        $direction = $device['direction'] ?? 'max';
                        $width = $device['value'] ?? '';
                        $device_key = strtolower($device['slug'] ?? '');

                        if ('base' === $device['value'] && 'desktop' === $key) {
                            $css_content .= $block;
                        } elseif (!empty($direction) && !empty($width) && $device_key === $key) {
                            $css_content .= "@media ({$direction}-width: {$width}px) {" . trim($block) . '}';
                        }

                        if ('customStyles' === $key && !$is_custom_styles_added) {
                            $is_custom_styles_added = true;
                            $css_content .= $block;
                        }
                    }
                }
            }
        }

        return $css_content;
    }

    protected function set_typography($parsed_block)
    {
        if (isset($parsed_block['attrs'])) {
            $typographies = array_filter(
                $parsed_block['attrs'],
                function ($key) {
                    $key = strtolower($key);
                    return str_contains($key, 'typography') || str_contains($key, 'typo');
                },
                ARRAY_FILTER_USE_KEY
            );

            if (! empty($typographies)) {
                foreach ($typographies as $typography) {
                    $font_weight = ! empty($typography['fontWeight']['value']) ? $typography['fontWeight']['value'] : 400;
                    if (! empty($typography['fontFamily']['value'])) {
                        $this->fonts[$typography['fontFamily']['value']][] = $font_weight;
                    }
                }
            }
        }
    }

    /**
     * Generate Google Fonts URL.
     *
     * @return string|bool Google Fonts URL or false if no fonts.
     */
    protected function generate_fonts_url()
    {
        if (!empty($this->fonts)) {
            $font_families = [];
            $font_url = 'https://fonts.googleapis.com/css2?family=';

            // Remove duplicates and sort the fonts
            $all_fonts = array_map(function ($arr) {
                $arr = array_unique($arr);
                sort($arr);
                return $arr;
            }, $this->fonts);

            foreach ($all_fonts as $font => $weights) {
                $weights = array_map(function ($weight) {
                    $invalid_list = ['normal', 'inherit', 'initial'];
                    return in_array($weight, $invalid_list) ? '400' : $weight;
                }, $weights);
                sort($weights);
                $font_families[] = str_replace(' ', '+', $font) . ':wght@' . implode(';', array_unique($weights));
            }

            $font_url .= implode('&family=', $font_families);
            $font_url .= '&display=swap';

            return $font_url;
        }

        return false;
    }

    /**
     * Sets the CSS for the blocks.
     *
     * @param array $parsed_block The parsed block data.
     * @return array The modified parsed block data.
     */
    public function set_blocks_css($parsed_block)
    {
        $css_content = $this->combine_blocks_assets($parsed_block);
        if (!empty($css_content)) {
            $this->css .= $css_content;
        }
        return $parsed_block;
    }

    /**
     * Add preconnect for Google Fonts.
     *
     * @param array  $urls URLs to print for resource hints.
     * @param string $relation_type The relation type the URLs are printed.
     * @return array
     */
    public function fonts_resource_hints($urls, $relation_type)
    {
        if (wp_style_is('table-builder-google-fonts', 'queue') && 'preconnect' === $relation_type) {
            $urls[] = [
                'href' => 'https://fonts.gstatic.com',
                'crossorigin',
            ];
        }

        return $urls;
    }

    /**
     * Enqueues the Google Fonts stylesheet if available.
     * Enqueues inline styles for the TableBuilder frontend.
     */
    public function enqueue_scripts()
    {
        global $post;

        if (!wp_is_block_theme() && !empty($post->post_content)) {
            do_blocks($post->post_content);
        }

        $fonts_url = $this->generate_fonts_url();
        if ($fonts_url) {
            wp_enqueue_style('table-builder-google-fonts', $fonts_url, false, null);
        }

        if ($this->css) {
            wp_add_inline_style('table-builder-style', $this->css);
        }
    }

    /**
     * Enqueues block assets (CSS & JS).
     *
     * @return void
     */
    public function block_assets()
    {
        wp_enqueue_style('table-builder-style', get_stylesheet_uri());
    }
}
