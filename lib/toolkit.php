<?php

require_once './vendor/simple_html_dom.php';
require_once './lib/html2md.php';

class ScraperToolkit {

  public $base_url = NULL;
  public $caching = TRUE;
  public $cache_dir = './cache';
  public $cache_ttl = 3600;
  public $curl_sleep = 0.5;
  public $curl_useragent = NULL;
  public $debug = FALSE;
  public $object_encoding = 'msgpack';
  public $pages = array();
  public $collections = array();
  public $reparse = FALSE;
  private $requests_count = 0;
  private $curl = NULL;

  public function __construct()
  {
    $this->_curl_init();
  }

  public function get_collection($name)
  {
    if(!isset($this->collections[$name]))
    {
      throw new Exception(sprintf('Neexistujici kolekce: %s', $name));
    }
    $config = $this->collections[$name];

    $args = array(
      'config' => $config,
      'name' => $name
    );

    // get index
    if(isset($config['fn_index']))
    {
      $index_fn = $config['fn_index'];
    }
    else
    {
      $index_fn = sprintf('index_%s', $name);
    }
    $data = $this->{$index_fn}($args);

    if(isset($config['fn_walk']))
    {
      $arr = $this->{$config['fn_walk']}($data, $args);
    }
    else
    {
      $arr = $this->walk($data, $args);
    }
    return $arr;
  }

  protected function walk($data, $args)
  {
    // process index
    $i= 0;
    $total_count = 0;
    $arr = array();
    foreach($data as $item)
    {
      $args['item'] = $item;

      $output = $this->get_page_object($args['config']['page'], array($args['item']), $args);
      $total_count += count($output);

      $this->debug(sprintf("[%d/%d] [walk] page: %s, id: %s, items: %d, HTTP requests: %d, collected items: %d",
        $i+1, count($data), $args['config']['page'], $item, count($output), $this->requests_count, $total_count));

      $arr = array_merge($arr, $output);
      $i++;
    }
    return array('items' => $arr);
  }

  protected function parse_page($pagetype, $values=NULL, $args=array())
  {
    list($value, $value2) = $values;

    if(!isset($this->pages[$pagetype]))
    {
      throw new Exception(sprintf('Neexistujici typ page: %s', $pagetype));
    }

    $config = $this->pages[$pagetype];
    switch($config['object_type'])
    {
      case 'json':
        $pg = $this->get_page($pagetype, $value, $value2);
        $pg = json_decode($pg);
        break;

      case 'html';
      default:
        $pg = $this->get_page_dom($pagetype, $value, $value2);
        break;
    }


    if(isset($config['fn_parse']))
    {
      $parse_page_fn = $config['fn_parse'];
    } 
    else 
    {
      $parse_page_fn = sprintf('parse_page_%s', $pagetype);
    }

    $this->debug(sprintf("Parsing page: %s, values: [%s,%s], fn: %s", $pagetype, $value, $value2, $parse_page_fn));
    return $this->$parse_page_fn($pg, $args);
  }

  protected function get_page_object($type, $values=NULL, $args=array())
  {
    list($value, $value2) = $values;
    $cn = sha1($this->get_url($type, $value, $value2));
    $output = ($this->reparse == FALSE ? $this->_cache_load($cn, 'object') : FALSE);
    if($output === FALSE)
    {
      $output = $this->parse_page($type, array($value, $value2), $args);
      $this->_cache_save($cn, $output, 'object');
    }
    return $output;
  }

  public function index_paginated($args)
  {
    return array(TRUE);
  }

  public function walk_paginated($items, $args)
  {
    $max_perpg = 0;
    $scanned_pages = 0;
    $i=0;
    $output = array('items' => array());
    foreach($items as $item)
    {
      $args['item'] = $item;
      $ret = $this->walk_paginated_item($args, count($output['items']), array($i+1, count($items), $scanned_pages, $max_perpg));
      $scanned_pages += $ret['scanned_pages'];
      $max_perpg = $ret['max_perpg'];
  
      foreach($ret['items'] as $r)
      {
        $output['items'][] = $r;
      }
      $i++;
    }
    return $output;
  }

  public function walk_paginated_item($args, $total_count=0, $offsets=array(1, 1, 0, 0))
  {
    $max_perpg = $offsets[3];
    $output = array();
    $page = 1;
    $maxpages = 1;
    $count = 0;
    $total = NULL;
    $scanned_pages = 0;

    while($page<=$maxpages)
    {
      switch($args['config']['pagination_type'])
      {
        case 'offset':
          $arg = ($page*$max_perpg)-$max_perpg;
          break;

        case 'pages':
        default:
          $arg = $page;
          break;
      }
    
      $scanned_pages++;
      $ret = $this->get_page_object($args['config']['page'], array($args['item'], $arg), $args);
      $items = count($ret['items']);
      if($max_perpg < $items)
      {
        $max_perpg = $items;
      }

      foreach($ret['items'] as $item)
      {
        $output[] = $item;
      }
  
      $start = (($scanned_pages-1)*$max_perpg)+($items > 0 ? 1 : 0);
      $end = (($scanned_pages-1)*$max_perpg)+$items;
      $total_items = ($total_count+count($output));
      $total_scanned_pages = $offsets[2]+$scanned_pages;

      $maxpages = $ret['pages'] == 0 ? 1 : $ret['pages'];
      $this->debug(sprintf("[%d/%d][%d/%d] [walk:paginated] page: %s, id: %s, items: %d [%d-%d], HTTP requests: %d, scanned pages: %d/%d, collected items: %d",
        $offsets[0], $offsets[1], $page, $maxpages, $args['name'], $args['item'], 
        $items, $start, $end, $this->requests_count, $scanned_pages, $total_scanned_pages, $total_items));

      $total = $ret['total'];
      $page++;
    }

    if(count($output) != $total)
    {
      throw new Exception(sprintf('Nesouhlasi pocet items (pagination): %d vs. %d', count($output), $total, $total_count));
    }
    return array('items' => $output, 'scanned_pages' => $scanned_pages, 'max_perpg' => $max_perpg);
  }

