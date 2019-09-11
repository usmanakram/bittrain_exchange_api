<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $api_response = $this->authFromBittrain($validatedData);

        // Slack Log
        // app('log')->channel('slack')->debug("Bittrain Login Resposponse: \n" . $api_response);

        $parsedData = json_decode($api_response, true);
        // app('log')->channel('slack')->debug($parsedData);

        /*$user = $parsedData['novus_user'][0];
        return response()->api($user);*/

        if ( isset($parsedData['novus_user'][0]['user_id']) ) {
            $bittrain_user = $parsedData['novus_user'][0];

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
            }


            $tokenResult = $user->createToken('Personal Access Token');

            $token = $tokenResult->token;

            if ($request->remember_me)
                $token->expires_at = Carbon::now()->addWeeks(1);

            $token->save();

            $response = [
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'expires_at' => Carbon::parse(
                    $tokenResult->token->expires_at
                )->toDateTimeString()
            ];
        } else {
            $response = [
                'code' => $parsedData['novus_user'][0]['code'],
                'message' => $parsedData['novus_user'][0]['message']
            ];
        }
        
        return response()->api($response);
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
        return response()->json($request->user());
    }
}