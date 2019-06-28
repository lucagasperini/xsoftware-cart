<?php

if(!defined("ABSPATH")) die;

if (!class_exists("xs_cart_options")) :

/*
*  XSoftware Cart Options Class
*  The following class is used to set the plugin options
*  Below is a description of the fields used
*  $ sys : array
*  It is an array containing important options of the plugin,
*  checkout is the URL of checkout page, default is empty string
*  menu is the menu item where show cart link, default is empty string
*  $ discount : array
*  It is a array containing all the discount in a product
*  default : empty array
*/
class xs_cart_options
{

        private $default = array (
                'sys' => [
                        'checkout' => '',
                        'menu' => '',
                ],
                'discount' => [

                ]
        );

        /*
        *  __construct : void
        *  The class constructor does not require any parameters and
        *  initializes the options and hooks for the administration panel
        */
        public function __construct()
        {
                $this->options = get_option('xs_options_cart', $this->default);

                add_action('admin_menu', [$this, 'admin_menu']);
                add_action('admin_init', [$this, 'section_menu']);
        }

        /*
        *  void : menu_page : void
        *  This method is used to create the entry in the XSoftware submenu
        */
        function admin_menu()
        {
                add_submenu_page(
                'xsoftware',
                'XSoftware Cart',
                'Cart',
                'manage_options',
                'xsoftware_cart',
                [$this, 'menu_page']);
        }

        /*
        *  void : menu_page : void
        *  This method is used to create the page template in the administration panel
        */
        public function menu_page()
        {
                if ( !current_user_can( 'manage_options' ) )  {
                        wp_die( __( 'Exit!' ) );
                }

                echo '<div class="wrap">';

                echo '<h2>Cart configuration</h2>';

                echo '<form action="options.php" method="post">';

                settings_fields('cart_setting');
                do_settings_sections('cart');

                submit_button( '', 'primary', 'submit', true, NULL );
                echo '</form>';

                echo '</div>';

        }

        /*
        *  void : section_menu : void
        *  This method is used to create references to the two most important
        *  methods of the options which are 'input' and 'show'
        */
        function section_menu()
        {
                register_setting(
                        'cart_setting',
                        'xs_options_cart',
                        [$this, 'input']
                );
                add_settings_section(
                        'cart_section',
                        'Settings',
                        [$this, 'show'],
                        'cart'
                );
        }

        /*
        *  array : input : array
        *  This method is used to validate and control the values
        *  passed from the administration page
        *  $input are the values of the administration panel
        */
        function input($input)
        {
                $current = $this->options;

                /* If isset array 'sys' and it's not empty, save all values into current */
                /* FIXME: Maybe take one by one value and check their value? */
                if(isset($input['sys']) && !empty($input['sys']))
                        foreach($input['sys'] as $key => $value)
                                $current['sys'][$key] = $value;

                /* Check if the new discount is set correctly with code and value */
                if(
                        isset($input['discount']) &&
                        !empty($input['discount']['code']) &&
                        !empty($input['discount']['value'])
                ) {
                        /* Make code uppercase to ignore case */
                        $code = strtoupper($input['discount']['code']);
                        /* Add new discount at code */
                        $current['discount'][$code] = $input['discount']['value'];
                }

                /*
                *  if user click to 'remove_discount' button,
                *  remove the discount from the 'discount' array
                */
                /* TODO: Cast value or not? */
                if(isset($input['remove_discount']) && !empty($input['remove_discount']))
                        unset($current['discount'][$input['remove_discount']]);

                return $current;
        }

        /*
        *  void : show : void
        *  This method is used to show and manage the various sections of the options
        */
        function show()
        {
                /*
                *  Create tabs for the various sections and put the current one in $tab
                */
                $tab = xs_framework::create_tabs([
                        'href' => '?page=xsoftware_cart',
                        'tabs' => [
                                'system' => 'System',
                                'discount' => 'Discount'
                        ],
                        'home' => 'system',
                        'name' => 'main_tab'
                ]);
                /*
                *  Switch for the current tab value and call the right method
                */
                switch($tab) {
                        case 'system':
                                $this->show_system();
                                return;
                        case 'discount':
                                $this->show_discount();
                                return;
                }
        }

        /*
        *  void : show_system : void
        *  This method is used to show system options
        */
        function show_system()
        {
                /* Create a html select with wordpress pages URL using {get_wp_pages_link} */
                $options = [
                        'name' => 'xs_options_cart[sys][checkout]',
                        'selected' => $this->options['sys']['checkout'],
                        'data' => xs_framework::get_wp_pages_link(),
                        'default' => 'Select a checkout page',
                        'echo' => TRUE
                ];

                add_settings_field(
                        $options['name'],
                        'Set checkout page',
                        'xs_framework::create_select',
                        'cart',
                        'cart_section',
                        $options
                );

                /* Get all menus object in wordpress searching by 'nav_menu' type */
                $menus = get_terms( 'nav_menu', ['hide_empty' => true] );
                /* Transform into an array as $key = slug and $value = name */
                foreach ($menus as $menu ) {
                        $data_menu[$menu->slug] = $menu->name;
                }

                /* Create a html select with the previous menu array */
                $options = [
                        'name' => 'xs_options_cart[sys][menu]',
                        'selected' => $this->options['sys']['menu'],
                        'data' => $data_menu,
                        'default' => 'Select menu',
                        'echo' => TRUE
                ];

                add_settings_field(
                        $options['name'],
                        'Set Menu where add cart and login',
                        'xs_framework::create_select',
                        'cart',
                        'cart_section',
                        $options
                );
        }

        /*
        *  void : show_discount : void
        *  This method is used to show and manage discount array
        */
        function show_discount()
        {
                /* Get the discount array */
                $discount = $this->options['discount'];

                /* Print all array values with a delete button */
                foreach($discount as $key => $value) {
                        $data[$key][0] = $key;
                        $data[$key][1] = $value;
                        $data[$key][2] = xs_framework::create_button([
                                'class' => 'button-primary',
                                'name' => 'xs_options_cart[remove_discount]',
                                'text' => 'Remove',
                                'value' => $key
                        ]);
                }

                /* Add a last line for add new discount */
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

                /* Print the table */
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