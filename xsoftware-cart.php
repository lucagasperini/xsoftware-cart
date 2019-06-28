<?php
/*
Plugin Name: XSoftware Cart
Description: Cart management on wordpress.
Version: 1.0
Author: Luca Gasperini
Author URI: https://xsoftware.it/
Text Domain: xsoftware_cart
*/

if(!defined("ABSPATH")) die;

include 'xsoftware-cart-options.php';

if (!class_exists('xs_cart_plugin')) :

/*
*  XSoftware Cart Plugin Class
*  The following class is used to execute plugin operations
*/
class xs_cart_plugin
{

        /*
        *  __construct : void
        *  The class constructor does not require any parameters and
        *  initializes the options and hooks for plugin operations
        */
        public function __construct()
        {
                $this->options = get_option('xs_options_cart');

                /* Create a filter to print Add to Cart button in wordpress */
                add_filter('xs_cart_add_html', [$this,'cart_add_html']);
                /* Create a shortcode to print Checkout page in a wordpress page */
                add_shortcode('xs_cart_checkout', [$this,'shortcode_checkout']);
                /* Use @xs_framework_menu_items to print cart menu item */
                add_filter('xs_framework_menu_items', [ $this, 'print_menu_item' ], 2);
                /* Create a filter to show current the sale order */
                add_filter('xs_cart_sale_order_html', [$this,'show_cart_html']);
                /* Create a filter to show payment approved page */
                add_filter('xs_cart_approved_html', [$this,'show_cart_approved_html']);
                /* Create a filter to show empty cart page */
                add_filter('xs_cart_empty_html', [$this,'show_cart_empty_html']);
        }

        function print_menu_item($items)
        {
                $top = xs_framework::insert_nav_menu_item([
                        'title' => '<i class="fas fa-user-circle"></i>',
                        'url' => '',
                        'order' => 100
                ]);

                $items[] = $top;
                $items[] = xs_framework::insert_nav_menu_item([
                        'title' => '<i class="fas fa-shopping-cart"></i><span> Carrello</span>',
                        'url' => $this->options['sys']['checkout'],
                        'order' => 101,
                        'parent' => $top->ID
                ]);

                if(is_user_logged_in()) {
                        $items[] = xs_framework::insert_nav_menu_item([
                                'title' => '<i class="fas fa-sign-out-alt"></i> Logout</span>',
                                'url' => wp_logout_url( home_url() ),
                                'order' => 102,
                                'parent' => $top->ID
                        ]);
                } else {
                        $items[] = xs_framework::insert_nav_menu_item([
                                'title' => '<i class="fas fa-sign-in-alt"></i><span> Login</span>',
                                'url' => wp_login_url( home_url() ),
                                'order' => 102,
                                'parent' => $top->ID
                        ]);
                }

                return $items;
        }

        function cart_add_html($post_id)
        {
                $output = '';

                wp_enqueue_style('xs_cart_item_style', plugins_url('style/item.min.css', __FILE__));

                $btn = xs_framework::create_button([
                        'name' => 'add_cart',
                        'value' => $post_id,
                        'text' => 'Add to Cart'
                ]);
                $qt = xs_framework::create_input_number([
                        'name' => 'qt',
                        'value' => 1
                ]);

                $output .= '<form action="'.$this->options['sys']['checkout'].'" method="get">';
                $output .= xs_framework::create_container([
                        'class' => 'xs_add_cart_container',
                        'obj' => [$btn, $qt],
                        'echo' => FALSE
                ]);
                $output .= '</form>';

                return $output;
        }

