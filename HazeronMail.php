<?php

require_once dirname(__FILE__).'/HazeronTools.php';

define('_HM_readBuffer', 0x4000);
define('_HM_system', iconv('UTF-8', _HM_charset, ' System'));
define('_HM_timeStampLength', strlen(iconv('UTF-8', _HM_charset, 'UTC:12345678')));

class ReadingException extends Exception {};

class HazeronMail {
// Class properties map
  protected $_map = array(
    'getId' => 'msgid',
    'getGalaxyId' => 'galaxyId',
   );

// Converted ids cache
  protected $_idmap = array();

// File access
  protected $name;   // File name
  protected $file;   // File handler
  protected $_buffer = ''; // Read buffer of _HM_readBuffer bytes long
  protected $_offset = 0;  // Parsing offset

// Known signatures
  static protected $universeSigs = array(
  // Universe 3 format
    'U3' => "!\0",
  // Universe 5 format (with possible galaxy id)
  // May also match U4, although unconfirmed
    'U5' => "!\x10"
  );

// Header structure
  protected $sign;   // ref: $universeSigs
  protected $msgid;  // int32 big endian
  // \0
  protected $QDateTime; // 8 bytes
  protected $from;   // From name - data + 4 bytes length
  protected $snd_id; // Sender ID - 4 bytes
  protected $rcp_id; // Recipient ID - 4 bytes
  protected $flags;  // 1 bytes
  protected $type;   // 1 byte
  protected $subj;   // Subject - data + 4 bytes length
  protected $body;   // Message body - data + 4 bytes length
  // \0
  protected $sys_id; // System ID (if exists) - 4 bytes
  protected $system; // System name - data + 4 bytes length
  protected $pla_id; // Planet ID (if exists) - 4 bytes
  protected $planet; // Planet name - data + 4 bytes length
  protected $galaxyId; // 1 byte Galaxy ID, always 00 for U3 format.
  protected $gps;    // XYZ - 3 * 4 bytes

// Derived/non-header data
  protected $date;   // Creation date from message body
  protected $charset;

  function __construct($name = NULL, $charset = _HM_charset)
  {
    $this->name = $name;
    $this->file = @fopen($name, 'rb');
    if(($this->file === false) && file_exists($name))
      throw new Exception("Unable to open '{$name}' for reading.");

    $this->charset = $charset;
  }

  function __destruct()
  {
    $_res = @fclose($this->file);
    if($_res === false)
    {
      throw new Exception("Unable to close '{$name}' - what a weird world.");
    }
  }

