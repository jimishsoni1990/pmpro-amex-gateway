<?php
	//load classes init method
	add_action('init', array('PMProGateway_example', 'init'));

	/**
	 * PMProGateway_gatewayname Class
	 *
	 * Handles example integration.
	 *
	 */
	class PMProGateway_example extends PMProGateway
	{
		function PMProGateway($gateway = NULL)
		{
			$this->gateway = $gateway;
			return $this->gateway;
		}

		/**
		 * Run on WP init
		 *
		 * @since 1.8
		 */
		static function init()
		{
			//make sure example is a gateway option
			add_filter('pmpro_gateways', array('PMProGateway_example', 'pmpro_gateways'));

			//add fields to payment settings
			add_filter('pmpro_payment_options', array('PMProGateway_example', 'pmpro_payment_options'));
			add_filter('pmpro_payment_option_fields', array('PMProGateway_example', 'pmpro_payment_option_fields'), 10, 2);

			//add some fields to edit user page (Updates)
			add_action('pmpro_after_membership_level_profile_fields', array('PMProGateway_example', 'user_profile_fields'));
			add_action('profile_update', array('PMProGateway_example', 'user_profile_fields_save'));

			//updates cron
			add_action('pmpro_activation', array('PMProGateway_example', 'pmpro_activation'));
			add_action('pmpro_deactivation', array('PMProGateway_example', 'pmpro_deactivation'));
			add_action('pmpro_cron_example_subscription_updates', array('PMProGateway_example', 'pmpro_cron_example_subscription_updates'));

			//code to add at checkout if example is the current gateway
			$gateway = pmpro_getOption("gateway");
			if($gateway == "example")
			{
				add_filter('pmpro_include_billing_address_fields', '__return_false');
				add_filter('pmpro_include_payment_information_fields', array('PMProGateway_example', 'pmpro_include_payment_information_fields'));
				add_action('pmpro_checkout_preheader', array('PMProGateway_example', 'pmpro_checkout_preheader'));
				add_filter('pmpro_checkout_order', array('PMProGateway_example', 'pmpro_checkout_order'));
				add_filter('pmpro_include_cardtype_field', array('PMProGateway_example', 'pmpro_include_billing_address_fields'));
				add_filter('pmpro_required_billing_fields', array('PMProGateway_example', 'pmpro_required_billing_fields'));
				add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_example', 'pmpro_checkout_default_submit_button'));
			}
		}

		/**
 * Remove required billing fields
 *
 * @since 1.8
 */
static function pmpro_required_billing_fields($fields)
{
	unset($fields['bfirstname']);
	unset($fields['blastname']);
	unset($fields['baddress1']);
	unset($fields['bcity']);
	unset($fields['bstate']);
	unset($fields['bzipcode']);
	unset($fields['bphone']);
	unset($fields['bemail']);
	unset($fields['bcountry']);
	unset($fields['CardType']);
	unset($fields['AccountNumber']);
	unset($fields['ExpirationMonth']);
	unset($fields['ExpirationYear']);
	unset($fields['CVV']);

	return $fields;
}

		/**
		 * Make sure example is in the gateways list
		 *
		 * @since 1.8
		 */
		static function pmpro_gateways($gateways)
		{
			if(empty($gateways['example']))
				$gateways['example'] = __('AMEX', 'pmpro');

			return $gateways;
		}

		/**
		 * Get a list of payment options that the example gateway needs/supports.
		 *
		 * @since 1.8
		 */
		static function getGatewayOptions()
		{
			$options = array(
				'sslseal',
				'nuclear_HTTPS',
				'gateway_environment',
				'amex_merchant_id',
				'currency',
				'use_ssl',
				'tax_state',
				'tax_rate',
				'accepted_credit_cards'
			);

			return $options;
		}

		/**
		 * Set payment options for payment settings page.
		 *
		 * @since 1.8
		 */
		static function pmpro_payment_options($options)
		{
			//get example options
			$example_options = PMProGateway_example::getGatewayOptions();

			//merge with others.
			$options = array_merge($example_options, $options);

			return $options;
		}

		/**
		 * Display fields for example options.
		 *
		 * @since 1.8
		 */
		static function pmpro_payment_option_fields($values, $gateway)
		{
		?>
		<tr class="pmpro_settings_divider gateway gateway_amex" <?php if($gateway != "example") { ?>style="display: none;"<?php } ?>>
			<td colspan="2">
				<?php _e('AMEX Settings', 'pmpro'); ?>
			</td>
		</tr>
		<tr class="gateway gateway_example" <?php if($gateway != "example") { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="amex_merchant_id"><?php _e('Merchant ID', 'pmpro');?>:</label>
			</th>
			<td>
				<input type="text" id="amex_merchant_id" name="amex_merchant_id" size="60" value="<?php echo esc_attr($values['amex_merchant_id'])?>" />
			</td>
		</tr>
		<tr class="gateway gateway_amex" <?php if($gateway != "example") { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label><?php _e('Web Hook URL', 'pmpro');?>:</label>
			</th>
			<td>
				<p><?php _e('To fully integrate with Stripe, be sure to set your Web Hook URL to', 'pmpro');?> <pre><?php echo admin_url("admin-ajax.php") . "?action=amex_webhook";?></pre></p>
			</td>
		</tr>
		<?php
		}

		/**
		 * Code added to checkout preheader.
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_preheader()
		{
			global $gateway, $pmpro_level;

			if($gateway == "example" && !pmpro_isLevelFree($pmpro_level))
			{

				//stripe js code for checkout
				function pmpro_stripe_javascript()
				{
					global $pmpro_gateway, $pmpro_level, $pmpro_stripe_lite;
				?>

				<?php
				$orderID = time().'-'.mt_rand();
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, 'https://gateway-na.americanexpress.com/api/rest/version/36/merchant/<your_merchant_id>/session');
				curl_setopt($ch, CURLOPT_USERPWD,  'merchant.<your_merchant_id>' . ":" . '<your_merchant_api>');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, '{"order": { "id": "'.$orderID.'", "currency": "USD" }, "apiOperation": "CREATE_CHECKOUT_SESSION" }');
				$result = curl_exec($ch);

				if($result === false){
				echo curl_error($ch);
				}

				curl_close($ch);
				$result = json_decode($result);
				//print_r($result);
				$ssID = $result->session->id;
				$successIndicator = $result->successIndicator;

				?>
				<style>
					.pmpro_btn.pmpro_btn-submit-checkout.process_card:hover {
					    background-color: #92d387;
					}
					.pmpro_btn.pmpro_btn-submit-checkout.process_card {
					    background-color: transparent;
					    background-image: none;
					    border: 2px solid #92d387;
					    border-radius: 0;
					    font-size: 18px;
					    font-weight: bold;
					}
					#pmpro_user_fields input {
					    border-radius: 0;
					    font-size: 14px;
					    letter-spacing: 1px;
					    max-width: 300px;
					    padding: 10px;
					    width: 100%;
					}
					#pmpro_user_fields label {
					    font-size: 14px;
					    padding: 5px 0;
					}
				</style>
				        <script src="https://gateway-na.americanexpress.com/checkout/version/36/checkout.js"
				                data-error="errorCallback"
				                data-complete="completeCallback">
				        </script>
								<script type="text/javascript">
            				function completeCallback(resultIndicator, sessionVersion) {
											var successIndicator = '<?php echo $successIndicator; ?>';
                			if(resultIndicator == successIndicator){
												console.log('payment receive');
												jQuery( "#pmpro_form" ).submit();
											} else {
												alert('Opps! there is something wrong. We could not process the payment. Please contact your card provider or feel free to contact us.');
											}
            				}
        				</script>
								<?php if( !is_user_logged_in() ){ ?>
								<script>
										function isValidEmailAddress(emailAddress) {
										    var pattern = /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i;
										    return pattern.test(emailAddress);
										};

										jQuery(document).on('click', '.pmpro_btn-submit-checkout' ,function(e){
											e.preventDefault();
											// validations goes here
											jQuery( ".val-error" ).remove();

											var user_name = jQuery('#username').val();
											if (user_name == ''){
												jQuery( "<p class='val-error' style='color:#ff0000;'><b>Error:</b> Please enter Username.</p>" ).insertBefore( jQuery( "#pmpro_user_fields" ) );
												return false;
											}

											var password = jQuery('#password').val();
											if (password == ''){
												jQuery( "<p class='val-error' style='color:#ff0000;'><b>Error:</b> Please enter Password.</p>" ).insertBefore( jQuery( "#pmpro_user_fields" ) );
												return false;
											}

											var password2 = jQuery('#password2').val();
											if (password2 == ''){
												jQuery( "<p class='val-error' style='color:#ff0000;'><b>Error:</b> Please enter Confirm Password.</p>" ).insertBefore( jQuery( "#pmpro_user_fields" ) );
												return false;
											}

											if (password != password2){
												jQuery( "<p class='val-error' style='color:#ff0000;'><b>Error:</b> Password does not match.</p>" ).insertBefore( jQuery( "#pmpro_user_fields" ) );
												return false;
											}

											var user_email = jQuery('#bemail').val();
											if (user_email == ''){
												jQuery( "<p class='val-error' style='color:#ff0000;'><b>Error:</b> Please enter Email Address.</p>" ).insertBefore( jQuery( "#pmpro_user_fields" ) );
												return false;
											}

											if( !isValidEmailAddress( user_email ) ) {
												jQuery( "<p class='val-error' style='color:#ff0000;'><b>Error:</b> Invalid Email Address.</p>" ).insertBefore( jQuery( "#pmpro_user_fields" ) );
												return false;
											}

											var bconfirmemail = jQuery('#bconfirmemail').val();
											if (bconfirmemail == ''){
												jQuery( "<p class='val-error' style='color:#ff0000;'><b>Error:</b> Please enter Confirm E-mail Address.</p>" ).insertBefore( jQuery( "#pmpro_user_fields" ) );
												return false;
											}

											if( !isValidEmailAddress( bconfirmemail ) ) {
												jQuery( "<p class='val-error' style='color:#ff0000;'><b>Error:</b> Invalid Confirm Email Address.</p>" ).insertBefore( jQuery( "#pmpro_user_fields" ) );
												return false;
											}

											if (user_email != bconfirmemail){
												jQuery( "<p class='val-error' style='color:#ff0000;'><b>Error:</b> Email Address does not match.</p>" ).insertBefore( jQuery( "#pmpro_user_fields" ) );
												return false;
											}

											jQuery('#pmpro_processing_message').css('visibility', 'visible');

											jQuery.ajax({
														 type : "post",
														 url : '<?php echo admin_url('admin-ajax.php'); ?>',
														 data : {
																	action: "check_username",
																	user_name : jQuery('#username').val(),
																	user_email : jQuery('#bemail').val(),
																},
														 success: function(response) {
															 	if (response == 1){
																	jQuery( "<p class='val-error' style='color:#ff0000;'><b>Error:</b> Username already in use.</p>" ).insertBefore( jQuery( "#pmpro_user_fields" ) );
																	jQuery('#pmpro_processing_message').css('visibility', 'hidden');
																	return false;
																}
																if (response == 2){
																	jQuery( "<p class='val-error' style='color:#ff0000;'><b>Error:</b> Email Address already in use.</p>" ).insertBefore( jQuery( "#pmpro_user_fields" ) );
																	jQuery('#pmpro_processing_message').css('visibility', 'hidden');
																	return false;
																}
																if (response == 0){
																		Checkout.showLightbox();
																}
														 }

											});


									});
								</script>
								<?php } else { ?>
										  <script type="text/javascript">

													jQuery(document).on('click', '.pmpro_btn-submit-checkout' ,function(e){
														e.preventDefault();
														Checkout.showLightbox();
													});
													
											</script>
								<?php } ?>
				        <script type="text/javascript">
				            function errorCallback(error) {
				                  console.log(JSON.stringify(error));
				            }
				            function cancelCallback() {
				                  console.log('Payment cancelled');
				            }

				            Checkout.configure({
				                merchant: '<your_merchant_id>',
				                session: {
				                    id: "<?php echo $ssID; ?>"
				                },
				                order: {
				                    amount: '<?php echo $pmpro_level->initial_payment; ?>',
				                    currency: 'USD',
				                    description: '<?php echo $pmpro_level->name; ?>',
				                   	id: '<?php echo $orderID; ?>',
												},
												interaction: {
									        merchant      : {
									            name: 'Merchant name',
									            address: {
																	line1: 'Merchant address line 1',
																	line2: 'Merchant address line 2'
									            },
									            email  : 'order@yourMerchantEmailAddress.com',
									            phone  : 'Merchant Phone'
									        },
									        locale        : 'en_US',
									        theme         : 'default',
									        displayControl: {
									            billingAddress  : 'HIDE'
									        }
									    }
				            });
				        </script>

				<?php
				}
				add_action("wp_head", "pmpro_stripe_javascript");

				//don't require the CVV
				function pmpro_stripe_dont_require_CVV($fields)
				{
					unset($fields['CVV']);
					return $fields;
				}
				add_filter("pmpro_required_billing_fields", "pmpro_stripe_dont_require_CVV");
			}
		}
		static function pmpro_checkout_default_submit_button($show)
		{
			global $gateway, $pmpro_requirebilling;

			//show our submit buttons
			?>

			<span id="pmpro_submit_span">
				<input type="hidden" name="submit-checkout" value="1" />
				<input type="submit" class="pmpro_btn pmpro_btn-submit-checkout process_card" value="<?php if($pmpro_requirebilling) { _e('Submit and Check Out', 'pmpro'); } else { _e('Submit and Confirm', 'pmpro');}?> &raquo;" />
			</span>

			<?php

			//don't show the default
			return false;
		}
		/**
		 * Filtering orders at checkout.
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_order($morder)
		{
			return $morder;
		}

		/**
		 * Code to run after checkout
		 *
		 * @since 1.8
		 */
		static function pmpro_after_checkout($user_id, $morder)
		{
		}

		/**
		 * Check settings if billing address should be shown.
		 * @since 1.8
		 */
		static function pmpro_include_billing_address_fields($include)
		{
			//check settings RE showing billing address
			if(!pmpro_getOption("amex_billingaddress"))
				$include = false;

			return $include;
		}

		/**
		 * Use our own payment fields at checkout. (Remove the name attributes.)
		 * @since 1.8
		 */
		static function pmpro_include_payment_information_fields($include)
		{
			//global vars
			global $pmpro_requirebilling, $pmpro_show_discount_code, $discount_code, $CardType, $AccountNumber, $ExpirationMonth, $ExpirationYear;

			?>
				<input type="hidden" id="CardType" name="CardType" value="American Express" />
			<?php

			//don't include the default
			return false;
		}

		/**
		 * Fields shown on edit user page
		 *
		 * @since 1.8
		 */
		static function user_profile_fields($user)
		{
		}

		/**
		 * Process fields from the edit user page
		 *
		 * @since 1.8
		 */
		static function user_profile_fields_save($user_id)
		{
		}

		/**
		 * Cron activation for subscription updates.
		 *
		 * @since 1.8
		 */
		static function pmpro_activation()
		{
			wp_schedule_event(time(), 'daily', 'pmpro_cron_example_subscription_updates');
		}

		/**
		 * Cron deactivation for subscription updates.
		 *
		 * @since 1.8
		 */
		static function pmpro_deactivation()
		{
			wp_clear_scheduled_hook('pmpro_cron_example_subscription_updates');
		}

		/**
		 * Cron job for subscription updates.
		 *
		 * @since 1.8
		 */
		static function pmpro_cron_example_subscription_updates()
		{
		}


		function process(&$order)
		{
			//check for initial payment
			if(floatval($order->InitialPayment) == 0)
			{
				//auth first, then process
				if($this->authorize($order))
				{
					$this->void($order);
					if(!pmpro_isLevelTrial($order->membership_level))
					{
						//subscription will start today with a 1 period trial (initial payment charged separately)
						$order->ProfileStartDate = date("Y-m-d") . "T0:0:0";
						$order->TrialBillingPeriod = $order->BillingPeriod;
						$order->TrialBillingFrequency = $order->BillingFrequency;
						$order->TrialBillingCycles = 1;
						$order->TrialAmount = 0;

						//add a billing cycle to make up for the trial, if applicable
						if(!empty($order->TotalBillingCycles))
							$order->TotalBillingCycles++;
					}
					elseif($order->InitialPayment == 0 && $order->TrialAmount == 0)
					{
						//it has a trial, but the amount is the same as the initial payment, so we can squeeze it in there
						$order->ProfileStartDate = date("Y-m-d") . "T0:0:0";
						$order->TrialBillingCycles++;

						//add a billing cycle to make up for the trial, if applicable
						if($order->TotalBillingCycles)
							$order->TotalBillingCycles++;
					}
					else
					{
						//add a period to the start date to account for the initial payment
						$order->ProfileStartDate = date("Y-m-d", strtotime("+ " . $order->BillingFrequency . " " . $order->BillingPeriod, current_time("timestamp"))) . "T0:0:0";
					}

					$order->ProfileStartDate = apply_filters("pmpro_profile_start_date", $order->ProfileStartDate, $order);
					return $this->subscribe($order);
				}
				else
				{
					if(empty($order->error))
						$order->error = __("Unknown error: Authorization failed.", "pmpro");
					return false;
				}
			}
			else
			{
				//charge first payment
				if($this->charge($order))
				{
					//set up recurring billing
					if(pmpro_isLevelRecurring($order->membership_level))
					{
						if(!pmpro_isLevelTrial($order->membership_level))
						{
							//subscription will start today with a 1 period trial
							$order->ProfileStartDate = date("Y-m-d") . "T0:0:0";
							$order->TrialBillingPeriod = $order->BillingPeriod;
							$order->TrialBillingFrequency = $order->BillingFrequency;
							$order->TrialBillingCycles = 1;
							$order->TrialAmount = 0;

							//add a billing cycle to make up for the trial, if applicable
							if(!empty($order->TotalBillingCycles))
								$order->TotalBillingCycles++;
						}
						elseif($order->InitialPayment == 0 && $order->TrialAmount == 0)
						{
							//it has a trial, but the amount is the same as the initial payment, so we can squeeze it in there
							$order->ProfileStartDate = date("Y-m-d") . "T0:0:0";
							$order->TrialBillingCycles++;

							//add a billing cycle to make up for the trial, if applicable
							if(!empty($order->TotalBillingCycles))
								$order->TotalBillingCycles++;
						}
						else
						{
							//add a period to the start date to account for the initial payment
							$order->ProfileStartDate = date("Y-m-d", strtotime("+ " . $this->BillingFrequency . " " . $this->BillingPeriod, current_time("timestamp"))) . "T0:0:0";
						}

						$order->ProfileStartDate = apply_filters("pmpro_profile_start_date", $order->ProfileStartDate, $order);
						if($this->subscribe($order))
						{
							return true;
						}
						else
						{
							if($this->void($order))
							{
								if(!$order->error)
									$order->error = __("Unknown error: Payment failed.", "pmpro");
							}
							else
							{
								if(!$order->error)
									$order->error = __("Unknown error: Payment failed.", "pmpro");

								$order->error .= " " . __("A partial payment was made that we could not void. Please contact the site owner immediately to correct this.", "pmpro");
							}

							return false;
						}
					}
					else
					{
						//only a one time charge
						$order->status = "success";	//saved on checkout page
						return true;
					}
				}
				else
				{
					if(empty($order->error))
						$order->error = __("Unknown error: Payment failed.", "pmpro");

					return false;
				}
			}
		}

		/*
			Run an authorization at the gateway.

			Required if supporting recurring subscriptions
			since we'll authorize $1 for subscriptions
			with a $0 initial payment.
		*/
		function authorize(&$order)
		{
			//create a code for the order
			if(empty($order->code))
				$order->code = $order->getRandomCode();

			//code to authorize with gateway and test results would go here

			//simulate a successful authorization
			$order->payment_transaction_id = "TAC-" . $order->code;
			$order->updateStatus("authorized");
			$order->payment_type = "American Express";
			$order->cardtype = "AMEX";
			return true;
		}

		/*
			Void a transaction at the gateway.

			Required if supporting recurring transactions
			as we void the authorization test on subs
			with a $0 initial payment and void the initial
			payment if subscription setup fails.
		*/
		function void(&$order)
		{
			//need a transaction id
			if(empty($order->payment_transaction_id))
				return false;

			//code to void an order at the gateway and test results would go here

			//simulate a successful void
			$order->payment_transaction_id = "TAC-" . $order->code;
			$order->updateStatus("voided");
			$order->payment_type = "American Express";
			$order->cardtype = "AMEX";
			return true;
		}

		/*
			Make a charge at the gateway.

			Required to charge initial payments.
		*/
		function charge(&$order)
		{
			//create a code for the order
			if(empty($order->code))
				$order->code = $order->getRandomCode();

			//code to charge with gateway and test results would go here

			//simulate a successful charge
			$order->payment_transaction_id = "TAC-" . $order->code;
			$order->updateStatus("success");
			$order->payment_type = "American Express";
			$order->cardtype = "AMEX";
			return true;
		}

		/*
			Setup a subscription at the gateway.

			Required if supporting recurring subscriptions.
		*/
		function subscribe(&$order)
		{
			//create a code for the order
			if(empty($order->code))
				$order->code = $order->getRandomCode();

			//filter order before subscription. use with care.
			$order = apply_filters("pmpro_subscribe_order", $order, $this);

			//code to setup a recurring subscription with the gateway and test results would go here

			//simulate a successful subscription processing
			$order->status = "success";
			$order->subscription_transaction_id = "TAC-" . $order->code;
			$order->payment_type = "American Express";
			$order->cardtype = "AMEX";
			return true;
		}

		/*
			Update billing at the gateway.

			Required if supporting recurring subscriptions and
			processing credit cards on site.
		*/
		function update(&$order)
		{
			//code to update billing info on a recurring subscription at the gateway and test results would go here

			//simulate a successful billing update
			return true;
		}

		/*
			Cancel a subscription at the gateway.

			Required if supporting recurring subscriptions.
		*/
		function cancel(&$order)
		{
			//require a subscription id
			if(empty($order->subscription_transaction_id))
				return false;

			//code to cancel a subscription at the gateway and test results would go here

			//simulate a successful cancel
			$order->updateStatus("cancelled");
			return true;
		}

		/*
			Get subscription status at the gateway.

			Optional if you have code that needs this or
			want to support addons that use this.
		*/
		function getSubscriptionStatus(&$order)
		{
			//require a subscription id
			if(empty($order->subscription_transaction_id))
				return false;

			//code to get subscription status at the gateway and test results would go here

			//this looks different for each gateway, but generally an array of some sort
			return array();
		}

		/*
			Get transaction status at the gateway.

			Optional if you have code that needs this or
			want to support addons that use this.
		*/
		function getTransactionStatus(&$order)
		{
			//code to get transaction status at the gateway and test results would go here

			//this looks different for each gateway, but generally an array of some sort
			return array();
		}
	}

add_action( 'wp_ajax_check_username', 'check_username_callback' );
add_action( 'wp_ajax_nopriv_check_username', 'check_username_callback' );

function check_username_callback() {
	global $wpdb; // this is how you get access to the database
	$error = 0;
	$user_name = $_POST['user_name'];
	$user_email = $_POST['user_email'];

				if( $user_name == '' || $user_email == '' ){
					echo 'cheating uh?'; exit;
				}

       if ( username_exists( $user_name ) ){
				   $error = 1;
			 }

			 if ( email_exists( $user_email ) ){
				   $error = 2;
			 }

      echo $error;

	wp_die(); // this is required to terminate immediately and return a proper response
}