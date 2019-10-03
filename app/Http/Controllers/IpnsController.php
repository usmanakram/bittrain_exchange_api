<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Currency;
use App\Coinpayments_transaction;
use App\Transaction;
use App\Balance;

class IpnsController extends Controller
{
	private function slackFakeIpnAlert($message)
	{
		// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
		app('log')->channel('slack')->alert(
			"Coinpayments IPN: \n" . 
			"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
			"*Data:* " . json_encode($_POST) . "\n" . 
			"*Error:* Someone trying to hit fake ipn. " . $message
		);
	}

	private function insertCoinpayments($user_id, $currency_id)
	{
		$coinpayments = Coinpayments_transaction::create([
			'deposit_id' => $_POST['deposit_id'], 
			'txn_id' => $_POST['txn_id'],
			'address' => $_POST['address'],
			'amount' => $_POST['amount'],
			'confirms' => $_POST['confirms'],
			'currency_id' => $currency_id,
			'fee' => (isset($_POST['fee']) ? $_POST['fee'] : 0),
			'fiat_amount' => $_POST['fiat_amount'],
			'fiat_coin' => $_POST['fiat_coin'],
			'fiat_fee' => (isset($_POST['fiat_fee']) ? $_POST['fiat_fee'] : 0),
			'ipn_id' => $_POST['ipn_id'],
			'ipn_mode' => $_POST['ipn_mode'],
			'ipn_type' => $_POST['ipn_type'],
			'ipn_version' => $_POST['ipn_version'],
			'label' => $_POST['label'],
			'merchant' => $_POST['merchant'],
			'status' => $_POST['status'],
			'status_text' => $_POST['status_text'],
			'ipn_log' => '[]'
		]);

		/*$transaction = Transaction::create([
			'user_id' => $user_id,
			'currency_id' => $currency_id,
			'type' => 'deposit',
			'payment_gateway' => 'coinpayments',
			'payment_gateway_table_id' => $coinpayments->id,
			'address' => $_POST['address'],
			'amount' => $_POST['amount'],
			'confirmations' => $_POST['confirms'],
			'txn_id' => $_POST['txn_id'],
			'status' => $_POST['status'],
			'status_text' => $_POST['status_text']
		]);*/

		$transaction = new Transaction([
			'user_id' => $user_id,
			'currency_id' => $currency_id,
			'type' => 'deposit',
			// 'payment_gateway' => 'coinpayments',
			// 'payment_gateway_table_id' => $coinpayments->id,
			'address' => $_POST['address'],
			'amount' => $_POST['amount'],
			'confirmations' => $_POST['confirms'],
			// 'txn_id' => $_POST['txn_id'],
			'transaction_id' => $_POST['txn_id'],
			'status' => $_POST['status'],
			'status_text' => $_POST['status_text']
		]);

		$coinpayments->transaction()->save($transaction);

		// return compact('coinpayments', 'transaction');
		return $coinpayments;
	}
	
	// private function updateCoinpayments($coinpayments, $transaction)
	private function updateCoinpayments($coinpayments)
	{
		$coinpayments->fill([
			'address' => $_POST['address'],
			'amount' => $_POST['amount'],
			'confirms' => $_POST['confirms'],
			'fee' => (isset($_POST['fee']) ? $_POST['fee'] : 0),
			'fiat_amount' => $_POST['fiat_amount'],
			'fiat_coin' => $_POST['fiat_coin'],
			'fiat_fee' => (isset($_POST['fiat_fee']) ? $_POST['fiat_fee'] : 0),
			'ipn_id' => $_POST['ipn_id'],
			'ipn_mode' => $_POST['ipn_mode'],
			'ipn_type' => $_POST['ipn_type'],
			'ipn_version' => $_POST['ipn_version'],
			'label' => $_POST['label'],
			'merchant' => $_POST['merchant'],
			'status' => $_POST['status'],
			'status_text' => $_POST['status_text']
		]);

		/*$transaction->fill([
			'address' => $_POST['address'],
			'amount' => $_POST['amount'],
			'confirmations' => $_POST['confirms'],
			'txn_id' => $_POST['txn_id'],
			'status' => $_POST['status'],
			'status_text' => $_POST['status_text']
		]);*/

		$coinpayments->transaction()->update([
			'address' => $_POST['address'],
			'amount' => $_POST['amount'],
			'confirmations' => $_POST['confirms'],
			// 'txn_id' => $_POST['txn_id'],
			'transaction_id' => $_POST['txn_id'],
			'status' => $_POST['status'],
			'status_text' => $_POST['status_text']
		]);

		// $coinpayments->transaction()->save();
	}

