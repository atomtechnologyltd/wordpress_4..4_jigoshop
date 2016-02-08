<?php
/*
Plugin Name: Atom Payment Gateway
Plugin URI: http://atomtech.in/
Description: Extends WooCommerce by Adding the Atom Paynetz Gateway.
Version: 1.0
Author: Atom
Author URI: http://atomtech.in/
*/
 
// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'jigoshop_atom_inits', 0 );
define('IMGDIR', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function jigoshop_atom_inits() {
  
  add_filter('jigoshop_payment_gateways', function ($methods){
	$methods[] = 'paynetz';

	return $methods;
	});
	
	 class paynetz extends jigoshop_payment_gateway {
		 
		 function __construct() {
			 parent::__construct();
			 
			 
			$options = Jigoshop_Base::get_options();
			$this->id			= 'atom';
			$this->icon = IMGDIR . 'logo.png';
			$this->has_fields = false;
			$this->enabled = $options->get('jigoshop_atom_enabled');
			$this->urls = $options->get('jigoshop_atom_url');
			$this->merchant_id = $options->get('jigoshop_atom_merchant_id');
			$this->atom_password = $options->get('jigoshop_atom_password');
			$this->product_id = $options->get('jigoshop_atom_product_id');
			$this->port = $options->get('jigoshop_atom_port');
			$this->ssl_version = $options->get('jigoshop_atom_ssl');	
			$this->notify_url = jigoshop_request_api::query_request('?js-api=JS_Gateway_Paynetz', false);

			// add_action('jigoshop_settings_scripts', array($this, 'admin_scripts'));
			

			add_action('jigoshop_api_js_gateway_paynetz', array($this, 'check_ipn_response'));
			add_action('receipt_paynetz', array($this, 'receipt_page'));
			add_action('init', array($this, 'legacy_ipn_response'));
			 
			 
		 }
		 
		 
		function legacy_ipn_response()
		{
			if (!empty($_GET['paynetzListener']) && $_GET['paynetzListener'] == 'paynetz_standard_IPN') {
				do_action('jigoshop_api_js_gateway_paynetz');
			}
			
		}
		function receipt_page($order)
		{
			echo '<p>'.__('Thank you for your order, please click the button below to pay with Atom.', 'jigoshop').'</p>';
			//echo $this->generate_paypal_form($order);
		}
		function check_ipn_response()
		{
					
			@ob_clean();

			if (!empty($_POST)  /*&&$this->check_ipn_request_is_valid()*/) {
				header('HTTP/1.1 200 OK');
				$this->successful_request($_POST);
			} else {
				wp_die('Atom IPN Request Failure');
			}
		}
		function successful_request( $posted ) {

			$checkout_redirect = apply_filters( 'jigoshop_get_checkout_redirect_page_id', jigoshop_get_page_id('thanks') );
			
				if ( !empty($posted['f_code']) ) {

					$order = new jigoshop_order( (int) $posted['mer_txn'] );
						
					if ($order->status !== 'completed') :
					  
						switch ($posted['f_code']) :
						
							case 'Ok' : $order->add_order_note(__('Atom Standard payment completed', 'jigoshop'));
										$order->payment_complete();  
										wp_safe_redirect( add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order_id, get_permalink( jigoshop_get_page_id('thanks') ) ) ) );
										exit;
							// case '0' : // Pending	
							// case '-2' : // Failed
								// $order->update_status('on-hold', sprintf(__('Atom payment failed (%s)', 'jigoshop'), strtolower($posted['status']) ) );
							// break;
							case 'F' : 
								$order->update_status('cancelled', __('Atom payment cancelled', 'jigoshop'));
							break;
							default:
								$order->update_status('cancelled', __('Atom exception', 'jigoshop'));
							break;
						endswitch;
					endif;
			
					exit;

				}

		}
		function process_payment($order_id){
			
			$order = new jigoshop_order( $order_id );
						
			$current_user	= wp_get_current_user();
			
			$user_email     = $order->billing_email;
			$first_name     = $order->billing_first_name;
			$last_name      = $order->billing_last_name;
			$phone_number   = $order->billing_phone;
			$country       	= $order->billing_country;
			$state       	= $order->billing_state;
			$city       	= $order->billing_city;
			$postcode       = $order->shipping_postcode;
			$address_1      = $order->shipping_address_1;
			$address_2      = $order->shipping_address_2;
			$udf1 			= $first_name." ".$last_name;
			$udf2			= $user_email;
			$udf3			= $phone_number;
			$udf4			= $country." ".$state." ".$city." ".$address_1." ".$address_2." ".$postcode;

			if($user_email == ''){
				$user_email 	= $_POST['billing_email'];
				$first_name 	= $_POST['billing_first_name'];
				$last_name  	= $_POST['billing_last_name'];
				$phone_number 	= $_POST['billing_phone'];
				$country       	= $_POST['billing_country'];
				$state       	= $_POST['billing_state'];
				$city       	= $_POST['billing_city'];
				$postcode       = $_POST['billing_postcode'];
				$address_1      = $_POST['billing_address_1'];
				$address_2      = $_POST['billing_address_2'];
				$udf1 		= $first_name." ".$last_name;
				$udf2		= $user_email;
				$udf3		= $phone_number;
				$udf4		= $country." ".$state." ".$city." ".$address_1." ".$address_2." ".$postcode;
			}
		
			$atom_login_id 	= $this->merchant_id;
			$atom_password 	= $this->atom_password;
			$atom_prod_id 	= $this->product_id;
			$amount 		= $order->order_total;
			$currency 		= "INR";
			$custacc 		= "1234567890";
			$txnid 			= $order_id;    
			$clientcode 	= urlencode(base64_encode(007));
			$datenow 		= date("d/m/Y");
			$encodedDate 	= str_replace(" ", "%20", $datenow);
			$ru 			= $this->notify_url;
			
		  $param = "&login=".$atom_login_id."&pass=".$atom_password."&ttype=NBFundTransfer"."&prodid=".$atom_prod_id."&amt=".$amount."&txncurr=".$currency."&txnscamt=0"."&clientcode=".$clientcode."&txnid=".$txnid."&date=".$datenow ."&custacc=".$custacc."&udf1=".$udf1."&udf2=".$udf2."&udf3=".$udf3."&udf4=".$udf4."&ru=".$ru;
		
			$ch = curl_init();
			$useragent = 'jigoshop plugin';	
			curl_setopt($ch, CURLOPT_URL, $this->urls);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_PORT , 443);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
			curl_setopt($ch,CURLOPT_SSLVERSION, 3);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
				
			$response = curl_exec ($ch); 
			
			if(curl_errno($ch))
			{	
				echo '<div class="jigoshop-error">Curl error: "'. curl_error($ch).". Error in gateway credentials.</div>";
				die;
			}
			curl_close($ch);

			$parser = xml_parser_create('');
			xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
			xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
			xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
			xml_parse_into_struct($parser, trim($response), $xml_values);
			xml_parser_free($parser);

			$returnArray = array();
			$returnArray['url'] = $xml_values[3]['value'];
			$returnArray['tempTxnId'] = $xml_values[5]['value'];
			$returnArray['token'] = $xml_values[6]['value'];    
			
			//code to generate form action
			$xmlObjArray = $returnArray;
			$url = $xmlObjArray['url'];
			
			$postFields  = "";
			$postFields .= "&ttype=NBfundTransfer";
			$postFields .= "&tempTxnId=".$xmlObjArray['tempTxnId'];
			$postFields .= "&token=".$xmlObjArray['token'];
			$postFields .= "&txnStage=1";
			$q = $url."?".$postFields;
			
			header("location:".$q);exit;
			
		 }
		 
		protected function get_default_options()
		{
			return array(
				array(
					'name' => sprintf(__('Atom Payment %s', 'jigoshop'), '<img style="vertical-align:middle;margin-top:-4px;margin-left:10px;" src="'.IMGDIR.'logo.png" alt="Atom">'),
					'type' => 'title',
					'desc' => __('Atom Standard works by sending the user to <a href="https://www.atomtech.in/">Atom</a> to enter their payment information.', 'jigoshop')
				),
				array(
					'name' => __('Enable Atom Payment', 'jigoshop'),
					'desc' => '',
					'tip' => '',
					'id' => 'jigoshop_atom_enabled',
					'std' => 'yes',
					'type' => 'checkbox',
					'choices' => array(
						'no' => __('No', 'jigoshop'),
						'yes' => __('Yes', 'jigoshop')
					)
				),
				array(
					'name' => __('Payment URL', 'jigoshop'),
					'desc' => '',
					'tip' => __('Please enter Payment URL of the Atom website.', 'jigoshop'),
					'id' => 'jigoshop_atom_url',
					'std' => __('https://paynetzuat.atomtech.in/paynetz/epi/fts', 'jigoshop'),
					'type' => 'longtext'
				),
				array(
					'name' => __('Vendor', 'jigoshop'),
					'desc' => '',
					'tip' => __('Please enter Merchant Id of the Atom website.', 'jigoshop'),
					'id' => 'jigoshop_atom_merchant_id',
					'std' => __("160", 'jigoshop'),
					'type' => 'longtext'
				),
				array(
					'name' => __('Password', 'jigoshop'),
					'desc' => '',
					'tip' => __('Please enter Password', 'jigoshop'),
					'id' => 'jigoshop_atom_password',
					'std' => '',
					'type' => 'longtext'
				),
				array(
					'name' => __('Product Id', 'jigoshop'),
					'desc' => '',
					'tip' => __('Please enter Product Id', 'jigoshop'),
					'id' => 'jigoshop_atom_product_id',
					'std' => 'NSE',
					'type' => 'longtext'
				),array(
					'name' => __('Port', 'jigoshop'),
					'desc' => '',
					'tip' => __('Please enter Port', 'jigoshop'),
					'id' => 'jigoshop_atom_port',
					'std' => '443',
					'type' => 'longtext'
				),
				array(
					'name' => __('SSL Version', 'jigoshop'),
					'desc' => '',
					'tip' => __('EX. 1 to 6', 'jigoshop'),
					'id' => 'jigoshop_atom_ssl',
					'std' => '443',
					'type' => 'longtext'
				),
					
			);
		}
	 }
}