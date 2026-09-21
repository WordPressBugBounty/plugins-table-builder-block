<?php
/**
 * General-purpose helper methods shared across the plugin
 *
 * @package TableKit
 */

namespace TableBuilder\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Global helper class.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Returns an array of allowed HTML tags and attributes for SVG elements.
	 *
	 * @return array The array of allowed HTML tags and attributes.
	 */
	public static function svg_allowed_html() {
		$allowed_svg_tags = array(
			'svg'            => array(
				'xmlns'               => true,
				'width'               => true,
				'height'              => true,
				'viewBox'             => true,
				'viewbox'             => true,
				'fill'                => true,
				'class'               => true,
				'aria-hidden'         => true,
				'aria-labelledby'     => true,
				'role'                => true,
				'preserveaspectratio' => true,
				'version'             => true,
			),
			'title'          => array( 'title' => true ),
			'g'              => array(
				'transform' => true,
				'style'     => true,
				'id'        => true,
			),
			'path'           => array(
				'd'                 => true,
				'fill'              => true,
				'fill-rule'         => true,
				'transform'         => true,
				'style'             => true,
				'opacity'           => true,
				'stroke'            => true,
				'stroke-width'      => true,
				'stroke-miterlimit' => true,
				'stroke-linecap'    => true,
				'stroke-linejoin'   => true,
				'fill-opacity'      => true,
			),
			'circle'         => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'ellipse'        => array(
				'cx'           => true,
				'cy'           => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'line'           => array(
				'x1'           => true,
				'y1'           => true,
				'x2'           => true,
				'y2'           => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'polygon'        => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'polyline'       => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'rect'           => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'text'           => array(
				'x'           => true,
				'y'           => true,
				'dx'          => true,
				'dy'          => true,
				'text-anchor' => true,
				'style'       => true,
			),
			'tspan'          => array(
				'x'           => true,
				'y'           => true,
				'dx'          => true,
				'dy'          => true,
				'text-anchor' => true,
				'style'       => true,
			),
			'defs'           => array(),
			'lineargradient' => array(
				'id'            => true,
				'x1'            => true,
				'y1'            => true,
				'x2'            => true,
				'y2'            => true,
				'gradientunits' => true,
			),
			'stop'           => array(
				'offset'       => true,
				'style'        => true,
				'stop-color'   => true,
				'stop-opacity' => true,
			),
			'radialgradient' => array(
				'id'                => true,
				'cx'                => true,
				'cy'                => true,
				'r'                 => true,
				'gradientunits'     => true,
				'gradienttransform' => true,
			),
		);

		/**
		 * Filters the allowed SVG tags and attributes used when sanitizing block output.
		 *
		 * @since Unknown
		 *
		 * @param array $allowed_svg_tags Allowed SVG elements and their attributes, keyed by tag name.
		 */
		return apply_filters( 'table_builder_allowed_svg_attrs_tags', $allowed_svg_tags );
	}

	/**
	 * Returns an array of allowed JSON attribute tags.
	 *
	 * This function defines an array of allowed JSON attribute tags and their corresponding properties.
	 * The array includes tags such as 'object', 'array', 'string', 'number', 'integer', 'boolean', 'null',
	 * 'enum', 'const', 'oneOf', 'allOf', 'anyOf', 'not', 'if', 'then', 'else', and 'format'.
	 *
	 * @return array The array of allowed JSON attribute tags.
	 */
	public static function allowed_json_attrs_tags() {
		$allowed_json_tags = array(
			'object'      => array(
				'type'                 => true,
				'properties'           => true,
				'required'             => true,
				'additionalProperties' => true,
				'propertyNames'        => true,
				'dependencies'         => true,
				'minProperties'        => true,
				'maxProperties'        => true,
			),
			'array'       => array(
				'type'        => true,
				'items'       => true,
				'minItems'    => true,
				'maxItems'    => true,
				'uniqueItems' => true,
			),
			'string'      => array(
				'type'             => true,
				'minLength'        => true,
				'maxLength'        => true,
				'pattern'          => true,
				'format'           => true,
				'contentEncoding'  => true,
				'contentMediaType' => true,
			),
			'number'      => array(
				'type'             => true,
				'minimum'          => true,
				'maximum'          => true,
				'exclusiveMinimum' => true,
				'exclusiveMaximum' => true,
				'multipleOf'       => true,
			),
			'integer'     => array(
				'type'             => true,
				'minimum'          => true,
				'maximum'          => true,
				'exclusiveMinimum' => true,
				'exclusiveMaximum' => true,
			),
			'boolean'     => array(
				'type' => true,
			),
			'null'        => array(
				'type' => true,
			),
			'enum'        => array(
				'type' => true,
				'enum' => true,
			),
			'const'       => array(
				'type'  => true,
				'const' => true,
			),
			'oneOf'       => array(
				'type'  => true,
				'oneOf' => true,
			),
			'allOf'       => array(
				'type'  => true,
				'allOf' => true,
			),
			'anyOf'       => array(
				'type'  => true,
				'anyOf' => true,
			),
			'not'         => array(
				'type' => true,
				'not'  => true,
			),
			'if'          => array(
				'type' => true,
				'if'   => true,
			),
			'then'        => array(
				'type' => true,
				'then' => true,
			),
			'else'        => array(
				'type' => true,
				'else' => true,
			),
			'format'      => array(
				'type'   => true,
				'format' => true,
			),
			'title'       => true,
			'description' => true,
			'default'     => true,
			'examples'    => true,
			'$ref'        => true,
			'$id'         => true,
			'$schema'     => true,
			// Adding Lottie-specific keys.
			'v'           => true,
			'meta'        => true,
			'fr'          => true,
			'ip'          => true,
			'op'          => true,
			'w'           => true,
			'h'           => true,
			'nm'          => true,
			'ddd'         => true,
			'assets'      => true,
			'layers'      => true,
			'markers'     => true,
		);

		/**
		 * Filters the allowed JSON Schema/Lottie attribute keys used when sanitizing block output.
		 *
		 * @since Unknown
		 *
		 * @param array $allowed_json_tags Allowed JSON keys, keyed by key name.
		 */
		return apply_filters( 'table_builder_allowed_json_attrs_tags', $allowed_json_tags );
	}

	/**
	 * Returns the allowed HTML tags and attributes for the iframe element.
	 *
	 * @return array The allowed HTML tags and attributes.
	 */
	public static function iframe_allowed_html() {
		return array(
			'iframe' => array(
				'src'             => true,
				'name'            => true,
				'sandbox'         => true,
				'width'           => true,
				'height'          => true,
				'marginheight'    => true,
				'marginwidth'     => true,
				'scrolling'       => true,
				'allowfullscreen' => true,
				'frameborder'     => true,
				'title'           => true,
				'id'              => true,
				'class'           => true,
				'style'           => true,
				'tabindex'        => true,
				'allow'           => true,
			),
		);
	}

	/**
	 * Returns the allowed HTML tags and attributes for the "gdc" (GenerateBlocks
	 * dynamic content) shortcode-style tag used in migrated content.
	 *
	 * @return array The allowed HTML tags and attributes.
	 */
	public static function gdc_allowed_html() {
		return array(
			'gdc' => array(
				'selectedpath'            => true,
				'class'                   => true,
				'id'                      => true,
				'fallback'                => true,
				'postcustomfield'         => true,
				'postcustomfieldkey'      => true,
				'postdatetype'            => true,
				'dateformat'              => true,
				'customdateformat'        => true,
				'excerptlength'           => true,
				'tagindex'                => true,
				'timetype'                => true,
				'timeformat'              => true,
				'customtimeformat'        => true,
				'categoryindex'           => true,
				'nocomment'               => true,
				'singlecomment'           => true,
				'multicomments'           => true,
				'currentdateformat'       => true,
				'customcurrentdateformat' => true,
				'currenttimeformat'       => true,
				'customcurrenttimeformat' => true,
				'authorinfo'              => true,
				'currentuserinfo'         => true,
				'acfgroup'                => true,
				'acffield'                => true,
			),
		);
	}

	/**
	 * Returns the allowed HTML tags and attributes for the img element.
	 *
	 * @return array The allowed HTML tags and attributes.
	 */
	public static function img_allowed_html() {
		return array(
			'img' => array(
				'alt'    => true,
				'src'    => true,
				'srcset' => true,
				'class'  => true,
				'height' => true,
				'width'  => true,
			),
		);
	}

	/**
	 * Returns the allowed HTML tags and attributes for the style element.
	 *
	 * @return array The allowed HTML tags and attributes.
	 */
	public static function style_allowed_html() {
		return array(
			'style' => array(
				'class' => true,
				'id'    => true,
			),
		);
	}

	/**
	 * Returns the default responsive breakpoint list (Desktop/Tablet/Mobile)
	 * used across the block editor's responsive controls.
	 *
	 * @return array[] List of device descriptors (label/slug/value/direction/isActive/isRequired).
	 */
	public static function get_device_list() {
		$default_device_list = array(
			array(
				'label'      => 'Desktop',
				'slug'       => 'Desktop',
				'value'      => 'base',
				'direction'  => 'max',
				'isActive'   => true,
				'isRequired' => true,
			),
			array(
				'label'      => 'Tablet',
				'slug'       => 'Tablet',
				'value'      => '1024',
				'direction'  => 'max',
				'isActive'   => true,
				'isRequired' => true,
			),
			array(
				'label'      => 'Mobile',
				'slug'       => 'Mobile',
				'value'      => '767',
				'direction'  => 'max',
				'isActive'   => true,
				'isRequired' => true,
			),
		);

		return $default_device_list;
	}

	/**
	 * Injects a `class="table-builder-icon"` attribute into a raw `<svg ...>` string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $svg Raw SVG markup.
	 * @return string The SVG markup with the class attribute injected, or unchanged if no `<svg` tag is found.
	 */
	public static function add_class_to_svg( $svg ) {
		$original_string  = $svg;
		$substring_to_add = "class='table-builder-icon' ";

		$position = strpos( $original_string, '<svg' );

		if ( false !== $position ) {
			$svg = substr_replace( $original_string, $substring_to_add, $position + 5, 0 );
			return $svg;
		}

		return $svg;
	}

	/**
	 * Retrieves the dynamic block wrapper attributes.
	 *
	 * This function retrieves the wrapper attributes for a dynamic block.
	 * It checks if the function "get_block_wrapper_attributes" exists and if the block is not empty.
	 * If both conditions are met, it retrieves the block attributes, including the block ID.
	 * It then merges the required attributes with any extra attributes provided.
	 * Finally, it applies the "tablebuilder/dynamic_block_wrapper_attributes" filter and returns the wrapper attributes.
	 *
	 * @param object $block The dynamic block object.
	 * @param array  $extra_attrs Additional attributes to be merged with the required attributes.
	 * @return string The wrapper attributes for the dynamic block.
	 */
	public static function get_dynamic_block_wrapper_attributes( $block, $extra_attrs = array() ) {
		if ( function_exists( 'get_block_wrapper_attributes' ) && ! empty( $block ) ) {
			$block_attrs    = $block->attributes;
			$block_id       = $block_attrs['blockID'];
			$required_attrs = array(
				'id' => 'block-' . $block_id,
			);
			/**
			 * Filters a dynamic block's wrapper attributes before they're rendered.
			 *
			 * @since Unknown
			 *
			 * @param array  $wrapper_attrs Merged required + extra wrapper attributes (e.g. "id").
			 * @param object $block         The dynamic block object.
			 */
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-namespaced hook name is this plugin's established public API (used by the Pro add-on); renaming would be a breaking change.
			$wrapper_attrs = apply_filters( 'tablebuilder/dynamic_block_wrapper_attributes', array_merge( $required_attrs, $extra_attrs ), $block );
			return get_block_wrapper_attributes( $wrapper_attrs );
		}
	}

	/**
	 * Extends the allowed HTML tags for post content.
	 *
	 * @return array The extended array of allowed HTML tags.
	 */
	public static function post_kses_extend_allowed_html() {
		$default_post_allowed_html = wp_kses_allowed_html( 'post' );

		$post_allowed_html = array_merge( $default_post_allowed_html, self::svg_allowed_html(), self::iframe_allowed_html(), self::gdc_allowed_html(), self::style_allowed_html() );

		return $post_allowed_html;
	}

	/**
	 * Retrieves the plugin's settings, or a specific key/field/inner-field within them,
	 * from the "table_builder_settings_list" option.
	 *
	 * @param string $key          Optional. Top-level settings key to look up.
	 * @param string $field        Optional. Field within that key to look up; defaults to "status"
	 *                             (returned as a bool, true when its value is "active").
	 * @param string $inner_field  Optional. Nested field within $field to look up.
	 * @return mixed The retrieved settings value, or false if not found.
	 */
	public static function get_settings( $key = '', $field = 'status', $inner_field = '' ) {
		$settings = get_option( 'table_builder_settings_list' );

		// check if $key & $field both empty.
		if ( empty( $key ) && empty( $field ) ) {
			return $settings;
		}

		// check for primary key.
		if ( ! empty( $key ) && ! empty( $settings[ $key ] ) ) {
			$settings = $settings[ $key ];
		}

		// check for primary field.
		if ( ! empty( $field ) ) {
			if ( 'status' === $field && ! empty( $settings[ $field ] ) ) {
				return ( 'active' === $settings[ $field ] ) ? true : false;
			} else {
				$settings = ! empty( $settings[ $field ] ) ? $settings[ $field ] : false;
			}
		}

		// check for inner field.
		if ( ! empty( $inner_field ) ) {
			if ( isset( $settings[ $inner_field ]['value'] ) ) {
				$settings = $settings[ $inner_field ]['value'];
			} else {
				$settings = ! empty( $settings[ $inner_field ] ) ? $settings[ $inner_field ] : false;
			}
		}

		return $settings;
	}

	/**
	 * Returns an array representing the border value based on the given key.
	 *
	 * @param mixed $key The key used to determine the border value.
	 * @return array An array representing the border value. The array contains the following keys:
	 *               - 'border': The border value, or null if the key is null.
	 */
	public static function get_border_value( $key ) {

		if ( ! is_array( $key ) ) {
			return array( 'border' => null );
		}

		// Per-side border box: keys are direction names (top/right/bottom/left),
		// each holding its own {width, style, color}.
		$sides       = array( 'top', 'right', 'bottom', 'left' );
		$is_per_side = count( array_intersect( array_keys( $key ), $sides ) ) > 0;

		if ( $is_per_side ) {
			$border = array();
			foreach ( $key as $direction => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( isset( $value['width'] ) && '' !== $value['width'] ) {
					$color = ! empty( $value['color'] ) ? $value['color'] : 'currentColor';
					$style = ! empty( $value['style'] ) ? $value['style'] : 'solid';
					$border[ "border-{$direction}" ] = "{$value['width']} {$style} {$color}";
				}
			}
			return $border;
		}

		// Flat border: a defined width alone is enough to emit a declaration —
		// this ensures an explicit 0 always produces `border-width: 0` instead
		// of being silently dropped when style/color weren't also set (which
		// let a theme's/Elementor's default table border show through).
		if ( isset( $key['width'] ) && '' !== $key['width'] ) {
			$color = ! empty( $key['color'] ) ? $key['color'] : 'currentColor';
			$style = ! empty( $key['style'] ) ? $key['style'] : 'solid';
			return array( 'border' => "{$key['width']} {$style} {$color}" );
		}

		if ( ! empty( $key['color'] ) ) {
			$style = ! empty( $key['style'] ) ? $key['style'] : 'solid';
			return array( 'border' => "1px {$style} {$key['color']}" );
		}

		return array( 'border' => null );
	}

	/**
	 * Builds a CSS box-model value (margin/padding/border-radius style) from a
	 * {top,right,bottom,left} array, collapsing to a shorthand string when all
	 * four sides share values.
	 *
	 * @param array  $key      Box values keyed by 'top'/'right'/'bottom'/'left'.
	 * @param string $property CSS property prefix (e.g. "margin", "padding"), or
	 *                         "border-radius" for the corner-radius longhand variant.
	 * @return array CSS declarations keyed by property name (shorthand or per-side longhand).
	 */
	public static function get_box_value( $key = array(), $property = '' ) {
		$top    = isset( $key['top'] ) ? $key['top'] : null;
		$right  = isset( $key['right'] ) ? $key['right'] : null;
		$bottom = isset( $key['bottom'] ) ? $key['bottom'] : null;
		$left   = isset( $key['left'] ) ? $key['left'] : null;

		$box_object = array(
			'top'    => $top,
			'right'  => $right,
			'bottom' => $bottom,
			'left'   => $left,
		);
		$count      = count(
			array_filter(
				$box_object,
				function ( $value ) {
					return null !== $value;
				}
			)
		);

		if ( 0 === $count ) {
			return array( $property => null );
		}

		if ( 4 === $count ) {
			$box_values = '';

			if ( $top === $bottom && $top === $right && $top === $left ) {
				$box_values = $top;
			} elseif ( $top === $bottom && $left === $right ) {
				$box_values = "{$top} {$right}";
			} else {
				$box_values = "{$top} {$right} {$bottom} {$left}";
			}

			return array( $property => $box_values );
		}

		$final_box = array();

		if ( 'border-radius' !== $property ) {
			foreach ( $box_object as $direction => $value ) {
				$final_box[ "{$property}-{$direction}" ] = isset( $key[ $direction ] ) ? $key[ $direction ] : null;
			}

			return $final_box;
		}

		if ( 'border-radius' === $property ) {
			$final_box['border-top-left-radius']     = $top;
			$final_box['border-top-right-radius']    = $right;
			$final_box['border-bottom-right-radius'] = $bottom;
			$final_box['border-bottom-left-radius']  = $left;

			return $final_box;
		}
	}

	/**
	 * Retrieves the color based on the given type and color.
	 *
	 * @param string $type The type of color to retrieve. Can be either 'gradient' or 'color'.
	 * @param string $color The color value.
	 * @return string The retrieved color.
	 */
	public static function get_color( $type, $color ) {

		if ( empty( $color ) || 0 === strpos( $color, 'linear-gradient(' ) || 0 === strpos( $color, 'radial-gradient(' ) || 0 === strpos( $color, '#' ) ) {
			return $color;
		}

		$color_parts = explode( ',', $color, 2 );

		$color_parts1 = $color_parts[0];
		$color_parts2 = $color_parts[1];

		$bg_color = '';

		if ( 'gradient' === $type ) {
			$bg_color = 'var(--wp--preset--gradient--' . $color_parts1 . ',' . $color_parts2 . ')';

		} else {
			$bg_color = 'var(--wp--preset--color--' . $color_parts1 . ',' . $color_parts2 . ')';
		}
		return $bg_color;
	}

	/**
	 * Builds CSS background-* declarations (color, gradient, or image with
	 * position/size/repeat/attachment) from a block's background attribute object.
	 *
	 * @param array  $background Background attribute object (backgroundType plus the
	 *                           relevant color/gradient/image sub-fields).
	 * @param string $device     Responsive device key used for position/size sub-fields; default "Desktop".
	 * @return array CSS declarations keyed by "background-*" property name.
	 */
	public static function fill_background_generator( $background, $device = 'Desktop' ) {

		$fill_background = array(
			'background-image' => '',
		);

		if ( isset( $background['backgroundType'] ) && 'classic' === $background['backgroundType'] ) {
			$fill_background['background-color'] = isset( $background['backgroundColor'] ) ? self::get_color( 'color', $background['backgroundColor'] ) : '';
		}

		if ( isset( $background['backgroundType'] ) && 'gradient' === $background['backgroundType'] && ! empty( $background['gradient'] ) ) {

			$fill_background['background-image'] = isset( $background['gradient'] ) ? ( self::get_color( 'gradient', $background['gradient'] ) ?? '' ) : '';
		}

		if ( isset( $background['backgroundType'] ) && 'image' === $background['backgroundType'] && ! empty( $background['backgroundImage'] ) ) {
			if ( ! empty( $background['backgroundImage']['imageUrl'] ) ) {
				$fill_background['background-image'] = "url({$background['backgroundImage']['imageUrl']})";
			}

			if ( ! empty( $background['backgroundAttachment'] ) ) {
				$fill_background['background-attachment'] = $background['backgroundAttachment'];
			}

			if ( ! empty( $background['backgroundPosition'][ $device ] ) && 'custom' !== $background['backgroundPosition'][ $device ] ) {
				$fill_background['background-position'] = $background['backgroundPosition'][ $device ];
			}

			if (
				! empty( $background['backgroundPosition'][ $device ] ) && 'custom' === $background['backgroundPosition'][ $device ] &&
				! empty( $background['customPositionX'][ $device ] ) && ! empty( $background['customPositionY'][ $device ] )
			) {
				$fill_background['background-position'] = self::get_slider_value( $background['customPositionX'][ $device ] ) . ' ' . self::get_slider_value( $background['customPositionY'][ $device ] );
			}

			if ( ! empty( $background['backgroundSize'] ) && 'custom' !== $background['backgroundSize'] ) {
				$fill_background['background-size'] = $background['backgroundSize'];
			}

			if (
				! empty( $background['backgroundSize'] ) && 'custom' === $background['backgroundSize'] &&
				! empty( $background['customSize'][ $device ] )
			) {
				$fill_background['background-size'] = self::get_slider_value( $background['customSize'][ $device ] ) . ' auto';
			}

			if ( ! empty( $background['backgroundRepeat'] ) ) {
				$fill_background['background-repeat'] = $background['backgroundRepeat'];
			}
		}

		return $fill_background;
	}

	/**
	 * Builds CSS font-, text-, line-height, letter-spacing, and word-spacing
	 * declarations from a typography attribute object for the given responsive device.
	 *
	 * @param array  $key    Typography attribute object (fontFamily/fontSize/fontStyle/
	 *                       fontWeight/textDecoration/textTransform/lineHeight/letterSpacing/wordSpacing).
	 * @param string $device Responsive device key (e.g. "Desktop"); non-Desktop devices
	 *                       omit font-family/style/weight/text-decoration/text-transform,
	 *                       which are treated as desktop-only.
	 * @return array CSS declarations keyed by property name.
	 */
	public static function get_typography_value( $key, $device ) {
		if ( 'Desktop' === $device ) {
			return array(
				'font-family'     => isset( $key['fontFamily']['value'] ) ? $key['fontFamily']['value'] : null,
				'font-size'       => isset( $key['fontSize'][ $device ]['size'] ) && isset( $key['fontSize'][ $device ]['unit'] )
					? $key['fontSize'][ $device ]['size'] . $key['fontSize'][ $device ]['unit']
					: null,
				'font-style'      => isset( $key['fontStyle'] ) ? $key['fontStyle'] : null,
				'font-weight'     => isset( $key['fontWeight']['value'] ) ? $key['fontWeight']['value'] : null,
				'text-decoration' => isset( $key['textDecoration'] ) ? $key['textDecoration'] : null,
				'text-transform'  => isset( $key['textTransform'] ) ? $key['textTransform'] : null,
				'line-height'     => isset( $key['lineHeight'][ $device ]['size'] ) && isset( $key['lineHeight'][ $device ]['unit'] )
					? $key['lineHeight'][ $device ]['size'] . $key['lineHeight'][ $device ]['unit']
					: null,
				'letter-spacing'  => isset( $key['letterSpacing'][ $device ]['size'] ) && isset( $key['letterSpacing'][ $device ]['unit'] )
					? $key['letterSpacing'][ $device ]['size'] . $key['letterSpacing'][ $device ]['unit']
					: null,
				'word-spacing'    => isset( $key['wordSpacing'][ $device ]['size'] ) && isset( $key['wordSpacing'][ $device ]['unit'] )
					? $key['wordSpacing'][ $device ]['size'] . $key['wordSpacing'][ $device ]['unit']
					: null,
			);
		} else {
			return array(
				'font-size'      => isset( $key['fontSize'][ $device ]['size'] ) && isset( $key['fontSize'][ $device ]['unit'] )
					? $key['fontSize'][ $device ]['size'] . $key['fontSize'][ $device ]['unit']
					: null,
				'line-height'    => isset( $key['lineHeight'][ $device ]['size'] ) && isset( $key['lineHeight'][ $device ]['unit'] )
					? $key['lineHeight'][ $device ]['size'] . $key['lineHeight'][ $device ]['unit']
					: null,
				'letter-spacing' => isset( $key['letterSpacing'][ $device ]['size'] ) && isset( $key['letterSpacing'][ $device ]['unit'] )
					? $key['letterSpacing'][ $device ]['size'] . $key['letterSpacing'][ $device ]['unit']
					: null,
				'word-spacing'   => isset( $key['wordSpacing'][ $device ]['size'] ) && isset( $key['wordSpacing'][ $device ]['unit'] )
					? $key['wordSpacing'][ $device ]['size'] . $key['wordSpacing'][ $device ]['unit']
					: null,
			);
		}
	}

	/**
	 * Gets slider value.
	 *
	 * Similar to getSliderValue function in JS helper.
	 *
	 * @param array $key Slider value array with "size" and "unit" keys.
	 * @return string|null The combined "{size}{unit}" string, or null if no size is set.
	 */
	public static function get_slider_value( $key ) {
		$value = '';

		if ( ! empty( $key['size'] ) && ! empty( $key['unit'] ) ) {
			$value = $key['size'] . $key['unit'];
		} elseif ( ! empty( $key['size'] ) && empty( $key['unit'] ) ) {
			$value = $key['size'];
		} else {
			$value = null;
		}

		return $value;
	}

	/**
	 * Converts a per-device map of {selector, ...cssValues} style records into
	 * per-device CSS text blocks.
	 *
	 * @param array $raw_css Style records keyed by device slug (desktop/tablet/mobile/etc.),
	 *                       each an array of {selector, ...property=>value} entries.
	 * @return array<string,string> Compiled CSS text keyed by device slug.
	 */
	public static function parse_css( $raw_css ) {

		$styles      = array();
		$device_list = array( 'desktop', 'tablet', 'mobile', 'tabletlandscape', 'mobilelandscape', 'laptop', 'widescreen' );

		foreach ( $device_list as $device ) {
			$device_styles = $raw_css[ $device ] ?? array();

			$styles[ $device ] = array_map(
				function ( $style ) {
					if ( ! is_array( $style ) || ! isset( $style['selector'] ) ) {
						return '';
					}

					$selector   = $style['selector'];
					$css_values = array_filter(
						$style,
						function ( $value, $key ) {
							return 'selector' !== $key && null !== $value && '' !== $value && ! is_numeric( $value ) && ! in_array( $value, array( 'px', 'em', 'rem', '%', 'vh', 'vw' ), true ) && false === strpos( $value, 'undefined' );
						},
						ARRAY_FILTER_USE_BOTH
					);

					if ( empty( $css_values ) ) {
						return '';
					}

					return "{$selector} { " . implode(
						' ',
						array_map(
							function ( $key, $value ) {
								return "{$key}: {$value};";
							},
							array_keys( $css_values ),
							$css_values
						)
					) . ' }';
				},
				$device_styles
			);
		}

		$device_styles = array_map(
			function ( $style ) {
				return implode( "\n", $style );
			},
			$styles
		);

		return $device_styles;
	}

	/**
	 * Checks whether a parsed block is one of this plugin's "tablebuilder/*" blocks,
	 * optionally requiring a specific (possibly nested) attribute to be present.
	 *
	 * @param string $block_content Rendered block HTML content.
	 * @param array  $parsed_block  Parsed block array (as passed to a `render_block` callback).
	 * @param string $attrs         Optional. Top-level attribute key to require.
	 * @param string $attrs2        Optional. Nested key within $attrs to require.
	 * @return bool True if this is a table-builder block matching the given criteria.
	 */
	public static function is_table_builder_block( $block_content, $parsed_block, $attrs = '', $attrs2 = '' ) {
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- explanatory prose comments, not commented-out code.
		// Check if $block_content is not empty.
		$has_block_content = ! empty( $block_content );
		// Check if $block['blockName'] is not empty and contains 'roxSlider'.
		$has_valid_block_name = isset( $parsed_block['blockName'] ) && false !== strpos( $parsed_block['blockName'], 'tablebuilder' );

		// Check if $block['attrs']['blockClass'] is not empty.
		$has_block_class = ! empty( $attrs ) && ! empty( $attrs2 )
			? ! empty( $parsed_block['attrs'][ $attrs ][ $attrs2 ] )
			: ! empty( $parsed_block['attrs'][ $attrs ] ?? '' );

		if ( empty( $attrs ) && empty( $attrs2 ) ) {
			$has_block_class = true;
		}

		// Return true if all conditions are met.
		return $has_block_content && $has_valid_block_name && $has_block_class;
	}

	/**
	 * Retrieves the link attributes based on the provided attribute array.
	 *
	 * @param array $attribute The attribute array containing the link data.
	 * @return string The generated link attributes as a string.
	 */
	public static function get_link_attributes( $attribute ) {
		if ( empty( $attribute['url'] ) ) {
			return '';
		}

		$link_data = array();

		$link_data['href'] = esc_url( $attribute['url'], wp_allowed_protocols() );

		( isset( $attribute['newTab'] ) && $attribute['newTab'] ) ? $link_data['target'] = '_blank' : '';

		( isset( $attribute['noFollow'] ) && $attribute['noFollow'] ) ? $link_data['rel'] = 'nofollow' : '';

		if ( isset( $attribute['customAttributes'] ) && is_array( $attribute['customAttributes'] ) ) {
			foreach ( $attribute['customAttributes'] as $key => $value ) {
				if ( ! empty( $value ) ) {
					$attr_key_value = explode( '|', $value );

					$attr_key = mb_strtolower( $attr_key_value[0] );

					// Not allowed characters are removed.
					preg_match( '/[-_a-z0-9]+/', $attr_key, $attr_key_matches );

					if ( empty( $attr_key_matches[0] ) ) {
						continue;
					}

					$attr_key = $attr_key_matches[0];

					// Javascript events and unescaped href are avoided.
					if ( 'href' === $attr_key || 'on' === substr( $attr_key, 0, 2 ) ) {
						continue;
					}

					if ( isset( $attr_key_value[1] ) ) {
						$attr_value = trim( $attr_key_value[1] );
					} else {
						$attr_value = '';
					}

					$link_data[ $attr_key ] = $attr_value;
				}
			}
		}

		$link_attributes = '';
		foreach ( $link_data as $key => $value ) {
			$link_attributes .= sprintf( '%s="%s" ', $key, esc_attr( $value ) );
		}

		return $link_attributes;
	}

	/**
	 * Gets the plugin's cached license status ("valid" or "invalid"), based on
	 * whether both the stored license key and license identifier are set.
	 *
	 * @return string "valid" or "invalid".
	 */
	public static function status() {

		$cached = wp_cache_get( 'table_builder__license_status' );

		if ( false !== $cached ) {
			return $cached;
		}

		$oppai  = get_option( '__table_builder_oppai__', '' );
		$key    = get_option( '__table_builder_license_key__', '' );
		$status = 'invalid';

		if ( '' !== $oppai && '' !== $key ) {
			$status = 'valid';
		}

		wp_cache_set( 'table_builder__license_status', $status );

		return $status;
	}

	/**
	 * Checks whether the current request's host's TLD is outside a known list of
	 * public/commercial TLDs — used as a rough heuristic to detect local/dev
	 * environments (e.g. `.test`, `.local`, `.localhost`) for license checks.
	 *
	 * @return bool True if the current host's TLD is NOT in the known public-TLD list
	 *              (i.e. it looks like a local/dev environment).
	 */
	public static function is_local() {
		$valid_domains = array(
			'.academy',
			'.accountant',
			'.accountants',
			'.actor',
			'.adult',
			'.africa',
			'.agency',
			'.airforce',
			'.apartments',
			'.app',
			'.army',
			'.art',
			'.asia',
			'.associates',
			'.attorney',
			'.auction',
			'.audio',
			'.auto',
			'.baby',
			'.band',
			'.bar',
			'.bargains',
			'.beer',
			'.berlin',
			'.best',
			'.bid',
			'.bike',
			'.bingo',
			'.bio',
			'.biz',
			'.black',
			'.blackfriday',
			'.blog',
			'.blue',
			'.boston',
			'.boutique',
			'.build',
			'.builders',
			'.business',
			'.buzz',
			'.cab',
			'.cafe',
			'.cam',
			'.camera',
			'.camp',
			'.capital',
			'.car',
			'.cards',
			'.care',
			'.careers',
			'.cars',
			'.casa',
			'.cash',
			'.casino',
			'.catering',
			'.center',
			'.ceo',
			'.chat',
			'.cheap',
			'.christmas',
			'.church',
			'.city',
			'.claims',
			'.cleaning',
			'.click',
			'.clinic',
			'.clothing',
			'.cloud',
			'.club',
			'.coach',
			'.codes',
			'.coffee',
			'.college',
			'.com',
			'.community',
			'.company',
			'.computer',
			'.condos',
			'.construction',
			'.consulting',
			'.contact',
			'.contractors',
			'.cooking',
			'.cool',
			'.country',
			'.coupons',
			'.courses',
			'.credit',
			'.creditcard',
			'.cricket',
			'.cruises',
			'.cymru',
			'.cyou',
			'.dance',
			'.date',
			'.dating',
			'.day',
			'.deals',
			'.degree',
			'.delivery',
			'.democrat',
			'.dental',
			'.dentist',
			'.desi',
			'.design',
			'.dev',
			'.diamonds',
			'.diet',
			'.digital',
			'.direct',
			'.directory',
			'.discount',
			'.doctor',
			'.dog',
			'.domains',
			'.download',
			'.earth',
			'.eco',
			'.education',
			'.email',
			'.energy',
			'.engineer',
			'.engineering',
			'.enterprises',
			'.equipment',
			'.estate',
			'.events',
			'.exchange',
			'.expert',
			'.exposed',
			'.express',
			'.fail',
			'.faith',
			'.family',
			'.fans',
			'.farm',
			'.fashion',
			'.feedback',
			'.film',
			'.finance',
			'.financial',
			'.fish',
			'.fishing',
			'.fit',
			'.fitness',
			'.flights',
			'.florist',
			'.flowers',
			'.football',
			'.forsale',
			'.foundation',
			'.fun',
			'.fund',
			'.furniture',
			'.futbol',
			'.fyi',
			'.gallery',
			'.game',
			'.games',
			'.garden',
			'.gay',
			'.gdn',
			'.gift',
			'.gifts',
			'.gives',
			'.glass',
			'.global',
			'.gmbh',
			'.gold',
			'.golf',
			'.graphics',
			'.gratis',
			'.green',
			'.gripe',
			'.group',
			'.guide',
			'.guitars',
			'.guru',
			'.hamburg',
			'.haus',
			'.health',
			'.healthcare',
			'.help',
			'.hiphop',
			'.hockey',
			'.holdings',
			'.holiday',
			'.horse',
			'.host',
			'.hosting',
			'.house',
			'.how',
			'.icu',
			'.immo',
			'.immobilien',
			'.inc',
			'.industries',
			'.info',
			'.ink',
			'.institute',
			'.insure',
			'.international',
			'.investments',
			'.irish',
			'.jetzt',
			'.jewelry',
			'.juegos',
			'.kaufen',
			'.kim',
			'.kitchen',
			'.kiwi',
			'.krd',
			'.kyoto',
			'.land',
			'.lat',
			'.lawyer',
			'.lease',
			'.legal',
			'.lgbt',
			'.life',
			'.lighting',
			'.limited',
			'.limo',
			'.link',
			'.live',
			'.llc',
			'.loan',
			'.loans',
			'.lol',
			'.london',
			'.love',
			'.ltd',
			'.ltda',
			'.luxury',
			'.maison',
			'.management',
			'.market',
			'.marketing',
			'.mba',
			'.media',
			'.melbourne',
			'.memorial',
			'.men',
			'.menu',
			'.miami',
			'.mobi',
			'.moda',
			'.moe',
			'.mom',
			'.money',
			'.monster',
			'.mortgage',
			'.movie',
			'.nagoya',
			'.name',
			'.navy',
			'.net',
			'.network',
			'.new',
			'.news',
			'.ninja',
			'.nyc',
			'.observer',
			'.okinawa',
			'.one',
			'.onl',
			'.online',
			'.org',
			'.osaka',
			'.page',
			'.paris',
			'.partners',
			'.parts',
			'.party',
			'.photo',
			'.photography',
			'.photos',
			'.pics',
			'.pictures',
			'.pink',
			'.pizza',
			'.place',
			'.plumbing',
			'.plus',
			'.poker',
			'.porn',
			'.press',
			'.pro',
			'.productions',
			'.properties',
			'.property',
			'.protection',
			'.pub',
			'.racing',
			'.realty',
			'.recipes',
			'.red',
			'.rehab',
			'.reise',
			'.reisen',
			'.rent',
			'.rentals',
			'.repair',
			'.report',
			'.republican',
			'.rest',
			'.restaurant',
			'.review',
			'.reviews',
			'.rip',
			'.rocks',
			'.rodeo',
			'.run',
			'.ryukyu',
			'.sale',
			'.sarl',
			'.school',
			'.schule',
			'.science',
			'.security',
			'.services',
			'.sex',
			'.sexy',
			'.shiksha',
			'.shoes',
			'.shop',
			'.shopping',
			'.show',
			'.singles',
			'.site',
			'.ski',
			'.soccer',
			'.social',
			'.software',
			'.solar',
			'.solutions',
			'.soy',
			'.space',
			'.storage',
			'.store',
			'.stream',
			'.studio',
			'.study',
			'.style',
			'.sucks',
			'.supplies',
			'.supply',
			'.support',
			'.surf',
			'.surgery',
			'.sydney',
			'.systems',
			'.tattoo',
			'.tax',
			'.taxi',
			'.team',
			'.tech',
			'.technology',
			'.tel',
			'.tennis',
			'.theater',
			'.theatre',
			'.tienda',
			'.tips',
			'.tires',
			'.today',
			'.tokyo',
			'.tools',
			'.top',
			'.tours',
			'.town',
			'.toys',
			'.trade',
			'.training',
			'.travel',
			'.tube',
			'.university',
			'.uno',
			'.vacations',
			'.vegas',
			'.ventures',
			'.vet',
			'.viajes',
			'.video',
			'.villas',
			'.vin',
			'.vip',
			'.vision',
			'.vodka',
			'.vote',
			'.voting',
			'.voto',
			'.voyage',
			'.wales',
			'.watch',
			'.webcam',
			'.website',
			'.wedding',
			'.wiki',
			'.win',
			'.wine',
			'.work',
			'.works',
			'.world',
			'.wtf',
			'.xn--3ds443g',
			'.xn--6frz82g',
			'.xxx',
			'.xyz',
			'.yoga',
			'.yokohama',
			'.zone',
		);

		$host = ! empty( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

		// get the domain.
		$domain = explode( '.', $host );
		if ( count( $domain ) >= 2 ) {
			$domain = '.' . $domain[ count( $domain ) - 1 ];
		} else {
			$domain = null;
		}

		return ! in_array( $domain, $valid_domains, true );
	}
}
