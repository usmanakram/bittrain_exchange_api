<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Currency;
use App\Coinpayments_transaction;

class IpnsController extends Controller
{
	private function slackFakeIpnAlert($message)
	{
		// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
		app('log')->channel('slack')->alert(
			"Coinpayments IPN: \n" . 
			"*Data:* " . json_encode($_POST) . "\n" . 
			"*Error:* Someone trying to hit fake ipn. " . $message
		);
	}

	public function coinpayments()
	{
		$merchant_id = env('COINPAYMENTS_MERCHANT_ID');
		$secret = env('COINPAYMENTS_SECRET');

		if (!$merchant_id || !$secret) {
			// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
			app('log')->channel('slack')->warning(
				"Coinpayments IPN: \n" . 
				"*Data:* " . json_encode($_POST) . "\n" . 
				"*Error:* Kindly, put Coinpayments Merchant ID and Secret in .env file");

			return response()->api('Some error occurred. Please, try again later', 400);
		}

		if (!isset($_SERVER['HTTP_HMAC']) || empty($_SERVER['HTTP_HMAC'])) {
			$this->slackFakeIpnAlert('No HMAC signature sent');

			die("No HMAC signature sent");
		}

		$merchant = isset($_POST['merchant']) ? $_POST['merchant']:'';
		if (empty($merchant)) {
			$this->slackFakeIpnAlert('No Merchant ID passed');

			die("No Merchant ID passed");
		}

		if ($merchant != $merchant_id) {
			$this->slackFakeIpnAlert('Invalid Merchant ID');

			die("Invalid Merchant ID");
		}

		$request = file_get_contents('php://input');
		if ($request === FALSE || empty($request)) {
			$this->slackFakeIpnAlert('Error reading POST data');

			die("Error reading POST data");
		}

		$hmac = hash_hmac("sha512", $request, $secret);
		if ($hmac != $_SERVER['HTTP_HMAC']) {
			$this->slackFakeIpnAlert('HMAC signature does not match');
			
			die("HMAC signature does not match");
		}

		//process IPN here

		try {
			
			$currency = Currency::firstOrCreate(
				['symbol' => strtoupper($_POST['currency'])],
				['name' => $_POST['currency']]
			);

			$transaction = Coinpayments_transaction::updateOrCreate(
				[
					'deposit_id' => $_POST['deposit_id'], 
					'txn_id' => $_POST['txn_id']
				],
				[
					'address' => $_POST['address'],
					'amount' => $_POST['amount'],
					'confirms' => $_POST['confirms'],
					'currency_id' => $currency->id,
					'fee' => $_POST['fee'],
					'fiat_amount' => $_POST['fiat_amount'],
					'fiat_coin' => $_POST['fiat_coin'],
					'fiat_fee' => $_POST['fiat_fee'],
					'ipn_id' => $_POST['ipn_id'],
					'ipn_mode' => $_POST['ipn_mode'],
					'ipn_type' => $_POST['ipn_type'],
					'ipn_version' => $_POST['ipn_version'],
					'label' => $_POST['label'],
					'merchant' => $_POST['merchant'],
					'status' => $_POST['status'],
					'status_text' => $_POST['status_text'],
				]
			);

			return response()->api('', 204);

		} catch (\Exception $e) {
			// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
			app('log')->channel('slack')->critical(
				"Coinpayments IPN: \n" . 
				"*Data:* " . json_encode($_POST) . "\n" . 
				"*Error:* " . $e->getMessage()
			);

			return response()->api('Some error occurred. Please, try again later', 400);
		}
    }
}
