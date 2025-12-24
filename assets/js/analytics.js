(function ($) {
    'use strict';

    // DOMが読み込まれた後に実行
    $(document).ready(function () {
        // リンクカードのクリックを追跡
        $('.kslc-link').on('click', function (e) {
            var $link = $(this);
            var url = $link.attr('href');
            var title = $link.find('.kslc-title').text();
            var pageUrl = window.location.href;

            // 内部リンクの場合は即座に遷移
            var isExternal = $link.attr('target') === '_blank';

            // kslc_ajax が定義されているかチェック
            if (typeof kslc_ajax === 'undefined') {
                console.error('KSLC Analytics: kslc_ajax is not defined!');
                return;
            }

            // AJAX でクリックデータを送信
            $.ajax({
                url: kslc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kslc_track_click',
                    nonce: kslc_ajax.nonce,
                    url: url,
                    title: title,
                    page_url: pageUrl,
                    is_external: isExternal
                },
                timeout: 2000, // 2秒でタイムアウト
                success: function (response) {
                    if (!response.success) {
                        console.error('KSLC Analytics: Server error:', response.data);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('KSLC Analytics: AJAX error', status, error, xhr.responseText);
                }
            });

            // 外部リンクの場合は少し遅延してから遷移
            if (isExternal) {
                e.preventDefault();
                setTimeout(function () {
                    window.open(url, '_blank', 'noopener');
                }, 100);
                return false;
            }
        });
    });

})(jQuery);
