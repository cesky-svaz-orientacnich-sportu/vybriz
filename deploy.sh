#!/bin/bash

umask 022
git pull
/usr/bin/php8.3 /usr/local/bin/composer install
pnpm install
pnpm run build
sudo rm -r temp/cache
