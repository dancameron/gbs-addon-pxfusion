<?php

class Group_Buying_PxFusion extends Group_Buying_Offsite_Processors {

	const API_POST = 'https://sec.paymentexpress.com/pxmi3/pxfusionauth';
	const API_WSDL = 'https://sec.paymentexpress.com/pxf/pxf.svc?wsdl';
	const API_SOAP = 'https://sec.paymentexpress.com/pxf/pxf.svc';

	const CHECKOUT_PAGE = 'pxfusion';
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';
	const API_USERNAME_OPTION = 'gb_pxfusion_username';
	const API_PASSWORD_OPTION = 'gb_pxfusion_password';
	const CURRENCY_CODE_OPTION = 'gb_pxpost_currency';
	const API_MODE_OPTION = 'gb_pxfusion_mode';
	const PAYMENT_METHOD = 'Credit (PxFusion)';
	protected static $instance;
	protected static $px_checkout;
	private $api_mode = self::MODE_TEST;
	private $api_username = '';
	private $api_password = '';

	// SOAP
	protected $soap_client;

	public static function get_instance() {
		if ( !( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function get_api_url() {
		return self::API_POST;
	}

	public function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	protected function __construct() {
		parent::__construct();
		$this->api_username = get_option( self::API_USERNAME_OPTION, '' );
		$this->api_password = get_option( self::API_PASSWORD_OPTION, '' );
		$this->currency_code = get_option( self::CURRENCY_CODE_OPTION, 'NZD' );
		$this->api_mode = get_option( self::API_MODE_OPTION, self::MODE_LIVE );

		// Remove the review page and add the new pages.
		add_action( 'gb_send_offsite_for_payment', array( $this, 'display_payment_page' ), 200, 1 );
		add_filter( 'gb_string_your-purchase-review', array( $this, 'modify_string' ), 10 );

		// Redirect back from Px and finalize purchase
		add_action( 'gb_load_cart', array( $this, 'process_px_payment' ), 10, 2 );
		add_action( 'purchase_completed', array( $this, 'complete_purchase' ), 10, 1 );

		// Settings
		add_action( 'admin_init', array( $this, 'register_settings' ), 10, 0 );

		// Limitations
		add_filter( 'group_buying_template_meta_boxes/deal-expiration.php', array( $this, 'display_exp_meta_box' ), 10 );
		add_filter( 'group_buying_template_meta_boxes/deal-price.php', array( $this, 'display_price_meta_box' ), 10 );
		add_filter( 'group_buying_template_meta_boxes/deal-limits.php', array( $this, 'display_limits_meta_box' ), 10 );
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, self::__( 'PxFusion' ) );
	}

	public static function checkout_icon() {
		return '<img src="http://www.paymentexpress.com/images/logos_white/paymentexpress_png.png" id="payment_express_icon"/>';
	}

	public function modify_string() {
		return gb__('Credit Card Information');
	}

	public function display_payment_page( Group_Buying_Checkouts $checkout ) {

		$cart = $checkout->get_cart();
		if ( $cart->get_total() < 0.01 ) { // for free deals.
			return;
		}

		if ( $_REQUEST['gb_checkout_action'] == Group_Buying_Checkouts::PAYMENT_PAGE ) {
			add_action( 'the_post', array( $this, 'view_checkout' ), 100, 1 );
		}
		
	}


	/**
	 * Update the global $pages array with the HTML for the current checkout page
	 *
	 * @static
	 * @param object  $post
	 * @return void
	 */
	public function view_checkout( $post ) {
		if ( $post->post_type == Group_Buying_Cart::POST_TYPE ) {
			remove_filter( 'the_content', 'wpautop' );
			
			$token_response = self::create_token( Group_Buying_Checkouts::get_instance() );
			if ( !$token_response )
				return;

			ob_start();
				include dirname( __FILE__ ) . '/views/form.php';
			$body = ob_get_clean();

			global $pages;
			$pages = array( $body );
		}
	}

	public function create_token( Group_Buying_Checkouts $checkout ) {

		$filtered_total = $this->get_payment_request_total( $checkout );
		if ( $filtered_total < 0.01 ) {
			return array();
		}

		$this->soap_client = new SoapClient( self::API_WSDL, array( 'soap_version' => SOAP_1_1 ) );

		$trans_detail = array();
		$trans_detail['txnType'] = 'Purchase';
		$trans_detail['currency'] = $this->currency_code;
		$trans_detail['returnUrl'] = add_query_arg( array( 'return' => 'px' ), Group_Buying_Checkouts::get_url() );
		$trans_detail['amount'] = gb_get_number_format( $filtered_total );
		$trans_detail['merchantReference'] = 'GBS Payments';
		$trans_detail['txnRef'] = get_current_user_id().'-'.time();

		$soap_array = array(
			'username' => $this->api_username,
			'password' => $this->api_password,
			'tranDetail' => $trans_detail
		);

		error_log( "soap array: " . print_r( $soap_array, true ) );

		$response = $this->soap_client->GetTransactionId( $soap_array );
		error_log( "response: " . print_r( $response, true ) );

		if ( !$response->GetTransactionIdResult->success ) {
			self::set_message( 'There was a problem getting a transaction id from DPS.', 'error' );
			return FALSE;
		}
		else {
			
			// create an array to return
			$response_array['pxfusion_session_id'] = $response->GetTransactionIdResult->sessionId;
			$response_array['pxfusion_transaction_id'] = $response->GetTransactionIdResult->transactionId;
			$response_array['amount'] = gb_get_number_format( $filtered_total );

			return $response_array;
		}
	}

	public function process_px_payment( Group_Buying_Checkouts $checkout, Group_Buying_Cart $cart ) {

		$transaction_id = isset( $_POST['transaction_id'] ) ? $_POST['transaction_id'] : FALSE;
		error_log( "transaction_id: " . print_r( $transaction_id, true ) );
		$session_id = isset( $_GET['sessionid'] ) ? $_GET['sessionid'] : FALSE;
		error_log( "session_id: " . print_r( $session_id, true ) );
		if ( !$transaction_id && !$session_id ) {
			if ( isset( $_REQUEST['return'] ) && $_REQUEST['return'] == 'px' ) {
				self::set_message('PxFusion Error');
				return;
			}
			return;
		}

		$transaction_id = ( !$transaction_id ) ? $session_id : $transaction_id ;

		// Get transaction object
		$this->soap_client = new SoapClient( self::API_WSDL, array( 'soap_version' => SOAP_1_1 ) );

		$array_for_soap = array(
			'username' => $this->api_username,
			'password' => $this->api_password,
			'transactionId' => $transaction_id
		);
		error_log( "array for soap: " . print_r( $array_for_soap , true ) );
		$response = $this->soap_client->GetTransaction( $array_for_soap );
		$transaction_details = get_object_vars( $response->GetTransactionResult );
		error_log( "return transaction details: " . print_r( $transaction_details, true ) );

		if ( $transaction_details['status'] != '0'  ) {
			switch ( $transaction_details['status'] ) {
				case '1':
					$message = 'Transaction declined.';
					break;
				case '2':
					$message = 'Transaction declined due to transient error (retry advised).';
					break;
				case '3':
					$message = 'Invalid data submitted in form post (alert site admin).';
					break;
				case '4':
					$message = 'Transaction result cannot be determined at this time (re-run GetTransaction).';
					break;
				case '5':
					$message = 'Transaction did not proceed due to being attempted after timeout timestamp or having been cancelled 
by a CancelTransaction call.';
					break;
				case '6':
					$message = 'No transaction found (SessionId query failed to return a transaction record – transaction not yet 
attempted).';
					break;
				default:
					$message = 'Transaction declined.';
					break;
			}
			$message = ( $transaction_details['responseText'] != '' ) ? $transaction_details['responseText'] : $message ;
			self::set_message( $message );
			return;
		}

		$checkout->cache['transaction_details'] = $transaction_details;

		// Complete
		add_filter( 'gb_checkout_pages', array($this, 'remove_checkout_pages') );
		$_REQUEST['gb_checkout_action'] = 'back_from_pg';
	}

	public function remove_checkout_pages( $pages ) {
		unset($pages[Group_Buying_Checkouts::PAYMENT_PAGE]);
		unset($pages[Group_Buying_Checkouts::REVIEW_PAGE]);
		return $pages;
	}


	/**
	 * Process a payment
	 *
	 * @param Group_Buying_Checkouts $checkout
	 * @param Group_Buying_Purchase $purchase
	 * @return Group_Buying_Payment|bool FALSE if the payment failed, otherwise a Payment object
	 */
	public function process_payment( Group_Buying_Checkouts $checkout, Group_Buying_Purchase $purchase ) {
		if ( $purchase->get_total( $this->get_payment_method() ) < 0.01 ) {
			// Nothing to do here, another payment handler intercepted and took care of everything
			// See if we can get that payment and just return it
			$payments = Group_Buying_Payment::get_payments_for_purchase( $purchase->get_id() );
			foreach ( $payments as $payment_id ) {
				$payment = Group_Buying_Payment::get_instance( $payment_id );
				return $payment;
			}
		}
		
		/*
		 * Purchase since payment was successful above.
		 */
		$deal_info = array(); // creating purchased products array for payment below
		foreach ( $purchase->get_products() as $item ) {
			if ( isset( $item['payment_method'][$this->get_payment_method()] ) ) {
				if ( !isset( $deal_info[$item['deal_id']] ) ) {
					$deal_info[$item['deal_id']] = array();
				}
				$deal_info[$item['deal_id']][] = $item;
			}
		}
		if ( isset( $checkout->cache['shipping'] ) ) {
			$shipping_address = array();
			$shipping_address['first_name'] = $checkout->cache['shipping']['first_name'];
			$shipping_address['last_name'] = $checkout->cache['shipping']['last_name'];
			$shipping_address['street'] = $checkout->cache['shipping']['street'];
			$shipping_address['city'] = $checkout->cache['shipping']['city'];
			$shipping_address['zone'] = $checkout->cache['shipping']['zone'];
			$shipping_address['postal_code'] = $checkout->cache['shipping']['postal_code'];
			$shipping_address['country'] = $checkout->cache['shipping']['country'];
		}
		$transaction_details = $checkout->cache['transaction_details'];
		$payment_id = Group_Buying_Payment::new_payment( array(
				'payment_method' => $this->get_payment_method(),
				'purchase' => $purchase->get_id(),
				'amount' => $transaction_details,
				'data' => array(
					'api_response' => $transaction_details
				),
				'deals' => $deal_info,
				'shipping_address' => $shipping_address,
			), Group_Buying_Payment::STATUS_AUTHORIZED );
		if ( !$payment_id ) {
			return FALSE;
		}
		$payment = Group_Buying_Payment::get_instance( $payment_id );
		do_action( 'payment_authorized', $payment );
		return $payment;
	}

	/**
	 * Complete the purchase after the process_payment action, otherwise vouchers will not be activated.
	 *
	 * @param Group_Buying_Purchase $purchase
	 * @return void
	 */
	public function complete_purchase( Group_Buying_Purchase $purchase ) {
		$items_captured = array(); // Creating simple array of items that are captured
		foreach ( $purchase->get_products() as $item ) {
			$items_captured[] = $item['deal_id'];
		}
		$payments = Group_Buying_Payment::get_payments_for_purchase( $purchase->get_id() );
		foreach ( $payments as $payment_id ) {
			$payment = Group_Buying_Payment::get_instance( $payment_id );
			do_action( 'payment_captured', $payment, $items_captured );
			do_action( 'payment_complete', $payment );
			$payment->set_status( Group_Buying_Payment::STATUS_COMPLETE );
		}
	}

	/**
	 * Grabs error messages from a Authorize response and displays them to the user
	 *
	 * @param array   $response
	 * @param bool    $display
	 * @return void
	 */
	private function set_error_messages( $response, $display = TRUE ) {
		if ( $display ) {
			self::set_message( $response, self::MESSAGE_STATUS_ERROR );
		} else {
			error_log( $response );
		}
	}

	public function register_settings() {
		$page = Group_Buying_Payment_Processors::get_settings_page();
		$section = 'gb_pxfusion_settings';
		add_settings_section( $section, self::__( 'PxFusion' ), array( $this, 'display_settings_section' ), $page );
		register_setting( $page, self::API_MODE_OPTION );
		register_setting( $page, self::API_USERNAME_OPTION );
		register_setting( $page, self::API_PASSWORD_OPTION );
		register_setting( $page, self::CURRENCY_CODE_OPTION );
		add_settings_field( self::API_MODE_OPTION, self::__( 'Mode' ), array( $this, 'display_api_mode_field' ), $page, $section );
		add_settings_field( self::API_USERNAME_OPTION, self::__( 'API Login (Username)' ), array( $this, 'display_api_username_field' ), $page, $section );
		add_settings_field( self::API_PASSWORD_OPTION, self::__( 'Transaction Key (Password)' ), array( $this, 'display_api_password_field' ), $page, $section );
		add_settings_field( null, self::__( 'Currency' ), array( $this, 'display_currency_code_field' ), $page, $section );
	}

	public function display_api_username_field() {
		echo '<input type="text" name="'.self::API_USERNAME_OPTION.'" value="'.$this->api_username.'" size="80" />';
	}

	public function display_api_password_field() {
		echo '<input type="text" name="'.self::API_PASSWORD_OPTION.'" value="'.$this->api_password.'" size="80" />';
	}

	public function display_api_mode_field() {
		echo '<label><input type="radio" name="'.self::API_MODE_OPTION.'" value="'.self::MODE_LIVE.'" '.checked( self::MODE_LIVE, $this->api_mode, FALSE ).'/> '.self::__( 'Live' ).'</label><br />';
		echo '<label><input type="radio" name="'.self::API_MODE_OPTION.'" value="'.self::MODE_TEST.'" '.checked( self::MODE_TEST, $this->api_mode, FALSE ).'/> '.self::__( 'Sandbox' ).'</label>';
	}

	public function display_currency_code_field() {
		echo '<input type="text" name="'.self::CURRENCY_CODE_OPTION.'" value="'.$this->currency_code.'" size="3" />';
	}

	public function display_exp_meta_box() {
		return GB_PATH . '/controllers/payment_processors/meta-boxes/exp-only.php';
	}

	public function display_price_meta_box() {
		return GB_PATH . '/controllers/payment_processors/meta-boxes/no-dyn-price.php';
	}

	public function display_limits_meta_box() {
		return GB_PATH . '/controllers/payment_processors/meta-boxes/no-tipping.php';
	}
}
Group_Buying_PxFusion::register();
