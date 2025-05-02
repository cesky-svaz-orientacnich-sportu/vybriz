# Výběrová řízení na pořadatele závodů soutěží sekce OB

## Použité nástroje
- [Nette](https://nette.org/)
- [Gulp](https://gulpjs.com/) for asset processing


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
4. Zkopíruj `.env.template` do `.env` a nastav správné klíče.
5. Vytvořt manuálně databázi a příkazem naimportuj databázové schéma:
```sh
php ./www/index.php o:s:c
```
6. Zkompiluj assety:
```sh
npm run build
```
7. Web je dostupný k prohlížení v prohlížeči ve složce `www/`.

Je **důležité** zajistit, aby složky `app/`, `log/` a `temp/` nebyly přístupné přímo v prohlížeči (viz [security warning](https://nette.org/cs/security-warning)).


## Vývoj
1. Používej [EditorConfig](https://editorconfig.org/) pro jednotný vzhled kódu.
2. Nainstaluj si aplikaci podle návodu výše.
3. Vytvoř virtualhost směřující do složky `www/`, nebo pusť aplikaci rovnou pomocí php příkazu:
```sh
php -S localhost:8000 -t www
```
4. Pokud hodláš pracovat s assety, spusť vývojový task (se zahrnutým watch taskem):
```sh
npm run dev
```


## Struktura repozitáře
- `master` větev odpovídá současné verzi na produkci
- `dev` větev je hlavní vývojová/testovací větev, ze které větvíme feature větve
- releasy jsou verzované podle [Semantic Versioning](https://semver.org/)
- prosím [udržuj CHANGELOG](https://keepachangelog.com/)


## Deploy
On the remote server run:
```
./deploy.sh
```