        function shortcode_checkout()
        {
                if(isset($_GET['add_cart']) && !empty($_GET['add_cart'])){
                        if(isset($_GET['qt']) && !empty($_GET['qt']) && is_numeric($_GET['qt']))
                                $qt = intval($_GET['qt']);
                        else
                                $qt = 1;

                        $id_item = $_GET['add_cart'];
                        apply_filters( 'xs_cart_add', $id_item, $qt );

                } else if(isset($_GET['success']) && $_GET['success'] === 'true') {

                        $info['payment'] = apply_filters('xs_cart_validate', $_SESSION['xs_cart']);

                        if($info['payment']['state'] === 'approved') {
                                $info += apply_filters( 'xs_cart_approved', $info['payment'] );
                                unset($_SESSION['xs_cart']);
                                if(isset($_SESSION['xs_cart_discount']))
                                        unset($_SESSION['xs_cart_discount']);

                                echo apply_filters('xs_cart_approved_html', $info);
                                return;
                        }

                } else if(isset($_GET['rem_cart']) && !empty($_GET['rem_cart'])) {
                        unset($_SESSION['xs_cart'][$_GET['rem_cart']]);
                }

                if(isset($_GET['discount']) && !empty($_GET['discount'])) {
                        $code = strtoupper($_GET['discount']);
                        if(isset($this->options['discount'][$code]))
                                $_SESSION['xs_cart_discount'] = $this->options['discount'][$code];
                }

                if(!is_user_logged_in()) {
                        $url = wp_login_url($this->options['sys']['checkout']);
                        echo '<script type="text/javascript">
                        window.location.href = "'.$url.'";
                        </script>';
                        exit;
                }
                if(isset($_SESSION['xs_cart']) && !empty($_SESSION['xs_cart'])){
                        $discount = isset($_SESSION['xs_cart_discount']) ?
                                $_SESSION['xs_cart_discount'] :
                                0;
                        $args = [
                                'cart' => $_SESSION['xs_cart'],
                                'discount' => $discount
                        ];
                        $sale_order = apply_filters('xs_cart_sale_order', $args);
                        echo apply_filters('xs_cart_sale_order_html', $sale_order);
                        apply_filters( 'xs_cart_approval_link', $sale_order );
                } else {
                        echo apply_filters('xs_cart_empty_html', NULL);
                }
        }

        function show_cart_empty_html()
        {
                wp_enqueue_style(
                        'xs_cart_checkout_style',
                        plugins_url('style/cart.min.css', __FILE__)
                );

                $output = '';
                $output .= '<h2>The cart is empty!</h2>';
                return $output;
        }

        function show_cart_approved_html($sale_order)
        {
                wp_enqueue_style(
                        'xs_cart_checkout_style',
                        plugins_url('style/cart.min.css', __FILE__)
                );

                $output = '';
                $output .= '<h2>The payment is successfull!</h2>';
                $output .= '<iframe src="'.$sale_order['invoice_url'].'" class="xs_cart_pdf_frame">
                        </iframe>';
                return $output;
        }


        function show_cart_html($sale_order)
        {
                wp_enqueue_style(
                        'xs_cart_checkout_style',
                        plugins_url('style/cart.min.css', __FILE__)
                );

                $output = '';
                $table = array();

                foreach($sale_order['items'] as $id => $values) {
                        $table[$id]['id'] = $values['id'];
                        $table[$id]['name'] = $values['name'];
                        $table[$id]['quantity'] = $values['quantity'];
                        $table[$id]['price'] = $values['price'] . ' ' . $sale_order['currency'];
                        $table[$id]['actions'] = '<a href="?rem_cart='.$values['id'].'">Remove</a>';
                }

                $output .= xs_framework::create_table([
                        'data' => $table,
                        'headers' => [
                                'ID',
                                'Name',
                                'Quantity',
                                'Price',
                                'Actions',
                        ],
                        'echo' => FALSE
                ]);

                $t['subtotal'][0] = 'Subtotal:';
                $t['subtotal'][1] = $sale_order['untaxed'] . ' ' . $sale_order['currency'];
                $t['taxed'][0] = 'Taxed:';
                $t['taxed'][1] = $sale_order['taxed'] . ' ' . $sale_order['currency'];
                $t['total'][0] = 'Total:';
                $t['total'][1] = $sale_order['total'] . ' ' . $sale_order['currency'];
                $output .= xs_framework::create_table([
                        'data' => $t,
                        'echo' => FALSE
                ]);

                $output .= '<form action="" method="GET">';

                $label = '<span>Discount Code:</span>';
                $discount = xs_framework::create_input([
                        'name' => 'discount'
                ]);
                $button = xs_framework::create_button([
                        'text' => 'Apply discount'
                ]);

                $output .= xs_framework::create_container([
                        'class' => 'xs_cart_discount',
                        'obj' => [$label, $discount, $button],
                        'echo' => FALSE
                ]);
                $output .= '</form>';

                return $output;
        }

}

endif;

$xs_cart_plugin = new xs_cart_plugin();

?>