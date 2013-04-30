<?php

require_once './lib/toolkit.php';

class Tesco extends ScraperToolkit
{
  public $base_url = 'http://nakup.itesco.cz/cs-CZ/';
  // nutny FAKE user-agent !!
  public $curl_useragent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.56 Safari/537.36';
  public $pages = array(
    'basecat' => array(
      'url' => 'Department/List?navId=P1_%s',
    ),
    'list' => array(
      'url' => 'Product/BrowseProducts?taxonomyId=%s&viewType=List&pageNo=%s&SortBy=Default',
    ),
    'promo_list' => array(
      'url' => 'Promotion/List?taxonomyId=%s&viewType=List&pageNo=%s&SortBy=Default',
      'fn_parse' => 'parse_page_list',
    ),
    'product' => array(
      'url' => 'ProductDetail/ProductDetail/%s',
    )
  );
  public $collections = array(
    'cats' => array(
      'page' => 'basecat',
    ),
    'list' => array(
      'page' => 'list',
      'fn_index' => 'index_list',
      'fn_walk' => 'walk_paginated',
    ),
    'promo_list' => array(
      'page' => 'promo_list',
      'fn_index' => 'index_list',
      'fn_walk' => 'walk_paginated',
    ),
    'products' => array(
      'page' => 'product',
      '_base' => 'list',
    ),
    'promo_products' => array(
      'page' => 'product',
      'fn_index' => 'index_products',
      '_base' => 'promo_list',
    ),
  );


  private function parse_product_name($name)
  {
    if(!preg_match("/^(.+)(((\d+)\s?x|)(\s?[\d\.\,]+\s?(g|kg|l|ml|cl|ks|L))|)$/smuU", $name, $match))
    {
      throw new Exception(sprintf('Nemuzu vyparsovat nazev produktu: %', $name));
    }
    $output = array(
      'full' => $name,
      'name' => $match[1], 
      'weight' => (!empty($match[5]) ? $match[5] : NULL), 
      'pcs' => (!empty($match[4]) ? $match[4] : "1"), 
    );
    // sanity all values
    return array_map(array(get_class($this), 'sanity'), $output);
  }

  public function index_cats()
  {
    $html = $this->get_page_url($this->base_url);
    preg_match_all("/Department\/List\?navId=P1_([\w\d_]+)/", $html, $match);
    if(!$match)
    {
      throw new Exception('Nemuzu najit odkazy na zakladni kategorie');
    }
    return $match[1];
  }

  public function index_products($args)
  {
    $output = array();
    $list = $this->get_collection($args['config']['_base']);
    foreach($list['items'] as $item)
    {
      $output[] = $item['id'];
    }
    return $output;
  }

  public function index_list()
  {
    $i = 0;
    $cats = $this->get_collection('cats');
    $output = array('cats' => $cats['items'], 'items' => array());

    foreach($output['cats'] as $cat)
    {
      if(isset($cat['final']) && $cat['final'] == TRUE)
      {
        $output['items'][] = $cat['id'];
      }
      $i++;
    }
    return $output['items'];
  }

  public function parse_page_list($pg, $args)
  {
    $perpg = 24;
    $cat_id = $args['item'];
    $items = array();
    $items_source = $pg->find('div#listedProductItems',0);

    if($items_source)
    {
      $imgs = $this->find_imgs($pg);

      foreach($items_source->find('div.product') as $product)
      {
        $anchor = $product->find('div.description a', 0);
        if(!preg_match("/ProductDetail\/(\d+)/", $anchor->href, $id_match))
        {
          throw new Exception(sprintf('Nemuzu vyparsovat id produktu z url: %s', $anchor->href));
        }

        if($product->find('p.addToBasketNotForSale'))
        {
        }

        $name = $this->parse_product_name($this->sanity($anchor->innertext));

        $price_kg = $this->sanity($product->find('span.linePriceAbbr', 0)->innertext);
        if(preg_match("/^\(([\d\s,]+) Kč\/(Kus|l|kg|m|Karton)\)$/smuU", $price_kg, $price_kg_match))
        {
        }
        $price = $product->find('span.linePrice', 0);

        // promo informace jen pokud jsou dostupne
        $promo = $this->sanity($product->find('p.promoMsg',0)->innertext);
        if(preg_match("/^\-(\d+)% běžná cena ([\d\s,]+) nyní ([\d\s,]+)$/smu", $promo, $promo_match))
        {
        }

        $promo_until = $this->sanity($product->find('p.promoUntil', 0)->innertext);
        if($promo_match && !preg_match("/^Cena je platná při dodání do (\d{2}\.\d{2}\.\d{4})$/", $promo_until, $promo_until_match))
        {
          throw new Exception(sprintf('Mame slevu ale nemame platnost: %s', $promo_until));
        }

        $items[] = array(
          'id' => $id_match[1],
          'url' => $this->get_url('product', $id_match[1]),
          'name_full' => $name['full'],
          'name' => $name['name'],
          'ean' => $imgs[$id_match[1]]['ean'],
          'pcs' => $name['pcs'],
          'weight' => !empty($name['weight']) ? $name['weight'] : NULL,
          'price' => $price ? $this->sanity_price($price->innertext) : NULL,
          'price_original' => $promo ? $this->sanity_price($promo_match[2]) : NULL,
          'price_unit' => $price_kg_match ? $this->sanity_price($price_kg_match[1]) : NULL,
          'price_unit_type' => $price_kg_match[2],
          'promo_sale' => $promo ? $promo_match[1] : FALSE,
          'promo_until' => $promo_until ? date('c', strtotime('+23 hours 59 minutes 59 seconds', strtotime($promo_until_match[1]))) : NULL,
          'img_small' => $imgs[$id_match[1]]['small'],
          'img_big' => $imgs[$id_match[1]]['big'],
          'cat' => $cat_id,
        );
      }
    }

    $total = count($items);
    if($total > 0)
    {
      $total_source = $pg->find('div.paginationContainer span.itemCount',0)->innertext;
      if($total_source)
      {
        if(preg_match("/\d+&nbsp;-&nbsp;(\d+)<\/span> z <span>(\d+)<\/span>/", $total_source, $total_match))
        {
          $total = $total_match[2];
        }
      } 
      else 
      {
        throw new Exception('Nemuzu najit pocet items');
      }
    }
    return array('items' => $items, 'pages' => ceil($total/$perpg), 'total' => $total);
  }

