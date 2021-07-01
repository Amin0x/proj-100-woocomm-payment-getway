<?php
/*
Plugin Name: Amin Payment Getway
Plugin URI: https://
Description:  Amin Payment Getway
Author: Amin Omer
Author URI: github.com/amin0x
*/

// Add a menu for our option page
add_action('admin_menu', 'pdev_plugin_add_settings_menu');

function pdev_plugin_add_settings_menu()
{
	add_options_page('PDEV Plugin Settings', 'PDEV Settings', 'manage_options', 'pdev_plugin', 'pdev_plugin_option_page');
}


// Create the option page
function pdev_plugin_option_page()
{
?>
	<div class="wrap">
		<h2>My plugin</h2>
		<form action="options.php" method="post">
			<?php
			settings_fields('pdev_plugin_options');
			do_settings_sections('pdev_plugin');
			submit_button('Save Changes', 'primary');
			?>
		</form>
	</div>
	Amin Payment.
<?php
}


// Register and define the settings
add_action('admin_init', 'pdev_plugin_admin_init');

function pdev_plugin_admin_init()
{
	$args = array(
		'type' => 'string',
		'sanitize_callback' => 'pdev_plugin_validate_options',
		'default' => NULL
	);
	// Register our settings
	register_setting('pdev_plugin_options', 'pdev_plugin_options', $args);

	// Add a settings section
	add_settings_section(
		'pdev_plugin_main',
		'PDEV Plugin Settings',
		'pdev_plugin_section_text',
		'pdev_plugin'
	);

	// Create our settings field for name
	add_settings_field(
		'pdev_plugin_name',
		'Your Name',
		'pdev_plugin_setting_name',
		'pdev_plugin',
		'pdev_plugin_main'
	);
}

// Draw the section header
function pdev_plugin_section_text()
{
	echo '<p>Enter your settings here.</p>';
}

// Display and fill the Name form field
function pdev_plugin_setting_name()
{
	// get option 'text_string' value from the database
	$options = get_option('pdev_plugin_options');
	$name = $options['name'];
	// echo the field
	echo "<input id='name' name='pdev_plugin_options[name]'
 type='text' value='" . esc_attr($name) . "'/>";
}

// Validate user input (we want text and spaces only)
function pdev_plugin_validate_options($input)
{
	$valid = array();
	$valid['name'] = preg_replace(
		'/[^a-zA-Z\s]/',
		'',
		$input['name']
	);
	return $valid;
}

add_filter('woocommerce_payment_gateways', 'aminpay_add_gateway_class');
function aminpay_add_gateway_class($gateways)
{
	$gateways[] = 'WC_AminPay_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'aminpay_init_gateway_class');
function aminpay_init_gateway_class()
{

	class WC_AminPay_Gateway extends WC_Payment_Gateway
	{

		/**
		 * Class constructor, more about it in Step 3
		 */
		public function __construct()
		{

			$this->id = 'aminpay'; // payment gateway plugin ID
			$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = false; // in case you need a custom credit card form
			$this->method_title = 'AminPay Gateway';
			$this->method_description = 'Amin payment gateway, Private Payment Gateway Used Only With http://domin.com. '; // will be displayed on the options page

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
			$this->testmode = 'yes' === $this->get_option('testmode');
			$this->store_name = $this->get_option('store_name');
			$this->shared_secret = $this->testmode ? $this->get_option('test_shared_secret') : $this->get_option('shared_secret');

			
			$this->endpoint = $this->testmode ? 'https://test.ipg-pay.com/payment/prosessing?' : 'https://ipg-pay.com/payment/prosessing?';
			

			// This action hook saves the settings
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

			// We need custom JavaScript to obtain a token
			add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

			// You can also register a webhook here
			// add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );

		}

		/**
		 * Plugin options, we deal with it in Step 3 too
		 */
		public function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Amin Payment Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Connect (Fiserv)',
					'desc_tip'    => true,
				),
				'store_name' => array(
					'title'       => 'Store Name (ID)',
					'type'        => 'text'
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your credit card via Fiserv payment gateway.',
				),
				'testmode' => array(
					'title'       => 'Test mode',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Place the payment gateway in test mode using test API keys.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'shared_secret' => array(
					'title'       => 'Live Shared Secret',
					'type'        => 'text'
				),
				'test_shared_secret' => array(
					'title'       => 'Test Shared Secret',
					'type'        => 'text'
				)
			);
		}

		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields()
		{

			parent::payment_fields();
			/*
			// ok, let's display some description before the payment form
			if ($this->description) {
				// you can instructions for test mode, I mean test card numbers etc.
				if ($this->testmode) {
					$this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#">documentation</a>.';
					$this->description  = trim($this->description);
				}
				// display the description with <p> tags etc.
				echo wpautop(wp_kses_post($this->description));
			}

			// I will echo() the form, but you can close PHP tags and print it directly in HTML
			echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

			// Add this action hook if you want your custom payment gateway to support it
			do_action('woocommerce_credit_card_form_start', $this->id);

			// I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
			echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
					<input id="misha_ccNo" type="text" autocomplete="off">
					</div>
					<div class="form-row form-row-first">
						<label>Expiry Date <span class="required">*</span></label>
						<input id="misha_expdate" type="text" autocomplete="off" placeholder="MM / YY">
					</div>
					<div class="form-row form-row-last">
						<label>Card Code (CVC) <span class="required">*</span></label>
						<input id="misha_cvv" type="password" autocomplete="off" placeholder="CVC">
					</div>
					<div class="clear"></div>';

			do_action('woocommerce_credit_card_form_end', $this->id);

			echo '<div class="clear"></div></fieldset>';
			*/
		}

		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
		public function payment_scripts()
		{
		}

		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields()
		{
		}

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment($order_id)
		{
			$order = wc_get_order( $order_id );

			$args = array();
			$args['txntype'] = 'sale';
			$args['timezone'] = 'Europe/Berlin';
			$args['txndatetime'] = $this->get_time_date();
			$args['hash_algorithm'] = 'HMACSHA256';
			$args['hashExtended'] = $this->calc_hash_alog();
			$args['storename'] = $this->get_option('store_name');
			$args['mode'] = '';
			$args['chargetotal'] = '';
			$args['currency'] = '';
			$args['oid'] = '';
			$args['ipgTransactionId'] = '';

			//*** Optional Form Fields ***//

			// This field allows you to set the checkout option to: 
			// ‘classic’ for a payment process that is split into multiple pages,
			// ‘combinedpage’ for a payment process where the payment
			// method choice and the typical next step (e.g. entry of card details
			// or selection of bank) in consolidated in a single page.
			$args['checkoutoption'] = 'combinedpage';

			// This parameter can be used to override the default payment page language
			// configured for your merchant store.
			$args['language'] = 'en_US';



			// Timezeone needs to be set
			date_default_timezone_set($args['timezone']);
			$dateTime = date("Y:m:d-H:i:s");
			$args['txndatetime'] = $dateTime;


			$separator = "|";
			$stringToHash = $args['storename'] . $separator . $args['txndatetime'] . $separator . $args['chargetotal'] . $separator . $args['currency'];
			$hashSHA256 = CryptoJS . HmacSHA384($stringToHash, $this->testmode ? $this->get_option('test_shared_secret') : $this->get_option('shared_secret'));
			$args['hashExtended'] = CryptoJS . enc . Base64 . stringify($hashSHA256);

			
			return array(
				'result'   => 'success',
				'redirect' => $this->endpoint . http_build_query( $args, '', '&' ),
			);
		}

		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook()
		{
		}
	} //WC_Misha_Gateway
}