	public function coinpayments($user_id)
	{
		/*$merchant_id = config('app.COINPAYMENTS_MERCHANT_ID');
		$secret = config('app.COINPAYMENTS_SECRET');*/

		$merchant_id = env('COINPAYMENTS_MERCHANT_ID');
		$secret = env('COINPAYMENTS_SECRET');

		if (!$merchant_id || !$secret) {
			// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
			Log::channel('slack')->warning(
				"Coinpayments IPN: \n" . 
				"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
				"*Data:* " . json_encode($_POST) . "\n" . 
				"*Error:* Kindly, put Coinpayments Merchant ID and Secret in .env file");

			return response()->api('Some error occurred. Please, try again later', 400);
		}

		/*if (!isset($_SERVER['HTTP_HMAC']) || empty($_SERVER['HTTP_HMAC'])) {
			$this->slackFakeIpnAlert('No HMAC signature sent');

			die("No HMAC signature sent");
		}*/

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

		/*$hmac = hash_hmac("sha512", $request, $secret);
		if ($hmac != $_SERVER['HTTP_HMAC']) {
			$this->slackFakeIpnAlert('HMAC signature does not match');
			
			die("HMAC signature does not match");
		}*/

		//process IPN here

		// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
		Log::channel('slack')->debug(
			"Coinpayments IPN: \n" . 
			"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
			"*Data:* " . json_encode($_POST) . "\n" . 
			"*Status:* After authentication, Before handling IPN"
		);

		DB::beginTransaction();

		try {
			
			$currency = Currency::firstOrCreate(
				['symbol' => strtoupper($_POST['currency'])],
				['name' => $_POST['currency']]
			);

			/*$transaction = Coinpayments_transaction::updateOrCreate(
				[
					'deposit_id' => $_POST['deposit_id'], 
					'txn_id' => $_POST['txn_id']
				],
				[
					'address' => $_POST['address'],
					'amount' => $_POST['amount'],
					'confirms' => $_POST['confirms'],
					'currency_id' => $currency->id,
					'fee' => (isset($_POST['fee']) ? $_POST['fee'] : 0),
					'fiat_amount' => $_POST['fiat_amount'],
					'fiat_coin' => $_POST['fiat_coin'],
					'fiat_fee' => (isset($_POST['fiat_fee']) ? $_POST['fiat_fee'] : 0),
					'ipn_id' => $_POST['ipn_id'],
					'ipn_mode' => $_POST['ipn_mode'],
					'ipn_type' => $_POST['ipn_type'],
					'ipn_version' => $_POST['ipn_version'],
					'label' => $_POST['label'],
					'merchant' => $_POST['merchant'],
					'status' => $_POST['status'],
					'status_text' => $_POST['status_text'],
				]
			);*/

			// START
			$status = '';
			$coinpayments = Coinpayments_transaction::where(['deposit_id' => $_POST['deposit_id'], 'txn_id' => $_POST['txn_id']])->first();
			if ( !$coinpayments ) {
				// Insert
				$status = 'insert';
				/*list(
					'coinpayments' => $coinpayments, 
					'transaction' => $transaction
				) = $this->insertCoinpayments($user_id, $currency->id);*/
				$coinpayments = $this->insertCoinpayments($user_id, $currency->id);


				if ($_POST['status'] === '100') {
					// Update user balance
					$status = 'update user balance in first hit';
					$balance = Balance::incrementUserBalance($user_id, $currency->id, $_POST['amount']);
				}

			} elseif ($_POST['status'] === '100' && $coinpayments->status !== 100) {
				// Update
				$status = 'update';

				/*$transaction = Transaction::where([
					'payment_gateway' => 'coinpayments', 
					'payment_gateway_table_id' => $coinpayments->id
				])
				->first();

				$this->updateCoinpayments($coinpayments, $transaction);
				$transaction->save();*/
				// $coinpayments->save();
				$this->updateCoinpayments($coinpayments);


				// Update user balance
				$status = 'update user balance';
				$balance = Balance::incrementUserBalance($user_id, $currency->id, $_POST['amount']);

			} else {
				$status = 'nothing happened';
			}

			$ipn_log = json_decode($coinpayments->ipn_log);
			$ipn_log[] = $_POST;
			$ipn_log = json_encode($ipn_log);

			$coinpayments->fill(['ipn_log' => $ipn_log]);

			$coinpayments->save();
			DB::commit();

			// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
			Log::channel('slack')->debug(
				"Coinpayments IPN: \n" . 
				"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
				"*Data:* " . json_encode($_POST) . "\n" . 
				"*Status:* " . $status
			);
			// END

			// return response()->api($coinpayments->wasRecentlyCreated, 200);
			// return response()->api($coinpayments->wasChanged(), 200);
			return response()->api('', 204);

		} catch (\Exception $e) {
			DB::rollBack();
			
			$error_msg = "ERROR at \nLine: " . $e->getLine() . "\nFILE: " . $e->getFile() . "\nActual File: " . __FILE__ . "\nMessage: ".$e->getMessage();
            Log::error($error_msg);

			// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
			Log::channel('slack')->critical(
				"Coinpayments IPN: \n" . 
				"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
				"*Data:* " . json_encode($_POST) . "\n" . 
				"*Error:* " . $error_msg
			);

			return response()->api('Some error occurred. Please, try again later', 400);
		}
    }
}
