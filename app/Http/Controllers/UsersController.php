<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Log;
use GuzzleHttp;
use Carbon\Carbon;
use App\Word;
use App\User;

class UsersController extends Controller
{
	private function convertTimeToString($time)
	{
		$alphabets = 'abcdefghij';
		$response = '';

		for ($i=0; $i < strlen($time); $i++) { 
			$response .= $alphabets[$time[$i]];
		}

		return $response;
	}

	private function getLoginRequestHeader()
	{
		$header = $this->convertTimeToString( gmdate('YmdHis') );
		$header .= Word::inRandomOrder()->first()->msg;
		$header .= $this->convertTimeToString( gmdate('YmdHis', time()+(2*60)) );
		return $header;
	}

	private function authFromBittrain($credentials)
	{
		/*$credentials = [
			'bit_uname' => 'tabassumali21',
			'bit_password' => '!Scitilop!1'
		];*/

		$endpoint = 'https://bittrain.org/API/Welcome/check_web_login';

		$header = $this->getLoginRequestHeader();

		$client = new \GuzzleHttp\Client();

		$response = $client->post($endpoint, [
			'headers' => ['AUTHENTICATION' => $header],
			// 'body' => $credentials,
			'form_params' => $credentials
		]);

		return (string) $response->getBody();
	}

	public function reactLogin(Request $request)
	{
		$validatedData = $request->validate([
			'bit_uname' => 'required',
			'bit_password' => 'required'
		]);

		$response = $this->authFromBittrain($validatedData);

		// Slack Log
		// app('log')->channel('slack')->debug("Bittrain Login Resposponse: \n" . $response);

		$response = json_decode($response, true);
		// app('log')->channel('slack')->debug($response);

		/*$user = $response['novus_user'][0];

		return response()->api($user);*/

		// START
		if ( isset($response['novus_user'][0]['user_id']) ) {
			$bittrain_user = $response['novus_user'][0];

			// app('log')->channel('slack')->debug($bittrain_user);


			$user = User::find($bittrain_user['user_id']);

			if ( !$user ) {
				$user = new User([
					'id' => $bittrain_user['user_id'],
					'name' => $bittrain_user['full_name'],
					'email' => $bittrain_user['real_email'],
					'password' => ''
				]);

				$user->save();
				
				/*return response()->json([
					'message' => 'Successfully created user!'
				], 201);*/
			}
			// return response()->api($user);


			$tokenResult = $user->createToken('Personal Access Token');

			$token = $tokenResult->token;

			if ($request->remember_me)
				$token->expires_at = Carbon::now()->addWeeks(1);

			$token->save();

			return response()->api([
				'access_token' => $tokenResult->accessToken,
				'token_type' => 'Bearer',
				'expires_at' => Carbon::parse(
					$tokenResult->token->expires_at
				)->toDateTimeString()
			]);
		} else {
			return response()->api([
				'code' => $response['novus_user'][0]['code']
				'message' => $response['novus_user'][0]['message']
			]);
		}
		// END
	}

	public function testApiEndpoint()
	{
		// emergency, alert, critical, error, warning, notice, info and debug
		// Log::channel('slack')->error('Hitting first log to slack');
		app('log')->channel('slack')->error('Hitting first log to slack');

		// return response()->json($response);
		// return response()->api($response);
		
		$requestHeader = getallheaders();
		$requestBody = file_get_contents('php://input');
		echo '<pre>';
		print_r($requestHeader);
		echo '</pre>';
		
		echo 'POST data';
		echo '<pre>';
		print_r($_POST);
		echo '</pre>';
		
		echo '<pre>';
		print_r($requestBody);
		echo '</pre>';
		// return response()->api($requestHeader);
	}

