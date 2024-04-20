#!/bin/bash

git pull
/usr/bin/php8.3 /usr/local/bin/composer install
npm install
npm run assets
sudo rm -r temp/cache
