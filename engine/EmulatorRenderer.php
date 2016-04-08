<?php
class EmulatorRenderer extends LvBaseRenderer {
  const VERSION = 4.0;

  protected $scripts = array();
  protected $config = array();
  /** @var \SimpleXMLElement */
  private $_xml;
  private $_discount = [];
  private $_fields;

  private $_landing = true;

  public function __construct($themePath, $page)
  {
    parent::__construct($themePath, $page);
    if (file_exists($this->themePath.'/config.xml')) {
      $this->_xml = simplexml_load_file($this->themePath.'/config.xml');
    } else {
      $xml = file_get_contents('assets/config.xml');
      $this->_xml = simplexml_load_string($xml);
      if (is_dir($this->themePath)) file_put_contents($this->themePath.'/config.xml',$xml);
      else $this->_landing = false;
    }
    if (!file_exists($this->themePath.'/vars.html')) {
      $vars = file_get_contents('assets/vars.html');
      if (is_dir($this->themePath)) file_put_contents($this->themePath.'/vars.html',$vars);
      else $this->_landing = false;
    }
  }
  private function registerFile($filename,$onTop = false)
  {
    $filename = strtolower($filename);
    $ext = substr(strrchr($filename, '.'), 1);
    if (!isset($this->scripts[$filename])) {
      if ($onTop === true) {
        if ($ext == 'js') $this->html = str_ireplace('<title', '<script type="text/javascript" src="'.$filename.'"></script>'."\n".'<title', $this->html);
        else $this->html = str_ireplace('<title', '<link rel="stylesheet" href="'.$filename.'"/><title', $this->html);
      } else {
        if ($ext == 'js') $this->html = str_ireplace('<title', '<script type="text/javascript" src="'.$filename.'"></script>'."\n".'<title', $this->html);
        else $this->html = str_ireplace('<title', '<link rel="stylesheet" href="'.$filename.'"/>'."\n".'<title', $this->html);
      }
      $this->scripts[$filename] = $filename;
    }
  }

  protected function renderAttributes($attributes)
  {
    return self::renderAttrs($attributes);
  }
  private static function renderAttrs($htmlOptions, $renderSpecialAttributesValue = true)
  {
    static $specialAttributes=array(
      'async'=>1,
      'autofocus'=>1,
      'autoplay'=>1,
      'checked'=>1,
      'controls'=>1,
      'declare'=>1,
      'default'=>1,
      'defer'=>1,
      'disabled'=>1,
      'formnovalidate'=>1,
      'hidden'=>1,
      'ismap'=>1,
      'loop'=>1,
      'multiple'=>1,
      'muted'=>1,
      'nohref'=>1,
      'noresize'=>1,
      'novalidate'=>1,
      'open'=>1,
      'readonly'=>1,
      'required'=>1,
      'reversed'=>1,
      'scoped'=>1,
      'seamless'=>1,
      'selected'=>1,
      'typemustmatch'=>1,
    );

    if($htmlOptions===array())
      return '';

    $html='';
    if(isset($htmlOptions['encode']))
    {
      $raw=!$htmlOptions['encode'];
      unset($htmlOptions['encode']);
    }
    else
      $raw=false;

    foreach($htmlOptions as $name=>$value)
    {
      if(isset($specialAttributes[$name]))
      {
        if($value)
        {
          $html .= ' ' . $name;
          if($renderSpecialAttributesValue)
            $html .= '="' . $name . '"';
        }
      }
      elseif($value!==null)
        $html .= ' ' . $name . '="' . ($raw ? $value : $value) . '"';
    }

    return $html;
  }

