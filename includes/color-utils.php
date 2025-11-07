<?php
/**
 * カラーユーティリティ関数
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * カスタムカラーからテーマカラーセットを生成
 * プリセットと完全に同じ配色を生成
 */
function kslc_generate_color_scheme($primary_color) {
    // プリセットテーマに完全一致する色があるか確認
    $themes = KSLC_COLOR_THEMES;
    foreach ($themes as $theme_name => $theme) {
        if (strcasecmp($theme['primary'], $primary_color) === 0) {
            // 完全一致：プリセットと同じ色を返す
            return $theme;
        }
    }
    
    // 一致しない場合：HSLで調整して生成
    $rgb = sscanf($primary_color, "#%02x%02x%02x");
    if (!$rgb) {
        return [
            'primary' => $primary_color,
            'text' => '#000000',
            'meta' => '#424242',
            'bg' => '#ffffff',
            'border' => '#e0e0e0'
        ];
    }
    
    list($h, $s, $l) = kslc_rgb_to_hsl($rgb[0], $rgb[1], $rgb[2]);
    
    // 背景色とボーダー色を生成
    $bg_hsl = [$h, $s * KSLC_COLOR_BG_SATURATION_RATIO, KSLC_COLOR_BG_LIGHTNESS];
    $bg_rgb = kslc_hsl_to_rgb($bg_hsl[0], $bg_hsl[1], $bg_hsl[2]);
    $bg_color = sprintf('#%02x%02x%02x', $bg_rgb[0], $bg_rgb[1], $bg_rgb[2]);
    
    $border_hsl = [$h, $s * KSLC_COLOR_BORDER_SATURATION_RATIO, KSLC_COLOR_BORDER_LIGHTNESS];
    $border_rgb = kslc_hsl_to_rgb($border_hsl[0], $border_hsl[1], $border_hsl[2]);
    $border_color = sprintf('#%02x%02x%02x', $border_rgb[0], $border_rgb[1], $border_rgb[2]);
    
    return [
        'primary' => $primary_color,
        'text' => '#000000',
        'meta' => '#424242',
        'bg' => $bg_color,
        'border' => $border_color
    ];
}

/**
 * RGB to HSL変換
 */
function kslc_rgb_to_hsl($r, $g, $b) {
    $r /= 255;
    $g /= 255;
    $b /= 255;
    
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    
    if ($max == $min) {
        $h = $s = 0;
    } else {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        
        switch ($max) {
            case $r:
                $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
                break;
            case $g:
                $h = (($b - $r) / $d + 2) / 6;
                break;
            case $b:
                $h = (($r - $g) / $d + 4) / 6;
                break;
        }
    }
    
    return [$h, $s, $l];
}

/**
 * HSL to RGB変換
 */
function kslc_hsl_to_rgb($h, $s, $l) {
    if ($s == 0) {
        $r = $g = $b = $l;
    } else {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        
        $r = kslc_hue_to_rgb($p, $q, $h + 1/3);
        $g = kslc_hue_to_rgb($p, $q, $h);
        $b = kslc_hue_to_rgb($p, $q, $h - 1/3);
    }
    
    return [round($r * 255), round($g * 255), round($b * 255)];
}

/**
 * Hue to RGB helper
 */
function kslc_hue_to_rgb($p, $q, $t) {
    if ($t < 0) $t += 1;
    if ($t > 1) $t -= 1;
    if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
    if ($t < 1/2) return $q;
    if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
    return $p;
}