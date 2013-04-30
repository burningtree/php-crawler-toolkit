all: 

reinstall: clean init

update:
	./crawler -c cats --debug
	./crawler -c promo_list --debug

update_all:
	./crawler -c cats --debug
	./crawler -c list --debug

init: update

clean: clean_db clean_cache

init_db:
	cat schema/schema.sql | \
	  mysql -h`php ./lib/config.php mysql host` -u`php ./lib/config.php mysql user` \
	  -p`php ./lib/config.php mysql pass` `php ./lib/config.php mysql db`

clean_db: init_db

clean_cache:
	rm -f ./cache/*

test:
	echo 'SELECT i.name, p.price FROM `items_last` i LEFT JOIN prices_last p ON p.id = i.id LIMIT 10;' | \
	  mysql -h`php ./lib/config.php mysql host` -u`php ./lib/config.php mysql user` \
    -p`php ./lib/config.php mysql pass` `php ./lib/config.php mysql db`

mysql_console:
	mysql -h`php ./lib/config.php mysql host` -u`php ./lib/config.php mysql user` \
    -p`php ./lib/config.php mysql pass` `php ./lib/config.php mysql db`
