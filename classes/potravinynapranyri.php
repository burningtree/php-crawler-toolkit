<?php

require_once './lib/toolkit.php';

class PotravinyNaPranyri extends ScraperToolkit
{
  public $base_url = 'http://www.potravinynapranyri.cz/';
  public $pages = array(
    'list' => array(
      'url' => 'Search.aspx?lang=cs&archive=actual&listtype=tiles&page=%s',
    ),
    'list_archive' => array(
      'url' => 'Search.aspx?lang=cs&archive=archive&listtype=tiles&page=%s',
      'fn_parse' => 'parse_page_list',
    ),
    'detail' => array(
      'url' => 'Detail.aspx?id=%s&lang=cs&archive=actual&listtype=tiles',
    )
  );
  public $collections = array(
    'list' => array(
      'page' => 'list',
      'fn_index' => 'index_paginated',
      'fn_walk' => 'walk_paginated',
    ),
    'archive' => array(
      'page' => 'list_archive',
      'fn_index' => 'index_paginated',
      'fn_walk' => 'walk_paginated',
    ),
  );

  public function parse_page_list($pg, $args)
  {
    $last_page_url = preg_match("/page=(\d+)$/",$pg->find('div#MainContent_pnlDocListPaging a.last', 0)->href, $pages_match);
    $pages = $pages_match[1];

    $items = array();

    foreach($pg->find('div#MainContent_pnlDocList div.item') as $item)
    {
      $type = preg_match('/icon\-([^\.]+)\.png$/smuU', $item->find('div.title div img', 0)->src, $type_match);
      $country_flag = preg_match('/\/([^\.\/]+)\.png/smuU', $item->find('div.supervisorAuthority img', 0)->src, $country_flag_match);
      $sbranch = preg_match('/sbranch\=(\d+)/', $item->find('div.branchAndGroupName a', 0)->href, $sbranch_match);
      $sgroup = preg_match('/sgroup\=(\d+)/', $item->find('div.branchAndGroupName a', 1)->href, $sgroup_match);
      $img = $item->find('div.galeryitems img', 0)->src;

      $items[] = array(
        'title' => $this->sanity($item->find('div.title div', 1)->innertext),
        'author' => $this->sanity($item->find('div.title div', 0)->innertext),
        'date' => date('Y-m-d',strtotime($this->sanity($item->find('div.title span', 0)->innertext))),
        'type' => $type ? $this->sanity($type_match[1]) : NULL,
        'img' => $img ? $this->sanity($this->base_url.$img) : NULL,
        'url' => $this->sanity($this->base_url.$item->find('div.title div[2] a', 0)->href),
        'country' => $this->sanity($item->find('div.supervisorAuthority a', 0)->innertext),
        'country_code' => $country_flag ? $country_flag_match[1] : NULL,
        'sbranch' => $this->sanity($item->find('div.branchAndGroupName a', 0)->innertext),
        'sbranch_id' => $sbranch ? $sbranch_match[1] : NULL,
        'sgroup' => $this->sanity($item->find('div.branchAndGroupName a', 1)->innertext),
        'sgroup_id' => $sgroup ? $sgroup_match[1] : NULL,
      );
    }

    return array('items' => $items, 'pages' => $pages, 'total' => ($pages*20));
  }
}
