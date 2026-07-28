#!/bin/sh
set -e

# vendor/ живёт в именованном Docker volume (не в bind mount с хоста) —
# так быстрее на Windows/macOS, где bind mount для тысяч мелких файлов
# vendor/node_modules заметно тормозит по сравнению с named volume.
if [ ! -f "vendor/autoload.php" ]; then
    echo "vendor/ не найден — выполняю composer install..."
    composer install --prefer-dist --no-interaction
fi

if [ ! -f ".env" ]; then
    echo ".env не найден — копирую .env.docker как .env для контейнера..."
    cp .env.docker .env
fi

php artisan config:clear
php artisan migrate --force

exec "$@"