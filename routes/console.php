<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('hexaqualify:expire')->hourly();
Schedule::command('hexaqualify:remind')->dailyAt('09:00')->timezone('Asia/Kathmandu');
Schedule::command('hexaqualify:purge')->dailyAt('02:30')->timezone('Asia/Kathmandu');
