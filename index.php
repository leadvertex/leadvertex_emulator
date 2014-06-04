<?php
include_once('engine/LvBaseRenderer.php');
include_once('engine/EmulatorRenderer.php');

if (!isset($_COOKIE['lv_lastcheck'])) {
  $actualCode = file_get_contents('https://raw.github.com/XAKEPEHOK/leadvertex_emulator/master/Renderer.php');
  if (preg_match('~const VERSION = (\d+\.\d+);~u',$actualCode,$matches)) {
    /*if ($matches[1] > Renderer::VERSION) {
      echo 'Вышла новая версия шаблонизатора. Просьба использовать именно её. О новых возможностях шаблонизатора читайте в README.md <br>';
      die('<a href="https://github.com/XAKEPEHOK/leadvertex_emulator/">https://github.com/XAKEPEHOK/leadvertex_emulator/</a>');
    }*/
  } else setcookie('lv_lastcheck',1,time()+60*60*24,null,null,null,true);
}

$page = @empty($_GET['page']) ? 'index' : $_GET['page'];
if (isset($_COOKIE['lv_landing']) && !empty($_COOKIE['lv_landing'])) $landing=$_COOKIE['lv_landing'];
else $landing = 'demo';

$basePath = __DIR__.'/templates/'.$landing;

$renderer = new EmulatorRenderer($basePath);
$renderer->render($page,[]);