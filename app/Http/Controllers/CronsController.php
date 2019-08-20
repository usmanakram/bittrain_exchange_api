<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Historical_price;
use App\Latest_price;

class CronsController extends Controller
{
	private $api;

	public function __construct()
	{
		$this->api = new \Binance\API("<api key>","<secret>");

		$this->api->useServerTime();
	}

	private function fetchNextHistory($symbol, $interval, $limit)
	{
		/*$latestCandle = $this->db->order_by('open_time', 'desc')
			->limit(1)
			->get_where('candlesticks', ['pair' => $symbol, 'time_interval' => $interval])
			->row_array();*/

		$latestCandle = Historical_price::where('pair', $symbol)
							->where('time_interval', $interval)
							->orderBy('open_time', 'desc')
							->first();

		// Get Kline/candlestick data for a symbol
		// Periods: 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
		// return $this->api->candlesticks($symbol, $interval, $limit, strtotime('2017-01-01 00:00:00') * 1000);
		return $this->api->candlesticks($symbol, $interval, $limit, $latestCandle['open_time'] + 1);
	}

	public function insert_history(string $symbol = 'BTCUSDT', string $interval = "1d")
	{
		$history = $this->fetchNextHistory($symbol, $interval, $limit = 200);

		if ($history) {
			// $query = $this->createQueryString($history, $symbol, $interval);
			// return $this->db->queryRun($query);

			$data = [];

			foreach ($history as $key => $value) {
				$data[] = new Historical_price([
					'pair' => $symbol,
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
			}
			
		} else {
			return 'History fully updated.';
		}
	}

	public function cron_1day(Request $request)
	{
		echo 'inside cron 1day';

		// start
		$pairs = ['BTCUSDT','ETHUSDT','XRPUSDT','BCHABCUSDT','LTCUSDT','BNBUSDT','EOSUSDT','XMRUSDT','XLMUSDT','TRXUSDT','ADAUSDT','DASHUSDT','LINKUSDT','NEOUSDT','IOTAUSDT','ETCUSDT'];

		$interval = '1d';
		$limit = 200;

		foreach ($pairs as $pair) {
			$response = $this->insert_history($pair);
		}

		echo '<pre>';
		print_r($response);
		echo '</pre>';

		// end



		/*require 'binance.php';
		$binance = new Binance_history();

		$pairs = ['BTCUSDT','ETHUSDT','XRPUSDT','BCHABCUSDT','LTCUSDT','BNBUSDT','EOSUSDT','XMRUSDT','XLMUSDT','TRXUSDT','ADAUSDT','DASHUSDT','LINKUSDT','NEOUSDT','IOTAUSDT','ETCUSDT'];

		foreach ($pairs as $pair) {
			$response = $binance->insert_history($pair);
		}

		echo '<pre>';
		print_r($response);
		echo '</pre>';*/
	}


	private function fetchNextHistory($symbol, $interval, $limit)
    {
        // $data = $this->db->get_where('candlesticks', ['pair' => 'BTCUSDT', 'time_interval' => '1d'])->result_array();
        $latestCandle = $this->db->order_by('open_time', 'desc')
                        ->limit(1)
                        ->get_where('candlesticks', ['pair' => $symbol, 'time_interval' => $interval])
                        ->row_array();

        // Get Kline/candlestick data for a symbol
        // Periods: 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
        // return $this->api->candlesticks($symbol, $interval, $limit, strtotime('2017-01-01 00:00:00') * 1000);
        return $this->api->candlesticks($symbol, $interval, $limit, $latestCandle['open_time'] + 1);
    }

    private function createQueryString($history, $symbol, $interval)
    {
        $query = 'INSERT INTO `candlesticks` (`pair`, `time_interval`, `open`, `high`, `low`, `close`, `volume`, `open_time`, `close_time`, `asset_volume`, `base_volume`, `trades`, `asset_buy_volume`, `taker_buy_volume`, `ignored`) VALUES ';

        foreach ($history as $key => $value) {

            $query .= '
            (' . 
                '"' . $symbol . '", ' . 
                '"' . $interval . '", ' . 
                $value['open'] . ', ' . 
                $value['high'] . ', ' . 
                $value['low'] . ', ' . 
                $value['close'] . ', ' . 
                $value['volume'] . ', ' . 
                $value['openTime'] . ', ' . 
                $value['closeTime'] . ', ' . 
                $value['assetVolume'] . ', ' . 
                $value['baseVolume'] . ', ' . 
                $value['trades'] . ', ' . 
                $value['assetBuyVolume'] . ', ' . 
                $value['takerBuyVolume'] . ', ' . 
                $value['ignored'] . 
            '), ';
        }

        return rtrim($query, ', ') . ';';
    }

    public function insert_history(string $symbol = 'BTCUSDT', string $interval = "1d")
    {
        $history = $this->fetchNextHistory($symbol, $interval, $limit = 200);

        if ($history) {
            $query = $this->createQueryString($history, $symbol, $interval);
            return $this->db->queryRun($query);
        } else {
            return 'History fully updated.';
        }
    }

	public function cron_1min(Request $request)
	{
		$symbols = ['BTCUSDT','ETHUSDT','XRPUSDT','BCHABCUSDT','LTCUSDT','BNBUSDT','EOSUSDT','XMRUSDT','XLMUSDT','TRXUSDT','ADAUSDT','DASHUSDT','LINKUSDT','NEOUSDT','IOTAUSDT','ETCUSDT'];

		$prevDayData = $this->api->prevDay();

		if ($prevDayData) {
			foreach ($symbols as $symbol) {
				if ( $index = array_search($symbol, array_column($prevDayData, 'symbol')) ) {

					$latestData = $prevDayData[$index];

					Latest_price::updateOrCreate(
						['pair' => $symbol],
						[
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
	}
}