  protected function getGoods()
  {
    $xmlParams = $this->_xml->goods->good;
    /** @var $xmlParams [] SimpleXMLElement */
    $goods = [];
    if (count($xmlParams)) {
      foreach ($xmlParams as $xmlParam) {
        $good = new Good();
        $good->alias = (string)$xmlParam['alias'];
        $good->unity = (string)$xmlParam['unity'];
        $good->price = (string)$xmlParam['price'];
        $good->name = (string)$xmlParam['name'];
        $goods[] = $good;
      }
    }
    return $goods;
  }
  protected function getGoodPrices($price)
  {
    $lines = explode(',', $price);

    $prices = [];
    foreach ($lines as $line) {
      $line = trim($line);
      $matches = [];
      preg_match('~^(\d+):(\d+)$~',$line,$matches);
      $prices[$matches[1]] = $matches[2];
    }

    return $prices;
  }
  protected function getGoodPrice($good, $quantity)
  {
    $prices = $this->getGoodPrices($good->price);
    return (isset($prices[$quantity])) ? $prices[$quantity] : 0;
  }
  protected function getGoodUnity($alias)
  {
    $goods = $this->getGoods();
    $goods = ArrayHelper::index($goods,'alias');
    if (isset($goods[$alias])) {
      return $goods[$alias]->unity;
    } else return '';
  }
  protected function getUpdateFormCookie()
  {
    return (isset($_COOKIE['orderUpdateTime']) && !empty($_COOKIE['orderUpdateTime'])) ? $_COOKIE['orderUpdateTime'] : null;
  }
  protected function registerJQuery()
  {
    $this->registerFile('/assets/jquery-1.9.1.js',true);
  }
  protected function registerScript($id,$script,$overhead = false)
  {
    if (!isset($this->scripts['*inline_'.$id])) {
      $regexp = $overhead ? '~<head[^>]*>~' : '~<body[^>]*>~';
      if (preg_match($regexp,$this->html,$matches)) $this->html = str_replace($matches[0],$matches[0]."\n".'<script>'.$script.'</script>',$this->html);
      $this->scripts['*inline_'.$id] = '*inline_'.$id;
    }
  }
  protected function registerScriptFile($path)
  {
    $this->registerFile($path);
  }
  protected function renderForm($model,$number,$noCss,$allowSetTotal)
  {
    $fields = (string)$this->_xml->form['fields'];
    $fields = explode(',',$fields);
    $fields = array_map('trim',$fields);
    $fields = array_combine($fields,$fields);
    $this->_fields = $fields;

    $form = [];
    foreach ($this->_xml->form->field as $field)
    {
      $name = (string)$field['name'];
      $form[$name] = [
        'name' => (string)$field->caption,
        'message' => (string)$field->error,
        'error' => (string)$field['error'],
        'required' => (string)$field['required'],
        'type' => (string)$field['type'],
        'pattern' => (string)$field->pattern,
      ];
      if ($name == 'quantity') $form[$name]['unit'] = $this->getConfigParam('quantity.unit');
    }
    $buttonText = (string)$this->_xml->form['button-text'];

    $html = '<form id="lv-form'.$number.'" class="lv-order-form'.($noCss ? '' : ' lv-order-form-css').'" data-form-number="'.$number.'" action="/success.html" method="post">';

    if ($allowSetTotal) {
      echo '<input id="lv-form'.$number.'-manual-total" class="lv-form-manual-total" type="hidden" value="0" name="lv-form-manual-total">';
      echo '<input id="lv-form'.$number.'-manual-total-hash" type="hidden" value="'.LvBaseRenderer::LvManualTotalHash().'" name="lv-form-manual-total-hash">';
    }

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
    $html.='<div class="lv-form-submit"><input class="lv-order-button" type="submit" name="yt0" value="'.$buttonText.'"></div>';
    $html.= '</form>';
    return $html;
  }
  protected function renderFormUpdate($model,$noCss)
  {
    $fields = (string)$this->_xml->form['fields_update'];
    $fields = explode(',',$fields);
    $fields = array_map('trim',$fields);
    $fields = array_combine($fields,$fields);
    $fields = array_filter($fields,function($value){return $value;});
    $this->_fields = $fields;

    if (empty($fields)) return '';

    $form = [];
    foreach ($this->_xml->form->field as $field)
    {
      $name = (string)$field['name'];
      $form[$name] = [
        'name' => (string)$field->caption,
        'message' => (string)$field->error,
        'error' => (string)$field['error'],
        'required' => (string)$field['required'],
        'type' => (string)$field['type'],
        'pattern' => (string)$field->pattern,
      ];
      if ($name == 'quantity') $form[$name]['unit'] = $this->getConfigParam('quantity.unit');
    }
    $buttonText = (string)$this->_xml->form['button-text'];

    $html = '<form id="lv-form-update" class="lv-order-form'.($noCss ? '' : ' lv-order-form-css').'" data-form-number="'.$number.'" action="/success.html" method="post">';

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
        $html.='<input name="Order['.$field.']" id="lv-form-'.$field.'" value="1" type="checkbox" class="lv-input-'.$field.'" data-required="'.(int)$required.'">';
        $html.='<label for="lv-form-'.$field.'">С условиями покупки согласен</label>';
        $html.='</div>';
      }
      else {
        $html.='<div class="lv-label"><label for="form_'.$field.'">'.$name.($required ? ' <span class="required">*</span>' : '').'</label></div>';
        $html.='<div class="lv-field">';

        if ($type == 'dropdown') {
          $html.='<select data-label="'.$name.'" name="Order['.$field.']" id="lv-form-'.$field.'" class="lv-input-'.$field.'" data-required="'.(int)$required.'">';
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
            $html .= '<option value="'.(int)trim($value).'" data-sum="'.$sum.'">' . $item . '</option>';
          }
          $html.='</select>';
        }
        elseif ($type == 'string') $html.='<input data-label="'.$name.'" name="Order['.$field.']" id="lv-form-'.$field.'" class="lv-input-'.$field.'" type="text" maxlength="255" data-required="'.(int)$required.'"/>';
        elseif ($type == 'text') $html.='<textarea data-label="'.$name.'" name="Order['.$field.']" id="lv-form-'.$field.'" class="lv-input-'.$field.'" data-required="'.(int)$required.'"></textarea>';