  function read($clear = false)
  {
    // Signature
    $this->sign = $this->_fread(2);
    $_format = array_search($this->sign, self::$universeSigs);
    if($_format === false)
    {
      $ta = unpack('nsig', $this->sign);
      throw new ReadingException(sprintf('Expectation failed. Signature \'%X\' do not match any known pattern.', $ta['sig']));
    }

    // Message-id
    $_ta = unpack('N1msgid', $this->_fread(4));
    $this->msgid = $_ta['msgid'];
    unset($_ta);

    // QDateTime
    $_ta = unpack('N1date/N1time', $this->_fread(8));

    // Datetime timezone specifier
    if($this->_fread(1) != chr(2))
      throw new ReadingException('Expectation failed. \2 is not 2.');

    $_tz = date_default_timezone_get();
    date_default_timezone_set('UTC');
    $this->QDateTime = jdtounix($_ta['date']) + $_ta['time'] / 1000;
    date_default_timezone_set($_tz);
    unset($_ta, $_tz);

    // From name
    $_ta = unpack('N1from_l', $this->_fread(4));
    $this->from = $this->_fread($_ta['from_l']);
    unset($_ta);

    // Sender and recipient Id's
    $_ta = unpack('N1snd_id/N1rcp_id', $this->_fread(8));
    $this->snd_id = $_ta['snd_id'];
    $this->rcp_id = $_ta['rcp_id'];
    unset($_ta);

    // Message type and flags
    $this->flags = ord($this->_fread(1));
    $this->type = ord($this->_fread(1));

    // Subject
    $_ta = unpack('N1subj_l', $this->_fread(4));
    $this->subj = $this->_fread($_ta['subj_l']);
    unset($_ta);

    // Message body
    $_ta = unpack('N1body_l', $this->_fread(4));
    $_body = $this->_fread($_ta['body_l']);
    unset($_ta);

    // Message date, if exists
    if(preg_match(
      '/^UTC\:([0-9a-fA-F]{8})/i',
      iconv($this->charset, 'UTF-8', substr($_body, 0, _HM_timeStampLength)),
      $_ta))
    {
      $this->date = hexdec($_ta[1]);
      $this->body = substr($_body, _HM_timeStampLength);
    }
    else
    {
      $this->body = $_body;
    }
    unset($_body, $_ta);

    // Implied zero
    if($this->_fread(1) != chr(0))
      throw new ReadingException('Expectation failed. EOF \0 is not 0.');

    if($this->flags & 0x01)
    {
      // System Id/name length
      $_ta = unpack('N1sys_id/N1sys_l', $this->_fread(8));
      $this->sys_id = $_ta['sys_id'];

      // System name
      $this->system = $this->_fread($_ta['sys_l']);
      unset($_ta);
    }

    if($this->flags & 0x02)
    {
      // Planet Id/name length
      $_ta = unpack('N1pla_id/N1pla_l', $this->_fread(8));
      $this->pla_id = $_ta['pla_id'];

      // Planet name
      $this->planet = $this->_fread($_ta['pla_l']);
      unset($_ta);
    }

    if($this->flags & 0x04)
    {
      // footer
      if($_format == 'U5')
      {
        $this->galaxyId = ord($this->_fread(1));
      }
      else
      {
        $this->galaxyId = 0;
      }
      $_x = $this->_fread(4);
      $_y = $this->_fread(4);
      $_z = $this->_fread(4);
      $this->gps = unpack('f1x/f1y/f1z', $_x[3].$_x[2].$_x[1].$_x[0] . $_y[3].$_y[2].$_y[1].$_y[0] . $_z[3].$_z[2].$_z[1].$_z[0]);
    }

    if(strlen($this->_buffer) != $this->_offset)
    {
      $_x = '';
      while(strlen($this->_buffer) > $this->_offset)
      {
        $ta = unpack('H*', $this->_fread(1));
        $_x .= $this->_hexdump($ta[1], ' \x%s');
      }
      throw new ReadingException("There's stuff left in the buffer:$_x");
    }
  }

  protected function _fread($len)
  {
    $_buff = '';
    $_len = $len;

    while($_len + $this->_offset > strlen($this->_buffer))
    {
      $_buff .= substr($this->_buffer, $this->_offset);
      $_len = $len - strlen($_buff);
      $this->_buffer = @fread($this->file, _HM_readBuffer);
      $this->_offset = 0;
      if(empty($this->_buffer))
      {
        $this->_buffer = $_buff;
        $_len = strlen($_buff);
        throw new Exception("Unable to read {$len} bytes from file pointer ({$_len} read). Read error or premature EOF encountered.");
      }
    }
    $_buff .= substr($this->_buffer, $this->_offset, $_len);
    $this->_offset += $_len;

    return $_buff;
  }

  function _fclear()
  {
    $_fpos = ftell($this->file);
    if(_HM_readBuffer > $this->_offset)
    {
      fseek($this->file, $_fpos - _HM_readBuffer + $this->_offset);
    }
    $this->_offset = 0;
    $this->_buffer = '';
  }

  protected function _hexdump($data, $format = ' %04X')
  {
    $_data = is_array($data) ? array_values($data) : array($data);
    $_f = '';
    for($i = 0; $i < count($_data); $i++)
    {
      $_f .= $format;
    }
    return vsprintf($_f, $_data);
  }

  function getSenderId($encoding = false)
  {
    if(!is_string($encoding))
      return $this->snd_id;

    return haz_id2str($this->snd_id, $encoding);
  }

