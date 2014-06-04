<?php
class Renderer {
  protected $data;
  protected $buttonText;
  protected $basePath;
  protected $form;
  protected $fields;
  protected $scripts = array();

  private $_html;
  private $_price;
  private $_oldPrice;
  private $_fields;
  private $_options = [];

  const VERSION = 2.6;

  function __construct($data = array(), $fields = array(), $form = array(), $buttonText = 'Оформить заказ')
  {
    $formDefault = array(
      'fio' => array(
        'name' => 'Ф.И.О.',
        'message' => 'Ф.И.О. заполненно неверно',
        'error' => false,
        'required' => false,
        'type' => 'string',
        'pattern' => '',
      ),
      'country' => array(
        'name' => 'Страна',
        'message' => 'Страна указана неверно',
        'error' => false,
        'required' => false,
        'type' => 'dropdown',
        'pattern' => 'Россия, Украина, Казахстан',
      ),
      'postIndex' => array(
        'name' => 'Индекс',
        'message' => 'Почтовый индекс неверн',
        'error' => false,
        'required' => false,
        'type' => 'string',
        'pattern' => '',
      ),
      'region' => array(
        'name' => 'Край/регион/область',
        'message' => 'Край/регион/область заполненно неверно',
        'error' => false,
        'required' => false,
        'type' => 'string',
        'pattern' => '',
      ),
      'city' => array(
        'name' => 'Город',
        'message' => 'Город заполненно неверно',
        'error' => false,
        'required' => false,
        'type' => 'string',
        'pattern' => '',
      ),
      'address' => array(
        'name' => 'Адрес',
        'message' => 'Адрес указан неверно',
        'error' => false,
        'required' => false,
        'type' => 'text',
        'pattern' => '',
      ),
      'phone' => array(
        'name' => 'Телефон.',
        'message' => 'Телефон указан неверно',
        'error' => false,
        'required' => false,
        'type' => 'string',
        'pattern' => '',
      ),
      'email' => array(
        'name' => 'Email',
        'message' => 'Email заполненно неверно',
        'error' => false,
        'required' => false,
        'type' => 'string',
        'pattern' => '',
      ),
      'quantity' => array(
        'name' => 'Количество',
        'message' => 'Количество указано неверно',
        'error' => false,
        'required' => false,
        //Меняь 'type' и 'pattern' ЗАПРЕЩЕНО!!!
        'type' => 'dropdown',
        'pattern' => '1, 2, 3, 4, 5, 6, 7, 8, 9, 10',
      ),
      'comment' => array(
        'name' => 'Комментарий',
        'message' => 'Комментарий заполненн неверно',
        'error' => false,
        'required' => false,
        'type' => 'text',
        'pattern' => '',
      ),
      'checkboxPersonalData' => array(
        'name' => 'Согласен на обработку персональных данных',
        'message' => 'Для оформления заказа вы должны дать согласие на обработку своих персональных данных',
        'error' => false,
        'required' => false,
        'type' => 'checkbox',
        'pattern' => '',
      ),
      'checkboxAgreeTerms' => array(
        'name' => 'Ф.И.О.',
        'message' => 'С условиями покупки согласен',
        'error' => false,
        'required' => false,
        'type' => 'checkbox',
        'pattern' => '',
      ),
      'additional1' => array(
        'name' => 'Доп.поле #1',
        'message' => 'Доп.поле #1 заполненно неверно',
        'error' => false,
        'required' => false,
        'type' => 'string',
        'pattern' => '',
      ),
      'additional2' => array(
        'name' => 'Доп.поле #2',
        'message' => 'Доп.поле #2 заполненно неверно',
        'error' => false,
        'required' => false,
        'type' => 'string',
        'pattern' => '',
      ),
      'additional3' => array(
        'name' => 'Доп.поле #3',
        'message' => 'Доп.поле #3 заполненно неверно',
        'error' => false,
        'required' => false,
        'type' => 'string',
        'pattern' => '',
      ),
      'additional4' => array(
        'name' => 'Доп.поле #4',
        'message' => 'Доп.поле #4 заполненно неверно',
        'error' => false,
        'required' => false,
        'type' => 'string',
        'pattern' => '',
      ),
    );
    $this->form = array_replace_recursive($formDefault,$form);

    $data_default = array(
      'title' => 'Мой интернет-магазин',
      'old_price' => 1990,
      'price' => 990,
      'delivery_price' => 100,
      'delivery_for_each' => true,
      'quantity' => 1,
      // Количество товара => array('discount' => скидка_в_процентах, 'round'=>bool_округлять)
      'discount' => array(
        2 => array(
          'discount' => 20,
          'round' => true,
        ),
        3 => array(
          'discount' => 30,
          'round' => true,
        ),
      ),
      'meta_keywords' => 'ключевоеСлово1, ключевоеСлово2, ключевоеСлово3, ключевоеСлово4',
      'meta_description' => 'Мета-описание, задаваемое в конфигурации',
    );
    $data_system = array(
      'domain' => $_SERVER['HTTP_HOST'],
      'today' => date('j') . ' ' . $this->getMonthName(time()),
      'year' => date('Y'),
    );
    $this->data = array_merge($data_default, $data, $data_system);
    $this->_oldPrice = $this->data['old_price'];
    $this->_price = $this->data['price'];
    $this->fields = empty($fields) ? array_keys($this->form) : $fields;

    $this->buttonText = $buttonText;
  }

