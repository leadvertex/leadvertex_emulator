<?php
abstract class LvBaseRenderer {

  protected $html;
  protected $data;
  protected $matches;
  protected $matchesFormUpdateIf = [];
  protected $matchesFormUpdateEndIf = [];
  protected $matchesWebmasterIf = [];
  protected $matchesWebmasterEndIf = [];
  protected $themePath;
  protected $page;
  protected $priceOptions = [];
  protected $goodTotalPrices = [];

  public $formCodes = [];
  public $forms = [];
  public $upsellTime = 10;
  public $upsellHide = 1;

  public function __construct($themePath, $page)
  {
    $this->themePath = $themePath;
    $this->page = $page;
  }

  abstract protected function registerJQuery();
  abstract protected function registerScriptFile($path);
  abstract protected function registerScript($id,$script);
  abstract protected function renderForm($model,$number,$noCss,$allowSetTotal);
  abstract protected function renderFormUpdate($model,$noCss);
  abstract protected function renderForms();
  abstract protected function renderAttributes($attributes);

  abstract protected function getGoods();
	abstract protected function getGoodsPrices();
  abstract protected function getGoodPrices($price);
  abstract protected function getGoodPrice($good, $quantity);
  abstract protected function getGoodUnity($good);
  abstract protected function getUpdateFormCookie();
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
  abstract protected function getOrderTotal();
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
  protected function tagOrderTotal()
  {
    if ($this->tagExists('order_total'))
      $this->html = str_replace('{{order_total}}', $this->getOrderTotal(), $this->html);
  }
  protected function tagUtm($label)
  {
    $label = 'utm_'.$label;
    if ($this->tagExists($label))
      $this->html = str_replace('{{'.$label.'}}', $this->getUtmArray($label), $this->html);
  }
  protected function tagGoodQuantity()
  {
    if ($this->tagExists('good-quantity')) {
      $regexp = "~\\{\\{good-quantity\\s+" . str_repeat("([a-z\\-\\d]+=(?:'|\")[^'\"]*(?:'|\"))?\\s*",20) . "\\}\\}~ui";
      if (preg_match_all($regexp, $this->html, $matches, PREG_SET_ORDER) > 0) {
        $goods = ArrayHelper::index($this->getGoods(), 'alias');
        foreach($matches as $params) {
          $replace = $params[0];
          unset($params[0]);
          $param = $this->parserParams($params);
          if (!isset($param['alias'])) continue;
          $alias = $param['alias'];
          $selectParams[$alias] = $param;

          if (isset($goods[$alias])) {
            $prices = $this->getGoodPrices($goods[$alias]->price);
            $maxQuantity = max(array_keys($prices));
            $formID = (isset($selectParams[$alias]['form'])) ? 'data-lv-form="' . $selectParams[$alias]['form'] .'"' : '';
            $cssClass = ArrayHelper::getValue($selectParams[$alias],'class','');
            unset($selectParams[$alias]['class']);
            $htmlParams = $selectParams[$alias];
            unset($htmlParams['alias']);
            unset($htmlParams['empty']);
            unset($htmlParams['form']);
            $htmlParams = $this->renderAttributes($htmlParams);
            $select = '<select class="lv-good-quantity ' . $cssClass .  '" data-lv-alias="'. $selectParams[$alias]['alias'] .'" data-lv-prices=\''. json_encode($prices) .'\' data-lv-max-quantity="'. $maxQuantity .'" '. $formID . ' ' . $htmlParams . '>';
            if (isset($selectParams[$alias]['empty'])) $select .= '<option value="0">' . $selectParams[$alias]['empty'] . '</option>';
            for ($j = 1; $j <= 10; $j++) {
              $select .= '<option value="'. $j .'">' . $j . ' ' . $goods[$alias]->unity . '</option>';
            }
            $select .= '</select>';

            $this->html = str_replace($replace, $select, $this->html);
          }
        }
      }
    }
  }
  protected function tagGoodSelect()
  {
    if ($this->tagExists('good-select')) {
      $regexp = "~\\{\\{good-select\\s+" . str_repeat("([a-z\\-\\d]+=(?:'|\")[^'\"]*(?:'|\"))?\\s*",20) . "\\}\\}~ui";
      if (preg_match_all($regexp, $this->html, $matches, PREG_SET_ORDER) > 0) {
        $goods = ArrayHelper::index($this->getGoods(), 'alias');
        foreach($matches as $params) {
          $replace = $params[0];
          unset($params[0]);
          $param = $this->parserParams($params);
          if (!isset($param['alias'])) continue;
          $selectGoods = $param['alias'];
          unset($param['alias']);
          $selectParams[$selectGoods] = $param;
          $prices = [];
          $options = '';
          $sGoods = explode(',', $selectGoods);
          foreach ($sGoods as $alias) {
            $alias = trim($alias);
            if (isset($goods[$alias])) {
              $prices[$alias] = $this->getGoodPrice($goods[$alias], 1);
              $options .= '<option value="'.$alias.'">'.$goods[$alias]->name.'</option>';
            }
          }
          $formID = (isset($selectParams[$selectGoods]['form'])) ? 'data-lv-form="' . $selectParams[$selectGoods]['form'] .'"' : '';
          $cssClass = ArrayHelper::getValue($selectParams[$selectGoods],'class','');
          unset($selectParams[$selectGoods]['class']);
          $htmlParams = $selectParams[$selectGoods];
          unset($htmlParams['alias']);
          unset($htmlParams['empty']);
          unset($htmlParams['form']);
          $htmlParams = $this->renderAttributes($htmlParams);
          $select = '<select class="lv-good-select ' . $cssClass .  '" data-lv-prices=\''. json_encode($prices) .'\' '. $formID . ' ' . $htmlParams . '>';
          if (isset($selectParams[$selectGoods]['empty'])) $select .= '<option value="0">' . $selectParams[$selectGoods]['empty'] . '</option>';
          $select .= $options;
          $select .= '</select>';
          $this->html = str_replace($replace, $select, $this->html);
        }
      }
    }
  }
  protected function tagGoodButton()
  {
    if ($this->tagExists('good-button')) {
      $defaultParams = [
        'class' => '',
        'add' => 'Добавить',
        'remove' => 'Удалить',
        'add-class' => 'add',
        'remove-class' => 'remove',
      ];

      $regexp = '~\{\{good-button\s+' . str_repeat("([a-z\\-\\d]+=(?:'|\")[^'\"]+(?:'|\"))?\\s?",20) . '\}\}~ui';
      if (preg_match_all($regexp, $this->html, $matches, PREG_SET_ORDER) > 0) {
        $goods = ArrayHelper::index($this->getGoods(), 'alias');
        foreach($matches as $params) {
          $replace = $params[0];
          unset($params[0]);
          $param = $this->parserParams($params);
          if (!isset($param['alias'])) continue;
          $alias = $param['alias'];
          $btnParams[$alias] = $param;

          if (isset($goods[$alias])) {
            $count = count($params);
            for ($i = 2; $i < $count; $i++) {
              $param = $this->parserParams($params[$i]);
              if ($param !== null) {
                $btnParams[$alias][$param[0]] = $param[1];
              }
            }

            $btnParams[$alias] = array_merge($defaultParams, $btnParams[$alias]);

            $attributes = [
              'class' => 'lv-good-button ' . $btnParams[$alias]['class'] . ' ' . $btnParams[$alias]['add-class'],
              'data-lv-alias' => $btnParams[$alias]['alias'],
              'data-lv-price' => $this->getGoodPrice($goods[$alias], 1),
              'data-lv-add-remove' => 'add',
              'data-lv-add-text' => $btnParams[$alias]['add'],
              'data-lv-add-class' => $btnParams[$alias]['add-class'],
              'data-lv-remove-text' => $btnParams[$alias]['remove'],
              'data-lv-remove-class' => $btnParams[$alias]['remove-class'],
            ];

            if (isset($btnParams[$alias]['form'])) $attributes['data-lv-form'] = $btnParams[$alias]['form'];
            if (isset($btnParams[$alias]['submit'])) $attributes['data-lv-submit'] = $btnParams[$alias]['submit'];

            $button = '<button '. $this->renderAttributes($attributes) .'>' . $btnParams[$alias]['add'] . '</button>';
            $this->html = str_replace($replace, $button, $this->html);
          }
        }
      }
    }
  }
  protected function tagIfFormUpdate()
  {
    if (count($this->matchesFormUpdateIf) == count($this->matchesFormUpdateEndIf)) {
      $count = count($this->matchesFormUpdateIf);
      if ($count > 0) {
        //если есть formUpdate то
        if ($this->getUpdateFormCookie() !== null) {
          $this->html = str_replace('[[formUpdate:]]', '', $this->html);
          $this->html = str_replace('[[:formUpdate]]', '', $this->html);
        } else {
          for ($i = 0; $i <= $count; $i++) {
            $string = $this->getStringBetweenTags('[[formUpdate:]]', '[[:formUpdate]]', $this->html);
            $this->html = $this->strReplaceOnce('[[formUpdate:]]' . $string . '[[:formUpdate]]', '', $this->html);
          }
        }
      }
    }
  }
  protected function tagIfWebmaster()
  {
    $this->matchesWebmasterIf = array_count_values($this->matchesWebmasterIf);
    $this->matchesWebmasterEndIf = array_count_values($this->matchesWebmasterEndIf);

    $webmasterId = $this->getWebmaster();
    $curWebmaster = 'webmaster=' . $webmasterId . ':';
    foreach ($this->matchesWebmasterIf as $webIf => $count) {
      $webEndIf = ':' . rtrim($webIf, ':');
      $tagBegin = '[[' . $webIf . ']]';
      $tagEnd = '[[' . $webEndIf . ']]';
      if (isset($this->matchesWebmasterEndIf[$webEndIf])) {
        if ($count == $this->matchesWebmasterEndIf[$webEndIf]) {
          for ($i = 0; $i <= $count; $i++) {
            if ($curWebmaster == $webIf) {
              $this->html = str_replace($tagBegin, '', $this->html);
              $this->html = str_replace($tagEnd, '', $this->html);
            } else {
              $string = $this->getStringBetweenTags($tagBegin, $tagEnd, $this->html);
              $this->html = $this->strReplaceOnce($tagBegin . $string . $tagEnd, '', $this->html);
            }
          }
        }
      }
    }
  }
  protected function tagGoodPrice()
  {
    if ($this->tagExists('good-price')) {
      $regexp = '~\{\{good-price\s+' . str_repeat("([a-z\\-\\d]+=(?:'|\")[^'\"]*(?:'|\"))?\\s*",20) . '\}\}~ui';
      if (preg_match_all($regexp, $this->html, $matches, PREG_SET_ORDER) > 0) {
        $goods = ArrayHelper::index($this->getGoods(), 'alias');
        foreach($matches as $params) {

          $replace = $params[0];
          unset($params[0]);
          $param = $this->parserParams($params);
          if (!isset($param['alias'])) continue;
          $alias = $param['alias'];
          $priceParams[$alias] = $param;

          if (isset($goods[$alias])) {
            $count = count($params);
            for ($i = 2; $i < $count; $i++) {
              $param = $this->parserParams($params[$i]);
              if ($param !== null) {
                $priceParams[$alias][$param[0]] = $param[1];
              }
            }

            $price = (isset($priceParams[$alias]['for'])) ? $this->getGoodPrice($goods[$alias], $priceParams[$alias]['for']) :  $this->getGoodPrice($goods[$alias], 1);
            $this->html = str_replace($replace, $price, $this->html);
          }
        }
      }
    }
  }
  protected function tagGoodUnity()
  {
    if ($this->tagExists('good-unity')) {
      $regexp = '~\{\{good-unity\s+' . str_repeat("([a-z\\-\\d]+=(?:'|\")[^'\"]*(?:'|\"))?\\s*",1) . '\}\}~ui';
      if (preg_match_all($regexp, $this->html, $matches, PREG_SET_ORDER) > 0) {
        $goods = ArrayHelper::index($this->getGoods(), 'alias');
        foreach($matches as $params) {

          $replace = $params[0];
          unset($params[0]);
          $param = $this->parserParams($params);
          if (!isset($param['alias'])) continue;
          $alias = $param['alias'];
          if (isset($goods[$alias])) {
            $this->html = str_replace($replace, $this->getGoodUnity($alias), $this->html);
          }
        }
      }
    }
  }

