Web crawler (for nakup.itesco.cz)
=================================

## FEATURES

- Fast reparsing - entire tesco.cz catalog is reparsed during 2 minutes (20123 items, 1005 pages)
- File (object) caching with [MessagePack](http://msgpack.org/) or JSON
- Reusable crawler toolkit
- "Almost-atomical" database changes - for better consistency
- JSON output mode
- CLI utility
- Usefull debugging


## SYSTEM REQUIREMENTS

- PHP 5.3+
- php-curl
- [php-msgpack](https://github.com/msgpack/msgpack-php) (optional)


## INSTALLATION

Create and modify config file:
```bash
$ cp config/config.sample.json config/config.json
$ vi config/config.json
```

Create database schema:
```bash
$ make init_db
```

## USAGE

Basic crawl collection: (tesco.cz collections: cats, promo_list, list, products)
```bash
$ ./crawler -c <collection>
```

Crawl to JSON file:
```bash
$ ./crawler -c <collection> -o outputfile.json
```

Crawl to MySQL database:
```bash
$ ./crawler -c <collection> -a mysql_simple
```

Print JSON output of scraping archive of PotravinyNaPranyri.cz:
```bash
$ ./crawler --class potravinynapranyri -c archive -a basic
```

Crawl all discounts from Skrz.cz :-D:
```bash
$ ./crawler --class skrz -c deals -o skrz.json
```

For debugging use `--debug` operator. 

You can modify cache TTL by `--ttl <secs>` and HTTP request interval by `-i <secs>`.
Reparsing is possible via `--reparse` option.


## EXAMPLE

```
sh# ./crawler -c cats -a mysql_simple --debug --reparse --ttl 0
[debug] Debugging enabled.
[debug] Cache TTL: 0s
[debug] Interval: 0.500000s
[debug] Using collection: cats
[debug] Downloading collection ..
[debug] Loading action: mysql_simple
[debug] Running action: mysql_simple [collection]
[debug] Get URL source: http://nakup.itesco.cz/cs-CZ/ 
[debug] HTTP GET: http://nakup.itesco.cz/cs-CZ/
[debug] Cache saved: cache/d/5/f/d5ff746e73acca5c314809af957988d8cea1f9db.plain [plain]
[debug] Get URL source: http://nakup.itesco.cz/cs-CZ/Department/List?navId=P1_Cat00000000 
[debug] HTTP GET: http://nakup.itesco.cz/cs-CZ/Department/List?navId=P1_Cat00000000
[debug] Cache saved: cache/a/3/c/a3c665fd28d6a93f2eded5b442d282177d22ce0f.plain [plain]
[debug] Parsing page: basecat, values: [Cat00000000,], fn: parse_page_basecat
[debug] Cache saved: cache/a/3/c/a3c665fd28d6a93f2eded5b442d282177d22ce0f.msgpack [msgpack]
[debug] [1/10] [walk] page: basecat, id: Cat00000000, items: 89, HTTP requests: 2, collected items: 89
[debug] Get URL source: http://nakup.itesco.cz/cs-CZ/Department/List?navId=P1_Cat00000183 
[debug] HTTP GET: http://nakup.itesco.cz/cs-CZ/Department/List?navId=P1_Cat00000183
[debug] Cache saved: cache/1/d/a/1daa2fbf0c7920d2f7b24c79fb63ecd7d7a9ae5b.plain [plain]
[debug] Parsing page: basecat, values: [Cat00000183,], fn: parse_page_basecat
[debug] Cache saved: cache/1/d/a/1daa2fbf0c7920d2f7b24c79fb63ecd7d7a9ae5b.msgpack [msgpack]
[debug] [2/10] [walk] page: basecat, id: Cat00000183, items: 18, HTTP requests: 3, collected items: 107
...
[debug] Updating database ..
[debug]  - 0/418
[debug]  - 100/418
[debug]  - 200/418
[debug]  - 300/418
[debug]  - 400/418
[debug] End action: mysql_simple [collection]
[debug] Done.
```

## AUTHOR

jan.stransky@arnal.cz

