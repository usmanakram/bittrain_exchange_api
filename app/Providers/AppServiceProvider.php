<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Queue::before(function (JobProcessing $event) {
            // $event->connectionName
            // $event->job
            // $event->job->payload()
        });

        Queue::after(function (JobProcessed $event) {
            // $event->connectionName
            // $event->job
            // $event->job->payload()
        });

        Queue::failing(function (JobFailed $event) {
            // $event->connectionName
            // $event->job
            // $event->exception
            
            // Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
            Log::channel('slack')->debug(
                "Job Failed: \n" . 
                // "*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
                "*Connection:* " . $event->connectionName . "\n" . 
                "*Job:* " . $event->job->resolveName() . "\n" . 
                "*Exception:* " . $event->exception->getMessage()
            );
        });

        /*Queue::looping(function () {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        });*/
    }
}