  protected function tagGoodPriceTotal()
  {
    if ($this->tagExists('good-price-total')) {
      $regexp = '~\{\{good-price-total\s*' . '(form=(?:\'|")[^\'"]+(?:\'|"))\s*\}\}~ui';
      if (preg_match_all($regexp, $this->html, $matches, PREG_SET_ORDER) > 0) {
        foreach ($matches as $params) {
          $replace = $params[0];
          $formID = explode('="', rtrim($params[1], '"'));
          $formID = $formID[1];
          $priceParams[$formID]['form'] = $formID;
          $total = ArrayHelper::getValue($this->goodTotalPrices,$formID,0);
          $price = '<span class="lv-good-price-total" data-lv-form="' . $priceParams[$formID]['form'] . '">'.$total.'</span>';
          $this->html = str_replace($replace, $price, $this->html);
        }
      }
    }
  }

  private function parserParams($param)
  {
    if ($param === null) return null;

    $result = [];
    $matches = [];

    if (is_array($param)) {
      foreach ($param as $string) {
        preg_match('~([a-z\-\d]+)="([^"]*)"~',$string,$matches);
        $result[$matches[1]] = $matches[2];
      }
    } else {
      preg_match('~([a-z\-\d]+)="([^"]*)"~',$param,$matches);
      $result = [trim($matches[1]),trim($matches[2])];
    }

    return $result;
  }
  private function parseFormFields(&$fieldParams, $type)
  {
    $rules = [];
    $name = $fieldParams['name'];
    $validator = '';
    if (isset($fieldParams['validator'])) {
      $validator =  $fieldParams['validator'];
      unset($fieldParams['validator']);
    }
    $required = '';
    if (isset($fieldParams['required'])) {
      $required =  $fieldParams['required'];
      unset($fieldParams['required']);
    }
    if ($validator != '') {
      switch ($type) {
        case 'select':
          $selectParams = explode(',', $validator);
          $rules[] = [$name, 'in', 'range' => $selectParams];
          $options = [];
          foreach ($selectParams as $param) {
            $options[$param] = $param;
          }
          $fieldParams['options'] = $options;
          break;
        case 'mask':
          $fieldParams['pattern'] = $validator;
          $regex = preg_quote($validator);
          $regex = str_replace("9", "[0-9]", $regex);
          $regex = str_replace("a", "[a-zA-Zа-яА-ЯёЁ]", $regex);
          $regex = str_replace('\*', ".*", $regex);
          $regex = str_replace('\?', ".", $regex);
          $regex = "~^" . $regex . "$~";
          $rules[] = [$name, 'match', 'pattern' => $regex];
          break;
        case 'checkbox':
          $rules[] = [$name, 'in', 'range' => [0,1]];
          break;
        default:
          $rules[] = [$name, 'match', 'pattern' => $validator];
          break;
      }
    }
    if ($required != 0) $rules[] = [$name, 'required'];
    return $rules;
  }
  protected function getStringBetweenTags($begin = "", $end = "", $string)
  {
    $tmp = strpos($string, $begin) + strlen($begin);
    $result = substr($string, $tmp, strlen($string));
    $dd = strpos($result, $end);
    if ($dd == 0) {
      $dd = strlen($result);
    }

    return substr($result, 0, $dd);
  }
  protected function strReplaceOnce($needle, $replace, $haystack)
  {
    $pos = strpos($haystack,$needle);
    if ($pos !== false) {
      return substr_replace($haystack,$replace,$pos,strlen($needle));
    } else return $haystack;
  }
  //Форма
  private function formTagExists()
  {
    return $this->tagExists(['form','form1','form2','form3','form4','form5','form6','form7','form8','form9','form_1','form_2','form_3','form_4','form_5','form_6','form_7','form_8','form_9']);
  }
  protected function tagForm()
  {
    $forms = [];
    $regexp = "~\\{\\{form(?:_?(\\d{1}))?(?:\\|(no_css))?(?:\\|(allow_set_total))?\\}\\}~i";
    if ($this->formTagExists())
      $this->html = preg_replace_callback($regexp,function ($matches) use (&$forms){
        if (isset($matches[1])) {
          $number = $matches[1];
          if ($number<2) $number = 1;
          if (in_array($number,$forms)) $number = max($forms)+1;
        } elseif (count($forms)>0) $number = max($forms)+1;
        else $number = 1;
        $noCss = isset($matches[2]) && !empty($matches[2]);
        $allowSetTotal = isset($matches[3]) && !empty($matches[3]);
        $forms[] = $number;
        if ($number==1) $number = '';
        return $this->renderForm($this->data['__model'],$number,$noCss,$allowSetTotal);
      },$this->html);
  }
  protected function tagForm2()
  {
    $this->renderForms();
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

  public function getViewFile($viewName = 'index', $notFound = true)
  {
    $file = $this->themePath.'/'.$viewName.'.html';
    $file = str_replace('//','/',$file);
    $exists = is_dir(dirname($file)) && file_exists($file);

    if (!in_array($viewName,['layout', 'index', 'pages/index']) && $exists === false) {
      if ($notFound) http_response_code(404);
      $file = $this->themePath.'/pages/index.html';
      $file = str_replace('//','/',$file);
      $exists = is_dir(dirname($file)) && file_exists($file);
    }
    if ($exists === false) return false;
    try {
      return file_get_contents($file);
    } catch (Exception $e) {return false;}
  }


  public function getFormsData()
  {
    $layout = $this->getViewFile('layout', false);
    if ($layout === false) $layout = $this->getViewFile('index', false);
    $html = $layout === false ? '{{content}}' : $layout;

    if (stripos($html, '{{content}}') !== false) {
      $page = (string)$this->getViewFile('pages/' . $this->page, false);
      if (stripos($page, '{{no_layout}}') === false) $html = str_replace('{{content}}', $page, $html);
      else $html = str_replace('{{no_layout}}', '', $page);
    }

    $forms = [];
    $formHeaders = [];
    $fieldIds = [];

    //парсим шапку формы
    $regexp = '~\{\{form(\d+|Update)Begin\s+' . str_repeat("([a-z\\-\\d]+=(?:'|\")[^'\"]*(?:'|\"))?\\s*",20) . '\}\}~ui';
    if (preg_match_all($regexp, $html, $matches, PREG_SET_ORDER) > 0) {
      foreach ($matches as $params) {
        $replace = $params[0];
        $formId = $params[1];
        $formParams = [];
        $count = count($params);
        for ($i = 2; $i < $count; $i++) {
          $param = $this->parserParams($params[$i]);
          if ($param !== null) $formParams[$param[0]] = $param[1];
        }
        if (!isset($formParams['action'])) $formParams['action'] = '/success.html';
        if (!isset($formParams['validationByAlert'])) $formParams['validationByAlert'] = 0;
        $this->formCodes[$formId][$replace] = $formParams;
        $this->formCodes[$formId][$replace]['tag'] = 'begin';
        $this->formCodes[$formId][$replace]['fields'] = [];
        $formHeaders[$formId] = $replace;
        $forms[$formId] = [];
      }
    }

    //парсим поля формы
    $regexp = '~\{\{form(\d+|Update)Field\s+' . str_repeat("([a-z\\-\\d]+=(?:'|\")[^'\"]*(?:'|\"))?\\s*",20) . '\}\}~ui';
    if (preg_match_all($regexp, $html, $matches, PREG_SET_ORDER) > 0) {
      foreach ($matches as $params) {
        $replace = $params[0];
        $formId = $params[1];
        $fieldParams = [];
        $count = count($params);
        for ($i = 2; $i < $count; $i++) {
          $param = $this->parserParams($params[$i]);
          //dump($param);
          if ($param !== null) {
            $fieldParams[$param[0]] = $param[1];
          }
        }

        if (isset($fieldParams['type']) && isset($fieldParams['name'])) {
          $type = $fieldParams['type'];
          $formFields = $this->parseFormFields($fieldParams, $type);
          if (isset($formHeaders[$formId])) {
            $formHeader = $formHeaders[$formId];
            $fieldParams['id'] = (isset($fieldParams['id'])) ? $fieldParams['id'] : 'lv-formLanding' . $formId . '-' . $fieldParams['name'];
            $this->formCodes[$formId][$formHeader]['fields'][$replace] = $fieldParams;
            $this->formCodes[$formId][$formHeader]['fields'][$replace]['tag'] = 'field';
            $forms[$formId] = array_merge($forms[$formId],$formFields);
            $fieldIds[$formId][$fieldParams['name']] = $fieldParams['id'];
          }
        }
      }
    }

    //парсим label
    $regexp = '~\{\{form(\d+|Update)Label\s+' . str_repeat('([a-z\-\d]+=(?:\'|")[^\'"]*(?:\'|")\s*)?',20) . '\}\}~ui';
    if (preg_match_all($regexp, $html, $matches, PREG_SET_ORDER) > 0) {
      foreach ($matches as $params) {
        $replace = $params[0];
        $formId = $params[1];
        $labelParams = [];
        $count = count($params);
        for ($i = 2; $i < $count; $i++) {
          $param = $this->parserParams($params[$i]);
          if ($param !== null) {
            $labelParams[$param[0]] = $param[1];
          }
        }

        if (isset($labelParams['for'])) {
          if (isset($formHeaders[$formId])) {
            $formHeader = $formHeaders[$formId];

            $name = $labelParams['for'];
            if (isset($fieldIds[$formId][$name])) {
              $labelParams['for'] = $fieldIds[$formId][$name];
            }
            $this->formCodes[$formId][$formHeader]['fields'][$replace] = $labelParams;
            $this->formCodes[$formId][$formHeader]['fields'][$replace]['tag'] = 'label';
          }
        }

      }
    }

    //парсим текст ошибки
    $regexp = '~\{\{form(\d+|Update)Error\s+' . str_repeat("([a-z\\-\\d]+=(?:'|\")[^'\"]*(?:'|\"))?\\s*",20) . '\}\}~ui';
    if (preg_match_all($regexp, $html, $matches, PREG_SET_ORDER) > 0) {
      foreach ($matches as $params) {
        $replace = $params[0];
        $formId = $params[1];
        $errorParams = [];
        $count = count($params);
        for ($i = 2; $i < $count; $i++) {
          $param = $this->parserParams($params[$i]);
          if ($param !== null) {
            $errorParams[$param[0]] = $param[1];
          }
        }

        if (isset($errorParams['name']) && isset($errorParams['text'])) {
          $name = $errorParams['name'];
          $text = $errorParams['text'];
          unset($errorParams['text']);

          $errorParams['inputID'] = '';
          $errorParams['id'] = '';
          if (isset($fieldIds[$formId][$name])) {
            $errorParams['inputID'] = $fieldIds[$formId][$name];
            $errorParams['id'] = $errorParams['inputID'] . '_em_';
          }

          if (isset($forms[$formId])) {
            $fields = $forms[$formId];
            foreach ($fields as $k => $field) {
              if ($field[0] == $name) $fields[$k] = ArrayHelper::merge($field, ['message' => $text]);
            }
            if (isset($formHeaders[$formId])) {
              $formHeader = $formHeaders[$formId];
              $this->formCodes[$formId][$formHeader]['fields'][$replace] = $errorParams;
              $this->formCodes[$formId][$formHeader]['fields'][$replace]['tag'] = 'error';
              $forms[$formId] = $fields;
            }
          }
        }
      }
    }

    foreach ($forms as $formId => $values) {
      if ($formId != 'Update' && !count($values)) unset($forms[$formId]);
    }
//    $forms = array_filter($forms,function($value){ return count($value);});
    if (count($forms) > 30) $forms = array_slice($forms, 0, 30, true);
    if (count($this->formCodes) > 30) $this->formCodes = array_slice($this->formCodes, 0, 30, true);

    return $forms;
  }

  public function renderPartial($html, $data = array())
  {
    $this->html = $html;

    preg_match_all('~\[\[([^\'\"\[\]\=]+(\=\d+)?:)\]\]~u',$this->html,$matchesIf);
    preg_match_all('~\[\[(:[^\'\"\[\]]+)\]\]~u',$this->html,$matchesEndIf);

    foreach ($matchesIf[1] as $match) {
      if (strpos($match, 'formUpdate:') !== false) $this->matchesFormUpdateIf[] = $match;
      if (strpos($match, 'webmaster') !== false) $this->matchesWebmasterIf[] = $match;
    }
    foreach ($matchesEndIf[1] as $match) {
      if (strpos($match, ':formUpdate') !== false) $this->matchesFormUpdateEndIf[] = $match;
      if (strpos($match, ':webmaster') !== false) $this->matchesWebmasterEndIf[] = $match;
    }

    if (count($this->matchesFormUpdateIf) && count($this->matchesFormUpdateEndIf)) $this->tagIfFormUpdate();
    if (count($this->matchesWebmasterIf) && count($this->matchesWebmasterEndIf)) $this->tagIfWebmaster();

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

    $this->tagQuantityDiscount();
    $this->tagFromTo();
    $this->tagOnlyTo();
    $this->tagGeo();
    $this->tagOrderNumber();
    $this->tagOrderTotal();
    $this->tagFiles();
    $this->tagWebmaster();
    $this->tagUpsell();

    $this->tagForm2();
    $this->tagGoodButton();
    $this->tagGoodQuantity();
    $this->tagGoodSelect();
    $this->tagGoodPrice();
    $this->tagGoodUnity();
    $this->tagGoodPriceTotal();

    $this->tagUtm('source');
    $this->tagUtm('medium');
    $this->tagUtm('term');
    $this->tagUtm('content');
    $this->tagUtm('campaign');

    $this->tagForm();
    $this->tagFormUpdate();

    $this->tagUserVars();
    foreach ($this->data as $key => $value) if (gettype($value) != 'object') $this->html = str_replace('{{' . $key . '}}', $value, $this->html);

    $this->tagCurrency();
    $this->tagPhone();
    $this->tagEmail();
    $this->tagPrice('price',$this->getPrice());
    $this->tagPrice('price_multi',$this->getPrice());
    $this->tagPrice('price_option',$this->getPrice());
    $this->tagPrice('oldPrice|old_price|price_old',$this->getOldPrice());
    $this->tagPrice('total_price|price_total',$this->getTotalPrice());
    $this->tagDiffPrice();
    $this->tagDeliveryPrice();

    $this->registerScript('window.leadvertex','if (!window.leadvertex) window.leadvertex = {};');
		$goodsPrices = $this->getGoodsPrices();
		if (count($goodsPrices)) {
			$this->registerScript('window.leadvertex.goodsPrices', 'if (!window.leadvertex.goodsPrices) window.leadvertex.goodsPrices = ' . json_encode($goodsPrices) . ';');
		}


    return $this->html;
  }


  public function render($data = null)
  {
    $layout = $this->getViewFile('layout');
    if ($layout === false) $layout = $this->getViewFile('index');

    $html = $layout === false ? '{{content}}' : $layout;

    if (stripos($html, '{{content}}') !== false) {
      $viewFile = $this->getViewFile('pages/' . $this->page);
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

  /**
   * @internal. Don't use directly
   * You must override this method for use
   */
  public static function LvManualTotalHash()
  {
    //dummy hash
    return md5(rand(1,9999));
  }
}