<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,        // Agent 9: Observers + Event-Listener
    App\Providers\InheritanceServiceProvider::class,   // Agent 4: Vererbungs-Services + Cache-Invalidierung
    App\Providers\PqlServiceProvider::class,           // Agent 5: PQL-Engine
    App\Providers\ExportServiceProvider::class,        // Agent 7: Export + Publixx
];