  public function formRender($number,$no_css = true)
  {
    $form = $this->form;
    $fields = $this->fields;

    $html = '<form id="lv-form'.$number.'" class="lv-order-form'.($no_css ? '' : ' lv-order-form-css').'" data-form-number="'.$number.'" action="/success.html" method="post">';

    foreach ($fields as $field) {
      $name = $form[$field]['name'];
      $message = $form[$field]['message'];
      $error = $form[$field]['error'] ? '' : 'display:none;';
      $errorClass = $form[$field]['error'] ? ' lv-row-error ' : '';
      $required = $form[$field]['required'];
      $type = $form[$field]['type'];
      $pattern = $form[$field]['pattern'];

      $html.='<div class="lv-row lv-row-'.$field.' '.($type == 'checkbox' ? 'lv-row-checkbox' : 'lv-row-input').$errorClass.'" data-name="'.$field.'" data-required="'.(int)$required.'">';
      if ($type == 'checkbox') {
        $html.='<div class="lv-label">';
        $html.='<input name="Order['.$field.']" id="lv-form'.$number.'-'.$field.'" value="1" type="checkbox" class="lv-input-'.$field.'" data-required="'.(int)$required.'">';
        $html.='<label for="lv-form'.$number.'-'.$field.'">С условиями покупки согласен</label>';
        $html.='</div>';
      }
      else {
        $html.='<div class="lv-label"><label for="form'.$number.'_'.$field.'">'.$name.($required ? ' <span class="required">*</span>' : '').'</label></div>';
        $html.='<div class="lv-field">';

        if ($type == 'dropdown') {
          $html.='<select data-label="'.$name.'" name="Order['.$field.']" id="lv-form'.$number.'-'.$field.'" class="lv-input-'.$field.'" data-required="'.(int)$required.'">';
          $items = explode(',',$pattern);
          foreach ($items as $item) {
            $item = trim($item);
            if ($field == 'quantity') $item = $item . $form[$field]['unit'];
            $matches = [];
            $sum = 0;
            if (preg_match('~\{\{(\d+)\}\}~', $item, $matches)) {
              $value = str_replace('{{' . $matches[1] . '}}', '', $item);
              $item = str_replace('{{' . $matches[1] . '}}', ' (+' . $matches[1] . ' руб.)', $item);
              $item = trim(str_replace('  ', ' ', $item));
              $sum = $matches[1];
            } else $value = $item;
            $html .= '<option value="'.trim($value).'" data-sum="'.$sum.'">' . $item . '</option>';
          }
          $html.='</select>';
        }
        elseif ($type == 'string') $html.='<input data-label="'.$name.'" name="Order['.$field.']" id="lv-form'.$number.'-'.$field.'" class="lv-input-'.$field.'" type="text" maxlength="255" data-required="'.(int)$required.'"/>';
        elseif ($type == 'text') $html.='<textarea data-label="'.$name.'" name="Order['.$field.']" id="lv-form'.$number.'-'.$field.'" class="lv-input-'.$field.'" data-required="'.(int)$required.'"></textarea>';

        $html.='</div>';
      }
      $html.='<div class="lv-error"><div class="lv-error-text" id="lv-form'.$number.'-'.$field.'_em_" style="'.$error.'">'.$message.'</div></div>';
      $html.='</div>';
    }
    $html.='<div class="lv-form-submit"><input class="lv-order-button" type="submit" name="yt0" value="'.$this->buttonText.'"></div>';
    $html.= '</form>';
    return $html;
  }
  private function getMonthName($timestamp)
  {
    $month = (int)date('m', $timestamp);
    $mArray = array(
      1 => 'января',
      2 => 'февраля',
      3 => 'марта',
      4 => 'апреля',
      5 => 'мая',
      6 => 'июня',
      7 => 'июля',
      8 => 'августа',
      9 => 'сентября',
      10 => 'октября',
      11 => 'ноября',
      12 => 'декабря',
    );
    return $mArray[$month];
  }
  private function calcDiscount($quantity = null)
  {
    $discount = $this->data['discount'];
    ksort($discount);
    if (empty($discount)) $discount = array(array('discount' => 0, 'round' => false));
    if ($quantity === null) return $discount;
    if (isset($discount[$quantity])) return $discount[$quantity];
    else {
      $keys = array_keys($discount);
      if (empty($keys)) return array('discount' => 0, 'round' => false);
      foreach ($keys as $value) if ($quantity >= $value) $index = $value; else break;
      if (isset($index)) return $discount[$index];
      else return array('discount' => 0, 'round' => false);
    }
  }

