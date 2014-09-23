<?php
abstract class LvBaseRenderer {

  protected $html;
  protected $data;
  protected $matches;
  protected $themePath;
  protected $priceOptions = [];

  public function __construct($themePath)
  {
    $this->themePath = $themePath;
  }

  abstract protected function registerJQuery();
  abstract protected function registerScriptFile($path);
  abstract protected function registerScript($id,$script);
  abstract protected function renderForm($model,$number,$noCss);
  abstract protected function renderFormUpdate($model,$noCss);

  abstract protected function getConfigParam($param);
  abstract protected function getFiles();
  abstract protected function getPrice();
  abstract protected function getPriceOptions();
  abstract protected function getDiscountOptions($quantity = null);
  abstract protected function getOldPrice();
  abstract protected function getTotalPrice();
  abstract protected function getDeliveryPrice($quantity = null);
  abstract protected function getGeoCity();
  abstract protected function getGeoRegion();
  abstract protected function getGeoCountry();
  abstract protected function getGeoCountryCode();
  abstract protected function getOrderNumber();
  abstract protected function getWebmaster();
  abstract protected function getUpsell();
  abstract protected function getDomain();
  abstract protected function getUtmArray($label = null);
  protected function getMonthName($timestamp)
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

  protected function tagExists($tag)
  {
    if (is_array($tag)) {
      foreach ($tag as $_tag) if (isset($this->matches[$_tag])) return true;
    } else return isset($this->matches[$tag]);
  }

  protected function tagFiles()
  {
    if ($this->tagExists('files'))
      $this->html = str_replace('{{files}}', $this->getFiles(), $this->html);
  }
  protected function tagJquery()
  {
    if ($this->tagExists('jquery')) {
      $this->html = str_replace('{{jquery}}', '', $this->html);
      $this->registerJQuery();
    }
  }

