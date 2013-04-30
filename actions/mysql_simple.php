<?

class Action_Mysql_Simple {

  function run_collection($tesco, $data, $table, $type, $config)
  {
    $my = mysql_connect($config->mysql->host, $config->mysql->user, 
                          $config->mysql->pass);

    mysql_select_db($config->mysql->db);
    mysql_query('SET NAMES utf8;');

    switch($type)
    {
      case 'list':
      case 'promo_list':
        $table = 'items';
        break;
        
      case 'products':
      case 'promo_products':
        $table = 'infos';
        break;

      default:
        $table = $type;
        break;
    }

    if(mysql_error())
    {
      throw new Exception(mysql_error());
    }

    $tesco->debug("Updating database ..");
    $this->process_table($data, $table, $tesco);
  }

  function process_table($arr, $table_name, $tesco)
  {
    $price_cols = array('price', 'price_unit', 'price_unit_type', 'promo_sale', 'promo_until', 'price_original');

    $i = 0;
    foreach($arr as $cat)
    {
      $array = $cat;

      $price = array();
      if($table_name == "items")
      {
        $price['id'] = $array['id'];
        foreach($price_cols as $pc)
        {
          if($array[$pc] != NULL)
          {
            $price[$pc] = $array[$pc];
          }
          unset($array[$pc]);
        }
      }

      $array['hash'] = sha1(serialize($array));
      $array['created'] = date("c");

      $array_vals = array_map(function($x){ 
        if($x=='NULL' OR $x == NULL)
        {
          return 'NULL';
        }
        return "'".mysql_real_escape_string($x)."'"; 
      }, $array);

      $check_q = "SELECT id, hash FROM ".$table_name
                  ." WHERE id='".mysql_real_escape_string($array['id'])."'"
                  ." ORDER BY created DESC LIMIT 1";

      $check_res = mysql_query($check_q);
      $num_rows = mysql_num_rows($check_res);
      $row = mysql_fetch_array($check_res);

      if($num_rows == 0 || ($num_rows == 1 && $row['hash'] != $array['hash']))
      {
        $tesco->debug(sprintf("Inserting to `%s`: %s ..", $table_name, (!empty($array['name']) ? $array['name'] : $array['id'])));
        $q = "INSERT INTO ".$table_name." (".join(',', array_keys($array)).")"
              ." VALUES (".join(', ', $array_vals).");";

        //var_dump($q);
        $out = mysql_query($q);
        if(mysql_error())
        {
          throw new Exception(mysql_error().": ".$q);
          //print mysql_error()."\n";
        }
      }

      if($table_name == "items" && count($price)>0)
      {
        $this->process_table(array($price), 'prices', $tesco);
      }

      if(count($arr) > 1 && $i%100 == 0)
      {
        $tesco->debug(sprintf(" - %d/%d", $i, count($arr)));
      }
      $i++;
    }
  }
}
