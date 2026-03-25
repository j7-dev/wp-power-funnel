<?php
/**
 * Power Funnel 整合測試引導檔案。
 *
 * 載入順序（不可更改）：
 * 1. Composer autoloader
 * 2. 解析 WP_TESTS_DIR 路徑
 * 3. 確認 WP 測試套件檔案存在
 * 4. 定義 WP_TESTS_PHPUNIT_POLYFILLS_PATH
 * 5. 載入 WP 測試函式（functions.php）
 * 6. 透過 muplugins_loaded hook 載入外掛
 * 7. 載入 WP 測試 bootstrap（bootstrap.php）
 */

declare(strict_types=1);

// 載入 Composer autoloader。
require_once dirname(__DIR__) . '/vendor/autoload.php';

// 若 Action Scheduler 函式不存在，提供 stub 以避免測試環境中的錯誤。
// Compatibility.php 使用這些函式進行版本升級排程，在測試中不需要實際執行。
if (!function_exists('as_enqueue_async_action')) {
    /**
     * Action Scheduler stub：測試環境中不實際排程。
     *
     * @param string  $hook    Hook 名稱
     * @param array   $args    參數
     * @param string  $group   群組
     * @param bool    $unique  是否唯一
     * @return int 虛擬 action ID
     */
    function as_enqueue_async_action(string $hook, array $args = [], string $group = '', bool $unique = false): int {
        return 0;
    }
}

if (!function_exists('as_schedule_single_action')) {
    /**
     * Action Scheduler stub：測試環境中不實際排程。
     *
     * @param int     $timestamp Unix timestamp
     * @param string  $hook      Hook 名稱
     * @param array   $args      參數
     * @param string  $group     群組
     * @param bool    $unique    是否唯一
     * @return int 虛擬 action ID
     */
    function as_schedule_single_action(int $timestamp, string $hook, array $args = [], string $group = '', bool $unique = false): int {
        return 0;
    }
}

if (!function_exists('as_next_scheduled_action')) {
    /**
     * Action Scheduler stub：回傳 false 表示無排程。
     *
     * @param string $hook  Hook 名稱
     * @param array  $args  參數
     * @param string $group 群組
     * @return bool|int
     */
    function as_next_scheduled_action(string $hook, array $args = [], string $group = ''): bool|int {
        return false;
    }
}

// 若 WooCommerce 函式不存在，提供 stub 以避免測試環境中的錯誤。
// RegistrationDTO 使用 wc_string_to_bool() 解析 auto_approved meta，
// 但測試環境中 WooCommerce 並未安裝。
if (!function_exists('wc_string_to_bool')) {
    /**
     * WooCommerce stub：將字串轉換為布林值。
     * 複製 WooCommerce 的 wc_string_to_bool() 實作。
     *
     * @param string|bool $value 要轉換的值
     * @return bool
     */
    function wc_string_to_bool( $value ): bool {
        return is_bool( $value ) ? $value : ( 'yes' === strtolower( $value ) || 1 === $value || 'true' === strtolower( (string) $value ) || '1' === (string) $value );
    }
}

// 取得 WP 測試路徑。
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// 確認 WP 測試套件存在。
if (!file_exists("{$_tests_dir}/includes/functions.php")) {
    echo "找不到 {$_tests_dir}/includes/functions.php\n";
    exit(1);
}

// 設定 Polyfills 路徑。
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills');

// 載入 WP 測試函式。
require_once "{$_tests_dir}/includes/functions.php";

/**
 * 在 WP 載入時啟用外掛（依賴順序：先載入 powerhouse，再載入 power-funnel）。
 */
function _manually_load_power_funnel_plugin(): void {
    // 先載入 powerhouse（提供 J7\WpUtils 類別），入口為 plugin.php
    $powerhouse_path = WP_CONTENT_DIR . '/plugins/powerhouse/plugin.php';
    if (file_exists($powerhouse_path)) {
        require_once $powerhouse_path;
    }

    // 再載入 power-funnel
    require dirname(__DIR__) . '/plugin.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_power_funnel_plugin');

// 啟動 WP 測試套件。
require "{$_tests_dir}/includes/bootstrap.php";
