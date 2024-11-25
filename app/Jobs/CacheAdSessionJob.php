<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Redis;

class CacheAdSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
     
    protected $sessid;
    protected $adsess;
    
    public function __construct($sessid, $adsess)
    {
        $this->sessid = $sessid;
        $this->adsess = $adsess;
    }

    public function handle()
    {
        $redisCon = Redis::connection('default');
        $redisCon->rawCommand('setex', "ad_sessions:".$this->sessid, 3600, json_encode($this->adsess));
    }
}
