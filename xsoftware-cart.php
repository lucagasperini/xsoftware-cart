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

                /* Create a shortcode to print Checkout page in a wordpress page */
                add_shortcode('xs_cart_checkout', [$this,'shortcode_checkout']);
                /* Use @xs_framework_menu_items to print cart menu item */
                add_filter('xs_framework_menu_items', [ $this, 'print_menu_item' ], 2);
        }

        /*
        *  array : print_menu_item : array
        *  This method is used to create the menu items
        *  using menu class build in on wordpress
        *  $items are the menu class defined on this wordpress installation
        */
        function print_menu_item($items)
        {
                /* Add a parent menu item for user */
                $top = xs_framework::insert_nav_menu_item([
                        'title' => '<i class="fas fa-user-circle"></i>',
                        'url' => '',
                        'order' => 100
                ]);
                /* Append this menu on input array */
                $items[] = $top;

                /* Add a child menu item for shopping cart */
                $items[] = xs_framework::insert_nav_menu_item([
                        'title' => '<i class="fas fa-shopping-cart"></i><span> Cart</span>',
                        'url' => $this->options['sys']['checkout'],
                        'order' => 101,
                        'parent' => $top->ID
                ]);

                /* If user is logged print Logout item, else Login item*/
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

                /* Return modify menu class array */
                return $items;
        }

        /*
        *  string : shortcode_checkout : void
        *  This method is used to create the checkout page and it's called by shortcode
        */
        function shortcode_checkout()
        {
                /* Check if is called add_cart operation */
                if(isset($_GET['add_cart']) && !empty($_GET['add_cart'])){
                        /* Check if is defined a quantity, if not quantity is 1 */
                        if(isset($_GET['qt']) && !empty($_GET['qt']) && is_numeric($_GET['qt']))
                                $qt = intval($_GET['qt']);
                        else
                                $qt = 1;

                        /* Get the post_id from add cart */
                        $id_item = $_GET['add_cart'];
                        /* Call xs_cart_add filter */
                        apply_filters( 'xs_cart_add', $id_item, $qt );

                /* Check if the payment is successful */
                } else if(!empty($_SESSION['xs_cart']) && $_GET['success'] === 'true') {

                        /* Call the validation of payment with xs_cart_validate filter */
                        $info = apply_filters('xs_cart_validate', $_SESSION['xs_cart']);

                        /* Check if the status is approved */
                        if($info['payment']['state'] === 'approved') {
                                /* Call the xs_cart_approved filter */
                                $info = apply_filters( 'xs_cart_approved', $info );
                                $info['invoice']['pdf'] = apply_filters(
                                        'xs_cart_invoice_pdf_print',
                                        $info
                                );
                                apply_filters('xs_cart_invoice_pdf',$info['invoice']);

                                /* Remove the cart from session */
                                unset($_SESSION['xs_cart']);
                                /* Remove the discount if is set */
                                if(isset($_SESSION['xs_cart_discount']))
                                        unset($_SESSION['xs_cart_discount']);

                                /* Call the HTML of approved payment with xs_cart_approved_html */
                                echo apply_filters('xs_cart_approved_html', $info);
                                return;
                        }

                /* Check if is called rem_cart operation */
                } else if(isset($_GET['rem_cart']) && !empty($_GET['rem_cart'])) {
                        /* Remove the item from cart */
                        unset($_SESSION['xs_cart'][$_GET['rem_cart']]);
                } else if(
                        isset($_GET['invoice']) &&
                        !empty($_GET['invoice']) &&
                        is_numeric($_GET['invoice'])
                ) {
                        $info = apply_filters('xs_cart_get_invoice', intval($_GET['invoice']));
                        echo apply_filters('xs_cart_show_invoice_html', $info);
                        return;
                }
                /* Check if is called discount operation */
                if(isset($_GET['discount']) && !empty($_GET['discount'])) {
                        /* Make the discount code uppercase to ignore case */
                        $code = strtoupper($_GET['discount']);
                        /* Check if the discount code exists, if so add on session */
                        if(isset($this->options['discount'][$code]))
                                $_SESSION['xs_cart_discount'] = $this->options['discount'][$code];
                }

                /* Check if the user is logged, if not redirect to login URL */
                if(!is_user_logged_in()) {
                        $url = wp_login_url($this->options['sys']['checkout']);
                        /*echo '<script type="text/javascript">
                        window.location.href = "'.$url.'";
                        </script>';
                        exit;*/
                }
                /* Check if cart session is set and not empty to show on html checkout page */
                if(isset($_SESSION['xs_cart']) && !empty($_SESSION['xs_cart'])) {
                        /* Check if is present a discount % */
                        $discount = isset($_SESSION['xs_cart_discount']) ?
                                $_SESSION['xs_cart_discount'] :
                                0;
                        /* Create the cart structure */
                        $args = [
                                'cart' => $_SESSION['xs_cart'],
                                'discount' => $discount
                        ];
                        /* Get a sale order by xs_cart_sale_order filter */
                        $so = apply_filters('xs_cart_sale_order', $args);
                        /* Print the sale order by xs_cart_sale_order_html filter */
                        echo apply_filters('xs_cart_sale_order_html', $so);
                        /* Print the payment link */
                        apply_filters( 'xs_cart_approval_link', $so );
                /* If cart is empty or is not set print the html page for empty cart */
                } else {
                        echo apply_filters('xs_cart_empty_html', NULL);
                }
        }

}

endif;

$xs_cart_plugin = new xs_cart_plugin();

?>