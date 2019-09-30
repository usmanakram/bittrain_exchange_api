<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Historical_price;
use App\Latest_price;
use App\Currency_pair;

class CronsController extends Controller
{
	private $api;

	public function __construct()
	{
		// ini_set('max_execution_time', '0');
		$this->api = new \Binance\API("<api key>","<secret>");

		$this->api->useServerTime();
	}

	// private function fetchNextHistory($symbol, $interval, $limit)
	private function fetchNextHistory($currency_pair, $interval, $limit)
	{
		// $latestCandle = Historical_price::where('pair', $symbol)
		$latestCandle = Historical_price::where('currency_pair_id', $currency_pair->id)
							->where('time_interval', $interval)
							->orderBy('open_time', 'desc')
							->first();

		// Get Kline/candlestick data for a symbol
		// Periods: 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
		// return $this->api->candlesticks($symbol, $interval, $limit, strtotime('2017-01-01 00:00:00') * 1000);
		return $this->api->candlesticks($currency_pair->symbol, $interval, $limit, $latestCandle['open_time'] + 1);
	}

	// private function insert_history(string $symbol = 'BTCUSDT', string $interval = "1d")
	private function insert_history($currency_pair, string $interval = "1d")
	{
		// $history = $this->fetchNextHistory($symbol, $interval, $limit = 200);
		$history = $this->fetchNextHistory($currency_pair, $interval, $limit = 200);

		if ($history) {

			$data = [];

			foreach ($history as $key => $value) {
				// $data[] = [
				$data = new Historical_price([
					'currency_pair_id' => $currency_pair->id,
					// 'pair' => $symbol,
					'pair' => '',
					'time_interval' => $interval,
					'open' => $value['open'], 
					'high' => $value['high'], 
					'low' => $value['low'], 
					'close' => $value['close'], 
					'volume' => $value['volume'], 
					'open_time' => $value['openTime'], 
					'close_time' => $value['closeTime'], 
					'asset_volume' => $value['assetVolume'], 
					'base_volume' => $value['baseVolume'], 
					'trades' => $value['trades'], 
					'asset_buy_volume' => $value['assetBuyVolume'], 
					'taker_buy_volume' => $value['takerBuyVolume'], 
					'ignored' => $value['ignored']
				]);

				$data->save();
			}

			// Historical_price::insert($data);
			
		} else {
			return 'History fully updated.';
		}
	}

	public function cron_1day(Request $request)
	{
		$pairs = ['BTCUSDT','ETHUSDT','XRPUSDT','BCHABCUSDT','LTCUSDT','BNBUSDT','EOSUSDT','XMRUSDT','XLMUSDT','TRXUSDT','ADAUSDT','DASHUSDT','LINKUSDT','NEOUSDT','IOTAUSDT','ETCUSDT'];

		$interval = '1d';
		$limit = 200;

		/*foreach ($pairs as $pair) {
			$response = $this->insert_history($pair);
		}*/

		$currency_pairs = Currency_pair::whereIn('symbol', $pairs)->get();
		foreach ($currency_pairs as $currency_pair) {
			$response = $this->insert_history($currency_pair);
		}

		echo '<pre>';
		print_r($response);
		echo '</pre>';
	}

	public function cron_1min(Request $request)
	{
		$symbols = ['BTCUSDT','ETHUSDT','XRPUSDT','BCHABCUSDT','LTCUSDT','BNBUSDT','EOSUSDT','XMRUSDT','XLMUSDT','TRXUSDT','ADAUSDT','DASHUSDT','LINKUSDT','NEOUSDT','IOTAUSDT','ETCUSDT'];

		$prevDayData = $this->api->prevDay();

		if ($prevDayData) {
			foreach ($symbols as $symbol) {
				if ( $index = array_search($symbol, array_column($prevDayData, 'symbol')) ) {

					$latestData = $prevDayData[$index];

					$currency_pair = Currency_pair::whereSymbol($symbol)->first();

					Latest_price::updateOrCreate(
						// ['pair' => $symbol],
						['currency_pair_id' => $currency_pair->id],
						[
							'pair' => '', // to be deleted
							'last_price' => $latestData['lastPrice'],
							'volume' => $latestData['volume'],
							'price_change_percent' => $latestData['priceChangePercent']
						]
					);


					/*$existingData = Latest_price::where('pair', $symbol)->get();

					if ( $existingData ) {

						$existingData->fill([
							'last_price' => $latestData['lastPrice'],
							'volume' => $latestData['volume'],
							'price_change_percent' => $latestData['priceChangePercent']
						]);

						$existingData->save();

					} else {

						$latest_price = new Latest_price([
							'pair' => $symbol,
							'last_price' => $latestData['lastPrice'],
							'volume' => $latestData['volume'],
							'price_change_percent' => $latestData['priceChangePercent']
						]);

						$latest_price->save();
					}*/
				}
			}
			$response = 'Data has been updated.';
		} else {
			$response = 'Data is not being fetched';
		}

		echo '<pre>';
		print_r($response);
		echo '</pre>';
 
		// $prices = \App\Latest_price::orderBy('id')->get();
		$prices = Currency_pair::with('latest_price')->whereStatus(true)->orderBy('id')->get();

		event(new \App\Events\NewMessage("NewMessage from home channel: Prices have been updated"));
		// event(new \App\Events\LiveRates("LiveRates from live channel: Prices have been updated"));
		event(new \App\Events\LiveRates( $prices ));

		/*ini_set('max_execution_time', 60);
		$this->api->miniTicker(function($api, $ticker) use ($symbols) {
			foreach ($symbols as $symbol) {
				if ( $index = array_search($symbol, array_column($ticker, 'symbol')) ) {
					Latest_price::updateOrCreate(['pair' => $symbol], ['last_price' => $ticker[$index]['open']]);
				}
			}
			event(new \App\Events\LiveRates(\App\Latest_price::orderBy('id')->get()));
		});*/
	}
}
