<?php

require_once './lib/toolkit.php';

class Skrz extends ScraperToolkit
{
  public $base_url = 'http://skrz.cz/';
  public $pages = array(
    'deal_list' => array(
      'url' => 'json/deal_list?msg=%s&offset=%s&order_type=actual&template=10&source_handler=deal_list',
      'object_type' => 'json',
    ),
  );
  public $collections = array(
    'deals' => array(
      'page' => 'deal_list',
      'fn_index' => 'index_paginated',
      'fn_walk' => 'walk_paginated',
      'pagination_type' => 'offset',
    ),
  );

  public function parse_page_deal_list($pg, $args)
  {
    $output = array(
      'items' => array(),
      'limit' => $pg->data->limit,
      'total' => $pg->data->totalCount,
      'pages' => ceil($pg->data->totalCount/$pg->data->limit),
    );


    $dom = $this->get_string_dom($pg->html_content);
    foreach($dom->find('div.deal-in') as $item)
    {
      $item = array(
        'title' => $this->sanity($item->find('h3',0)->innertext),
        'price' => $this->sanity($this->pg("/([\d\s,]+ (Kč|€))$/", $item->find('.deal-price',0)->innertext)),
        'price_guarantee' => $item->find('.deal-guarantee-price') ? TRUE : FALSE,
        'discount' => $item->find('.deal-discount',0)->innertext,
        'img' => $item->find('.deal-image img',0)->original,
        'server' => $item->find('.deal-server img',0)->title,
        'server_img' => $item->find('.deal-server img',0)->src,
        'location' => $item->find('.deal-location a',0) ? $item->find('.deal-location a',0)->getAttribute('data-title') : NULL,
        'deal_time' => $item->find('.deal-time',0)->innertext,
        'customers' => $this->pg("/Koupeno ([\d\s]+)&times;$/", $item->find('.deal-customers',0)->title),
        'url' => $item->find('.button-buy', 0)->href,
      );
      $output['items'][] = $item;
    }
    return $output;
  }
}
