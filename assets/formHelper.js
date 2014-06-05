if (!window.leadvertex) window.leadvertex = {};
if (!window.leadvertex.selling) window.leadvertex.selling = {};
if (!window.leadvertex.selling.delivery) window.leadvertex.selling.delivery = {};
if (!window.leadvertex.selling.discount) window.leadvertex.selling.discount = {};
if (!window.leadvertex.selling.price) window.leadvertex.selling.price = {};
if (!window.leadvertex.selling.quantity) window.leadvertex.selling.quantity = 0;
if (!window.leadvertex.selling.additional) window.leadvertex.selling.additional = {};
if (!window.leadvertex.selling.additionalSum) window.leadvertex.selling.additionalSum = 0;

window.leadvertex.form = {};

window.leadvertex.form.label = function(field,name,form){
    if (!form) form = ''; else if (form<2) form = '';
    $(document).ready(function(){
        $('label[for=lv-form'+form+'-'+field +']').text(name);
    });
};
window.leadvertex.form.subLabel = function(field,text,form){
    if (!form) form = ''; else if (form<2) form = '';
    $(document).ready(function(){
        $('#lv-form'+form+' div.lv-row-'+field +'>.lv-field').after('<div class="lv-sub-label"><label for="lv-form'+form+'-'+field +'">'+text+'</label></div>');
    });
};

window.leadvertex.form.buttonText = function(text,form){
    if (!form) form = ''; else if (form<2) form = '';
    $(document).ready(function(){
        $('#lv-form'+form +' .lv-order-button').val(text);
    });
}

window.leadvertex.form.placeholder = function(field,placeholder,form){
    if (!form) form = ''; else if (form<2) form = '';
    $(document).ready(function(){
        $('#lv-form'+form+'-'+field).attr('placeholder',placeholder);
    });
};
window.leadvertex.form.placeholderOnly = function(form){
    if (!form) form = ''; else if (form<2) form = '';
    $(document).ready(function(){
        $('#lv-form'+form+' .lv-row.lv-row-input').each(function(i,e){
            $(e).find('.lv-label').hide();
            var $input = $(e).find('.lv-field > *');
            $input.attr('placeholder',$input.attr('data-label')+($input.attr('data-required') == '1' ? ' *' : ''));
        });
    });
};

window.leadvertex.form.validation = function($form, data, hasError) {
    if (hasError) {
        var errors = '';
        if ($form.attr('data-validation-by-alert')) {
            for (var i in data) errors+= data[i][0]+"\n\n";
            alert(errors);
        }
    }
    return true;
}
window.leadvertex.form.validationByAlert = function(form){
    if (!form) form = ''; else if (form<2) form = '';
    $(document).ready(function(){
        var $form = $('#lv-form'+form);
        $form.attr('data-validation-by-alert',1);
        $form.find('.lv-error').hide();
    });
}

window.leadvertex.form.showOnly = function (fields,form){
    if (!form) form = ''; else if (form<2) form = '';
    $(document).ready(function(){
        var $form = $('#lv-form'+form);
        $form.find('.lv-row').each(function(i,e){
            var name = $(e).attr('data-name');
            var notShow = $.inArray(name,fields)==-1;
            var isRequired = $(e).attr('data-required');
            if (name == 'checkboxPersonalData' || name == 'checkboxAgreeTerms') {
                $(e).prop('checked', true);
                isRequired = 0;
            }
            if (notShow && isRequired==0) $(e).hide();
        });
    });
}

$(document).ready(function(){
    $('.lv-move').each(function(i,e){
        var form = $(e).attr('data-form');
        if (!form) form = '';
        var position = $(e).attr('data-position').toString().toLowerCase();
        var field = $(e).attr('data-field').toString().toLowerCase();
        if (field == 'submit') field = 'div.lv-form-submit';
        else field = 'div.lv-row-'+field;
        var $element = $('#lv-form'+form+' '+field);
        if (position == 'before') $element.before($(e));
        if (position == 'after') $element.after($(e));
    });



    function calcAdditionalSum()
    {
        window.leadvertex.selling.additionalSum = 0;
        for (var i in window.leadvertex.selling.additional)
            window.leadvertex.selling.additionalSum+= window.leadvertex.selling.additional[i];
        return parseInt(window.leadvertex.selling.additionalSum);
    }

    //Обновляем выпадающие списки на всех формах, т.к. именно они формируют цены
    $('.lv-order-form select').change(function(){
        $('.lv-order-form select.'+$(this).attr('class')).val($(this).val());
        reCalc();
    });

    $('.lv-order-form select').not('.lv-input-quantity').change(function(){
        window.leadvertex.selling.additional[$(this).attr('class')] = $(this).find('option:selected').attr('data-sum');
        $('.lv-multi-price').text(window.leadvertex.selling.price['price']+calcAdditionalSum());
        reCalc();
    });

    function reCalc(){
        var $quantity = $('.lv-input-quantity');
        var quantity = window.leadvertex.selling.quantity;
        if ($quantity.length>0) quantity = parseInt($quantity.val());

        var deliveryObject = window.leadvertex.selling.delivery;
        var discountObject = window.leadvertex.selling.discount;

        // Доставка
        var deliveryPrice = parseInt(deliveryObject['price']);
        var delivery;
        if (deliveryObject['for_Each']) {
            delivery = deliveryPrice * quantity;
            $('.lv-delivery-price').text(delivery);
        } else delivery = deliveryPrice;

        //Итого
        var price = parseInt(window.leadvertex.selling.price['price']) + calcAdditionalSum();
        var discountPercent = 0;
        var discountRound = true;
        var discountSum = 0;
        if (discountObject[quantity]) {
            discountPercent = discountObject[quantity]['discount'];
            discountRound = discountObject[quantity]['round'];
            discountSum = discountObject[quantity]['sum'];
        } else {
            var index = -1;
            for (var i in discountObject) if (quantity>=i) {
                index = i;
            } else break;
            if (index == -1) {
                discountPercent = 0;
                discountRound = true;
                discountSum = 0;
            } else {
                discountPercent = discountObject[index]['discount'];
                discountRound = discountObject[index]['round'];
                discountSum = 0;
            }
        }
        var newPrice = parseFloat((price * quantity / 100) * (100-discountPercent)).toFixed(2);
        if (discountSum>0) newPrice = discountSum;

        var discountResultSum = price*quantity-newPrice;
        price = newPrice;
        if (discountRound) price = Math.round(price);
        $('.lv-quantity-discount-sum').text(parseInt(discountResultSum));
        $('.lv-quantity-discount-percent').text(parseInt(discountPercent));
        $('.lv-total-price').text(parseInt(delivery)+parseFloat(price));
    };
});