  protected function sanity($str)
  {
    return trim(strip_tags(html_entity_decode(str_replace("&#160;"," ",$str))));
  }

  protected function sanity_price($price_str)
  {
    $price_str = $this->sanity($price_str);
    if(!preg_match("/^([\d\s,]+)( Kč|)$/smuU", $price_str, $match))
    {
      throw new Exception(sprintf('Nemuzu zpracovat cenu: %s', $price_str));
    }
    return str_replace(array(',',' '), array('.',''), trim($match[1]));
  }
  
  protected function get_url($type, $value=NULL, $value2=NULL)
  {
    $url = FALSE;
    if(isset($this->pages[$type]))
    {
      $url = $this->base_url.sprintf($this->pages[$type]['url'], $value, $value2);
    }
    return $url;
  }

  protected function get_string_dom($string)
  {
    $html = new simple_html_dom();
    $html->load($string);
    return $html;
  }

  protected function get_page_dom($type, $value=NULL, $value2=NULL)
  {
    $html = new simple_html_dom();
    $html->load($this->get_page($type, $value, $value2));
    return $html;
  }

  protected function get_page($type, $value=NULL, $value2=NULL)
  {
    return $this->get_page_url($this->get_url($type, $value, $value2));
  }

  protected function get_page_url($url)
  {
    $this->debug(sprintf("Get URL source: %s ",$url));
    //var_dump($url, sha1($url));
    if($this->caching)
    {
      $hash = sha1($url);
      if($cache = $this->_cache_load($hash))
      {
        return $cache;
      }
    }
    
    $this->debug(sprintf("HTTP GET: %s", $url));
  
    sleep($this->curl_sleep);
    curl_setopt($this->curl, CURLOPT_URL, $url); 
    $data = curl_exec($this->curl); 

    $this->requests_count += 1;

    if($this->caching)
    {
      $hash = sha1($url);
      $this->_cache_save($hash, $data);
    }

    return $data;
  }

  protected function _cache_fn($hash, $suffix=NULL)
  {
    $base_dir = basename($this->cache_dir).'/';
    $cache_path = sprintf('%s/%s/%s/', $hash[0], $hash[1], $hash[2]);
    $cache_dir = $base_dir.$cache_path;
    if(!file_exists($cache_dir))
    {
      mkdir($cache_dir, 0777, TRUE);
    }
    return $cache_dir.$hash.($suffix ? '.'.$suffix : '');
  }
  private function _cache_resolve_suffix($suffix)
  {
    if($suffix == 'object')
    {
      $enc = $this->object_encoding;
      if($enc == 'msgpack' && !extension_loaded('msgpack'))
      {
        $enc = 'json';
      }
      return $enc;
    } elseif($suffix == NULL)
    {
      return 'plain';
    }
    return $suffix;
  }
  
  protected function _cache_load($hash, $suffix=NULL)
  {
    $suffix = $this->_cache_resolve_suffix($suffix);
    $fn = $this->_cache_fn($hash, $suffix);

    //$this->debug(sprintf("Cache load request: %s [%s]", $fn, $suffix));
    if(file_exists($fn) && filesize($fn) > 0 && filemtime($fn) > time()-$this->cache_ttl)
    {
      $content = file_get_contents($fn);
      if($suffix == 'json')
      {
        $content = json_decode($content, TRUE);
      }
      elseif($suffix == 'msgpack')
      {
        $content = msgpack_unpack($content);
      }
      $this->debug(sprintf("Cache hit: %s [%s]", $fn, $suffix));
      return $content;
    }
    return FALSE;
  }

  protected function _cache_save($hash, $content, $suffix=NULL)
  {
    $suffix = $this->_cache_resolve_suffix($suffix);
    $fn = $this->_cache_fn($hash, $suffix);
    if($suffix == 'json')
    {
      $content = json_encode($content);
    }
    elseif($suffix == 'msgpack')
    {
      $content = msgpack_pack($content);
    }
    file_put_contents($fn, $content);
    $this->debug(sprintf("Cache saved: %s [%s]", $fn, $suffix));
  }

  private function _curl_init()
  {
    $this->curl = curl_init();
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1); 

    if($this->curl_useragent != NULL)
    {
      curl_setopt($this->curl, CURLOPT_USERAGENT, $this->curl_useragent);
    }
  }

  public function pg($preg, $string, $num=1)
  {
    $match = preg_match($preg, $string, $matches);
    return $match ? $matches[$num] : FALSE;
  }

  public function debug($msg, $type=NULL, $wrap=TRUE)
  {
    $type = ($type == NULL ? "debug" : $type);
    if($this->debug)
    {
      return print sprintf("[%s] %s%s", $type, $msg, ($wrap ? "\n" : ""));
    }
  }
}
