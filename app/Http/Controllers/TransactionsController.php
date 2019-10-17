<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Libs\CoinPaymentsAPI;
use App\Currency;
use App\User_deposit_address;
use App\Transaction;
use App\Balance;

// use Illuminate\Support\Facades\Mail;
use App\Mail\BittrainCoinDeposit;

class TransactionsController extends Controller
{
	private function generateGetCallbackAddress($user_id, $currency)
	{
		/*$private_key = config('app.COINPAYMENTS_API_PRIVATE_KEY');
		$public_key = config('app.COINPAYMENTS_API_PUBLIC_KEY');*/

		$private_key = env('COINPAYMENTS_API_PRIVATE_KEY');
		$public_key = env('COINPAYMENTS_API_PUBLIC_KEY');

    	if (!$private_key || !$public_key) {
			throw new \Exception('Kindly, put Coinpayments private and public keys in .env file.');
		}

		$cps = new CoinPaymentsAPI();
		$cps->Setup($private_key, $public_key);

		// $currency = 'BTC';
		// $ipn_url = 'http://18.220.217.218/coinpayments/ipn.php';
		// $label = '1st testing address';
		$ipn_url = 'http://18.220.217.218/bittrain_exchange_api/public/api/coinpayments-ipn/' . $user_id;
		$label = $user_id;

		return $cps->GetCallbackAddress($currency, $ipn_url, $label);
	}

	private function getAddress($user_id, $currency)
	{
		// Get logged in user's "user_deposit_addresses" JOIN with "currency"

		/*$address = User_deposit_address::where('user_id', $user_id)
			->with('currency')
			->whereHas('currency', function($query) use ($currency) {
				$query->where('symbol', strtoupper($currency));
			})
			->first();*/

		$address = User_deposit_address::where('user_id', $user_id)
			->with([
				'currency', 
				'currency.balances' => function($query) use ($user_id) {
					$query->where('user_id', $user_id);
				}
			])
			->whereHas('currency', function($query) use ($currency) {
				$query->where('symbol', strtoupper($currency));
			})
			->first();
		
		/*$address = Currency::whereSymbol(strtoupper($currency))
			->with([
				'user_deposit_address' => function($query) use ($user_id) {
					$query->where('user_id', $user_id)->first();
				}, 
				'balances' => function($query) use ($user_id) {
					$query->where('user_id', $user_id)->first();
				}
			])
			->first();*/

		if ($address) {
			$address['currency_name'] = $address->currency->name;
			$address['currency_symbol'] = $address->currency->symbol;
			$address['total_balance'] = $address->currency->balances[0]['total_balance'] ?? 0;
			$address['in_order_balance'] = $address->currency->balances[0]['in_order_balance'] ?? 0;
			// unset($address->currency);
		}

		return $address;
	}

	public function getDepositAddress(Request $request, $currency)
	{
		$user_id = $request->user()->id;

		if (strtoupper($currency) === 'BC') {
			return response()->api('Deposit address of BittrainCoin cannot be created.', 405); // 405 Method Not Allowed
		}

		$currencyDetail = Currency::where('symbol', strtoupper($currency))->first();

		if ( !$currencyDetail ) {
			return response()->api('Invalid Currency', 404); // 404 Not Found
		}

		// Create user record in "balances" table, if not exist
		Balance::createUserBalance($user_id, $currencyDetail->id);
		
		$address = $this->getAddress($user_id, $currency);

		// Create new address if not exist
		if ( !$address ) {

			try {
				// generate address using CoinPaymentsAPI
				$response = $this->generateGetCallbackAddress($user_id, $currency);

				if (!isset($response['result']['address'])) {
					// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
					Log::channel('slack')->emergency(
						"CoinPaymentsAPI Response: \n" . 
						"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
						"*User:* " . json_encode($request->user()) . "\n" . 
						"*File:* " . __FILE__ . "\n" . 
						"*API Response:* " . json_encode($response)
					);

					return response()->api('Some error occurred. Please, try again later', 400); // 400 Bad Request
				}
				
				$address = new User_deposit_address([
					'user_id' => $user_id,
					'currency_id' => $currencyDetail->id,
					'address' => $response['result']['address']
				]);

				$address->save();

				// Get newly generated address
				$address = $this->getAddress($user_id, $currency);

			} catch (\Exception $e) {

				$error_msg = "ERROR at \nLine: " . $e->getLine() . "\nFILE: " . $e->getFile() . "\nActual File: " . __FILE__ . "\nMessage: ".$e->getMessage();
				Log::error($error_msg);

				// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
				app('log')->channel('slack')->emergency(
					"CoinPaymentsAPI Response: \n" . 
					"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
					"*User:* " . json_encode($request->user()) . "\n" . 
					"*Error:* " . $error_msg
				);

				return response()->api('Some error occurred. Please, try again later', 400); // 400 Bad Request
			}
		}

		return response()->api($address);
	}