  protected function tagPrice($tag, $sumPrice)
  {
    if ($this->tagExists(explode('|',$tag)))
      if (preg_match_all('~(?:\{\{(?:' . $tag . ')(?:(\+|\-|\*|/)(\d+)(%)?)?(?: (\w+)="([^"]{1,200})")?(?: for=(\d{1,10}))?\}\})~ui', $this->html, $prices_all, PREG_SET_ORDER) > 0) {
        $data_operation = '+';
        $data_sum = 0;
        $data_percent = 0;
        foreach ($prices_all as $matches) {
          $price = $sumPrice;
          if (isset($matches[1])) { //Арифметическая операция
            $data_operation = $matches[1];
            $sum = $matches[2]; //Сумма
            $data_sum = $sum;
            if (isset($matches[3])) { //Проценты
              $data_percent = 1;
              $sum = round($price / 100 * $matches[2]);
            }
            switch ($matches[1]) {
              case '-':
                $price = $price - $sum;
                break;
              case '+':
                $price = $price + $sum;
                break;
              case '*':
                $price = $price * $sum;
                break;
              case '/':
                $price = round($price / $sum);
                break;
            }
          }

          //Ценовые опции
          if (isset($matches[4]) && isset($matches[5]))
            if (isset($this->priceOptions[$matches[4]]) && isset($this->priceOptions[$matches[4]][$matches[5]]))
              if ($tag == 'price_option') $price = $this->priceOptions[$matches[4]][$matches[5]];
              else $price += $this->priceOptions[$matches[4]][$matches[5]];

          if (isset($matches[6])) {
            $price = $price * $matches[6];
            $discount = $this->getDiscountOptions($matches[6]);
            $price = $discount['sum'] > 0 ? $discount['sum'] : $price - ($price / 100 * $discount['discount']);
          }

          if ($tag == 'total_price|price_total') {
            $price = '<span class="lv-total-price" data-operation="'.$data_operation.'" data-sum="'.$data_sum.'" data-percent="'.$data_percent.'">' . $price . '</span>';
          }
          if ($tag == 'price_multi') $price = '<span class="lv-multi-price">' . $price . '</span>';
          $this->html = str_replace($matches[0], $price, $this->html);
        }
      }
  }
  protected function tagDeliveryPrice()
  {
    if ($this->tagExists(['delivery_price','price_delivery']))
      if (preg_match_all('~(?:\{\{(?:delivery_price|price_delivery)(?:=(\d+))?\}\})~ui', $this->html, $matches_all, PREG_SET_ORDER) > 0) {
        foreach ($matches_all as $matches) {
          $isSetQuantity = isset($matches[1]) && !empty($matches[1]);
          $price = $isSetQuantity ? $this->getDeliveryPrice($matches[1]) : $this->getDeliveryPrice();
          if ($isSetQuantity) $replace = $price;
          else $replace = '<span class="lv-delivery-price">'.$price.'</span>';
          $this->html = str_replace($matches[0], $replace, $this->html);
        }
      }
  }
  protected function tagDiffPrice()
  {
    $diff = $this->getOldPrice()-$this->getPrice();
    if ($this->tagExists('diff_price_sum')) $this->html = str_replace('{{diff_price_sum}}',$diff,$this->html);

    if ($this->tagExists('diff_price_percent')) {
      if ($this->getOldPrice() == 0) $this->html = str_replace('{{diff_price_percent}}',0,$this->html);
      else $this->html = str_replace('{{diff_price_percent}}',round(100-$this->getPrice()/($this->getOldPrice()/100)),$this->html);
    }
  }
  protected function tagCurrency()
  {
    if ($this->tagExists('currency'))
      $this->html = str_replace('{{currency}}',$this->getConfigParam('currency.price'),$this->html);
  }
  protected function tagQuantityDiscount(){
    if ($this->tagExists(['quantity_discount_sum','quantity_discount_percent']))
      if (preg_match_all('~(?:\{\{quantity_discount_(sum|percent)(?:=(\d+))?\}\})~ui', $this->html, $matches_all, PREG_SET_ORDER) > 0) {
        foreach ($matches_all as $matches) {
          $matches[1] = strtolower($matches[1]);
          $isSetQuantity = isset($matches[2]) && !empty($matches[2]);
          $quantity = $isSetQuantity ? (int)$matches[2] : $this->getConfigParam('quantity.default');
          $discountArray = $this->getDiscountOptions($quantity);
          $discount = $discountArray['discount'];
          if ($matches[1] == 'sum') {
            $discount = round($this->getPrice()*$quantity/100*$discount);
            if ($discountArray['sum']>0) $discount = round($this->getPrice()*$quantity-$discountArray['sum']);
          }
          if ($isSetQuantity) $replace = $discount;
          else $replace = '<span class="lv-quantity-discount-'.$matches[1].'">'.$discount.'</span>';
          $this->html = str_replace($matches[0], $replace, $this->html);
        }
      }
  }
  protected function tagFromTo()
  {
    if ($this->tagExists('from_to'))
      if (preg_match_all('~(?:\{\{from_to(?:=(\d+))?\}\})~ui', $this->html, $matches_all, PREG_SET_ORDER) > 0) {
        foreach ($matches_all as $matches) {
          $discountDuration = (isset($matches[1]) && !empty($matches[1])) ? (int)$matches[1] : 7;
          $oldDate = time() - $discountDuration * (60 * 60 * 24);
          $fromMonth = $this->getMonthName($oldDate);
          $toMonth = $this->getMonthName(time());

          if ($fromMonth != $toMonth) $from_to = date('j', $oldDate) . ' ' . $fromMonth . ' по ' . date('j') . ' ' . $toMonth;
          else $from_to = date('j', $oldDate) . ' по ' . date('j') . ' ' . $toMonth;
          $this->html = str_replace($matches[0], $from_to, $this->html);
        }
      }
  }
  protected function tagOnlyTo()
  {
    if ($this->tagExists('only_to'))
      if (preg_match_all('~(?:\{\{only_to(?:=(\d+))?\}\})~ui', $this->html, $matches_all, PREG_SET_ORDER) > 0) {
        foreach ($matches_all as $matches) {
          $discountDuration = (isset($matches[1]) && !empty($matches[1])) ? (int)$matches[1] : 2;
          $toDate = time() + $discountDuration * 86400;
          $toMonth = $this->getMonthName($toDate);
          $to = date('j', $toDate) . ' ' . $toMonth;
          $this->html = str_replace($matches[0], $to, $this->html);
        }
      }
  }
  protected function tagGeo()
  {
    if ($this->tagExists('geo_city')) $this->html = str_replace('{{geo_city}}', $this->getGeoCity(), $this->html);
    if ($this->tagExists('geo_region')) $this->html = str_replace('{{geo_region}}', $this->getGeoRegion(), $this->html);
    if ($this->tagExists('geo_country')) $this->html = str_replace('{{geo_country}}', $this->getGeoCountry(), $this->html);
    if ($this->tagExists('geo_country_code')) $this->html = str_replace('{{geo_country_code}}', $this->getGeoCountryCode(), $this->html);
  }
  protected function tagOrderNumber()
  {
    if ($this->tagExists('order_number'))
      $this->html = str_replace('{{order_number}}', $this->getOrderNumber(), $this->html);
  }
  protected function tagUtm($label)
  {
    $label = 'utm_'.$label;
    if ($this->tagExists($label))
      $this->html = str_replace('{{'.$label.'}}', $this->getUtmArray($label), $this->html);
  }

