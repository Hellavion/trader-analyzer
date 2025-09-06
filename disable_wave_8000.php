<?php
// Скрипт для запуска Laravel сервера на порту 8000 БЕЗ Laravel Wave

// Временно отключаем Wave
$_ENV['BROADCAST_DRIVER'] = 'log';

require_once 'public/index.php';