<?php

$config = json_decode(file_get_contents('./config/config.json'), TRUE);

if(isset($argv[1]) && isset($argv[2]))
{
  print $config[$argv[1]][$argv[2]]."\n";
}
else if(isset($argv[1]))
{
  print $config[$argv[1]]."\n";
}
