<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\InvestmentSubmitted;
use App\Events\ProjectSubmitted;
use App\Listeners\SendInvestmentSubmittedNotification;
use App\Listeners\NotifyAdminsOfProjectSubmission;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        InvestmentSubmitted::class => [
            SendInvestmentSubmittedNotification::class,
        ],

       ProjectSubmitted::class => [
            NotifyAdminsOfProjectSubmission::class,
        ],
        
    ];

    public function boot()
    {
        parent::boot();
    }
}