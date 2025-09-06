<?php

use Illuminate\Support\Facades\Broadcast;

// Тестовый канал для проверки broadcasting (публичный)
Broadcast::channel('test-trades', function () {
    return true;
});

// Публичный канал для реальных сделок
Broadcast::channel('live-trades', function () {
    return true;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Приватные каналы для real-time трейдинг данных
Broadcast::channel('user.{userId}.trades', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('user.{userId}.wallet', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('user.{userId}.positions', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
