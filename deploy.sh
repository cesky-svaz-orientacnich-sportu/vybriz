#!/bin/bash

git pull
/usr/bin/php8.2 /usr/local/bin/composer install
npm install
npm run assets
sudo rm -r temp/cache
