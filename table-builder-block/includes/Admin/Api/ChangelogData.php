<?php
/**
 * REST endpoint that parses and serves the Free + Pro changelog
 *
 * @package TableKit
 */

namespace TableBuilder\Admin\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and serves the tablebuilder/v1/changelog REST route.
 */
class ChangelogData {

	private const ROUTE = 'changelog';

	private const SOURCES = array(
		'Free' => array(
			'file'  => TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'readme.txt',
			'regex' => '/= (?:TableKit|Table Builder Block)\s*:?\s*([^(=]+?)\s*\(([^)]+)\)\s*=\R(.*?)(?=\R= (?:TableKit|Table Builder Block)\s*:?\s*|\z)/s',
		),
	);

	/**
	 * Hooks changelog REST route registration into WordPress.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the tablebuilder/v1/changelog REST route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'tablebuilder/v1',
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_changelog' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * REST callback: parse and return the combined Free + Pro changelog entries,
	 * newest first.
	 *
	 * @param \WP_REST_Request $request Request; only its nonce header is used.
	 * @return \WP_REST_Response
	 */
	public function get_changelog( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new \WP_REST_Response( array(), 403 );
		}

		$items = array_merge(
			$this->parse_source( 'Free', self::SOURCES['Free'] ),
			$this->parse_source( 'Pro', $this->get_pro_source() ),
		);

		usort( $items, fn( $a, $b ) => strtotime( $b['publish_date'] ) <=> strtotime( $a['publish_date'] ) );

		return new \WP_REST_Response( $items, 200 );
	}

	/**
	 * Builds the file/regex source descriptor for the Pro add-on's changelog.txt.
	 *
	 * @return array{file:string,regex:string}
	 */
	private function get_pro_source(): array {
		$dir = defined( 'TABLE_BUILDER_BLOCK_PRO_PLUGIN_DIR' )
			? TABLE_BUILDER_BLOCK_PRO_PLUGIN_DIR
			: dirname( rtrim( TABLE_BUILDER_BLOCK_PLUGIN_DIR, '/\\' ) ) . '/table-builder-block-pro/';

		return array(
			'file'  => $dir . 'changelog.txt',
			'regex' => '/= Version:\s*([^(=]+?)\s*\(([^)]+)\)\s*=\R(.*?)(?=\R= Version:|\z)/s',
		);
	}

	/**
	 * Parses a "== Changelog ==" section out of a source file into changelog items.
	 *
	 * @param string $type   Source label, e.g. "Free" or "Pro".
	 * @param array  $source Source descriptor with "file" and "regex" keys.
	 * @return array Parsed changelog items (see build_item()), possibly empty.
	 */
	private function parse_source( string $type, array $source ): array {
		if ( ! file_exists( $source['file'] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local file bundled with the plugin (readme.txt / Pro changelog.txt), not a remote URL; wp_remote_get() doesn't apply here.
		$content = file_get_contents( $source['file'] );

		if ( ! $content || ! preg_match( '/== Changelog ==\R(.*)$/s', $content, $m ) ) {
			return array();
		}

		preg_match_all( $source['regex'], trim( $m[1] ), $entries, PREG_SET_ORDER );

		return array_filter(
			array_map(
				fn( $entry ) => $this->build_item( $entry, $type ),
				$entries
			)
		);
	}

	/**
	 * Builds a single changelog item from a regex-matched version-entry block.
	 *
	 * @param array  $entry Regex match groups: [1]=title, [2]=date, [3]=body lines.
	 * @param string $type  Source label, e.g. "Free" or "Pro".
	 * @return array{title:string,type:string,publish_date:string,content:string}|null
	 *               The parsed item, or null if it has no bullet-point content.
	 */
	private function build_item( array $entry, string $type ): ?array {
		$list_items = array_filter(
			array_map( fn( $line ) => $this->parse_list_line( $line ), preg_split( '/\R/', trim( $entry[3] ) ) )
		);

		if ( empty( $list_items ) ) {
			return null;
		}

		return array(
			'title'        => trim( $entry[1] ),
			'type'         => $type,
			'publish_date' => $this->format_date( trim( $entry[2] ) ),
			'content'      => '<ul>' . implode( '', $list_items ) . '</ul>',
		);
	}

	/**
	 * Converts one "* ..." changelog bullet line into a list item element.
	 *
	 * @param string $line Raw line from the changelog body.
	 * @return string The <li> markup, or an empty string if the line isn't a bullet.
	 */
	private function parse_list_line( string $line ): string {
		$line = trim( $line );

		if ( ! str_starts_with( $line, '*' ) ) {
			return '';
		}

		$text = trim( ltrim( $line, '* ' ) );

		return '' !== $text ? '<li>' . esc_html( $text ) . '</li>' : '';
	}

	/**
	 * Format a changelog date string into a human-readable "Month D, Year" form.
	 *
	 * @param string $date Raw date string as it appears in the changelog.
	 * @return string Formatted date, or the original string if it couldn't be parsed.
	 */
	private function format_date( string $date ): string {
		$timestamp = strtotime( $date );
		return $timestamp ? gmdate( 'F j, Y', $timestamp ) : $date;
	}
}
