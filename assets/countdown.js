window.leadvertex = {};
if (!window.leadvertex.seconds) window.leadvertex.seconds = 300;
window.leadvertex.countdownWrapDigits = function(digit) {
    if (digit < 10)  digit = "0"+digit;
    var wrap1 = '<span class="lv_countdown_digit">'+digit.toString().substr(0,1)+'</span>';
    var wrap2 = '<span class="lv_countdown_digit">'+digit.toString().substr(1,1)+'</span>';
    return wrap1+wrap2;
}
window.leadvertex.countdown = function () {
    var countdown = localStorage.getItem('lv_countdown');
    if (countdown === null ) countdown = window.leadvertex.seconds;

    var sec_num = parseInt(countdown, 10);
    var days    = Math.floor(sec_num / 86400);
    var hours   = Math.floor(sec_num / 3600 - days*24);
    var minutes = Math.floor((sec_num - (hours * 3600) - days*86400) / 60);
    var seconds = sec_num - (hours * 3600)  - days*86400 - (minutes * 60);

    lvjq1('.lv_countdown_days').html(leadvertex.countdownWrapDigits(days));
    lvjq1('.lv_countdown_hours').html(leadvertex.countdownWrapDigits(hours));
    lvjq1('.lv_countdown_minutes').html(leadvertex.countdownWrapDigits(minutes));
    lvjq1('.lv_countdown_seconds').html(leadvertex.countdownWrapDigits(seconds));

    if (countdown>0) localStorage.setItem('lv_countdown',countdown - 1);
    else localStorage.setItem('lv_countdown',window.leadvertex.seconds);
}

lvjq1(document).ready(function(){
    leadvertex.countdown();
    setInterval(function(){leadvertex.countdown()},1000);
});