        $html.='</div>';
      }
      $html.='<div class="lv-error"><div class="lv-error-text" id="lv-form-'.$field.'_em_" style="'.$error.'">'.$message.'</div></div>';
      $html.='</div>';
    }
    $html.='<div class="lv-form-submit"><input class="lv-order-button" type="submit" name="yt0" value="'.$buttonText.'"></div>';
    $html.= '</form>';
    return $html;
  }
  protected function renderForms()
  {
    $this->getFormsData();

    //скрываем форму уточнения заказа, если upsell_hide=1
    if (isset($this->formCodes['Update']) && $this->getUpdateFormCookie() === null) {
      $begin = key($this->formCodes['Update']);
      $string = $this->getStringBetweenTags($begin, '{{formUpdateEnd}}', $this->html);
      $this->html = $this->strReplaceOnce($begin . $string . '{{formUpdateEnd}}', '', $this->html);
      unset($this->formCodes['Update']);
    }

    foreach ($this->formCodes as $formID => $form) {
      foreach ($form as $code => $options) {
        $fields = $options['fields'];
        $options['id'] = (isset($options['id'])) ? $options['id'] : 'lv-formLanding' . $formID;
        unset($options['fields']);
        unset($options['tag']);
        $alias = null;
        if (isset($options['alias'])) {
          $alias = $options['alias'];
          unset($options['alias']);
        }

        $formAction = $options['action'];
        unset($options['action']);

        $validationByAlert = $options['validationByAlert'];
        unset($options['validationByAlert']);

        @$options['class'] .= ' lv2-form lv2-form' . $formID;
        if ($validationByAlert == 1) {
          $options['class'] .= ' lv2-form-validation-by-alert';
        }

        $options = $this->renderAttributes($options);
        $formBegin = '<form method="post" action="'.$formAction.'" ' . $options . '>';
        $this->html = str_replace($code, $formBegin, $this->html);

        foreach ($fields as $fieldCode => $fieldsOptions) {
          $tag = $fieldsOptions['tag'];
          unset($fieldsOptions['tag']);
          switch ($tag) {
            case 'label':
              $for = $fieldsOptions['for'];
              $labelText = $fieldsOptions['label'];
              unset($fieldsOptions['label']);
              $fieldsOptions = $this->renderAttributes($fieldsOptions);
              $label = '<label '.$fieldsOptions.'>'.$labelText.'</label>';
              $this->html = str_replace($fieldCode,$label,$this->html);
              break;
            case 'field':
              $type = $fieldsOptions['type'];
              $name = $fieldsOptions['name'];
              unset($fieldsOptions['type']);
              unset($fieldsOptions['name']);
              switch ($type) {
                case 'text':
                  $fieldsOptions = $this->renderAttributes($fieldsOptions);
                  $textField = '<input type="'.$type.'" name="'.$name.'" '.$fieldsOptions.'/>';
                  $this->html = str_replace($fieldCode,$textField,$this->html);
                  break;
                case 'textarea':
                  $fieldsOptions = $this->renderAttributes($fieldsOptions);
                  $textField = '<textarea name="'.$name.'" '.$fieldsOptions.'></textarea>';
                  $this->html = str_replace($fieldCode,$textField,$this->html);
                  break;
                case 'select':
                  $data = $fieldsOptions['options'];
                  unset($fieldsOptions['options']);
                  $fieldsOptions = $this->renderAttributes($fieldsOptions);
                  $select = '<select name="'.$name.'" '.$fieldsOptions.'/>';
                  foreach ($data as $option) {
                    $select .= '<option>' . $option . '</option>';
                  }
                  $select .= '</select>';
                  $this->html = str_replace($fieldCode,$select,$this->html);
                  break;
                case 'checkbox':
                  $fieldsOptions = $this->renderAttributes($fieldsOptions);
                  $checkbox ='<input name="'.$name.'" type="'.$type.'" '.$fieldsOptions.'>';
                  $this->html = str_replace($fieldCode,$checkbox,$this->html);
                  break;
                case 'mask':
                  $this->registerScriptFile('/assets/jquery.mask.js');
                  $pattern = $fieldsOptions['pattern'];
                  unset($fieldsOptions['pattern']);
                  $this->registerScript('mask-'.$name,'$(document).ready(function(){$("#'.$fieldsOptions['id'].'").mask("'.$pattern.'");});');
                  $fieldsOptions = $this->renderAttributes($fieldsOptions);
                  $mask = '<input type="text" name="'.$name.'" '.$fieldsOptions.'/>';
                  $this->html = str_replace($fieldCode,$mask,$this->html);
                  break;
                case 'payment':
                  @$fieldsOptions['class'] .= ' lv-input-paymentOn';
                  if (isset($fieldsOptions['options'])) {
                    $data = $fieldsOptions['options'];
                    unset($fieldsOptions['options']);
                    $fieldsOptions = $this->renderAttributes($fieldsOptions);
                    if (count($data) > 1) {
                      $select = '<select name="'.$name.'" '.$fieldsOptions.'/>';
                      foreach ($data as $key => $option) {
                        $select .= '<option value="'.$key.'">' . $option . '</option>';
                      }
                      $select .= '</select>';
                      $this->html = str_replace($fieldCode,$select,$this->html);
                    } else {
                      $paymentHidden = '<input type="hidden" value="'.key($data).'" name="FormLanding[paymentOn]" id="FormLanding_paymentOn">';
                      $this->html = str_replace($fieldCode,$paymentHidden,$this->html);
                    }
                  }
                  break;
              }
              break;
            case 'error':
              $name = $fieldsOptions['name'];
              $text = $fieldsOptions['text'];
              unset($fieldsOptions['name']);
              unset($fieldsOptions['text']);
              @$fieldsOptions['class'] .= ' lv2-form-error';
              $fieldsOptions = $this->renderAttributes($fieldsOptions);
              $error = '<div name="'.$name.'" '.$fieldsOptions.' >'.$text.'</div>';
              $this->html = str_replace($fieldCode,$error,$this->html);
              break;
          }
        }

        $formEndCode = '</form>';
        $formEndCode = '<input type="hidden" value="'.$formID.'" name="formID" id="formID">' . $formEndCode;
        $formEndCode = '<input type="hidden" value="'.$formAction.'" name="FormLanding[redirect]" id="FormLanding_redirect">' . $formEndCode;
        $id = 'lv-formLanding' . $formID . '-goods';
        if ($alias !== null) {
          $goodValue = [];
          $totalPrice = 0;
          $goods = ArrayHelper::index($this->getGoods(), 'alias');
          $alias = explode(',', $alias);
          foreach ($alias as $a) {
            $a = trim($a);
            if (isset($goods[$a])) {
              $goodValue[$a] = ['quantity' => 1,'sum' =>  $this->getGoodPrice($goods[$a], 1)];
              $totalPrice += $goodValue[$a]['sum'];
            }
          }
          if (count($goodValue)) {
            $formEndCode = '<input class="lv-input-goods" id="'.$id.'" type="hidden" value=\''.json_encode($goodValue).'\' name="FormLanding[goods]">' . $formEndCode;
            $this->goodTotalPrices[$formID] = $totalPrice;
          }
        } else {
          $formEndCode = '<input class="lv-input-goods" id="'.$id.'" type="hidden" name="FormLanding[goods]">' . $formEndCode;
        }

        $this->html = str_replace('{{form'.$formID.'End}}',$formEndCode,$this->html);
      }
    }
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
          foreach ($options as $paramValue) {
            $paramKey = trim($paramValue);
            if (preg_match('~\{\{(\d+)\}\}~', $paramValue, $matches)) {
              $paramKey = trim(str_replace('{{'.$matches[1].'}}','',$paramValue));
              $this->priceOptions[$fieldName][$paramKey] = $matches[1];
            }
            else $this->priceOptions[$fieldName][$paramKey] = 0;
          }
        }
      }
    }
    //print_r($this->priceOptions);
    //die;
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
    return $this->getConfigParam('delivery.price');
  }
  protected function getGeoCity()
  {
    return 'Москва';
  }
  protected function getGeoRegion()
  {
    return 'Московская область';
  }
  protected function getGeoCountry()
  {
    return 'Россия';
  }
  protected function getGeoCountryCode()
  {
    return 'RU';
  }
  protected function getOrderNumber()
  {
    return rand(100,1000);
  }
  protected function getOrderTotal()
  {
    return rand(1000,2000);
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
  protected function getUtmArray($label=null)
  {
    $utm = [
      'utm_source' => 'source_label',
      'utm_medium' => 'medium_label',
      'utm_term' => 'term_label',
      'utm_content' => 'content_label',
      'utm_campaign' => 'campaign_label',
    ];
    if ($label===null) return $utm;
    if (isset($utm[$label])) return $utm[$label];
    else throw new Exception('Такой метки не существует');
  }

  protected function tagForm()
  {
    parent::tagForm();
    $this->registerFile('/assets/placeholders.min.js');
    $this->registerFile('/assets/formHelper.js');
    $this->registerFile('/assets/form.css');

    if (isset($this->_fields['quantity'])) {
      $script = 'if (!window.leadvertex.selling) window.leadvertex.selling = {};'."\n";
      $script.= 'window.leadvertex.selling.discount = '.json_encode($this->getDiscountOptions(),JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT).";\n";
      $this->registerScript('window.leadvertex.selling.discount',$script);
    }
    $script = 'if (!window.leadvertex.selling.delivery) window.leadvertex.selling.delivery = {};'."\n";
    $script.= 'window.leadvertex.selling.delivery.price = '.$this->getConfigParam('delivery.price').";\n";
    $script.= 'window.leadvertex.selling.delivery.for_Each = '.($this->getConfigParam('delivery.forEach') ? 'true' : 'false').";\n";
    $this->registerScript('window.leadvertex.selling.delivery',$script);

    $script = 'if (!window.leadvertex.selling.price) window.leadvertex.selling.price = {};'."\n";
    $script.= 'window.leadvertex.selling.price.price = '.$this->getPrice().";\n";
    $script.= 'window.leadvertex.selling.price.old = '.$this->getOldPrice().";\n";
    $script.= 'window.leadvertex.selling.quantity = '.$this->getConfigParam('quantity.default').";\n";
    $this->registerScript('window.leadvertex.selling.price',$script);
  }
  protected function tagCountdownJs($script='/assets/countdown.js')
  {
    parent::tagCountdownJs($script);
  }

  protected function checkDirectory()
  {
    if (!$this->_landing) return false;
    $extensions = array(
      'code' => array('css', 'js', 'htm', 'html', 'txt', 'less', 'xml', 'htc','htaccess'),
      'image' => array('jpg', 'jpeg', 'png', 'gif', 'svg', 'ico'),
      'other' => array(
        'ttf', 'eot', 'woff', 'woff2', 'otf',
        'rar', 'zip', '7z', 'exe', 'tar',
        'mp3', 'mp4', 'flv', '3gp', 'swf', 'ogg', 'webm', 'ogv',
        'doc', 'docx', 'pdf',
      )
    );
    $extensions = array_merge($extensions['code'], $extensions['image'], $extensions['other']);
    $errors = [];
    $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->themePath), RecursiveIteratorIterator::SELF_FIRST);
    foreach($objects as $name => $object){
      $basename = basename($name);
      if ($basename !='.' && $basename!='..') {
        $shortName = str_ireplace($this->themePath,'',$name);
        $shortName = str_replace('\\','/',$shortName);
        if (is_dir($name)) {
          if (preg_match('~^[a-z\d\-_][a-z\d\-_\x20\.]*$~i', $basename)==false) $errors[] = 'Неверное имя каталога: '.$shortName."\n";
        } else {
          $extRegExp = implode('|', $extensions);
          if (preg_match('~(^[a-z\d\-_][a-z\d\-_\.\x20@]*\.(' . $extRegExp . '))|(\.htaccess)$~ui', $basename)==false) $errors[] = 'Неверное имя файла: '.$shortName."\n";
        }
      }
    }
    return $errors;
  }
  protected function renderDebugBar()
  {
    if (TEMPLATE) return true;
    $base = __DIR__.'/../templates';
    $dirFileList = scandir($base);
    unset($dirFileList[0]);
    unset($dirFileList[1]);
    $dirList = [];
    foreach ($dirFileList as $dir) {
      if (is_dir($base.'/'.$dir)) {
        $selected = $dir == LV_LANDING ? ' selected="selected"' : '';
        $dirList[] = '<option'.$selected.'>'.$dir.'</option>';
      }
    }

    $errors = $this->checkDirectory();

    if (!preg_match('~<html[^>]*>~i',$this->html)) {
      $errors[] = 'На вашем лендинге отутствует обязательный открывающий тег &lt;html&gt;';
      $this->html = '<html>'.$this->html;
    }
    if (!preg_match('~</html>~i',$this->html)) {
      $errors[] = 'На вашем лендинге отутствует обязательный закрывающий тег &lt;/html&gt;';
      $this->html = $this->html.'</html>';
    }
    if (!preg_match('~<body[^>]*>~i',$this->html)) {
      $errors[] = 'На вашем лендинге отутствует обязательный открывающий тег &lt;body&gt;';
      if (preg_match('~<html[^>]*>~i',$this->html,$matches)) $this->html = str_replace($matches[0],$matches[0].'<body>',$this->html);
    }
    if (!preg_match('~</body>~i',$this->html)) {
      $errors[] = 'На вашем лендинге отутствует обязательный закрывающий тег &lt;/body&gt;';
      if (preg_match('~</html>~i',$this->html,$matches)) $this->html = str_replace($matches[0],'</body>'.$matches[0],$this->html);
    }
    if (!preg_match('~<head[^>]*>~i',$this->html)) {
      $errors[] = 'На вашем лендинге отутствует обязательный открывающий тег &lt;head&gt;';
      if (preg_match('~<html[^>]*>~i',$this->html,$matches)) $this->html = str_replace($matches[0],$matches[0].'<head>',$this->html);
    }
    if (!preg_match('~</head>~i',$this->html)) {
      $errors[] = 'На вашем лендинге отутствует обязательный закрывающий тег &lt;/head&gt;';
      if (preg_match('~<body[^>]*>~i',$this->html,$matches)) $this->html = str_replace($matches[0],'</head>'.$matches[0],$this->html);
    }
    if (!preg_match('~<title[^>]*>~i',$this->html)) {
      $errors[] = 'На вашем лендинге отутствует обязательный тег &lt;title&gt;';
      if (preg_match('~<head[^>]*>~i',$this->html,$matches)) $this->html = str_replace($matches[0],$matches[0].'<title></title>',$this->html);
    }

    $errorStr = '';
    if (is_array($errors)) foreach ($errors as $error) $errorStr.='<li>'.$error.'</li>';
    if (!empty($errorStr)) $errorStr = '<ul id="lv_errors">'.$errorStr.'</ul>';

    $html = '<div id="lv_debug_bar">';
    $html.= '    <div id="lv_toggle" title="Свернуть или развернуть отладочную панель"></div>';
    $html.= '    <a target="_blank" href="/index.php?tar=1" id="lv_download_as_tar" title="Скачать текущий лендинг в «*.tar» арохиве">Скачать как «*.tar»</a>';
    $html.= '    <select id="lv_landing">'.implode('',$dirList).'</select>';
    $html.= '   '.$errorStr;
    $html.= '</div>';

    if (preg_match('~<body[^>]*>~',$this->html,$matches)) $this->html = str_replace($matches[0],$matches[0].$html,$this->html);
    $this->registerFile('/assets/debug.css');
    $this->registerFile('/assets/debug.js');
  }

  public function renderPartial($html, $data = [])
  {
    $data['__model'] = true;
    $this->html = parent::renderPartial($html, $data);
    $this->renderDebugBar();

    $this->registerScript('lvjq1-noconflict',"document.write('<scr'+'ipt type=\"text/javascript\">lvjq1 = jQu'+'ery.noConflict(true);</scr'+'ipt>');",true);
    $this->registerScript('lvjq1',"document.write('<scr'+'ipt type=\"text/javascript\" src=\"/assets/jquery-1.9.1.js\">'+'</scr'+'ipt>');",true);

    return $this->html;
  }
  public function noLanding()
  {
    return file_get_contents(__DIR__.'/../assets/no_landing.html');
  }
  public function render($view = 'index', $data = null)
  {
    $layout = $this->getViewFile('layout');
    $html = $layout === false ? '{{content}}' : $layout;
    if ($this->_landing) {
      if (stripos($html, '{{content}}') !== false) {
        $viewFile = $this->getViewFile('pages/' . $view);
        if ($viewFile === false) {
          header("HTTP/1.0 404 Not Found");
          $data['code'] = 404;
          $data['message'] = 'Запрашиваемой Вами страницы не существует';
          $page = '<h1 class="errorCode">Ошибка 404</h1><p class="errorMessage">' . $data['message'] . '</p>';
        } else $page = $viewFile;
        if (stripos($page, '{{no_layout}}') === false) $html = str_ireplace('{{content}}', $page, $html);
        else $html = str_ireplace('{{no_layout}}', '', $page);
      }
    } else $html = $this->noLanding();
    if (file_exists($this->themePath.'/layout.html') == false && file_exists($this->themePath.'/pages/index.html') == false)
      $html = $this->noLanding();

    echo $this->renderPartial($html, $data);
  }

}

class Good
{
  public $alias;
  public $unity;
  public $price;
  public $name;

  public function __construct()
  {
  }
}