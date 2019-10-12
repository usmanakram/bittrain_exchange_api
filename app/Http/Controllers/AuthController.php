<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\User;
use App\Word;
use GuzzleHttp;

class AuthController extends Controller
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
            'bit_password' => '!Scitilop!1a'
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

    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'bit_uname' => 'required|string',
            'bit_password' => 'required'
        ]);

        try {

            $api_response = $this->authFromBittrain($validatedData);

        } catch (\Exception $e) {
            // Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
            Log::channel('slack')->emergency(
                "Bittrain Login Resposponse: \n" . 
                "*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
                "*User:* " . json_encode($validatedData) . "\n" . 
                $e->getMessage()
            );

            return response()->api('Some error occurred. Please, try again later', 400);
        }

        $parsedData = json_decode($api_response, true);
        // app('log')->channel('slack')->debug($parsedData);

        /*$parsedData = array (
            'novus_user' => array (
                0 => array (
                    'user_id' => '538',
                    'full_name' => 'Tabassum Ali',
                    'real_email' => 'tabassumali970@hotmail.com',
                    'username' => 'tabassumali21',
                    'pic_url' => 'https://www.bittrain.org/sample/Profiles/538/Snapchat-181798176.jpg',
                    'create_date' => '2018-09-21',
                    'invest_amount' => '',
                    'total_earnings' => '0',
                    'roi_earnings' => '100',
                    'roi_coins' => '8.58',
                    'package_coins' => NULL,
                    'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjoiNTM4IiwiZnVsbF9uYW1lIjoiVGFiYXNzdW0gQWxpIiwicmVhbF9lbWFpbCI6InRhYmFzc3VtYWxpOTcwQGhvdG1haWwuY29tIiwidXNlcm5hbWUiOiJ0YWJhc3N1bWFsaTIxIiwiY3JlYXRlX2RhdGUiOiIyMDE4LTA5LTIxIn0.7QMu24Btw2aVh828O8uaQPVJ8_hoEXA1_zXXxXjlSXI',
                ),
            ),
        );
        $api_response = json_encode($parsedData);*/

        if ( isset($parsedData['novus_user'][0]['user_id']) ) {
            $bittrain_user = $parsedData['novus_user'][0];

            // Log::channel('slack')->debug($bittrain_user);

            $user = User::find($bittrain_user['user_id']);

            // Create user
            if ( !$user ) {
                $user = new User([
                    'id' => $bittrain_user['user_id'],
                    'name' => $bittrain_user['full_name'],
                    'email' => $bittrain_user['real_email'],
                    'password' => ''
                ]);

                $user->save();
            }

            // Create / Update user's bittrain data
            if ($user->bittrain_detail) {
                $user->bittrain_detail->data = $api_response;
                $user->bittrain_detail->save();
            } else {
                $user->bittrain_detail()->create(['data' => $api_response]);
            }
            // $user->bittrain_detail()->updateOrCreate(['data' => $api_response]);


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
            return response()->api($parsedData['novus_user'][0]['message'], 400);
        }
    }

    /**
     * Create user
     *
     * @param  [string] name
     * @param  [string] email
     * @param  [string] password
     * @param  [string] password_confirmation
     * @return [string] message
     */
    public function signup(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|confirmed'
        ]);
        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);
        $user->save();
        return response()->json([
            'message' => 'Successfully created user!'
        ], 201);
    }
  
    /**
     * Login user and create token
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [boolean] remember_me
     * @return [string] access_token
     * @return [string] token_type
     * @return [string] expires_at
     */
    /*public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);

        $credentials = request(['email', 'password']);
        
        if(!Auth::attempt($credentials))
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);

        $user = $request->user();

        $tokenResult = $user->createToken('Personal Access Token');
        
        $token = $tokenResult->token;
        
        if ($request->remember_me)
            $token->expires_at = Carbon::now()->addWeeks(1);
        
        $token->save();
        
        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString()
        ]);
    }*/
  
    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }
  
    /**
     * Get the authenticated User
     *
     * @return [json] user object
     */
    public function user(Request $request)
    {
        // return response()->json($request->user());
        return response()->api($request->user());
    }
}