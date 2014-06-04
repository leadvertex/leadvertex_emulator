<?php
class EmulatorRenderer extends LvBaseRenderer {
  protected $scripts = array();
  protected $config = array();
  /** @var \SimpleXMLElement */
  private $_xml;
  private $_discount = [];

  public function __construct($themePath)
  {
    parent::__construct($themePath);
    if (file_exists($this->themePath.'/config.xml')) {
      $this->_xml = simplexml_load_file($this->themePath.'/config.xml');
    } else {
      if (is_dir($this->themePath)) {
        $xml = file_get_contents('assets/config.xml');
        file_put_contents($this->themePath.'/config.xml',$xml);
        $this->_xml = simplexml_load_string($xml);
      }
    }
  }
  private function registerFile($filename,$onTop = false)
  {
    $filename = strtolower($filename);
    $ext = substr(strrchr($filename, '.'), 1);
    if (!isset($this->scripts[$filename]) || 1==1) {
      if ($onTop === true) {
        if ($ext == 'js') $this->html = str_ireplace('<title', '<script type="text/javascript" src="'.$filename.'"></script><title', $this->html);
        else $this->_html = str_ireplace('<title', '<link rel="stylesheet" href="'.$filename.'"/><title', $this->html);
      } else {
        if ($ext == 'js') $this->html = str_ireplace('<title', '<script type="text/javascript" src="'.$filename.'"></script><title', $this->html);
        else $this->html = str_ireplace('<title', '<link rel="stylesheet" href="'.$filename.'"/><title', $this->html);
      }
      $this->scripts[$filename] = $filename;
    }
  }

  protected function registerJQuery()
  {
    $this->registerFile('/assets/jquery-1.9.1.js',true);
  }
  protected function registerScript($id,$script)
  {
    if (!isset($this->scripts['*inline_'.$id])) {
      if (preg_match('~<body[^>]*>~',$this->html,$matches)) $this->html = str_replace($matches[0],$matches[0].'<script>'.$script.'</script>',$this->html);
      $this->scripts['*inline_'.$id] = '*inline_'.$id;
    }
  }
  protected function registerScriptFile($path)
  {
    $this->registerFile($path);
  }
  protected function renderForm($model,$number,$noCss)
  {
    return '';
  }

  protected function getConfigParam($param)
  {
    if (empty($this->config)) {
      $xmlParams = $this->_xml->config->param;
      /** @var $xmlParams[] SimpleXMLElement */
      foreach ($xmlParams as $xmlParam) $this->config[(string)$xmlParam['name']] = (string)$xmlParam['value'];
    }
    return $this->config[$param];
  }
  protected function getFiles()
  {
    if (isset($_COOKIE['lv_landing']) && !empty($_COOKIE['lv_landing'])) $landing=$_COOKIE['lv_landing'];
    else $landing = 'demo';
    return '/templates/'.$landing.'/files';
  }
  protected function getPriceOptions()
  {
    if (empty($this->priceOptions)) {
      foreach ($this->_xml->form->field as $field) {
        $fieldName = (string)$field['name'];
        if ($field['type'] == 'dropdown') {
          $options = explode(',',(string)$field->pattern);
          foreach ($options as $paramKey=>$paramValue)
            if (preg_match('~\{\{(\d+)\}\}~', $paramValue, $matches)) $this->priceOptions[$fieldName][$paramKey] = $matches[1];
            else $this->priceOptions[$fieldName][$paramKey] = 0;
        }
      }
    }
    return $this->priceOptions;
  }
  protected function getDiscountOptions($quantity = null)
  {
    if (empty($this->_discount)) {
      foreach ($this->_xml->discount->discount as $discount) {
        $this->_discount[(int)$discount['quantity']] = [
          'discount' => (int)$discount['discount'],
          'sum' => (int)$discount['sum'],
          'round' => (int)$discount['round'],
        ];
      }
    }
    $empty = ['discount' => 0, 'round' => false, 'sum' => 0];
    $discount = $this->_discount;
    if (empty($discount)) $discount = [$empty];
    if ($quantity === null) return $discount;
    //Если скидка на такое количество задана, то возвращаем её
    if (isset($discount[$quantity])) return $discount[$quantity];
    //Иначе рассчитываем исходя из процентов
    else {
      $keys = array_keys($discount);
      if (empty($keys)) return $empty;
      //Ищем ту скидку, где кол-во >= заданного
      foreach ($keys as $value) if ($quantity>=$value) $index = $value; else break;
      //Если скидка для этого количества не задана точно, то убираем фиксированную сумму
      if (isset($index)) {
        $result = $discount[$index];
        $result['sum'] = 0;
        return $result;
      }
      else return $empty;
    }
  }
  protected function getPrice()
  {
    return (string)$this->_xml->xpath('/emulator/price')[0]->price;
  }
  protected function getOldPrice()
  {
    return (string)$this->_xml->xpath('/emulator/price')[0]->price_old;
  }
  protected function getTotalPrice()
  {
    return $this->getPrice()*$this->getConfigParam('quantity.default');
  }
  protected function getDeliveryPrice($quantity = null)
  {
    return (string)$this->_xml->xpath('/emulator/price')[0]->price_delivery;
  }
  protected function getGeoCity()
  {
    return 'Москва';
  }
  protected function getGeoRegion()
  {
    return 'Московская область';
  }
  protected function getOrderNumber()
  {
    return rand(100,1000);
  }
  protected function getWebmaster()
  {
    return rand(10,100);
  }
  protected function getUpsell()
  {
    return '?upsell_url_not_available_in_emulator=false';
  }
  protected function getDomain()
  {
    return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'http://example/';
  }

