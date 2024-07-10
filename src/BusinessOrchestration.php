<?php

namespace Dimita\BusinessOrchestration;

use Illuminate\Support\Facades\Facade;

class BusinessOrchestration extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'business-orchestration';
    }
}