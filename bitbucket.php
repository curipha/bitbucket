<?php
error_reporting(-1);  // Display ALL errors

class BitBucket {

  // Preference {{{
  const ENCODING = 'UTF-8'; // Character encoding
  const BUCKET   = './tmp'; // Directory which put data (never put '/' at the end)
  //}}}

  private $msg = array();

  // Constructor {{{
  function __construct() {
    ini_set('default_charset', self::ENCODING);
    mb_internal_encoding(self::ENCODING);

    if (!is_dir(self::BUCKET)) {
      $this->setmessage('Bucket directory is not exists.', 'red');
      exit();
    }
    if (!is_writable(self::BUCKET)) {
      $this->setmessage('Write permission is not set to the bucket directory.', 'red');
      exit();
    }
  }
  //}}}
  // Destructor {{{
  function __destruct() {
    $this->output();
  }
  //}}}

  // Set message {{{
  public function setmessage($txt, $color = 'green') {
    $this->msg[] = array('txt' => $txt, 'color' => $color);
  }
  //}}}

  // Get posted data {{{
  public function getpostdata() {
    if (empty($_POST['t'])) {
      $str = '';
    }
    else {
      $str = str_replace("\r", '', $_POST['t']);  // Kill CR
      $str = trim($str);

      $str .= "\n"; // Add a new line at the end of the string
    }

    return $str;
  }
  //}}}
  // Store to bucket {{{
  public function store($str) {
    $fp = fopen($this->getfilepath(), 'xb', false);

    if ($fp === false) {
      output('Can not open a file with write mode.', 'red');
      exit();
    }
    else {
      flock($fp, LOCK_EX);
      fwrite($fp, $str);
      fflush($fp);
      flock($fp, LOCK_UN);
      fclose($fp);
    }
  }
  //}}}

  // Get escaped string {{{
  private function h($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, self::ENCODING);
  }
  //}}}
  // Get remote address {{{
  private function getremoteaddr() {
    return empty($_SERVER['REMOTE_ADDR']) ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'];
  }
  //}}}
  // Get bucket file path {{{
  private function getfilepath() {
    $try = 0;
    while (true) {
      if ($try > 100) {
        $this->setmessage('It was not possible to resolve the conflict in the file path.', 'red');
        exit();
      }

      $path = sprintf('%s/%010d-%s-%08d.txt', self::BUCKET, time(), $this->getremoteaddr(), mt_rand(0, 99999999));
      if (!file_exists($path)) {
        break;
      }

      $try++;
    }

    return $path;
  }
  //}}}

  // Create message {{{
  private function createmsg() {
    $msg = '';

    foreach ($this->msg as $m) {
      $t = $this->h($m['txt']);
      $c = $this->h($m['color']);

      $msg .= <<<MSG
<font color="${c}"><b>${t}</b></font><br>
MSG;
    }

    return $msg;
  }
  //}}}
  // Create file list {{{
  private function createfilelist() {
    $list = '<ul>';

    // In destructor, the working directory can be different.
    // It is necessary to return to the expected.
    // For more details, see http://www.php.net/manual/en/language.oop5.decon.php#language.oop5.decon.destructor
    chdir(dirname($_SERVER['SCRIPT_FILENAME']));

    $files = scandir(self::BUCKET);

    if (is_array($files)) {
      array_unique($files);

      foreach ($files as $k => $v) {
        if ($v === '.' || $v === '..') {
          unset($files[$k]);
        }
      }
    }

    if (empty($files)) {
      $list .= '<li>Nothing</li>';
    }
    else {
      rsort($files);

      foreach ($files as $f) {
        $f = $this->h(basename($f));
        $list .= "<li>${f}</li>";
      }
    }

    $list .= '</ul>';

    return $list;
  }
  //}}}

  // Output {{{
  private function output() {
    if (count($this->msg) < 1) {
      $this->setmessage('Unknown error occurred!!', 'red');
    }

    $msg  = $this->createmsg();
    $list = $this->createfilelist();

    $encode = $this->h(self::ENCODING);
    $remote = $this->h(gethostbyaddr($this->getremoteaddr()));
    $date   = $this->h(date('Y/m/d (D) H:i:s T'));

    $self   = $this->h($_SERVER['PHP_SELF']);

    header('Cache-Control: private, max-age=0');
    header('Expires: -1');

    echo <<<HTML
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=${encode}">
<meta name="robots" content="noindex">
<title>BitBucket</title>
</head>
<body>
${msg}
<hr>
<form method="post" action="${self}">
<textarea name="t" cols="80" rows="14"></textarea>
<br>
<input type="submit" value="Send to Blackhole">
</form>
<hr>
Recent uploads:
${list}
<hr>
<font color="gray" size="-1">${remote}<br>${date}</font>
</body>
</html>

HTML;
  }
  //}}}

}


$bb = new BitBucket();

$post = $bb->getpostdata();

if (empty($post)) {
  $bb->setmessage('Ready.');
  exit();
}
else {
  $bb->store($post);
  $bb->setmessage('Success!!', 'blue');
}

