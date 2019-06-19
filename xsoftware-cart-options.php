<?php

if(!defined("ABSPATH")) die;

if (!class_exists("xs_cart_options")) :

class xs_cart_options
{

        private $default = array (
                'sys' => [
                        'checkout' => '',
                        'currency' => 'EUR'
                ],
                'discount' => [

                ]
        );

        public function __construct()
        {
                add_action('admin_menu', array($this, 'admin_menu'));
                add_action('admin_init', array($this, 'section_menu'));
                $this->options = get_option('xs_options_cart', $this->default);
        }

        function admin_menu()
        {
                add_submenu_page(
                'xsoftware',
                'XSoftware Cart',
                'Cart',
                'manage_options',
                'xsoftware_cart',
                array($this, 'menu_page') );
        }


        public function menu_page()
        {
                if ( !current_user_can( 'manage_options' ) )  {
                        wp_die( __( 'Exit!' ) );
                }

                echo '<div class="wrap">';

                echo "<h2>Cart configuration</h2>";

                echo '<form action="options.php" method="post">';

                settings_fields('cart_setting');
                do_settings_sections('cart');

                submit_button( '', 'primary', 'submit', true, NULL );
                echo '</form>';

                echo '</div>';

        }

        function section_menu()
        {
                register_setting(
                        'cart_setting',
                        'xs_options_cart',
                        array($this, 'input')
                );
                add_settings_section(
                        'cart_section',
                        'Settings',
                        array($this, 'show'),
                        'cart'
                );
        }

        function input($input)
        {
                $current = $this->options;

                if(isset($input['sys']) && !empty($input['sys']))
                        foreach($input['sys'] as $key => $value)
                                $current['sys'][$key] = $value;

                if(
                        isset($input['discount']) &&
                        !empty($input['discount']['code']) &&
                        !empty($input['discount']['value'])
                ) {
                        $code = strtoupper($input['discount']['code']);
                        $current['discount'][$code] = $input['discount']['value'];
                }

                if(isset($input['remove_discount']) && !empty($input['remove_discount']))
                        unset($current['discount'][$input['remove_discount']]);

                return $current;
        }

        function show()
        {
                $tab = xs_framework::create_tabs( array(
                        'href' => '?page=xsoftware_cart',
                        'tabs' => array(
                                'system' => 'System',
                                'discount' => 'Discount'
                        ),
                        'home' => 'system',
                        'name' => 'main_tab'
                ));

                switch($tab) {
                        case 'system':
                                $this->show_system();
                                return;
                        case 'discount':
                                $this->show_discount();
                                return;
                }
        }

        function show_system()
        {
                $options = array(
                        'name' => 'xs_options_cart[sys][checkout]',
                        'selected' => $this->options['sys']['checkout'],
                        'data' => xs_framework::get_wp_pages_link(),
                        'default' => 'Select a checkout page',
                        'echo' => TRUE
                );

                add_settings_field(
                        $options['name'],
                        'Set checkout page',
                        'xs_framework::create_select',
                        'cart',
                        'cart_section',
                        $options
                );
                $options = array(
                        'name' => 'xs_options_cart[sys][currency]',
                        'selected' => $this->options['sys']['currency'],
                        'data' => xs_framework::get_currency_list(),
                        'default' => 'Select a currency',
                        'echo' => TRUE
                );

                add_settings_field(
                        $options['name'],
                        'Set Currency',
                        'xs_framework::create_select',
                        'cart',
                        'cart_section',
                        $options
                );

                $menus = get_terms( 'nav_menu', array( 'hide_empty' => true ) );
                foreach ($menus as $menu ) {
                        $data_menu[$menu->slug] = $menu->name;
                }

                $options = array(
                        'name' => 'xs_options_cart[sys][menu]',
                        'selected' => $this->options['sys']['menu'],
                        'data' => $data_menu,
                        'default' => 'Select menu',
                        'echo' => TRUE
                );

                add_settings_field(
                        $options['name'],
                        'Set Menu where add cart and login',
                        'xs_framework::create_select',
                        'cart',
                        'cart_section',
                        $options
                );
        }

        function show_discount()
        {
                $importance = $this->options['discount'];

                foreach($importance as $key => $value) {
                        $data[$key][0] = $key;
                        $data[$key][1] = $value;
                        $data[$key][2] = xs_framework::create_button([
                                'class' => 'button-primary',
                                'name' => 'xs_options_cart[remove_discount]',
                                'text' => 'Remove',
                                'value' => $key
                        ]);
                }

                $new[0] = xs_framework::create_input([
                        'class' => 'xs_full_width',
                        'name' => 'xs_options_cart[discount][code]'
                ]);
                $new[1] = xs_framework::create_input_number([
                        'class' => 'xs_full_width',
                        'value' => 0.01,
                        'step' => 0.001,
                        'name' => 'xs_options_cart[discount][value]'
                ]);
                $data[] = $new;

                xs_framework::create_table([
                        'class' => 'xs_admin_table xs_full_width',
                        'data' => $data,
                        'headers' => ['Code', 'Discount (%)', 'Actions']
                ]);

        }


}

endif;

$xs_cart_options = new xs_cart_options();

?>