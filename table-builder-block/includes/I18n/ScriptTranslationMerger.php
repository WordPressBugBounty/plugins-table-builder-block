<?php

namespace TableBuilder\I18n;

defined('ABSPATH') || exit;

final class ScriptTranslationMerger
{
    use \TableBuilder\Traits\Singleton;

    /** Text domain this merger is responsible for. */
    private const DOMAIN = 'table-builder-block';

    /** How long a merged result is cached before being recomputed. */
    private const CACHE_TTL = DAY_IN_SECONDS;

    /** Prefix for the transient cache key. */
    private const CACHE_PREFIX = 'tbb_js_i18n_';

    private function __construct()
    {
        add_filter('pre_load_script_translations', [$this, 'maybe_merge'], 10, 4);
    }

    /* -----------------------------------------------------------------
     * Filter callback
     * --------------------------------------------------------------- */

    /**
     * @param string|false|null $translations JSON-encoded translations, or null/false if unresolved.
     * @param string|false      $file         The path core was about to try. Unused: we merge by domain, not by file.
     * @param string            $handle       Script handle.
     * @param string            $domain       Text domain.
     * @return string|false|null
     */
    public function maybe_merge($translations, $file, $handle, $domain)
    {
        if (null !== $translations || self::DOMAIN !== $domain) {
            return $translations;
        }

        $locale = $this->current_locale();
        if (null === $locale) {
            return $translations;
        }

        $files = $this->find_translation_files($locale);
        if (empty($files)) {
            return $translations;
        }

        $merged = $this->get_cached_merge($locale, $files);

        return $merged ?? $translations;
    }

    /* -----------------------------------------------------------------
     * Locale / file discovery
     * --------------------------------------------------------------- */

    private function current_locale(): ?string
    {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

        // Nothing to merge for the source language.
        if (empty($locale) || 0 === strpos($locale, 'en_')) {
            return null;
        }

        return $locale;
    }

    private function languages_dir(): string
    {
        return defined('TABLE_BUILDER_BLOCK_PLUGIN_DIR')
            ? TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'languages'
            : dirname(__DIR__, 2) . '/languages';
    }

    /** @return string[] Absolute paths of every candidate JSON file for this locale. */
    private function find_translation_files(string $locale): array
    {
        $pattern = sprintf('%s/%s-%s-*.json', $this->languages_dir(), self::DOMAIN, $locale);

        return glob($pattern) ?: [];
    }

    /* -----------------------------------------------------------------
     * Caching
     * --------------------------------------------------------------- */

    /**
     * @param string[] $files
     */
    private function get_cached_merge(string $locale, array $files): ?string
    {
        $cache_key = self::CACHE_PREFIX . md5(self::DOMAIN . '|' . $locale . '|' . $this->fingerprint($files));
        $cached    = get_transient($cache_key);

        if (is_string($cached) && '' !== $cached) {
            return $cached;
        }

        $merged = $this->merge($files, $locale);
        if (null === $merged) {
            return null;
        }

        set_transient($cache_key, $merged, self::CACHE_TTL);

        return $merged;
    }

    /**
     * Cheap "version" signal for the cache key: changes automatically the
     * moment any candidate file is added, edited, or removed.
     *
     * @param string[] $files
     */
    private function fingerprint(array $files): string
    {
        $parts = array_map(
            static fn (string $file): string => $file . ':' . @filemtime($file) . ':' . @filesize($file),
            $files
        );
        sort($parts);

        return md5(implode('|', $parts));
    }

    /* -----------------------------------------------------------------
     * Merging
     * --------------------------------------------------------------- */

    /**
     * @param string[] $files
     */
    private function merge(array $files, string $locale): ?string
    {
        $messages = [];
        $meta     = null;

        foreach ($files as $file) {
            [$file_messages, $file_meta] = $this->read_jed_file($file);
            $messages = array_merge($messages, $file_messages);
            $meta     = $meta ?? $file_meta;
        }

        if (empty($messages)) {
            return null;
        }

        $messages[''] = $meta ?: $this->default_meta($locale);

        return wp_json_encode([
            'translation-revision-date' => gmdate('Y-m-d H:i:s'),
            'generator'                 => self::class,
            'domain'                    => 'messages',
            'locale_data'               => [
                'messages' => $messages,
            ],
        ]);
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
     * @return array{0: array<string, array>, 1: array|null} [messages keyed by msgid, meta row (the "" key)]
     */
    private function read_jed_file(string $file): array
    {
        $contents = @file_get_contents($file);
        if (false === $contents) {
            return [[], null];
        }

        $data = json_decode($contents, true);
        if (empty($data['locale_data']) || !is_array($data['locale_data'])) {
            return [[], null];
        }

        $catalog = $data['locale_data']['messages'] ?? reset($data['locale_data']);
        if (!is_array($catalog)) {
            return [[], null];
        }

        $messages = [];
        $meta     = null;

        foreach ($catalog as $key => $value) {
            if ('' === $key) {
                $meta = $value;
                continue;
            }
            // Skip empty translations so an untranslated string in one file
            // can't clobber a real translation found in another.
            if (is_array($value) && isset($value[0]) && '' !== $value[0]) {
                $messages[$key] = $value;
            }
        }

        return [$messages, $meta];
    }

    private function default_meta(string $locale): array
    {
        return [
            'domain'       => 'messages',
            'lang'         => $locale,
            'plural-forms' => 'nplurals=2; plural=(n != 1);',
        ];
    }
}