  protected function checkDirectory()
  {
    if (is_dir($this->themePath)===false) return false;
    $extensions = array(
      'code' => array('css', 'js', 'htm', 'html', 'txt', 'less', 'xml', 'htc'),
      'image' => array('jpg', 'jpeg', 'png', 'gif', 'svg', 'ico'),
      'other' => array(
        'ttf', 'eot', 'woff',
        'rar', 'zip', '7z', 'exe', 'tar',
        'mp4', 'flv', '3gp', 'swf',
        'doc', 'docx', 'pdf',
      )
    );
    $extensions = array_merge($extensions['code'], $extensions['image'], $extensions['other']);
    $extRegExp = implode('|', $extensions);
    $errors = [];
    $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->themePath), RecursiveIteratorIterator::SELF_FIRST);
    foreach($objects as $name => $object){
      $basename = basename($name);
      if ($basename !='.' && $basename!='..') {
        if (is_dir($name)) {
          if (preg_match('~^[a-z\d\-_][a-z\d\-_\x20\.]*$~i', $basename)==false) $errors[] = 'Неверное имя каталога: '.$name."\n";
        } else {
          $extRegExp = implode('|', $extensions);
          if (preg_match('~^[a-z\d\-_][a-z\d\-_\.\x20@]*\.(' . $extRegExp . ')$~ui', $basename)==false) $errors[] = 'Неверное имя файла: '.$name."\n";
        }
      }
    }
    return $errors;
  }
  protected function renderDebugBar()
  {
    $base = __DIR__.'/../templates';
    $dirFileList = scandir($base);
    unset($dirFileList[0]);
    unset($dirFileList[1]);
    $dirList = [];
    foreach ($dirFileList as $dir) if (is_dir($base.'/'.$dir)) $dirList[] = '<option>'.$dir.'</option>';

    $errors = $this->checkDirectory();
    $errorStr = '';
    if (is_array($errors)) foreach ($errors as $error) $errorStr.='<li>'.$error.'</li>';
    if (!empty($errorStr)) $errorStr = '<ul id="lv_errors">'.$errorStr.'</ul>';

    $html = '
    <div id="lv_debug_bar">
      <div id="lv_toggle" title="Свернуть или развернуть отладочную панель"></div>
      <select id="lv_landing">'.implode('',$dirList).'</select>
    '.$errorStr.'
    </div>
    ';
    $this->registerFile('/assets/jquery-1.9.1.js',true);
    $this->registerFile('/assets/debug.css');
    $this->registerFile('/assets/debug.js');


    $this->html = str_ireplace('</body>',$html.'</body>',$this->html);
  }

  public function renderPartial($html, $data = [])
  {
    $data['__model'] = true;
    $this->html = parent::renderPartial($html, $data);
    $this->renderDebugBar();
    return $this->html;
  }

} 