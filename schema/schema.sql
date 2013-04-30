
SET foreign_key_checks = 0;
SET names 'utf8';

DROP TABLE IF EXISTS cats;
CREATE TABLE cats
(
  id VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  parent VARCHAR(255),
  final TINYINT(1),
  url VARCHAR(255),
  created DATETIME NOT NULL,
  hash VARCHAR(255) NOT NULL PRIMARY KEY

) COLLATE='utf8_czech_ci';

CREATE INDEX cats_id ON cats (id);
CREATE INDEX cats_created ON cats (created);
CREATE INDEX cats_group ON cats (id, created, hash);

DROP VIEW IF EXISTS cats_last;
CREATE VIEW cats_last AS SELECT c1.* FROM cats c1 LEFT JOIN cats c2 
  ON c1.id = c2.id AND c1.created < c2.created WHERE c2.id IS NULL GROUP BY c1.id;

DROP TABLE IF EXISTS items;
CREATE TABLE items
(
  id VARCHAR(255) NOT NULL,
  cat VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  name_full VARCHAR(255) NOT NULL,
  ean VARCHAR(13) NULL,
  pcs INT NOT NULL,
  weight VARCHAR(255) NULL,
  img_small VARCHAR(255) NULL,
  img_big VARCHAR(255) NULL,
  url VARCHAR(255) NOT NULL,
  created DATETIME NOT NULL,
  hash VARCHAR(255) NOT NULL PRIMARY KEY

) COLLATE='utf8_czech_ci';

DROP VIEW IF EXISTS items_last;
CREATE VIEW items_last AS SELECT c1.* FROM items c1 LEFT JOIN items c2
  ON c1.id = c2.id AND c1.created < c2.created WHERE c2.id IS NULL GROUP BY c1.id;

CREATE INDEX items_id ON items (id);
CREATE INDEX items_created ON items (created);
CREATE INDEX items_group ON items (id, created, hash);

DROP TABLE IF EXISTS prices;
CREATE TABLE prices
(
  id VARCHAR(255) NOT NULL,
  price FLOAT(8,2),
  price_unit FLOAT(8,2),
  price_unit_type VARCHAR(10),
  promo_sale INT,
  promo_until TIMESTAMP NULL,
  price_original FLOAT(8,2),
  created DATETIME NOT NULL,
  hash VARCHAR(255) NOT NULL PRIMARY KEY

) COLLATE='utf8_czech_ci';

DROP VIEW IF EXISTS prices_last;
CREATE VIEW prices_last AS SELECT c1.* FROM prices c1 LEFT JOIN prices c2 
  ON c1.id = c2.id AND c1.created < c2.created WHERE c2.id IS NULL GROUP BY c1.id;

CREATE INDEX prices_id ON prices (id);
CREATE INDEX prices_created ON prices (created);
CREATE INDEX prices_group ON prices (id, created, hash);

DROP TABLE IF EXISTS infos;
CREATE TABLE infos
(
  id VARCHAR(255) NOT NULL,
  description TEXT,
  composition TEXT,
  storage TEXT,
  packaging TEXT,
  lifestyle TEXT,
  alcohol TEXT,
  energylabels TEXT,
  created DATETIME NOT NULL,
  hash VARCHAR(255) NOT NULL PRIMARY KEY

) COLLATE='utf8_czech_ci';

DROP VIEW IF EXISTS infos_last;
CREATE VIEW infos_last AS SELECT c1.* FROM infos c1 LEFT JOIN infos c2 
  ON c1.id = c2.id AND c1.created < c2.created WHERE c2.id IS NULL GROUP BY c1.id;

CREATE INDEX infos_id ON infos (id);
CREATE INDEX infos_created ON infos (created);
CREATE INDEX infos_group ON infos (id, created, hash);

