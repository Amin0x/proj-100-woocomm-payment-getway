public function __construct() {
    $this->id                   = 'my-payment-gateway';
    $this->icon                 = plugins_url( '/path/to/image.extension', __FILE__ );
    $this->has_fields           = true;
    $this->method_title         = __( 'My Payment Gateway', 'txdomain' );
    $this->method_description   = __( 'Add some descriptions here.', 'txdomain' );
    $this->init_form_fields();
    $this->init_settings();
    $this->title                = $this->get_option( 'title' );
    $this->description          = $this->get_option( 'description' );

    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
        $this,
        'process_admin_options'
    ) );
    // below is the hook you need for that purpose
    add_action( 'woocommerce_receipt_' . $this->id, array(
        $this,
        'pay_for_order'
    ) );
}

// here, your process payment method, returning the pay for order page
// where you will can submit the form to your payment gateway providers' page
public function process_payment( $order_id ) {
    $order = new WC_Order( $order_id );

    return array(
        'result' => 'success',
        'redirect' => $order->get_checkout_payment_url( true )
    );
}

// here, prepare your form and submit it to the required URL
public function pay_for_order( $order_id ) {
    $order = new WC_Order( $order_id );
    echo '<p>' . __( 'Redirecting to payment provider.', 'txtdomain' ) . '</p>';
    // add a note to show order has been placed and the user redirected
    $order->add_order_note( __( 'Order placed and user redirected.', 'txtdomain' ) );
    // update the status of the order should need be
    $order->update_status( 'on-hold', __( 'Awaiting payment.', 'txtdomain' ) );
    // remember to empty the cart of the user
    WC()->cart->empty_cart();

    // perform a click action on the submit button of the form you are going to return
    wc_enqueue_js( 'jQuery( "#submit-form" ).click();' );

    // return your form with the needed parameters
    return '<form action="' . 'https://example.com' . '" method="post" target="_top">
        <input type="hidden" name="merchant_key" value="">
        <input type="hidden" name="success_url" value="">
        <input type="hidden" name="cancelled_url" value="">
        <input type="hidden" name="deferred_url" value="">
        <input type="hidden" name="invoice_id" value="">
        <input type="hidden" name="total" value="">
        <div class="btn-submit-payment" style="display: none;">
            <button type="submit" id="submit-form"></button>
        </div>
    </form>';
}