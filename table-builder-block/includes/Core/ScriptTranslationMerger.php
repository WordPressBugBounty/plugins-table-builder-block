<?php
/**
 * Merges per-plugin JS translation catalogs so multiple sources (WP-CLI/core,
 * Loco Translate, etc.) can contribute to the same script's translations
 *
 * @package TableKit
 */

namespace TableBuilder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Merges Jed-format JS translation files for the "table-builder-block" domain.
 */
final class ScriptTranslationMerger {

	use \TableBuilder\Traits\Singleton;

	/** Text domain this merger is responsible for. */
	private const DOMAIN = 'table-builder-block';

	/** How long a merged result is cached before being recomputed. */
	private const CACHE_TTL = DAY_IN_SECONDS;

	/** Prefix for the transient cache key. */
	private const CACHE_PREFIX = 'tbb_js_i18n_';

	/**
	 * Hooks the script-translation merge filter into WordPress.
	 */
	private function __construct() {
		add_filter( 'pre_load_script_translations', array( $this, 'maybe_merge' ), 10, 4 );
	}

	/*
	-----------------------------------------------------------------
	 * Filter callback
	 * ---------------------------------------------------------------
	 */

	/**
	 * Merges cached translation catalogs into core's script-translation loading,
	 * hooked to "pre_load_script_translations".
	 *
	 * @param string|false|null $translations JSON-encoded translations, or null/false if unresolved.
	 * @param string|false      $file         The path core was about to try. Unused: we merge by domain, not by file.
	 * @param string            $handle       Script handle.
	 * @param string            $domain       Text domain.
	 * @return string|false|null
	 */
	public function maybe_merge( $translations, $file, $handle, $domain ) {
		if ( null !== $translations || self::DOMAIN !== $domain ) {
			return $translations;
		}

		$locale = $this->current_locale();
		if ( null === $locale ) {
			return $translations;
		}

		$files = $this->find_translation_files( $locale );
		if ( empty( $files ) ) {
			return $translations;
		}

		$merged = $this->get_cached_merge( $locale, $files );

		return $merged ?? $translations;
	}

	/*
	-----------------------------------------------------------------
	 * Locale / file discovery
	 * ---------------------------------------------------------------
	 */

	/**
	 * Gets the current request's locale, or null if it's a source-language
	 * locale (en_*) with nothing to merge.
	 *
	 * @return string|null
	 */
	private function current_locale(): ?string {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		// Nothing to merge for the source language.
		if ( empty( $locale ) || 0 === strpos( $locale, 'en_' ) ) {
			return null;
		}

		return $locale;
	}

	/**
	 * Gets the plugin's languages directory (absolute path).
	 *
	 * @return string
	 */
	private function languages_dir(): string {
		return defined( 'TABLE_BUILDER_BLOCK_PLUGIN_DIR' )
			? TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'languages'
			: dirname( __DIR__, 2 ) . '/languages';
	}

	/**
	 * Finds every candidate translation JSON file for a locale.
	 *
	 * @param string $locale Locale to find candidate translation files for.
	 * @return string[] Absolute paths of every candidate JSON file for this locale.
	 */
	private function find_translation_files( string $locale ): array {
		$pattern = sprintf( '%s/%s-%s-*.json', $this->languages_dir(), self::DOMAIN, $locale );

		$files = glob( $pattern );

		return $files ? $files : array();
	}

	/*
	-----------------------------------------------------------------
	 * Caching
	 * ---------------------------------------------------------------
	 */

	/**
	 * Gets a cached merged translation catalog for a locale/file-set, computing
	 * and caching it (as a transient, keyed by a content fingerprint) if not already cached.
	 *
	 * @param string   $locale Locale being merged for.
	 * @param string[] $files  Candidate translation JSON files to merge.
	 * @return string|null The merged Jed-format JSON catalog, or null if there's nothing to merge.
	 */
	private function get_cached_merge( string $locale, array $files ): ?string {
		$cache_key = self::CACHE_PREFIX . md5( self::DOMAIN . '|' . $locale . '|' . $this->fingerprint( $files ) );
		$cached    = get_transient( $cache_key );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$merged = $this->merge( $files, $locale );
		if ( null === $merged ) {
			return null;
		}

		set_transient( $cache_key, $merged, self::CACHE_TTL );

		return $merged;
	}

