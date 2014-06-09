<?php


include_once('engine/LvBaseRenderer.php');
include_once('engine/EmulatorRenderer.php');

if (!isset($_COOKIE['lv_lastcheck'])) {
  $checked = false;
  try {
    $actualCode = @file_get_contents('https://raw.github.com/XAKEPEHOK/leadvertex_emulator/master/engine/EmulatorRenderer.php');
    if (preg_match('~const VERSION = (\d+\.\d+);~u',$actualCode,$matches)) {
      if ($matches[1] > EmulatorRenderer::VERSION) {
        echo 'Вышла новая версия шаблонизатора. Просьба использовать именно её. О новых возможностях шаблонизатора читайте в README.md <br>';
        die('<a href="https://github.com/XAKEPEHOK/leadvertex_emulator/">https://github.com/XAKEPEHOK/leadvertex_emulator/</a>');
      } else $checked = true;
    } else $checked = true;
  } catch (Exception $e) {
    $checked = true;
  }
  if ($checked) setcookie('lv_lastcheck',1,time()+60*60*24,null,null,null,true);
}

$page = @empty($_GET['page']) ? 'index' : $_GET['page'];
if (isset($_COOKIE['lv_landing']) && !empty($_COOKIE['lv_landing'])) $landing=$_COOKIE['lv_landing'];
else $landing = 'demo';

$basePath = __DIR__.'/templates/'.$landing;

if (isset($_GET['tar']) && $_GET['tar']==1 && is_dir($basePath)) {
  $filename = $basePath.'.tar';
  $phar = new PharData($filename);
  $phar->buildFromDirectory($basePath);
  header('Content-Description: File Transfer');
  header('Content-Type: application/octet-stream');
  header('Content-Length: ' . filesize($filename));
  header('Content-Disposition: attachment; filename=' . basename($filename));
  readfile($filename);
}

$renderer = new EmulatorRenderer($basePath);
$renderer->render($page,[]);