    public function login()
    {
    	$credentials = [
    		'bit_uname' => 'tabassumali21',
    		'bit_password' => '!Scitilop!1'
    	];
    	// $credentials = 'bit_uname=tabassumali21&bit_password=!Scitilop!1';


    	// $endpoint = 'http://18.220.217.218/bittrain_exchange_api/public/api/currencies';
    	// $endpoint = 'http://localhost/projects/bittrain_exchange/bittrain_exchange_api/public/api/currencies';
    	// $endpoint = 'http://localhost/projects/bittrain_exchange/bittrain_exchange_api/public/api/test-get-apiendpoint';
    	// $endpoint = 'http://localhost/projects/bittrain_exchange/bittrain_exchange_api/public/api/test-post-apiendpoint';
    	$endpoint = 'https://bittrain.org/API/Welcome/check_web_login';
    	// $endpoint = 'http://18.220.217.218/test.php';

    	$header = $this->getLoginRequestHeader();

    	$client = new \GuzzleHttp\Client();
    	
    	/*$response = $client->get($endpoint, [
    		'headers' => [
    			'HTTP_AUTHENTICATION' => $header
    		]
    	]);*/
    	// $response = $client->request('get', $endpoint);

    	$response = $client->post($endpoint, [
    		'headers' => [
    			// 'HTTP_AUTHENTICATION' => $header
    			'AUTHENTICATION' => $header
    		],
    		// 'body' => $credentials,
    		'form_params' => $credentials
    	]);

		echo $response->getBody();
		echo '<hr /><br />';
		var_dump($response->getBody());

    	/*
    	$client = new \GuzzleHttp\Client();
		// $response = $client->request('GET', 'https://api.github.com/repos/guzzle/guzzle');
		$response = $client->get('https://api.github.com/repos/guzzle/guzzle');

		echo $response->getStatusCode(); # 200
		echo $response->getHeaderLine('content-type'); # 'application/json; charset=utf8'
		echo $response->getBody(); # '{"id": 1420053, "name": "guzzle", ...}'
		*/
    }

    public function testCurl()
    {
    	// curl -X POST -H "HTTP_AUTHENTICATION: cabjajacbecibg4phf0st3m1c5cabjajacbedabg" --data "bit_uname=tabassumali21&bit_password='!Scitilop!1'" https://bittrain.org/API/Welcome/check_web_login
    	

    	// $endpoint = 'http://localhost/projects/bittrain_exchange/bittrain_exchange_api/public/api/test-get-apiendpoint';
    	$endpoint = 'http://localhost/projects/bittrain_exchange/bittrain_exchange_api/public/api/test-post-apiendpoint';
    	$endpoint = 'https://bittrain.org/API/Welcome/check_web_login';
    	// $endpoint = 'http://18.220.217.218/test.php';
    	// $endpoint = 'http://18.220.217.218/bittrain_exchange_api/public/api/test-post-apiendpoint';

    	$header = $this->getLoginRequestHeader();
    	// echo $header;
    	// die;

    	$credentials = [
    		'bit_uname' => 'tabassumali21',
    		'bit_password' => '!Scitilop!1'
    	];
    	// $credentials = 'bit_uname=tabassumali21&bit_password=!Scitilop!1';



		$curl = curl_init();

		curl_setopt_array($curl, array(
		    CURLOPT_URL => $endpoint,
		    // CURLOPT_RETURNTRANSFER => true,
		    // CURLOPT_ENCODING => "",
		    // CURLOPT_MAXREDIRS => 10,
		    // CURLOPT_TIMEOUT => 30000,
		    // CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		    CURLOPT_CUSTOMREQUEST => "POST",
		    CURLOPT_POSTFIELDS => json_encode($credentials),
		    CURLOPT_HTTPHEADER => array(
		        "AUTHENTICATION: " . $header
		    ),
		    CURLOPT_HEADER => false,
		    CURLOPT_HEADER => 0
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		/*if ($err) {
		    echo "cURL Error #:" . $err;
		} else {
		    print_r(json_decode($response));
		}
		echo '<br />';
		var_dump($response);*/
    }
}