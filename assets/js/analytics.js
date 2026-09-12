(function () {
    'use strict';

    // リンクカードのクリックを計測する
    // 遷移は妨げない（preventDefault しない）。送信は navigator.sendBeacon で行うため
    // ページ離脱後も届き、setTimeout + window.open によるポップアップブロックも起きない
    document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('.kslc-link') : null;
        if (!link) {
            return;
        }

        if (typeof kslc_ajax === 'undefined') {
            return;
        }

        var titleEl = link.querySelector('.kslc-title');
        var data = new FormData();
        data.append('action', 'kslc_track_click');
        data.append('nonce', kslc_ajax.nonce);
        data.append('url', link.getAttribute('href') || '');
        data.append('title', titleEl ? titleEl.textContent : '');
        data.append('page_url', window.location.href);

        if (navigator.sendBeacon) {
            navigator.sendBeacon(kslc_ajax.ajax_url, data);
            return;
        }

        // sendBeacon 非対応ブラウザ向け（keepalive で離脱後も送信を継続）
        if (window.fetch) {
            fetch(kslc_ajax.ajax_url, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                keepalive: true
            }).catch(function () {});
        }
    }, true);
})();
