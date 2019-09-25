<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Currency;
use App\Balance;
use App\Transaction;
use App\Bittrain_transaction;
use App\Mail\BittrainCoinDeposit;

class BittrainTransactionsController extends Controller
{
	public function depositFromBittrain(Request $request)
	{
		$requestHeader = getallheaders();
		$requestBody = file_get_contents('php://input');

		// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
		Log::channel('slack')->critical(
			"Bittrain Deposit Request: \n" . 
			"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
			"*Request Header:* " . json_encode($requestHeader) . "\n" . 
			"*Request Body:* " . $requestBody . "\n" . 
			"*Status:* Before handling request"
		);
		
		/*$this->printResponse($requestHeader);
		$this->printResponse($requestBody);
		$this->printResponse($_POST);*/
		// $this->printResponse($request);

		if (isset($requestHeader['Authorization'])) {
			$currency = Currency::firstOrCreate(
				['symbol' => 'BC'],
				['name' => 'Bittrain Coin']
			);

			// $code = mt_rand(100000, 999999);
			// $code = sprintf("%06d", mt_rand(1, 999999));
			$code = sprintf("%06d", mt_rand(1, 999999));

			parse_str($requestBody, $post);

			DB::beginTransaction();

			try {

				$jwtData = $this->jwtDecode($requestHeader['Authorization']);
				$user = $jwtData['claims'];
				
				if ($user['username'] !== 'tabassumali21') {
					return response()->api('You are not allowed to transfer.', 400);
				}
				
				$bittrain = Bittrain_transaction::firstOrCreate(
					[
						'token' => $requestHeader['Authorization'],
						'txn_id' => '',
					],
					[
						'currency_id' => $currency->id,
						'type' => 'deposit',
						'amount' => 0,
						'txn_id' => '',
						'code' => $code,
						'status_text' => 'Deposit request initiated'
					]
				);

				/*$jwtData = $this->jwtDecode($bittrain->token);
				$user = $jwtData['claims'];*/

				// Send email
				$mailData = new \stdClass();
				$mailData->receiver_name = $user['full_name'];
				$mailData->verification_code = $code;

				// Uncommint following line, to view email as template
				// return (new BittrainCoinDeposit($mailData))->render();

				Mail::to($user['real_email'])->send(new BittrainCoinDeposit($mailData));

				DB::commit();

				return response()->api('Request Accepted');

			} catch (\Exception $e) {
				DB::rollBack();
			
				$error_msg = "ERROR at \nLine: " . $e->getLine() . "\nFILE: " . $e->getFile() . "\nActual File: " . __FILE__ . "\nMessage: ".$e->getMessage();
				Log::error($error_msg);

				// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
				Log::channel('slack')->critical(
					"Bittrain Deposit Request: \n" . 
					"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
					"*Data:* " . json_encode($post) . "\n" . 
					"*Error:* " . $error_msg
				);

				return response()->api('Some error occurred. Please, try again later', 400);
			}
		}

		return response()->api('Request Denied', 401);
	}

	private function increateUserBalance($user_id, $currency_id, $amount)
	{
		$balance = Balance::firstOrCreate(
			['user_id' => $user_id, 'currency_id' => $currency_id],
			['in_order_balance' => 0, 'total_balance' => 0]
		);

		return $balance->increment('total_balance', $amount);
	}

	private function confirmBittrainDeposit($bittrain, $post, $user_id)
	{
		$address = '';
		$confirmations = 0;
		$status = 0;
		$status_text = 'Deposit Confirmed';


		$transaction = new Transaction([
			'user_id' => $user_id,
			'currency_id' => $bittrain->currency_id,
			'type' => 'deposit',
			'address' => $address,
			'amount' => $post['amount'],
			'confirmations' => $confirmations,
			'txn_id' => $post['transaction_id'],
			'status' => $status,
			'status_text' => $status_text
		]);
		$bittrain->transaction()->save($transaction);


		$bittrain->fill([
			'amount' => $post['amount'],
			'txn_id' => $post['transaction_id'],
		]);

		$bittrain->save();
	}

	public function validateBittrainDeposit(Request $request)
	{
		$requestHeader = getallheaders();
		$requestBody = file_get_contents('php://input');

		// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
		Log::channel('slack')->critical(
			"Bittrain Deposit Verification: \n" . 
			"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
			"*Request Header:* " . json_encode($requestHeader) . "\n" . 
			"*Request Body:* " . $requestBody . "\n" . 
			"*Status:* Before handling request"
		);

		/*$this->printResponse($requestHeader);
		$this->printResponse($requestBody);
		$this->printResponse($_POST);*/

		// $requestBody = 'transaction_id=123&code=678671&amount=123';
		parse_str($requestBody, $post);

		if (isset($requestHeader['Authorization']) && isset($post['code'])) {

			$bittrain = Bittrain_transaction::where([
				'token' => $requestHeader['Authorization'],
				'code' => $post['code']
			])->first();

			if ($bittrain) {

				if ($bittrain->txn_id === '') {

					DB::beginTransaction();
					try {

						// $user_id = 0;
						$jwtData = $this->jwtDecode($bittrain->token);
						$user_id = $jwtData['claims']['user_id'];
						
						$this->confirmBittrainDeposit($bittrain, $post, $user_id);

						// Update user balance
						$this->increateUserBalance($user_id, $bittrain->currency_id, $post['amount']);

						DB::commit();

						return response()->api('Deposit Successful.', 200);

					} catch (\Exception $e) {
						DB::rollBack();
			
						$error_msg = "ERROR at \nLine: " . $e->getLine() . "\nFILE: " . $e->getFile() . "\nActual File: " . __FILE__ . "\nMessage: ".$e->getMessage();
						Log::error($error_msg);

						// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
						Log::channel('slack')->critical(
							"Bittrain Deposit Verification: \n" . 
							"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
							"*Data:* " . json_encode($post) . "\n" . 
							"*Error:* " . $error_msg
						);

						return response()->api('Some error occurred. Please, try again later', 400);
					}

				} else {
					return response()->api('Deposit has already been performed.', 200);
				}
			} else {
				return response()->api('Invalid Request', 401);
			}
		}

		return response()->api('Request Denied', 401);
	}
}
