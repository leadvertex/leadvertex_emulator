lvjq1(document).ready(function () {
    function getCookie(name) {
        var matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    }
    function setCookie(name, value, options) {
        options = options || {};

        var expires = options.expires;

        if (typeof expires == "number" && expires) {
            var d = new Date();
            d.setTime(d.getTime() + expires * 1000);
            expires = options.expires = d;
        }
        if (expires && expires.toUTCString) {
            options.expires = expires.toUTCString();
        }

        value = encodeURIComponent(value);

        var updatedCookie = name + "=" + value;

        for (var propName in options) {
            updatedCookie += "; " + propName;
            var propValue = options[propName];
            if (propValue !== true) {
                updatedCookie += "=" + propValue;
            }
        }

        document.cookie = updatedCookie;
    }

    var $debugBar = lvjq1('#lv_debug_bar');
    if (localStorage.getItem('lv_minimized')==1) {
        if (lvjq1('ul#lv_errors').length == 0) $debugBar.addClass('lv_minimize');
    }
    lvjq1('#lv_toggle').click(function () {
        $debugBar.toggleClass('lv_minimize');
        localStorage.setItem('lv_minimized',($debugBar.hasClass('lv_minimize') ? 1 : 0));
    });

    var $landing = lvjq1('#lv_landing');
    var landing = getCookie('lv_landing');
    if (landing) $landing.val(landing);
    $landing.change(function () {
        setCookie('lv_landing', lvjq1(this).val(), {expire: 60 * 60 * 24 * 30 * 365});
        window.location.href = '/';
    });
    $landing.click(function(){
        if (lvjq1(this).find('option').size()==1) {
            setCookie('lv_landing', lvjq1(this).val(), {expire: 60 * 60 * 24 * 30 * 365});
            window.location.href = '/';
        }
    });
});