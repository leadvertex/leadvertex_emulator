$(document).ready(function () {
    $('.sourceCode').each(function (i, e) {
        var html = $(e).html();
        var maxlen = 999999;
        var strings = html.replace('\r', '').split('\n');
        var regexp = /^(\s*)/;
        for (var i = 0; i < strings.length - 1; i++) {
            var match = regexp.exec(strings[i]);
            if (match !== null && strings[i].length > 0) {
                var matchLen = match[0].toString().length;
                if (matchLen < maxlen) maxlen = matchLen;
            }
        }

        var re = new RegExp('([\x20\t]{' + maxlen + '})', "g");
        html = html.replace(re, '');
        html = html.split('[[').join('{{');
        html = html.split(']]').join('}}');
        $(e).html(html);

        try {
            hljs.configure({useBR: false});
            hljs.configure({tabReplace: '    '}); // 4 spaces
            hljs.highlightBlock(e);
        } catch (e) {
        }
    });
});