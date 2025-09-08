# Deployment Instructions - Trader Analyzer

## Правильная настройка для разных сред

### Development (локальная разработка)

1. **Apache Virtual Host**
   - Скопировать `apache-vhost.conf` в Apache `sites-available`
   - Включить сайт: `a2ensite trader-analyzer`
   - Добавить в `/etc/hosts`: `127.0.0.1 projects.test`
   - Перезагрузить Apache

2. **Windows (XAMPP/WAMP)**
   - Добавить конфигурацию в `httpd-vhosts.conf`
   - Добавить в `C:\Windows\System32\drivers\etc\hosts`: `127.0.0.1 projects.test`
   - Перезапустить Apache

### Production (хостинг)

1. **Shared Hosting**
   ```
   Domain: your-domain.com
   DocumentRoot должен указывать на /public папку Laravel
   
   Структура на сервере:
   /home/user/your-domain.com/
   ├── app/
   ├── bootstrap/
   ├── config/
   ├── database/
   ├── public/          <- DocumentRoot указывает сюда
   │   ├── index.php
   │   └── .htaccess
   ├── resources/
   ├── routes/
   └── vendor/
   ```

2. **VPS/Dedicated Server**
   ```apache
   <VirtualHost *:80>
       ServerName your-domain.com
       DocumentRoot /var/www/your-app/public
       
       <Directory /var/www/your-app/public>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

3. **Nginx Configuration**
   ```nginx
   server {
       listen 80;
       server_name your-domain.com;
       root /var/www/your-app/public;
       index index.php;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```

### Environment Configuration

**Development (.env):**
```
APP_URL=http://projects.test
APP_ENV=local
APP_DEBUG=true
```

**Production (.env):**
```
APP_URL=https://your-domain.com
APP_ENV=production
APP_DEBUG=false
```

### Важные принципы:

1. **DocumentRoot всегда указывает на /public**
2. **APP_URL содержит только домен без пути**
3. **Laravel .htaccess обрабатывает маршрутизацию**
4. **Никаких изменений в коде для разных сред**

### После настройки сервера:

1. Установить зависимости: `composer install --no-dev`
2. Настроить .env файл
3. Сгенерировать ключ: `php artisan key:generate`
4. Выполнить миграции: `php artisan migrate`
5. Собрать frontend: `npm run build`
6. Настроить права доступа на storage/ и bootstrap/cache/
7. Настроить cron для Laravel scheduler (если используется)

### Troubleshooting:

- **404 на маршрутах**: Проверить DocumentRoot, должен указывать на /public
- **Permissions errors**: Настроить права доступа для web-сервера
- **Assets не загружаются**: Проверить настройки Vite/asset compilation
- **CSRF ошибки**: Убедиться что APP_URL соответствует реальному домену