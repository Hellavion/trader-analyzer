<?php
// Отдельный SSE сервер только для Laravel Wave
// Порт 8001 - только /wave endpoint

// Маркер что это SSE сервер
$_ENV['SSE_SERVER_MODE'] = 'true';
$_ENV['DEV_ENABLE_SSE'] = 'true';
$_ENV['BROADCAST_CONNECTION'] = 'redis';

// Загружаем Laravel с включенными Wave маршрутами
require_once __DIR__.'/public/index.php';