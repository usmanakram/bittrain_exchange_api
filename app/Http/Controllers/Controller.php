<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function printResponse($arg) {
		/*$dir = 'coinpayments_ipn';

		// create new directory with 744 permissions if it does not exist yet
		// owner will be the user/group the PHP script is run under
		if ( !file_exists($dir) ) {
			mkdir ($dir, 0744);
		}

		file_put_contents ($dir.'/test.txt', 'Hello File');*/

		$file = fopen('custom.log', 'a') or die('Unable to open file!');
		fwrite($file, date('Y-m-d H:i:s') . "\n" . print_r($arg, true) . "\n\n");
		fclose($file);
	}

	private function decodeFragment($value)
	{
		return (array) json_decode(base64_decode($value));
	}

	protected function jwtDecode($jwt)
	{
		// $jwt = $jwt ?? \Auth::getToken();
		if ($jwt) {
			$jwt = list($header, $claims, $signature) = explode('.', $jwt);

			$header = $this->decodeFragment($header);
			$claims = $this->decodeFragment($claims);
			$signature = (string) base64_decode($signature);

			return [
				'header' => $header,
				'claims' => $claims,
				'signature' => $signature
			];
		}

		return false;
	}
}