  private function tagJquery()
  {
    if (stripos($this->_html, '{{jquery}}') !== false) {
      $this->_html = str_ireplace('{{jquery}}', '', $this->_html);
      $this->registerFile('/assets/jquery-1.9.1.js',true);
    }
  }
  private function tagPrice($tag,$sumPrice) {
    if (preg_match_all('~(?:\{\{(?:'.$tag.')(?:(\+|\-|\*|/)(\d+)(%)?)?(?: (\w+)="([^"]{1,200})")?(?: for=(\d{1,10}))?\}\})~ui', $this->_html, $prices_all, PREG_SET_ORDER) > 0) {
      foreach ($prices_all as $matches) {
          $price = $sumPrice;
          if (isset($matches[1])) {
            $sum = $matches[2];
            if (isset($matches[3])) $sum = round($price / 100 * $matches[2]);
            switch ($matches[1]) {
              case '-': $price = $price - $sum; break;
              case '+': $price = $price + $sum; break;
              case '*': $price = $price * $sum; break;
              case '/': $price = round($price / $sum); break;
            }
          }

        //Ценовые опции
          if (isset($matches[4]) && isset($matches[5]))
            if (isset($this->_options[$matches[4]]) && isset($this->_options[$matches[4]][$matches[5]]))
              if ($tag == 'price_option') $price = $this->_options[$matches[4]][$matches[5]];
              else $price += $this->_options[$matches[4]][$matches[5]];

          if (isset($matches[6])) {
            $price = $price*$matches[6];
            $discountPercent = $this->calcDiscount($matches[6]);
            $discountPercent = $discountPercent['discount'];
            $price = $price-($price/100*$discountPercent);
          }

          if ($tag == 'total_price|price_total') $price = '<span class="lv-total-price">' . $price . '</span>';
          if ($tag == 'price_multi') $price = '<span class="lv-multi-price">' . $price . '</span>';
          $this->_html = str_ireplace($matches[0], $price, $this->_html);
        }
    }
  }
  private function tagDeliveryPrice()
  {
    if (preg_match_all('~(?:\{\{(?:delivery_price|price_delivery)(?:=(\d+))?\}\})~ui', $this->_html, $matches_all, PREG_SET_ORDER) > 0) {
      foreach ($matches_all as $matches) {
        $isSetQuantity = isset($matches[1]) && !empty($matches[1]);
        $price = $isSetQuantity ? ($this->data['delivery_for_each'] ? $this->data['delivery_price'] * $matches[1] : $this->data['delivery_price']) : $this->data['delivery_price'];
        if ($isSetQuantity) $replace = $price;
        else $replace = '<span class="lv-delivery-price">' . $price . '</span>';
        $this->_html = str_ireplace($matches[0], $replace, $this->_html);
      }
    }
  }
  private function tagDiffPrice()
  {
    $diff = $this->_oldPrice-$this->_price;
    $this->_html = str_ireplace('{{diff_price_sum}}',$diff,$this->_html);
    $this->_html = str_ireplace('{{diff_price_percent}}',round(100-$this->_price/($this->_oldPrice/100)),$this->_html);
  }
  private function tagQuantityDiscount(){
    if (preg_match_all('~(?:\{\{quantity_discount_(sum|percent)(?:=(\d+))?\}\})~ui', $this->_html, $matches_all, PREG_SET_ORDER) > 0) {
        foreach ($matches_all as $matches) {
          $matches[1] = strtolower($matches[1]);
          $isSetQuantity = isset($matches[2]) && !empty($matches[2]);
          $quantity = $isSetQuantity ? (int)$matches[2] : $this->data['quantity'];
          $discount = $this->calcDiscount($quantity);
          $discount = $discount['discount'];
          if ($matches[1] == 'sum') $discount = round($this->_price*$quantity/100*$discount);
          if ($isSetQuantity) $replace = $discount;
          else $replace = '<span class="lv-quantity-discount-'.$matches[1].'">'.$discount.'</span>';
          $this->_html = str_ireplace($matches[0], $replace, $this->_html);
        }
      }
  }
  private function tagFromTo(){
    if (preg_match_all('~(?:\{\{from_to(?:=(\d+))?\}\})~ui', $this->_html, $matches_all, PREG_SET_ORDER) > 0) {
        foreach ($matches_all as $matches) {
          $discountDuration = (isset($matches[1]) && !empty($matches[1])) ? (int)$matches[1] : 7;
          $oldDate = time() - $discountDuration * (60 * 60 * 24);
          $fromMonth = $this->getMonthName($oldDate);
          $toMonth = $this->getMonthName(time());

          if ($fromMonth != $toMonth) $from_to = date('j', $oldDate) . ' ' . $fromMonth . ' по ' . date('j') . ' ' . $toMonth;
          else $from_to = date('j', $oldDate) . ' по ' . date('j') . ' ' . $toMonth;
          $this->_html = str_ireplace($matches[0], $from_to, $this->_html);
        }
      }
  }
  private function tagOnlyTo(){
    if (preg_match_all('~(?:\{\{only_to(?:=(\d+))?\}\})~ui', $this->_html, $matches_all, PREG_SET_ORDER) > 0) {
        foreach ($matches_all as $matches) {
          $discountDuration = (isset($matches[1]) && !empty($matches[1])) ? (int)$matches[1] : 2;
          $toDate = time() + $discountDuration * 86400;
          $toMonth = $this->getMonthName($toDate);
          $to = date('j', $toDate) . ' ' . $toMonth;
          $this->_html = str_ireplace($matches[0], $to, $this->_html);
        }
      }
  }
  private function tagForm()
  {
    $forms = array();
    $object = &$this;
    $this->_html = preg_replace_callback('~\{\{form(?:_?(\d{1}))?(?:\|(no_css))?\}\}~i', function ($matches) use (&$forms, $object) {
      if (isset($matches[1])) {
        $number = $matches[1];
        if ($number < 2) $number = 1;
        if (in_array($number, $forms)) $number = max($forms) + 1;
      } elseif (count($forms) > 0) $number = max($forms) + 1;
      else $number = 1;
      $noCss = isset($matches[2]);
      $forms[] = $number;
      if ($number == 1) $number = '';
      return $object->formRender($number,$noCss);
    }, $this->_html);
    if (!empty($forms)) {
      $this->registerFile('/assets/jquery-1.9.1.js',true);
      $this->registerFile('/assets/placeholders.min.js');
      $this->registerFile('/assets/formHelper.js');
      $this->registerFile('/assets/form.css');
    }
    if (!isset($this->scripts['JS-CDATA'])) {
      $script = '<script type="text/javascript">'."\n";
      $script.= 'if (!window.leadvertex.selling) window.leadvertex.selling = {};'."\n";
      $script.= 'window.leadvertex.selling.discount = '.json_encode($this->calcDiscount()).';'."\n";
      $script.= 'if (!window.leadvertex.selling.delivery) window.leadvertex.selling.delivery = {};'."\n";
      $script.= 'window.leadvertex.selling.delivery.price = '.$this->data['delivery_price'].";\n";
      $script.= 'window.leadvertex.selling.delivery.for_Each = '.$this->data['delivery_for_each'].";\n";
      $script.= 'if (!window.leadvertex.selling.price) window.leadvertex.selling.price = {};'."\n";
      $script.= 'window.leadvertex.selling.price.price = '.$this->data['price'].";\n";
      $script.= 'window.leadvertex.selling.price.old = '.$this->data['old_price'].";\n";
      $script.= '</script>'."\n";
      if (preg_match('~<body[^>]*>~',$this->_html,$matches)) $this->_html = str_replace($matches[0],$matches[0].$script,$this->_html);
      $this->scripts['JS-CDATA'] = 'JS-CDATA';
    }
  }
  private function tagGeo()
  {

    $this->_html = str_ireplace('{{geo_city}}', 'Москва', $this->_html);
    $this->_html = str_ireplace('{{geo_region}}', 'Московская область', $this->_html);
  }
  private function tagOrderNumber()
  {
    $this->_html = str_ireplace('{{order_number}}', rand(500,2000), $this->_html);
  }
  private function tagPhone(){
    $this->_html = str_ireplace('{{phone}}', $this->data['phone'], $this->_html);
  }
  private function tagEmail(){
    if (preg_match_all('~(?:\{\{email(?:="([^"\}]+)")?(?:\s*\|(protected))?\}\})~ui', $this->_html, $matches_all, PREG_SET_ORDER) > 0) {
      foreach ($matches_all as $matches) {
        $email = (isset($matches[1]) && !empty($matches[1])) ? $matches[1] : $this->data['email'];
        if (isset($matches[2]) && strtolower($matches[2]) == 'protected') {
          $email = str_replace('@', '@@', $email);
          $split = str_split($email, rand(4, 5));
          $email = '';
          foreach ($split as $part) $email .= '<!--' . uniqid('@') . '. -->' . $part;
          $email = str_replace('@@', '&#64;', $email);
          $email = str_replace('.', '&#46;', $email);
        }
        $this->_html = str_ireplace($matches[0], $email, $this->_html);
      }
    }
  }
  private function tagFiles(){
    $this->_html = str_ireplace('{{files}}', '/'.$this->data['basePath'].'/files', $this->_html);
  }
  private function tagUserVars(&$data){
    if (preg_match_all('~(?:\{\{([a-z\d_-]+)="([^\}"]*)"\}\})~ui', $this->_html, $matches_all, PREG_SET_ORDER) > 0) {
      foreach ($matches_all as $matches) {
        $data[$matches[1]] = $matches[2];
        $this->_html = str_ireplace($matches[0], '', $this->_html);
      }
    }
  }
  private function tagCountdownJs()
  {
    if (stripos($this->_html, '{{countdown.js}}') !== false) {
      $this->registerFile('/assets/jquery-1.9.1.js',true);
      $this->registerFile('/assets/countdown.js');
      $this->_html = str_ireplace('{{countdown.js}}','',$this->_html);
    }
  }
  private function tagWebmaster()
  {
    $this->_html = str_ireplace('{{webmaster}}', 0, $this->_html);
  }

