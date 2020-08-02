#!/usr/bin/php -f
<?php
/**
* $Id: mbox-feeder.php 384 2015-11-23 13:34:55Z anrdaemon $
*/

require_once dirname(__FILE__).'/HazeronMail.php';

$src = '.';
$unlink = false;
$out = 'php://output';

if($argc < 2)
{
  file_put_contents('php://stderr',
    "Assuming default behavior.\n- Read from current directory.\n- No deletion.\n- Output to STDOUT.\n");
}
else
{
  $_ta = $argv;
  $_help = false;
  array_shift($_ta);
  while(count($_ta))
  {
    $opt = array_shift($_ta);
    switch($opt)
    {
      case '-m':
        $unlink = true;
        break;
      case '-s':
        $out = false;
        break;
      case '--help':
      case '--version':
        $_ta = array();
        $_help = true;
      case '-o':
        if(count($_ta))
        {
          $opt = $_ta[0];
          if($opt == '-')
          {
            $out = 'php://output';
            array_shift($_ta);
            break;
          }

          if($opt != '.' && $opt[0] != '-')
          {
            $out = $opt;
            array_shift($_ta);
            break;
          }

          $_ta = array();
          $_help = true;
        }
      case '--':
        $opt = array_shift($_ta);
      default:
        if($opt)
        {
          if(is_dir($opt))
            $src = $opt;
          else
            die(file_put_contents('php://stderr', "Unable to access source directory '$opt'.\n"));
        }

        if($_help)
        {
          $_help = <<<TEXT
Usage:
  mbox-feeder [-m] [-o FILENAME] [--] [DIRECTORY]
  mbox-feeder [-m] [-s] [--] [DIRECTORY]

By default, the DIRECTORY is the current working directory, the output goes
to STDOUT and no deletion is performed.

-o FILENAME
        write all mail into the specified FILENAME instead of STDOUT.

-m      move files. The cache files that have been succesfully read will be
        removed.

-s      sort mail. The mail will be delivered to each RecipientId's personal
        mailbox in the DIRECTORY.
        (unimplemented) If -o is also specified, FILENAME will be used
        instead of DIRECTORY as base name for destination mailboxes.
        The resulting mailbox names will be formed as simple concatenation
        of FILENAME and RecipientId.

TEXT;
          die($_help);
        }
    }
  }
}

$d = dir($src);
$i = 0;
while(false !== ($entry = $d->read()))
{
  if(substr($entry, -2) == '.m')
  {
    $msg = new HazeronMail("{$d->path}/$entry");
    try
    {
      $msg->read();
      $_sender = $msg->getSenderId('UTF-8') . '$hazeron.com';
      $_date = gmdate('D M d H:i:s Y'); // Wed Sep 17 21:00:00 2003
      if(@file_put_contents($out ?: "{$d->path}/" . $msg->getRecipientId('UTF-8') . ".mbox",
        "From $_sender $_date\n" . str_replace("\r", '', $msg->getAsEmail()) . "\n", FILE_APPEND))
      {
        unset($msg);
        if($unlink)
          unlink("{$d->path}/$entry");
      }
      else
      {
        die("Unable to write to the destination stream.\n");
      }
    }
    catch(Exception $e)
    {
      // Don't increase or print counter
      continue;
    }
    if(++$i % 10000)
    {
      if($i % 1000)
      {
        if($i % 100)
        {
        }
        else
          file_put_contents('php://stderr', '.');
      }
      else
        file_put_contents('php://stderr', '*');
    }
    else
      file_put_contents('php://stderr', '!');
  }
}
$d->close();
