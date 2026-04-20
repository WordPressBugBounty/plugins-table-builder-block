<?php

namespace TableBuilder\Admin;
use TableBuilder\Traits\PluginHelper;

defined('ABSPATH') || exit;

//The admin class for TableBuilder
class Admin
{
    use \TableBuilder\Traits\Singleton;
    use PluginHelper;

    // slug of the admin menu
    private $menu_slug = 'tablebuilder';

    // $menu_link_part Part of the menu link used in the admin panel.
    private $menu_link_part;

    //Initialize the class
    public function __construct()
    {
        $this->menu_link_part = admin_url('admin.php?page=tablebuilder');

        // Register Settings API
        new Api\SettingsData();
        new Api\OnboardData();
        new Api\ChangelogData();
        add_action('admin_menu', [$this, 'add_admin_menu'], 9);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            esc_html__('TableKit', 'table-builder-block'),
            esc_html__('TableKit', 'table-builder-block'),
            'manage_options',
            $this->menu_slug,
            [$this, 'settings_menu_callback'],
            TABLE_BUILDER_BLOCK_PLUGIN_URL . 'assets/icons/admin-menu.svg',
            26
        );

        add_submenu_page(
            $this->menu_slug,
            esc_html__('TableKit', 'table-builder-block'),
            esc_html__('TableKit', 'table-builder-block'),
            'manage_options',
            admin_url('admin.php?page=tablebuilder&onboard=1'),
            '',
            1
        );

        add_submenu_page(
            $this->menu_slug,
            esc_html__('Welcome', 'table-builder-block'),
            esc_html__('Welcome', 'table-builder-block'),
            'manage_options',
            $this->menu_link_part . '#welcome',
            '',
            2
        );

        add_submenu_page(
			$this->menu_slug,
			esc_html__('Features', 'table-builder-block'),
			esc_html__('Features', 'table-builder-block'),
			'manage_options',
			$this->menu_link_part . '#features',
			'',
			3
		);

        remove_submenu_page($this->menu_slug, $this->menu_slug);
    }

    private function should_show_onboard(): bool
    {
        if ( get_option( 'tablebuilder_onboard_completed', false ) ) {
            return false;
        }

        if ( isset( $_GET['onboard'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['onboard'] ) ) ) {
            return true;
        }

        return (bool) get_transient( 'tablebuilder_show_onboard' );
    }

    // Callback for settings submenu
    public function settings_menu_callback()
    {
      $admin_mode = $this->should_show_onboard() ? 'onboard' : 'dashboard';
      ?>
        <div class="wrap">
            <div class="tablebuilder-dashboard" data-admin="<?php echo esc_attr( $admin_mode ); ?>"></div>
        </div>
     <?php
    }
}
