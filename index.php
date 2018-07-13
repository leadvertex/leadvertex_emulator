<?php
define('TEMPLATE',false);
include_once('engine/LvBaseRenderer.php');
include_once('engine/EmulatorRenderer.php');
include_once('engine/ArrayHelper.php');

if (!TEMPLATE && !isset($_COOKIE['lv_lastcheck'])) {
  $checked = false;
  try {
    $actualCode = @file_get_contents('https://raw.github.com/XAKEPEHOK/leadvertex_emulator/master/engine/EmulatorRenderer.php');
    if (preg_match('~const VERSION = (\d+\.\d+);~u',$actualCode,$matches)) {
      if (round($matches[1],1) > round(EmulatorRenderer::VERSION,1)) {
        echo '<html><head><title>Вышла новая версия шаблонизатора '.$matches[1].'</title></head><body>';
        echo 'Вышла новая версия шаблонизатора v'.$matches[1].'. Просьба использовать именно её. О новых возможностях шаблонизатора читайте в <a href="https://github.com/XAKEPEHOK/leadvertex_emulator/blob/master/README.md">README.md</a><br>';
        die('<a href="https://github.com/XAKEPEHOK/leadvertex_emulator/">https://github.com/XAKEPEHOK/leadvertex_emulator/</a></body></html>');
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
if (TEMPLATE) $landing = TEMPLATE;
define('LV_LANDING',$landing);

$basePath = __DIR__.'/templates/'.LV_LANDING;

if (!TEMPLATE && isset($_GET['tar']) && $_GET['tar']==1 && is_dir($basePath)) {
  $filename = '/tmp/'. basename($basePath.'.tar');
  $phar = new PharData($filename);
  $phar->buildFromDirectory($basePath);
  header('Content-Description: File Transfer');
  header('Content-Type: application/octet-stream');
  header('Content-Length: ' . filesize($filename));
  header('Content-Disposition: attachment; filename=' . basename($filename));
  readfile($filename);
  unlink($filename);
  exit;
}

$_COOKIE['orderUpdateTime'] = time() + 120 * 60 * 60;

$renderer = new EmulatorRenderer($basePath, $page);

$varsHtml = $renderer->getViewFile('vars');
$data = [];
if (preg_match_all('~(?:\{\{([a-z\d_-]+)="([^\}"]*)"\}\})~ui', $varsHtml, $matches_all, PREG_SET_ORDER) > 0) {
  foreach ($matches_all as $matches) {
    $key = strtolower($matches[1]);
    if (isset($data[$key]) === false || (isset($data[$key]) && is_scalar($data[$key]))) $data[$matches[1]] = $matches[2];
  }
}
//Создаем модели форм исходя их кода на лендинге
if (count($data)) {
  $renderer->upsellTime = ArrayHelper::getValue($data, 'upsell_time', $renderer->upsellTime);
  $renderer->upsellHide = ArrayHelper::getValue($data, 'upsell_hide', $renderer->upsellHide);
}

$renderer->render($page,$data);