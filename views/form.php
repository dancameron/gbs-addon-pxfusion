
<form enctype='multipart/form-data' action="https://sec.paymentexpress.com/pxmi3/pxfusionauth" method="post">

	<div class="checkout_block clearfix">

		<fieldset id="gb-credit-card">
			<table class="credit-card">
				<tbody>
					<tr class="gb_credit_card_field_wrap">
						<td class="odd first"><label for="gb_credit_cc_name">Cardholder Name</label></td>
						<td>	
							<span class="gb-form-field gb-form-field-text gb-form-field-required">
								<input type="text" name="CardHolderName" id="gb_credit_cc_name" class="text-input" autocomplete="off">
							</span>
						</td>
					</tr>
					<tr class="gb_credit_card_field_wrap">
						<td class="odd first"><label for="gb_credit_cc_name">Card Number</label></td>
						<td>	
							<span class="gb-form-field gb-form-field-text gb-form-field-required">
								<input type="text" name="CardNumber" id="gb_credit_cc_name" class="text-input" autocomplete="off">
							</span>
						</td>
					</tr>
					<tr class="gb_credit_card_field_wrap">
						<td class="odd first"><label for="gb_credit_cc_expiration_year">Expiration Date</label></td>
						<td>	
							<span class="gb-form-field gb-form-field-select gb-form-field-required">
								<select name="ExpiryMonth" id="gb_credit_cc_expiration_month" autocomplete="off">
										<option value="01">01 – January</option>
										<option value="02">02 – February</option>
										<option value="03">03 – March</option>
										<option value="04">04 – April</option>
										<option value="05">05 – May</option>
										<option value="06">06 – June</option>
										<option value="07">07 – July</option>
										<option value="08">08 – August</option>
										<option value="09">09 – September</option>
										<option value="10">10 – October</option>
										<option value="11">11 – November</option>
										<option value="12">12 – December</option>
									</select>
							</span>
					 		<span class="gb-form-field gb-form-field-select gb-form-field-required">
								<select name="ExpiryYear" id="gb_credit_cc_expiration_year" autocomplete="off">
										<option value="12">2012</option>
										<option value="13">2013</option>
										<option value="14">2014</option>
										<option value="15">2015</option>
										<option value="16">2016</option>
										<option value="17">2017</option>
										<option value="18">2018</option>
										<option value="19">2019</option>
										<option value="20">2020</option>
										<option value="21">2021</option>
								</select>
							</span>
						</td>
					</tr>
					<tr class="gb_credit_card_field_wrap">
						<td class="odd first"><label for="gb_credit_cc_name">Card Security Code</label></td>
						<td>	
							<span class="gb-form-field gb-form-field-text gb-form-field-required">
								<input type="text" name="Cvc2" id="gb_credit_cc_name" class="text-input" autocomplete="off">
							</span>
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>

	</div>

	<input type="hidden" name="SessionId" value="<?php echo $token_response['pxfusion_session_id']; ?>" />
	<input type="hidden" name="Action" value="Add" />
	<input type="hidden" name="Object" value="DpsPxPay" />
	<div class="checkout-controls clearfix">
			<input class="form-submit submit checkout_next_step" type="submit" value="Complete Payment">	
	</div>
</form>
