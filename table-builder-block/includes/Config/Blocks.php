<?php

namespace TableBuilder\Config;

defined('ABSPATH') || exit;

use WP_Query;
use TableBuilder\Traits\Singleton;
use TableBuilder\Helpers\Utils;

class Blocks
{
    use Singleton;

    protected function __construct()
    {
        add_action('init', [$this, 'register_blocks']);
        add_action('block_categories_all', [$this, 'register_block_categories'], 10, 2);
        add_action('enqueue_block_assets', [$this, 'enqueue_block_assets']);
        add_action('enqueue_block_editor_assets', [$this, 'block_editor_assets'], 5);
        add_filter('render_block', [$this, 'save_block_element'], 10, 3);
    }

    /**
     * Register custom blocks for TableBuilder.
     */
    public function register_blocks()
    {
        $blocks_list = \TableBuilder\Config\BlockList::get_block_list();

        if (! empty($blocks_list)) {
            foreach ($blocks_list as $key => $block) {
                $package = isset($block['package']) ? $block['package'] : '';
                $blocks_dir = '';
                $plugin_dir = '';
                $plugin_slug = '';

                if (!empty($package) &&  $package === 'free') {
                    $plugin_dir = TABLE_BUILDER_BLOCK_PLUGIN_DIR;
                    $blocks_dir = TABLE_BUILDER_BLOCK_DIR . $key;
                    $plugin_slug = 'table-builder-block';
                }

                if (!empty($package) &&  $package === 'pro' && defined('TABLE_BUILDER_BLOCK_PRO_PLUGIN_DIR')) {
                    $plugin_dir = TABLE_BUILDER_BLOCK_PRO_PLUGIN_DIR;
                    $blocks_dir = $plugin_dir . '/build/blocks/' . $key;
                    $plugin_slug = 'table-builder-block-pro';
                }

                if (file_exists($blocks_dir)) {
                    register_block_type($blocks_dir);
                }
            }
        }
    }

    /**
     * Register custom block categories for TableBuilder.
     */
    public function register_block_categories($categories, $post)
    {
        return array_merge([
            [
                'slug'  => 'tablebuilder',
                'title' => __('Table Builder', 'tablebuilder'),
            ],
        ], $categories);
    }

    /**
     * Enqueue global assets for both block editor and frontend.
     */
    public function enqueue_block_assets()
    {
        wp_enqueue_style(
            'table-builder-block-components',
            TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/tablebuilder/components.css',
            [],
            TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
            'all'
        );


        // wp_enqueue_style(
        //     'table-builder-block-global-style',
        //     TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/tablebuilder/style-global.css',
        //     [],
        //     TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
        //     'all'
        // );
    }

    /**
     * Enqueue block editor assets.
     */
    public function block_editor_assets()
    {
        $blocks_list = \TableBuilder\Config\BlockList::get_block_list();

        foreach ($blocks_list as $key => $block) {
            $block_dir = TABLE_BUILDER_BLOCK_PLUGIN_DIR . "blocks/{$key}";
            $plugin_url = TABLE_BUILDER_BLOCK_PLUGIN_URL . "build/blocks/{$key}";

            if (!file_exists("{$block_dir}/index.asset.php")) {
                continue;
            }

            $editor_asset = include "{$block_dir}/index.asset.php";

            wp_enqueue_script(
                "{$key}-editor",
                "{$plugin_url}/index.js",
                $editor_asset['dependencies'] ?? [],
                $editor_asset['version'] ?? TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
                true
            );

            wp_enqueue_style(
                "{$key}-editor-style",
                "{$plugin_url}/index.css",
                [],
                TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
                'all'
            );
        }
    }

    /**
     * Save block element markup.
     */
    public function save_block_element($block_content, $parsed_block, $instance)
    {
        if (!empty($block_content) && Utils::is_table_builder_block($block_content, $parsed_block, 'blockClass')) {
            $block_processor = new \WP_HTML_Tag_Processor($block_content);
            $block_processor->next_tag();

            $attributes = [
                'id'         => "block-" . ($parsed_block['attrs']['blockID'] ?? 'default'),
                'data-block' => $parsed_block['blockName'] ?? '',
            ];

            foreach ($attributes as $attr => $value) {
                if (empty($block_processor->get_attribute($attr))) {
                    $block_processor->set_attribute($attr, $value);
                }
            }

            if (!empty($parsed_block['attrs']['blockClass'])) {
                $block_processor->add_class($parsed_block['attrs']['blockClass']);
            }

            $block_processor->add_class('table-builder-block');

            $before_markup = apply_filters('tablebuilder/save_element_markup_before', "", $parsed_block);
            $after_markup = apply_filters('tablebuilder/save_element_markup_after', "", $parsed_block);
            $block_content = apply_filters('tablebuilder_save_element_markup', $block_processor, $parsed_block, $instance);

            if (method_exists($block_content, 'get_updated_html')) {
                $block_content = $block_content->get_updated_html();
            }

            return $before_markup . $block_content . $after_markup;
        }

        return $block_content;
    }
}