  public function parse_page_basecat($pg, $args)
  {
    $cat = $args['item'];

    $cats = array();
    $cats[] = array(
      'id' => $cat,
      'name' => $this->sanity($pg->find('h1', 0)->innertext),
      'url' => $this->get_url('basecat', $cat)
    );          
    foreach($pg->find('div.tertiary') as $pane)
    {
      foreach($pane->find('h3,ul') as $row)
      { 
        if($row->tag == 'h3')
        { 
          $_mastercat = $this->sanity($row->innertext);
          $cats[] = array(
            'id' => md5($_mastercat),
            'name' => $_mastercat,
            'parent' => $cat,
          );          
        }
        else if($row->tag == 'ul')
        {
          foreach($row->find('li a') as $a)
          {
            if(!preg_match('/taxonomyId=([\w\d]+)/', $a->href, $id_match))
            {
              throw new Exception(sprintf('Nemuzu najit ID kategorie z url: %s', $a->href));
            }
            $cats[] = array(
              'id' => $id_match[1],
              'name' => $this->sanity($a->innertext),
              'parent' => md5($_mastercat),
              'final' => TRUE
            );
          }
        }
      }
    }
    return $cats;
  }

  private function find_imgs($pg)
  {
    $imgs = array();
    foreach($pg->find('script') as $script)
    {
      if(preg_match("/ProductList\.create\((\[[^\]]+\])\);/smuU",$script->innertext, $imgs_match))
      {
        $json = json_decode($imgs_match[1], TRUE);
        if(!$json)
        {
          throw new Exception(sprintf('Nemuzu dekodovat JSON s imgdaty: %s, json error: %s', $imgs_match[1], json_last_error()));
        }
        if(is_array($json))
        {
          $imgs = array_merge($imgs, $json);
        }
      }
    }
    $output = array();
    foreach($imgs as $img)
    {
      $sum = $img['Summary'];
      $img_path = str_replace("\\","/",$sum['DefaultImageUrl']);
      if(preg_match("/\/default\//",$img_path))
      {
        continue;
      }

      $ean = preg_match("/assets\/CZ\/\d+\/(\d{13}|\d{6})\//smuU", $img_path, $ean_match);

      $oi = array(
        'small' => sprintf('http://nakup.itesco.cz%s', $img_path),
        'ean' => $ean ? $ean_match[1] : NULL
      );
      $oi['big'] = str_replace("135x135", "328x328", $oi['small']);
      $output[$sum['Id']] = $oi;
    }
    return $output;
  }

  public function parse_page_product($pg, $args)
  {
    $attrs = $pg->find('div.extendedAttributes', 0);
    if(!$attrs) return array();

    $map = array(
      'Popis produktu' => 'description',
      'Složení' => 'composition',
      'Skladování a použití' => 'storage',
      'Balení' => 'packaging',
      'Zdraví a životní styl' => 'lifestyle',
      'Alkohol' => 'alcohol',
      'Energetické štítky' => 'energylabels',
    );

    $output = array(
      'id' => $args['item'],
    );

    foreach($attrs->find('div.itemTypeGroupContainer') as $div)
    {
      $title = $this->sanity($div->find('h3', 0)->innertext);
      if(!isset($map[$title]))
      {
        throw new Exception(sprintf('Neexistujici popis produktu: %s', $title));
      }
      $key = $map[$title];
      $output[$key] = $div->find('div.itemTypeGroup',0)->innertext;
    }
  
    if(count($output) == 1) return array();

    return array($output);
  }
}