  //Форма
  private function formTagExists()
  {
    return $this->tagExists(['form','form1','form2','form3','form4','form5','form6','form7','form8','form9','form_1','form_2','form_3','form_4','form_5','form_6','form_7','form_8','form_9']);
  }
  protected function tagForm()
  {
    $forms = [];
    $regexp = '~\{\{form(?:_?(\d{1}))?(?:\|(no_css))?\}\}~i';
    if ($this->formTagExists())
      $this->html = preg_replace_callback($regexp,function ($matches) use (&$forms){
        if (isset($matches[1])) {
          $number = $matches[1];
          if ($number<2) $number = 1;
          if (in_array($number,$forms)) $number = max($forms)+1;
        } elseif (count($forms)>0) $number = max($forms)+1;
        else $number = 1;
        $noCss = isset($matches[2]);
        $forms[] = $number;
        if ($number==1) $number = '';
        return $this->renderForm($this->data['__model'],$number,$noCss);
      },$this->html);
  }
  protected function tagFormUpdate()
  {
    if ($this->formTagExists()) return false;
    $regexp = '~\{\{form_update(?:\|(no_css))?\}\}~i';
    if ($this->tagExists('form_update'))
      $this->registerScriptFile('/js/formHelper.js');
    $this->html = preg_replace_callback($regexp,function ($matches){
      $noCss = isset($matches[1]);
      return $this->renderFormUpdate($this->data['__update'],$noCss);
    },$this->html);
  }

  protected function tagPhone()
  {
    if ($this->tagExists('phone'))
      $this->html = str_replace('{{phone}}', $this->getConfigParam('application.phone'), $this->html);
  }
  protected function tagEmail($email = null)
  {
    if ($this->tagExists('email')) {
      if ($email === null) $email = $this->getConfigParam('application.email');
      if (preg_match_all('~(?:\{\{email(?:="([^"\}]+)")?(?:\s*\|(protected))?\}\})~ui', $this->html, $matches_all, PREG_SET_ORDER) > 0) {
        foreach ($matches_all as $matches) {
          $email = (isset($matches[1]) && !empty($matches[1])) ? $matches[1] : $email;
          if (isset($matches[2]) && strtolower($matches[2]) == 'protected') {
            $email = str_replace('@', '@@', $email);
            $split = str_split($email, rand(4, 5));
            $email = '';
            foreach ($split as $index=>$part) {
              if ($index>0 && strpos($split[$index-1],'@')!==false) $part = str_replace('@', '', $part);
              $email .= '<!--' . uniqid('@') . '. -->' . $part;
            }
            $email = str_replace('@@', '&#64;', $email);
            $email = str_replace('.', '&#46;', $email);
          }
          $this->html = str_replace($matches[0], $email, $this->html);
        }
      }
    }
  }
  protected function tagUserVars()
  {
    if (preg_match_all('~(?:\{\{([a-z\d_-]+)="([^\}"]*)"\}\})~ui', $this->html, $matches_all, PREG_SET_ORDER) > 0) {
      foreach ($matches_all as $matches) {
        $key = strtolower($matches[1]);
        if (isset($this->data[$key]) === false || (isset($this->data[$key]) && is_scalar($this->data[$key]))) $this->data[$matches[1]] = $matches[2];
        $this->html = str_replace($matches[0], '', $this->html);
      }
    }
  }
  protected function tagCountdownJs($script = '/js/countdown.js')
  {
    if ($this->tagExists('countdown.js')) {
      $this->html = str_replace('{{countdown.js}}','',$this->html);
      $this->registerScriptFile($script);
    }
  }
  protected function tagWebmaster()
  {
    if ($this->tagExists('webmaster'))
      $this->html = str_replace('{{webmaster}}', $this->getWebmaster(), $this->html);
  }
  protected function tagUpsell()
  {
    if ($this->tagExists('upsell')) {
      $upsell = $this->getUpsell();
      $this->html = str_replace('{{upsell}}', $upsell, $this->html);
    }
  }

