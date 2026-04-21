<?php

namespace TableBuilder\Admin\Api;

defined('ABSPATH') || exit;

class ChangelogData {

    private const ROUTE = 'changelog';

    private const SOURCES = [
        'Free' => [
            'file'  => TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'readme.txt',
            'regex' => '/= (?:TableKit|Table Builder Block)\s*:?\s*([^(=]+?)\s*\(([^)]+)\)\s*=\R(.*?)(?=\R= (?:TableKit|Table Builder Block)\s*:?\s*|\z)/s',
        ],
    ];

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('tablebuilder/v1', self::ROUTE, [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_changelog'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function get_changelog(\WP_REST_Request $request): \WP_REST_Response {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new \WP_REST_Response([], 403);
        }

        $items = array_merge(
            $this->parse_source('Free', self::SOURCES['Free']),
            $this->parse_source('Pro', $this->get_pro_source()),
        );

        usort($items, fn($a, $b) => strtotime($b['publish_date']) <=> strtotime($a['publish_date']));

        return new \WP_REST_Response($items, 200);
    }

    private function get_pro_source(): array {
        $dir = defined('TABLE_BUILDER_BLOCK_PRO_PLUGIN_DIR')
            ? TABLE_BUILDER_BLOCK_PRO_PLUGIN_DIR
            : dirname(rtrim(TABLE_BUILDER_BLOCK_PLUGIN_DIR, '/\\')) . '/table-builder-block-pro/';

        return [
            'file'  => $dir . 'changelog.txt',
            'regex' => '/= Version:\s*([^(=]+?)\s*\(([^)]+)\)\s*=\R(.*?)(?=\R= Version:|\z)/s',
        ];
    }

    private function parse_source(string $type, array $source): array {
        if (!file_exists($source['file'])) {
            return [];
        }

        $content = file_get_contents($source['file']);

        if (!$content || !preg_match('/== Changelog ==\R(.*)$/s', $content, $m)) {
            return [];
        }

        preg_match_all($source['regex'], trim($m[1]), $entries, PREG_SET_ORDER);

        return array_filter(array_map(
            fn($entry) => $this->build_item($entry, $type),
            $entries
        ));
    }

    private function build_item(array $entry, string $type): ?array {
        $list_items = array_filter(
            array_map(fn($line) => $this->parse_list_line($line), preg_split('/\R/', trim($entry[3])))
        );

        if (empty($list_items)) {
            return null;
        }

        return [
            'title'        => trim($entry[1]),
            'type'         => $type,
            'publish_date' => $this->format_date(trim($entry[2])),
            'content'      => '<ul>' . implode('', $list_items) . '</ul>',
        ];
    }

    private function parse_list_line(string $line): string {
        $line = trim($line);

        if (!str_starts_with($line, '*')) {
            return '';
        }

        $text = trim(ltrim($line, '* '));

        return $text !== '' ? '<li>' . esc_html($text) . '</li>' : '';
    }

    private function format_date(string $date): string {
        $timestamp = strtotime($date);
        return $timestamp ? gmdate('F j, Y', $timestamp) : $date;
    }
}