	public function getTransactionsHistory(Request $request)
	{
		// $request->validate(['type' => 'required|string']);

		$deposits =  Transaction::with('currency:id,name,symbol')
			->where([
				'user_id' => $request->user()->id,
				'type' => 'deposit',
			])
			->latest()
			->get()
			->makeVisible('created_at');

		$withdrawals =  Transaction::with('currency:id,name,symbol')
			->where([
				'user_id' => $request->user()->id,
				'type' => 'withdrawal',
			])
			->latest()
			->get()
			->makeVisible('created_at');

		return response()->api(compact('deposits', 'withdrawals'));
	}

	public function requestToWithdraw(Request $request)
	{
		echo 'reached';





		/*$private_key = config('app.COINPAYMENTS_API_PRIVATE_KEY');
		$public_key = config('app.COINPAYMENTS_API_PUBLIC_KEY');*/

		$private_key = env('COINPAYMENTS_API_PRIVATE_KEY');
		$public_key = env('COINPAYMENTS_API_PUBLIC_KEY');

    	if (!$private_key || !$public_key) {
			throw new \Exception('Kindly, put Coinpayments private and public keys in .env file.');
		}

		$cps = new CoinPaymentsAPI();
		$cps->Setup($private_key, $public_key);

		/*// $currency = 'BTC';
		// $ipn_url = 'http://18.220.217.218/coinpayments/ipn.php';
		// $label = '1st testing address';
		$ipn_url = 'http://18.220.217.218/bittrain_exchange_api/public/api/coinpayments-ipn/' . $user_id;
		$label = $user_id;

		return $cps->GetCallbackAddress($currency, $ipn_url, $label);*/

		$amount = 0.0006;
		$currency = 'BTC';
		$address = '39ny9XXWzmwaBvXNfV2NAogUbZU2unkBN2';
		$auto_confirm = false;
		$ipn_url = 'http://18.220.217.218/bittrain_exchange_api/public/api/coinpayments-withdrawal-ipn';

		return $cps->CreateWithdrawal($amount, $currency, $address, $auto_confirm, $ipn_url);
	}

	public function testEmail(Request $request)
	{
		/*$num1 = mt_rand(100000, 999999);

		$code = sprintf("%06d", mt_rand(1, 999999));

		var_dump($code);

		$requestBody = 'trasnaction_id=0&code=0&amount=0';
		parse_str($requestBody, $post);

		$requestHeader['Authorization'] = 'testing';

		var_dump($post);
		return 'good to see you';*/


		// Mail::to('email@doe.com')->send(new TestAmazonSes('It works!'));

		$objDemo = new \stdClass();
		/*$objDemo->demo_one = 'Demo One Value';
		$objDemo->demo_two = 'Demo Two Value';
		$objDemo->sender = 'SenderUserName';
		$objDemo->receiver = 'ReceiverUserName';*/

		$mailData = new \stdClass();
		$mailData->demo_one = 'Demo One Value';
		$mailData->demo_two = 'Demo Two Value';
		$mailData->sender = 'Admin';
		$mailData->receiver = 'Tabassum';
		$mailData->receiver_email = 'tabassum@gmail.com';
		$mailData->verification_code = '786786';

		// Mail::to("receiver@example.com")->send(new DemoEmail($objDemo));
		// Mail::to("receiver@example.com")->send(new BittrainCoinDeposit($objDemo));

		return (new BittrainCoinDeposit($mailData))->render();

		/*try {
			Mail::to("usman.akram99@gmail.com")->send(new BittrainCoinDeposit($mailData));
		} catch (\Exception $e) {
			echo $e->getMessage();
		}*/
	}
}
