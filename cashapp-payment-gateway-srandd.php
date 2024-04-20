<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the
 * plugin admin area. This file also includes all of the dependencies used by
 * the plugin, registers the activation and deactivation functions, and defines
 * a function that starts the plugin.
 *
 * @link              https://srandd.com
 * @since             1.0.0
 * @package           Cashapp_Payment_for_WooCommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Cashapp payment gateway by SRandD.com
 * Plugin URI:        https://srandd.com/wordpress-plugins/cashapp-payment-gateway
 * Description:       Accept cashapp payments in woocommerce
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Author:            Jerome Stonebridge
 * Author URI:        http://srandd.com
 * 
 * WC requires at least: 5.0.0
 * WC tested up to: 8.5.2
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'add_cashapp_class');
function add_cashapp_class($gateways)
{
  $gateways[] = 'WC_Cashapp'; // your class name is here
  return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'init_cashapp_class');
function init_cashapp_class()
{

  class WC_Cashapp extends WC_Payment_Gateway
  {
    public function __construct()
    {

      $this->id = 'cashapp'; // payment gateway plugin ID
      $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
      $this->has_fields = true; // in case you need a custom credit card form
      $this->method_title = 'Cashapp';
      $this->method_description = 'Accept cashapp payments in woocommerce'; // will be displayed on the options page

      // gateways can support subscriptions, refunds, saved payment methods,
      // but in this tutorial we begin with simple payments
      $this->supports = array(
        'products'
      );

      // Method with all the options fields
      $this->init_form_fields();

      // Load the settings.
      $this->init_settings();
      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');
      $this->enabled = $this->get_option('enabled');
      $this->cashappid = $this->get_option('cashappid');

      // This action hook saves the settings
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

      // We need custom JavaScript to obtain a token
      //add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

      // You can also register a webhook here
      // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
    }

    public function init_form_fields()
    {

      $this->form_fields = array(
        'enabled' => array(
          'title' => 'Enable/Disable',
          'label' => 'Enable Cashapp payments',
          'type' => 'checkbox',
          'description' => '',
          'default' => 'no'
        ),
        'title' => array(
          'title' => 'Title',
          'type' => 'text',
          'description' => 'This controls the title which the user sees during checkout.',
          'default' => 'Cashapp',
          'desc_tip' => true,
        ),
        'description' => array(
          'title' => 'Description',
          'type' => 'textarea',
          'description' => 'This controls the description which the user sees during checkout.',
          'default' => 'Pay with cashapp app',
        ),
        'cashappid' => array(
          'title' => 'Your cashapp id',
          'type' => 'text'
        )
      );
    }


    public function payment_fields()
    {

      // ok, let's display some description before the payment form
      if ($this->description) {
        // display the description with <p> tags etc.
        echo wpautop(wp_kses_post($this->description));
      }

      // I will echo() the form, but you can close PHP tags and print it directly in HTML
      echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-cashapp-form wc-payment-form" style="background:transparent;">';

      // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
      echo '<div class="form-row form-row-wide"><label>Your cashapp id <span class="required">*</span></label>
        <input name="clients_cashapp_id" id="clients_cashapp_id" type="text" autocomplete="off">
        </div>
        <div class="clear"></div>';

      echo 'IMPORTANT: Send payment to <span class="cashapp-id">' . $this->cashappid . '</span> to complete payment.  Include your order id (will be shown on the next page) in the notes for quickest processing.';


      echo '<div class="clear"></div></fieldset>';

    }

    /*
     * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
     */

    public function validate_fields()
    {
      if (empty($_POST['clients_cashapp_id'])) {
        wc_add_notice('Your cashapp id is required!', 'error');
        return false;
      }
      return true;
    }

    /*
     * We're processing the payments here, everything about it is in Step 5
     */
    public function process_payment($order_id)
    {

      global $woocommerce;

      // we need it to get any order detailes
      $order = wc_get_order($order_id);

      $total = $order->get_total();

      $order->reduce_order_stock();

      // some notes to customer (replace true with false to make it private)
      $order->add_order_note('Send ' . $total . ' to ' . $this->cashappid . ' and include order id #' . $order_id . ' to complete your order.  Thank you!', true);
      //$order->add_order_note('Expect payment from ' . $_POST['clients_cashapp_id'], false);

      // Empty cart
      $woocommerce->cart->empty_cart();

      // Redirect to the thank you page
      return array(
        'result' => 'success',
        'redirect' => $this->get_return_url($order)
      );

    }

  }
}


?>