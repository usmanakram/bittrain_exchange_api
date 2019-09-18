<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Libs\CoinPaymentsAPI;
use App\Currency;
use App\User_deposit_address;
use App\Coinpayments_transaction;

class TransactionsController extends Controller
{
	private function generateGetCallbackAddress($user_id, $currency)
	{
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

		/*$address = $request->user()
			->user_deposit_addresses()
			->with('currency')
			->whereHas('currency', function($query) use ($currency) {
				$query->where('symbol', strtoupper($currency));
			})->first();*/

		$address = User_deposit_address::where('user_id', $user_id)
			->with('currency')
			->whereHas('currency', function($query) use ($currency) {
				$query->where('symbol', strtoupper($currency));
			})
			->first();

		return $address;
	}

	public function getDepositAddress(Request $request, $currency)
	{
		$user_id = $request->user()->id;

		$address = $this->getAddress($user_id, $currency);

		// Create new address if not exist
		if ( !$address ) {
			
			$currencyDetail = Currency::where('symbol', strtoupper($currency))->first();

			try {
				// generate address using CoinPaymentsAPI
				$response = $this->generateGetCallbackAddress($user_id, $currency);
				
				$address = new User_deposit_address([
					'user_id' => $user_id,
					'currency_id' => $currencyDetail->id,
					'address' => $response['result']['address']
				]);

				$address->save();

				// Get newly generated address
				$address = $this->getAddress($user_id, $currency);

			} catch (\Exception $e) {

				// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
				app('log')->channel('slack')->emergency(
					"CoinPaymentsAPI Resposponse: \n" . 
					"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
					"*User:* " . json_encode($request->user()) . "\n" . 
					"*Error:* " . $e->getMessage()
				);

				return response()->api('Some error occurred. Please, try again later', 400);
			}
		}

		return response()->api($address);
	}

	public function getTransactionsHistory(Request $request)
	{
		// $request->validate(['type' => 'required|string']);

		$history =  Coinpayments_transaction::where('label', $request->user()->id)
			->get(['address', 'amount', 'confirms', 'currency_id', 'fee', 'fiat_amount', 'fiat_coin', 'fiat_fee', 'label', 'status', 'status_text', 'txn_id', 'created_at', 'updated_at']);

		return response()->api($history);
	}
}
