@echo off
echo Starting Laravel Scheduler...
:loop
php artisan schedule:run
timeout /t 60 > nul
goto loop