	/**
	 * Cheap "version" signal for the cache key: changes automatically the
	 * moment any candidate file is added, edited, or removed.
	 *
	 * @param string[] $files Candidate files to fingerprint.
	 * @return string MD5 hash summarizing each file's path/mtime/size.
	 */
	private function fingerprint( array $files ): string {
		$parts = array_map( array( $this, 'file_signature' ), $files );
		sort( $parts );

		return md5( implode( '|', $parts ) );
	}

	/**
	 * Builds a "path:mtime:size" signature for one file, without relying on the
	 * error control operator to silence a missing-file warning.
	 *
	 * @param string $file Absolute file path.
	 * @return string The file's signature; mtime/size are 0 if the file no longer exists.
	 */
	private function file_signature( string $file ): string {
		if ( ! file_exists( $file ) ) {
			return $file . ':0:0';
		}

		return $file . ':' . filemtime( $file ) . ':' . filesize( $file );
	}

	/*
	-----------------------------------------------------------------
	 * Merging
	 * ---------------------------------------------------------------
	 */

	/**
	 * Merges multiple Jed-format translation files into a single catalog.
	 *
	 * @param string[] $files  Translation JSON files to merge.
	 * @param string   $locale Locale, used to build fallback meta if none of the files provide one.
	 * @return string|null The merged Jed-format JSON catalog, or null if no messages were found.
	 */
	private function merge( array $files, string $locale ): ?string {
		$messages = array();
		$meta     = null;

		foreach ( $files as $file ) {
			list($file_messages, $file_meta) = $this->read_jed_file( $file );
			$messages                        = array_merge( $messages, $file_messages );
			$meta                            = $meta ?? $file_meta;
		}

		if ( empty( $messages ) ) {
			return null;
		}

		$messages[''] = $meta ? $meta : $this->default_meta( $locale );

		return wp_json_encode(
			array(
				'translation-revision-date' => gmdate( 'Y-m-d H:i:s' ),
				'generator'                 => self::class,
				'domain'                    => 'messages',
				'locale_data'               => array(
					'messages' => $messages,
				),
			)
		);
	}

	/**
	 * Reads one Jed-format translation JSON file.
	 *
	 * WP-CLI / WordPress core always nest the catalog under the literal key
	 * "messages", regardless of the actual text domain -- but Loco Translate
	 * nests it under the *real* domain name instead (e.g. "table-builder-block").
	 * Both are valid Jed 1.x files, just disagreeing on that one key name, so
	 * we accept "messages" first and otherwise fall back to whatever single
	 * catalog is actually present.
	 *
	 * @param string $file Absolute path to the Jed-format JSON file to read.
	 * @return array{0: array<string, array>, 1: array|null} [messages keyed by msgid, meta row (the "" key)]
	 */
	private function read_jed_file( string $file ): array {
		if ( ! is_readable( $file ) ) {
			return array( array(), null );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local translation JSON file, not a remote URL; wp_remote_get() doesn't apply here.
		$contents = file_get_contents( $file );
		if ( false === $contents ) {
			return array( array(), null );
		}

		$data = json_decode( $contents, true );
		if ( empty( $data['locale_data'] ) || ! is_array( $data['locale_data'] ) ) {
			return array( array(), null );
		}

		$catalog = $data['locale_data']['messages'] ?? reset( $data['locale_data'] );
		if ( ! is_array( $catalog ) ) {
			return array( array(), null );
		}

		$messages = array();
		$meta     = null;

		foreach ( $catalog as $key => $value ) {
			if ( '' === $key ) {
				$meta = $value;
				continue;
			}
			// Skip empty translations so an untranslated string in one file
			// can't clobber a real translation found in another.
			if ( is_array( $value ) && isset( $value[0] ) && '' !== $value[0] ) {
				$messages[ $key ] = $value;
			}
		}

		return array( $messages, $meta );
	}

	/**
	 * Builds a fallback Jed meta ("") row when none of the merged files provided one.
	 *
	 * @param string $locale Locale to embed in the meta row.
	 * @return array Jed meta row (domain/lang/plural-forms).
	 */
	private function default_meta( string $locale ): array {
		return array(
			'domain'       => 'messages',
			'lang'         => $locale,
			'plural-forms' => 'nplurals=2; plural=(n != 1);',
		);
	}
}