  function getRecipientId($encoding = false)
  {
    if(!is_string($encoding))
      return $this->rcp_id;

    return haz_id2str($this->rcp_id, $encoding);
  }

  function getSystemId($encoding = false)
  {
    if(!is_string($encoding))
      return $this->sys_id;

    return haz_id2str($this->sys_id, $encoding);
  }

  function getPlanetId($encoding = false)
  {
    if(!is_string($encoding))
      return $this->pla_id;

    return haz_id2str($this->pla_id, $encoding);
  }

  function getBody($encoding = false)
  {
    if(!is_string($encoding))
      return $this->body;

    return iconv(_HM_charset, $encoding, $this->body);
  }

  function getAsEmail()
  {
    $_prefs = array('scheme' => 'Q',
      'input-charset' => 'UTF-8',
      'output-charset' => 'UTF-8',
      'line-length' => _HM_readBuffer);

    $_buff = sprintf("X-msgid: %u\r\n", $this->msgid);
    $_buff .= 'X-Date: ' . gmdate('r', $this->QDateTime) . CRLF;

    $_from = iconv(_HM_charset, 'UTF-8', $this->from);
    $_buff .= iconv_mime_encode('X-From', $_from, $_prefs) . CRLF;
    $_buff .= 'X-Sender-Id: ' . $this->getSenderId('UTF-8') . CRLF;
    $_buff .= 'X-Recipient-Id: ' . $this->getRecipientId('UTF-8') . CRLF;
    $_buff .= 'X-Type:' . $this->_hexdump($this->type, ' %02X') . CRLF;

    $_subj = iconv(_HM_charset, 'UTF-8', $this->subj);
    $_buff .= iconv_mime_encode('X-Subject', $_subj, $_prefs) . CRLF;

    if($this->system)
    {
      $_sys = iconv(_HM_charset, 'UTF-8', $this->system);
      $_buff .= iconv_mime_encode('X-System', $_sys, $_prefs) . sprintf(' <%s:;>', $this->getSystemId('UTF-8')) . CRLF;

      if(!preg_match('/^System /', $_sys))
      {
        $_sys .= ' System';
      }
      $_sys = ', ' . $_sys;
    }

    if($this->planet)
    {
      $_plan = iconv(_HM_charset, 'UTF-8', $this->planet);
      $_buff .= iconv_mime_encode('X-Planet', $_plan, $_prefs) . sprintf(' <%s:;>', $this->getPlanetId('UTF-8')) . CRLF;
      $_plan = ', ' . $_plan;
    }

    if($this->gps)
    {
      if(!empty($this->galaxyId))
      {
        $_buff .= sprintf('X-GPS: %u, ', $this->galaxyId);
      }
      else
      {
        $_buff .= 'X-GPS: ';
      }
      $_buff .= sprintf("%0.4f, %0.4f, %0.4f\r\n", $this->gps['x'], $this->gps['y'], $this->gps['z']);
    }

    $_buff .= sprintf("Message-Id: %s\$%08X\$%02X\$hazeron.com\r\n",
      $this->getSenderId('UTF-8'), $this->date ?: $this->QDateTime, $this->type);
    $_buff .= iconv_mime_encode('From', $_subj, $_prefs) . sprintf(' <%s:;>', $this->getSenderId('UTF-8')) . CRLF;
    $_buff .= iconv_mime_encode('Subject', $_from . (@$_plan ?: '') . (@$_sys ?: ''), $_prefs) . CRLF;
    if($this->date)
      $_buff .= sprintf("Date: %s\r\n", gmdate('r', $this->date));

    $_buff .= "Content-Type: text/html; charset=UTF-8\r\n";
    $_buff .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $_buff .= chunk_split(base64_encode($this->getBody('UTF-8')));
    return $_buff;
  }

  function __call($name, $args)
  {
    $_n = $this->_map[$name] ?: false;
    if($_n)
      return $this->$_n;
  }
}
