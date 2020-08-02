#!/usr/bin/env php
<?php

require_once dirname(__FILE__).'/HazeronMail.php';

if($argc < 2)
{
  $src = 'php://stdin';
}
else
{
  $src = $argv[1];
}

$msg = new HazeronMail($src);
try
{
  $msg->read();
}
catch(Exception $e)
{
  file_put_contents('php://stderr', $e);
}

print($msg->getAsEmail());
