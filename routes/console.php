<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('news:monitor')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