  protected function getViewFile($viewName = 'index')
  {
    $file = $this->themePath.'/'.$viewName.'.html';
    $file = str_replace('//','/',$file);
    $exists = file_exists($file);
    if ($exists === false) return false;
    try {
      return file_get_contents($file);
    } catch (Exception $e) {return false;}
  }
  public function renderPartial($html, $data = array())
  {
    $this->html = $html;

    preg_match_all('~\{\{([^\}\|=\s]{1,200})[^\}]{0,200}\}\}~u',$this->html,$this->matches);
    $this->matches = array_flip($this->matches[1]);

    $this->data = array_merge($data, array(
      'title' => $this->getConfigParam('application.name'),
      'meta_keywords' => $this->getConfigParam('application.meta.keywords'),
      'meta_description' => $this->getConfigParam('application.meta.description'),
      'domain' => $this->getDomain(),
      'today' => date('j') . ' ' . $this->getMonthName(time()),
      'year' => date('Y'),
    ));

    $this->getPriceOptions();
    $this->tagJquery();
    $this->tagCountdownJs();

    $this->tagPrice('price',$this->getPrice());
    $this->tagPrice('price_multi',$this->getPrice());
    $this->tagPrice('price_option',$this->getPrice());
    $this->tagPrice('oldPrice|old_price|price_old',$this->getOldPrice());
    $this->tagPrice('total_price|price_total',$this->getTotalPrice());
    $this->tagDiffPrice();
    $this->tagDeliveryPrice();
    $this->tagCurrency();
    $this->tagQuantityDiscount();
    $this->tagFromTo();
    $this->tagOnlyTo();
    $this->tagGeo();
    $this->tagOrderNumber();
    $this->tagPhone();
    $this->tagEmail();
    $this->tagFiles();
    $this->tagWebmaster();
    $this->tagUpsell();

    $this->tagUtm('source');
    $this->tagUtm('medium');
    $this->tagUtm('term');
    $this->tagUtm('content');
    $this->tagUtm('campaign');

    $this->tagForm();
    $this->tagFormUpdate();
    $this->tagUserVars();

    $this->registerScript('window.leadvertex','if (!window.leadvertex) window.leadvertex = {};');

    foreach ($this->data as $key => $value) if (gettype($value) != 'object') $this->html = str_replace('{{' . $key . '}}', $value, $this->html);
    return $this->html;
  }
  public function render($view = 'index', $data = null)
  {
    $layout = $this->getViewFile('layout');
    if ($layout === false) $layout = $this->getViewFile('index');

    $html = $layout === false ? '{{content}}' : $layout;

    if (stripos($html, '{{content}}') !== false) {
      $viewFile = $this->getViewFile('pages/' . $view);
      if ($viewFile === false) {
        header("HTTP/1.0 404 Not Found");
        $data['code'] = 404;
        $data['message'] = 'Запрашиваемой Вами страницы не существует';
        $page = '<h1 class="errorCode">Ошибка 404</h1><p class="errorMessage">' . $data['message'] . '</p>';
      } else $page = $viewFile;
      if (stripos($page, '{{no_layout}}') === false) $html = str_replace('{{content}}', $page, $html);
      else $html = str_replace('{{no_layout}}', '', $page);
    }
    echo $this->renderPartial($html, $data);
  }
}