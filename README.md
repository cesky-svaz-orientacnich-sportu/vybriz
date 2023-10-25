# Výběrová řízení na pořadatele závodů soutěží sekce OB

## Použité nástroje
- [Nette](https://nette.org/)


## Instalace
1. Nainstaluj závislosti:
```sh
composer install
npm install
```
2. Povol práva zápisu složkám `temp/` a `log/`:
```sh
mkdir temp log
chmod -R a+rw temp log
```
3. Zkopíruj `app/config/config.local.neon.example` do `app/config/config.local.neon` a nastav správné údaje pro připojení k databázi a maileru.
4. Vytvořt manuálně databázi a příkazem naimportuj databázové schéma:
```sh
php ./www/index.php o:s:c
```
5. Zkopíruj assety do `www` složky:
```sh
npm run assets
```
6. Web je dostupný k prohlížení v prohlížeči ve složce `www/`.

Je **důležité** zajistit, aby složky `app/`, `log/` a `temp/` nebyly přístupné přímo v prohlížeči (viz [security warning](https://nette.org/cs/security-warning)).


## Vývoj
1. Používej [EditorConfig](https://editorconfig.org/) pro jednotný vzhled kódu.
2. Nainstaluj si aplikaci podle návodu výše.
3. Vytvoř virtualhost směřující do složky `www/`, nebo pusť aplikaci rovnou pomocí php příkazu:
```
php -S localhost:8000 -t www
```


## Struktura repozitáře
- `master` větev odpovídá současné verzi na produkci
- `dev` větev je hlavní vývojová/testovací větev, ze které větvíme feature větve
- releasy jsou verzované podle [Semantic Versioning](https://semver.org/)
- prosím [udržuj CHANGELOG](https://keepachangelog.com/)

