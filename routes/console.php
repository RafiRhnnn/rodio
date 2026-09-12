<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('audio:recover')->everyTenMinutes();
Schedule::command('audio:cleanup')->hourly();
