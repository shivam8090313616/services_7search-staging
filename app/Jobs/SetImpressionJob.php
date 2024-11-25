<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Redis;

class SetImpressionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
     
    protected $impData;
    protected $pricingModel;
    protected $countryCode;
    protected $advCpm;
    protected $pubCpm;
    
    public function __construct($impData, $pricingModel, $countryCode, $advCpm, $pubCpm)
    {
        $this->impData = $impData;
        $this->pricingModel = $pricingModel;
        $this->countryCode = $countryCode;
        $this->advCpm = $advCpm;
        $this->pubCpm = $pubCpm;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Assuming Redis is configured to use the default connection
        $redisCon = Redis::connection('default');
        setImpression($this->impData, $this->pricingModel, $this->countryCode, $this->advCpm, $this->pubCpm);
    }
}
