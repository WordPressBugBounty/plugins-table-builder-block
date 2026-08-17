<?php
/**
 * Integration with Wpmet's shared UtilityPackage (cross-promotion admin menu)
 *
 * @package TableKit
 */

namespace TableBuilder\Libs;

defined( 'ABSPATH' ) || exit;

use TableBuilderScopedDeps\Wpmet\UtilityPackage;

/**
 * Registers the Wpmet cross-promotion admin menu.
 */
class UtilityPackages {


	use \TableBuilder\Traits\Singleton;

	/**
	 * UtilityPackages class constructor.
	 *
	 * Wires up the same Wpmet shared widgets GutenKit uses
	 * (see gutenkit-blocks-addon/includes/Libs/UtilityPackages.php):
	 * cross-promotion menu, dismissible Notice AJAX handler, the
	 * Stories dashboard widget, the Banner ("jhanda") admin notice,
	 * and the "ask for rating" prompt.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_cross_promotion_menu' ) );

		// Powers the dismiss button on Banner/Notice widgets below.
		UtilityPackage\Notice\Notice::init();

		// Show Wpmet stories widget in the Dashboard.
		UtilityPackage\Stories\Stories::instance( 'table-builder-block' )
			->set_plugin( 'TableKit', 'https://wpmet.com/plugin/table-builder-block/' )
			->set_api_url( 'https://api.wpmet.com/public/stories/' )
			->call();

		// Show Wpmet banner (codename: jhanda) on TableKit's own admin screens.
		UtilityPackage\Banner\Banner::instance( 'table-builder-block' )
			->set_api_url( 'https://api.wpmet.com/public/jhanda' )
			->set_plugin_screens( 'toplevel_page_tablebuilder' )
			->call();

		// Ask for a WordPress.org rating after the plugin has been in use for a while.
		UtilityPackage\Rating\Rating::instance( 'table-builder-block' )
			->set_plugin_logo( 'https://ps.w.org/table-builder-block/assets/icon-128x128.png' )
			->set_plugin( 'TableKit', 'https://wordpress.org/support/plugin/table-builder-block/reviews/#new-post' )
			->set_allowed_screens( 'toplevel_page_tablebuilder' )
			->set_priority( 30 )
			->set_first_appear_day( 7 )
			->set_condition( true )
			->set_support_url( 'https://wpmet.com/support-ticket-form/' )
			->call();
	}

	/**
	 * Registers the "Our Plugins" cross-promotion submenu, listing Wpmet's
	 * other plugins. Hooked to "init" (rather than passed as an inline
	 * closure) so it stays removable via remove_action() if ever needed.
	 *
	 * @return void
	 */
	public function register_cross_promotion_menu() {
		UtilityPackage\Plugins\Plugins::instance()->init( 'table-builder-block' )
			->set_parent_menu_slug( 'tablebuilder' )
			->set_submenu_name(
				esc_html__( 'Our Plugins', 'table-builder-block' )
			)
			->set_section_title(
				esc_html__( 'Take Your WordPress Website To Next Level!', 'table-builder-block' )
			)
			->set_section_description(
				esc_html__( 'Our diverse range of plugins has every solution for WordPress, Gutenberg, Elementor, and WooCommerce.', 'table-builder-block' )
			)
			->set_items_per_row( 4 )
			->set_plugins(
				array(
					'elementskit-lite/elementskit-lite.php' => array(
						'name' => esc_html__( 'ElementsKit', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/elementskit-lite/',
						'icon' => 'https://ps.w.org/elementskit-lite/assets/icon-256x256.gif?rev=2518175',
						'desc' => esc_html__( 'All-in-one Elementor addon trusted by 1 Million+ users, makes your website builder process easier with ultimate freedom.', 'table-builder-block' ),
						'docs' => 'https://wpmet.com/docs/elementskit/',
					),
					'gutenkit-blocks-addon/gutenkit-blocks-addon.php' => array(
						'name' => esc_html__( 'GutenKit', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/gutenkit-blocks-addon/',
						'icon' => 'https://ps.w.org/gutenkit-blocks-addon/assets/icon-256x256.gif?rev=2518175',
						'desc' => esc_html__( 'Page Builder Blocks, Patterns, and Templates for Gutenberg Block Editor.', 'table-builder-block' ),
						'docs' => 'https://wpmet.com/docs/gutenkit/',
					),
					'popup-builder-block/popup-builder-block.php' => array(
						'name' => esc_html__( 'PopupKit', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/popup-builder-block/',
						'icon' => 'https://ps.w.org/popup-builder-block/assets/icon-128x128.png?rev=3330842',
						'desc' => esc_html__( 'Create stunning popups with ease using our drag-and-drop builder, pre-designed templates, and advanced targeting options.', 'table-builder-block' ),
						'docs' => 'https://wpmet.com/doc/popupkit/',
					),
					'getgenie/getgenie.php'             => array(
						'name' => esc_html__( 'GetGenie AI', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/getgenie/',
						'icon' => 'https://ps.w.org/getgenie/assets/icon-256x256.gif?rev=2798355',
						'desc' => esc_html__( 'Your personal AI assistant for content and SEO. Write content that ranks on Google with NLP keywords and SERP analysis data.', 'table-builder-block' ),
						'docs' => 'https://getgenie.ai/docs/',
					),
					'shopengine/shopengine.php'         => array(
						'name' => esc_html__( 'ShopEngine', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/shopengine/',
						'icon' => 'https://ps.w.org/shopengine/assets/icon-256x256.gif?rev=2505061',
						'desc' => esc_html__( 'Complete WooCommerce solution for Elementor to fully customize any pages including cart, checkout, shop page, and so on.', 'table-builder-block' ),
						'docs' => 'https://wpmet.com/doc/shopengine/',
					),
					'metform/metform.php'               => array(
						'name' => esc_html__( 'MetForm', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/genie-image-ai/',
						'icon' => 'https://ps.w.org/metform/assets/icon-256x256.png?rev=2544152',
						'desc' => esc_html__( 'Drag & drop form builder for Elementor to create contact forms, multi-step forms, and more - smoother, faster, and better!', 'table-builder-block' ),
						'docs' => 'https://wpmet.com/doc/metform/',
					),
					'emailkit/EmailKit.php'             => array(
						'name' => esc_html__( 'EmailKit', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/genie-image-ai/',
						'icon' => 'https://ps.w.org/emailkit/assets/icon-256x256.png?rev=3003571',
						'desc' => esc_html__( 'Advanced email customizer for WooCommerce and WordPress. Build, customize, and send emails from WordPress to boost your sales!', 'table-builder-block' ),
						'docs' => 'https://wpmet.com/doc/emailkit/',
					),
					'wp-social/wp-social.php'           => array(
						'name' => esc_html__( 'WP Social', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/wp-social/',
						'icon' => 'https://ps.w.org/wp-social/assets/icon-256x256.png?rev=2544214',
						'desc' => esc_html__( 'Add social share, login, and engagement counter - unified solution for all social media with tons of different styles for your website.', 'table-builder-block' ),
						'docs' => 'https://wpmet.com/doc/wp-social/',
					),
					'wp-ultimate-review/wp-ultimate-review.php' => array(
						'name' => esc_html__( 'WP Ultimate Review', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/wp-ultimate-review/',
						'icon' => 'https://ps.w.org/wp-ultimate-review/assets/icon-256x256.png?rev=2544187',
						'desc' => esc_html__( 'Collect and showcase reviews on your website to build brand credibility and social proof with the easiest solution.', 'table-builder-block' ),
						'docs' => 'https://wpmet.com/doc/wp-ultimate-review/',
					),
					'wp-fundraising-donation/wp-fundraising.php' => array(
						'name' => esc_html__( 'FundEngine', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/wp-fundraising-donation/',
						'icon' => 'https://ps.w.org/wp-fundraising-donation/assets/icon-256x256.png?rev=2544150',
						'desc' => esc_html__( 'Create fundraising, crowdfunding, and donation websites with PayPal and Stripe payment gateway integration.', 'table-builder-block' ),
						'docs' => 'https://wpmet.com/doc/fundengine/',
					),
					'blocks-for-shopengine/shopengine-gutenberg-addon.php' => array(
						'name' => esc_html__( 'Blocks for ShopEngine', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/blocks-for-shopengine/',
						'icon' => 'https://ps.w.org/blocks-for-shopengine/assets/icon-256x256.gif?rev=2702483',
						'desc' => esc_html__( 'All in one WooCommerce solution for Gutenberg! Build your WooCommerce pages in a block editor with full customization.', 'table-builder-block' ),
						'docs' => 'https://wpmet.com/doc/shopengine/shopengine-gutenberg/',
					),
					'genie-image-ai/genie-image-ai.php' => array(
						'name' => esc_html__( 'Genie Image', 'table-builder-block' ),
						'url'  => 'https://wordpress.org/plugins/genie-image-ai/',
						'icon' => 'https://ps.w.org/genie-image-ai/assets/icon-256x256.png?rev=2977297',
						'desc' => esc_html__( 'AI-powered text-to-image generator for WordPress with OpenAI\'s DALL-E 2 technology to generate high-quality images in one click.', 'table-builder-block' ),
						'docs' => 'https://getgenie.ai/docs/',
					),
				)
			)
			->call();
	}
}