  public function render($html)
  {
    $this->_html = $html;
    $this->tagJquery();
    $this->tagCountdownJs();
    $this->tagPrice('price',$this->data['price']);
    $this->tagPrice('price_multi',$this->data['price']);
    $this->tagPrice('price_option',$this->data['price']);
    $this->tagPrice('oldPrice|old_price|price_old',$this->data['old_price']);
    $this->tagPrice('total_price|price_total',$this->data['price']*$this->data['quantity']);
    $this->tagDiffPrice();
    $this->tagDeliveryPrice();
    $this->tagQuantityDiscount();
    $this->tagFromTo();
    $this->tagOnlyTo();
    $this->tagGeo();
    $this->tagOrderNumber();
    $this->tagPhone();
    $this->tagEmail();
    $this->tagFiles();
    $this->tagForm();
    $this->tagWebmaster($this->data);
    $this->tagUserVars($this->data);

    foreach ($this->data as $key => $value) if (!in_array(gettype($value),array('object','array'))) $this->_html = str_ireplace('{{' . $key . '}}', $value, $this->_html);
    $this->renderDebugBar();
    echo $this->_html;
  }



  protected function renderDebugBar()
  {
    $base = __DIR__.'/templates';
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
      <div id="lv_toggle"></div>
      <select id="lv_landing">'.implode('',$dirList).'</select>
    '.$errorStr.'
    </div>
    ';
    $this->registerFile('/assets/jquery-1.9.1.js',true);
    $this->registerFile('/assets/debug.css');
    $this->registerFile('/assets/debug.js');


    $this->_html = str_ireplace('</body>',$html.'</body>',$this->_html);
  }
}