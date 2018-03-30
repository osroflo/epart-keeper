# ePart Keeper
System process to keep content up-to-date. The content is the electronic parts information.:

1- Update the current database of electronic parts that the client already has (missing info). The following info should be updated:
- Manufacturer
- PN
- Product description
- Qty/Stock (Change qty randomly): Always have more than the competitors.
- Price (only parts that are quoted in the invoice system).
- Product or electronic part image.

2 - Every part that is quoted in the current invoice system should update the DB of electronic parts (products) automatically.

3 - Constantly getting new parts using a scraper or any other API bridge that can provide parts information.

4 - Notification System:
- Inform of the scraper or api activity.

### Sites to crawl
* oemstrade
* vyrian
* verical

### Technology
ePart Keeper uses a number of open source projects to work properly:

* Laravel 5.2
* Apache 2.4 >
* PHP 5.6
* MySql 5.6


### Installation
```sh
# clone
$ git clone [git-repo-url]
$ cd epart-keeper

# install dependencies
$ composer install

# create db
mysql> create database epart-keeper

# migrate tables
php artisan migrate

# seed database
php artisan db:seed

# grant write permissions to bootstrap/cache directory for server (i.e. Apache)
# username will differ in different environments (i.e. apache, www-data)
sudo chown www-data bootstrap/cache/
```

### Configuration
```sh
# create .env file by copying from infra

# edit .env file and set appropriate credentials for your DB
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=epart-keeper
DB_USERNAME=<your user name>
DB_PASSWORD=<your password>
```
### Manualy running the scrapper
oemstrade (currently oemstrade is the only process that is scheduled to run automatically every 10 mins)
```sh
cd /var/www/epart-keeper/current/
php artisan scrapper:new

# To check scrapper activity
tail -f storage/logs/oemstrade.log
```

vyrian
```sh
cd /var/www/epart-keeper/current/
php artisan scrapper:vyrian

# To check scrapper activity
tail -f storage/logs/vyrian.log
```


### Scheduling Command (Cron)
```sh
# Setup the task scheduling (prodution only)
# This task scheduler is used to crawl websites to get pn.
crontab -e
* * * * * php /var/www/epart-keeper/current/artisan schedule:run >> /dev/null 2>&1
```
Disclaimer
----
This project is for sample code purpose only.
