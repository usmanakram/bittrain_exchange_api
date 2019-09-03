<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp;
use App\Word;

class UsersController extends Controller
{
	public function testGetApiEndpoint()
	{		
		// return response()->json($response);
		// return response()->api($response);

		$requestHeader = getallheaders();
		$requestBody = file_get_contents('php://input');
		return response()->api($requestBody);
	}

	public function testPostApiEndpoint()
	{
		// return response()->json($response);
		// return response()->api($response);
		
		$requestHeader = getallheaders();
		$requestBody = file_get_contents('php://input');
		echo '<pre>';
		print_r($requestHeader);
		echo '</pre>';
		
		echo '<pre>';
		print_r($requestBody);
		echo '</pre>';
		// return response()->api($requestHeader);
	}

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

    public function login()
    {
    	/*$credentials = [
    		'bit_uname' => 'tabassumali21',
    		'bit_password' => '!Scitilop!1'
    	];*/
    	$credentials = 'bit_uname=tabassumali21&bit_password=!Scitilop!1';


    	// $endpoint = 'http://18.220.217.218/bittrain_exchange_api/public/api/currencies';
    	// $endpoint = 'http://localhost/projects/bittrain_exchange/bittrain_exchange_api/public/api/currencies';
    	// $endpoint = 'http://localhost/projects/bittrain_exchange/bittrain_exchange_api/public/api/test-get-apiendpoint';
    	// $endpoint = 'http://localhost/projects/bittrain_exchange/bittrain_exchange_api/public/api/test-post-apiendpoint';
    	$endpoint = 'https://bittrain.org/API/Welcome/check_web_login';

    	$header = $this->getLoginRequestHeader();

    	$client = new \GuzzleHttp\Client();
    	
    	/*$response = $client->get($endpoint, [
    		'headers' => [
    			'HTTP_AUTHENTICATION' => $header
    		]
    	]);*/
    	// $response = $client->request('get', $endpoint);
    	/*$response = $client->request('get', $endpoint, [
    		'headers' => [
    			'HTTP_AUTHENTICATION' => $header
    		]
    	]);*/

    	$response = $client->post($endpoint, [
    		/*'headers' => [
    			'HTTP_AUTHENTICATION' => $header
    		],*/
    		'headers' => [
    			'HTTP_AUTHENTICATION' => $header
    		],
    		'body' => $credentials
    	]);

    	// echo $response->getStatusCode(); # 200
		// echo '<br />';
		// echo $response->getHeaderLine('content-type'); # 'application/json; charset=utf8'
		// echo '<br />';
		// echo $response->getBody(); # '{"id": 1420053, "name": "guzzle", ...}'

		echo $response->getBody();
		echo '<br /><br />';
		var_dump($response->getBody());

    	echo '<pre>';
    	print_r($response->getBody());
    	echo '</pre>';

    	dd($response);

    	/*
    	$client = new \GuzzleHttp\Client();
		// $response = $client->request('GET', 'https://api.github.com/repos/guzzle/guzzle');
		$response = $client->get('https://api.github.com/repos/guzzle/guzzle');

		echo $response->getStatusCode(); # 200
		echo '<br />';
		echo $response->getHeaderLine('content-type'); # 'application/json; charset=utf8'
		echo '<br />';
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
