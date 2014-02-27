<?php
/**
 * Copyright (c) 2014, Mollie B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 */

class MPM_Gateway extends WC_Payment_Gateway
{
	protected $_data = null;

	public function __construct()
	{
		// Register this method with MPM_Settings
		global $mpm;
		if ($mpm->count >= count($mpm->methods))
		{
			$mpm->count = 0;
		}
		$this->method_index = $mpm->count++;
		$this->_data = $mpm->methods[$this->method_index];

		// Assign ids and titles
		$this->id = $this->_data->id;
		$this->method_description = $this->_data->description;
		$this->method_title = $this->_data->description;
		$this->title = $this->_data->description;

		// Define issuers (if any)
		$issuers = $mpm->api->issuers->all();
		$this->has_fields = FALSE;
		$this->issuers = array();
		foreach ($issuers as $issuer)
		{
			if ($issuer->method === $this->id) {
				$this->has_fields = TRUE;
				$this->issuers[] = $issuer;
			}
		}

		// Assign image
		if (isset($this->_data->image) && $mpm->get_option('show_images', 'no') !== 'no')
		{
			$this->icon = $this->_data->image->normal;
		}

		// Initialise
		$this->init_form_fields();
		$this->init_settings();
	}

	/**
	 * It seems this option is mandatory for a (visible) gateway
	 * @return void
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'default' => 'yes',
			),
		);
	}

	/**
	 * Generates a bank list for iDeal payments
	 * @return void
	 */
	public function payment_fields()
	{
		if (!$this->has_fields)
		{
			return;
		}
		echo '<select name="mpm_issuer_' . $this->id . '">';
		echo '<option value="">' . __('Select your bank:', 'MPM') . '</option>';
		foreach ($this->issuers as $issuer)
		{
			echo '<option value="' . htmlspecialchars($issuer->id) . '">' . htmlspecialchars($issuer->name) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Sends a payment request to Mollie, redirects the user to the payscreen.
	 * @param int $order_id
	 * @return array|void
	 */
	public function process_payment($order_id)
	{
		global $mpm, $woocommerce;
		$order = new WC_Order($order_id);
		$order->update_status('pending', __('Awaiting payment confirmation', 'MPM'));

		$data = array(
			"amount"			=> $order->get_total(),
			"description"		=> str_replace('%', $order_id,$mpm->get_option('description', 'Order %')),
			"redirectUrl"		=> $mpm->get_return_link() . '&order='.$order_id.'&key='.$order->order_key,
			"method"			=> $this->id,
			"issuer"			=> empty($_POST["mpm_issuer_" . $this->id]) ? null : $_POST["mpm_issuer_" . $this->id],
			"metadata"			=> array(
				"order_id"		=> $order_id,
			),

			"billingCity"		=> $order->billing_city,
			"billingRegion"		=> $order->billing_state,
			"billingPostal"		=> $order->billing_postcode,
			"billingCountry"	=> $order->billing_country,

			"shippingCity"		=> $order->shipping_city,
			"shippingRegion"	=> $order->shipping_state,
			"shippingPostal"	=> $order->shipping_postcode,
			"shippingCountry"	=> $order->shipping_country,
		);

		$payment = $mpm->api->payments->create($data);

		$woocommerce->cart->empty_cart();

		return array(
			'result' => 'success',
			'redirect' => $payment->getPaymentUrl(),
		);
	}
}