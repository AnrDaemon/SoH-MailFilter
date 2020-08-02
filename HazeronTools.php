<?php

define('_HM_charset', 'UTF-16BE');
if(!defined('CRLF'))
  define('CRLF', "\r\n");

function haz_id2str($id, $encoding = _HM_charset)
{
  $_id = '';
  while(true)
  {
    $_id .= chr(0x41 + $id % 26);
    $id = (int) ($id / 26);
    if(empty($id))
      break;
  }

  if(!is_string($encoding))
    $encoding = _HM_charset;

  return iconv('UTF-8', $encoding, $_id);
}

function haz_str2id($id, $encoding = _HM_charset)
{
  if(!is_string($encoding))
    $encoding = _HM_charset;

  $_id = iconv($encoding, 'UTF-8', $id);

  if(preg_match('/[^A-Z]/', $_id))
    throw new Exception('Invalid literals found in Id string.');

  if(!strlen($_id))
    throw new Exception('Id string is empty.');

  $i = 0;
  $b = 1;
  $_idn = 0;
  while($i < strlen($_id))
  {
    $_idn += (ord($_id[$i]) - 0x41) * $b;
    $b *= 26;
    $i++;
  }
  return $_idn;
}
