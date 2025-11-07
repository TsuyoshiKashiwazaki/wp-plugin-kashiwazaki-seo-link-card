jQuery(document).ready(function($) {
    // カラーピッカーを初期化
    $('.kslc-color-picker').wpColorPicker({
        defaultColor: false,
        change: function(event, ui) {
            // カラーが変更されたときの処理
        },
        clear: function() {
            // クリアボタンが押されたときの処理
        },
        hide: true,
        palettes: false // パレットは設定ファイルから取得するべきなので無効化
    });
    
    // セレクトボックスの変更でカラーピッカーの表示/非表示を切り替え
    $('.kslc-color-select').on('change', function() {
        var $select = $(this);
        var colorWrapperId = $select.attr('id').replace('_color_theme', '_custom_color_wrapper');
        var $wrapper = $('#' + colorWrapperId);
        
        if ($select.val() === 'custom') {
            $wrapper.show();
        } else {
            $wrapper.hide();
        }
    });
});