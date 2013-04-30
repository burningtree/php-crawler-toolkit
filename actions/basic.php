<?php

class Action_Basic {

  public function run($tesco, $config)
  {
  }

  public function run_collection($tesco, $data, $table, $type, $config)
  {
    $output_file = $config->opts['o'];
    $stdout = FALSE;
    if(empty($output_file))
    {
      //$output_file = strtolower(get_class($tesco).'-'.$type.'-'.date('Y-m-d-h-i').'.json');
      $stdout = TRUE;
      $ext = 'json';
    }
    else
    {
      $ext = pathinfo($output_file, PATHINFO_EXTENSION);
    }
    if($ext == 'msgpack')
    {
      $format = $ext;
      $content = msgpack_pack($data);
    }
    else
    {
      $format = 'json';
      $json_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
      $content = json_encode($data, $stdout ? $json_flags | JSON_PRETTY_PRINT : $json_flags);
    }

    if($output_file)
    {
      $tesco->debug(sprintf("Saving to: %s [format:%s]", $output_file, $format));
      file_put_contents($output_file, $content);
    }
    elseif($stdout)
    {
      $tesco->debug(sprintf("Printing format: %s ..", $format));
      print $content;
    }
  }

}
