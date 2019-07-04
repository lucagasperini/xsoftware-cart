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
        *  string : cart_add_html : int
        *  This method is used to create the add to cart button
        *  $post_id is the current post id to add on cart
        */
        function cart_add_html($post_id)
        {
                /* Initialize string HTML variable */
                $output = '';

                /* Add the css */
                wp_enqueue_style('xs_cart_item_style', plugins_url('style/item.min.css', __FILE__));

                /* Create a button with $post_id as GET value*/
                $btn = xs_framework::create_button([
                        'name' => 'add_cart',
                        'value' => $post_id,
                        'text' => 'Add to Cart'
                ]);
                /* Create a number input as quantity of this item */
                $qt = xs_framework::create_input_number([
                        'name' => 'qt',
                        'value' => 1
                ]);

                /* Create a form on checkout page as GET method */
                $output .= '<form action="'.$this->options['sys']['checkout'].'" method="get">';
                /* Get HTML string as container of css class */
                $output .= xs_framework::create_container([
                        'class' => 'xs_add_cart_container',
                        'obj' => [$btn, $qt],
                        'echo' => FALSE
                ]);
                /* Close the form */
                $output .= '</form>';

                /* Return HTML */
                return $output;
        }

        /*
        *  string : cart_add_html : int
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
                        echo '<script type="text/javascript">
                        window.location.href = "'.$url.'";
                        </script>';
                        exit;
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

        /*
        *  string : cart_add_html : void
        *  This method is used to create the html page for empty cart
        */
        function show_cart_empty_html()
        {
                /* Add the css style */
                wp_enqueue_style(
                        'xs_cart_checkout_style',
                        plugins_url('style/cart.min.css', __FILE__)
                );

                /* Print the HTML */
                $output = '';
                $output .= '<h2>The cart is empty!</h2>';
                /* Return the HTML */
                return $output;
        }

        /*
        *  string : show_cart_approved_html : array
        *  This method is used to create the html page for approved payment
        */
        function show_cart_approved_html($so)
        {
                /* Add the css style */
                wp_enqueue_style(
                        'xs_cart_checkout_style',
                        plugins_url('style/cart.min.css', __FILE__)
                );

                /* Print the HTML */
                $output = '';
                $output .= '<h2>The payment is successfull!</h2>';
                /* Print the invoice pdf on a frame */
                $output .= '<iframe src="'.$so['invoice_url'].'" class="xs_cart_pdf_frame">
                        </iframe>';
                /* Return the HTML */
                return $output;
        }

        /*
        *  string : show_cart_html : array
        *  This method is used to create the html page for no empty cart
        */
        function show_cart_html($so)
        {
                /* Add the css style */
                wp_enqueue_style(
                        'xs_cart_checkout_style',
                        plugins_url('style/cart.min.css', __FILE__)
                );

                /* Print the HTML */
                $output = '';
                /* Create table array */
                $table = array();

                /* Get the currency symbol */
                $symbol = $so['currency_symbol'];

                /* Get the variable for sale order */
                foreach($so['items'] as $id => $values) {
                        $table[$id]['id'] = $values['id'];
                        $table[$id]['name'] = $values['name'];
                        $table[$id]['quantity'] = $values['quantity'];
                        $table[$id]['price'] = $values['price'] . ' ' . $symbol;
                        $table[$id]['actions'] = '<a href="?rem_cart='.$values['id'].'">Remove</a>';
                }

                /* Print the table */
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

                /* Get the global property from sale order */
                $t['subtotal'][0] = 'Subtotal:';
                $t['subtotal'][1] = $so['untaxed'] . ' ' . $symbol;
                $t['taxed'][0] = 'Taxed:';
                $t['taxed'][1] = $so['taxed'] . ' ' . $symbol;
                $t['total'][0] = 'Total:';
                $t['total'][1] = $so['total'] . ' ' . $symbol;
                /* Get the table */
                $output .= xs_framework::create_table([
                        'data' => $t,
                        'echo' => FALSE
                ]);

                /* Get the form for discount code */
                $output .= '<form action="" method="GET">';

                /* Print discount code label and text input */
                $label = '<span>Discount Code:</span>';
                $discount = xs_framework::create_input([
                        'name' => 'discount'
                ]);
                /* Print the button */
                $button = xs_framework::create_button([
                        'text' => 'Apply discount'
                ]);

                /* Print the container */
                $output .= xs_framework::create_container([
                        'class' => 'xs_cart_discount',
                        'obj' => [$label, $discount, $button],
                        'echo' => FALSE
                ]);
                /* Close the form */
                $output .= '</form>';

                /* Return the HTML string */
                return $output;
        }

}

endif;

$xs_cart_plugin = new xs_cart_plugin();

?>