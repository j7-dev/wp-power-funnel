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

/**
 * Action Scheduler 測試用排程記錄器。
 *
 * 當 Action Scheduler 真實函式不可用時，此記錄器模擬排程的追蹤行為，
 * 允許 as_has_scheduled_action()、as_get_scheduled_actions()、
 * as_unschedule_all_actions() 等函式正常運作於測試環境。
 */
class ActionSchedulerTestRegistry {
    /** @var array<int, array{hook: string, args: array<mixed>, group: string, timestamp: int}> 已排程的 actions */
    private static array $scheduled = [];
    /** @var int 下一個 action ID 的計數器 */
    private static int $next_id = 1;

    /**
     * 新增排程
     *
     * @param int    $timestamp Unix timestamp
     * @param string $hook      Hook 名稱
     * @param array<mixed>  $args      參數
     * @param string $group     群組
     * @return int action ID
     */
    public static function schedule(int $timestamp, string $hook, array $args = [], string $group = ''): int {
        $id = self::$next_id++;
        self::$scheduled[$id] = [
            'hook'      => $hook,
            'args'      => $args,
            'group'     => $group,
            'timestamp' => $timestamp,
        ];
        return $id;
    }

    /**
     * 判斷是否有符合條件的排程
     *
     * @param string       $hook  Hook 名稱
     * @param array<mixed>|null $args  參數（null 表示不過濾）
     * @param string       $group 群組
     * @return bool
     */
    public static function has(string $hook, ?array $args = null, string $group = ''): bool {
        foreach (self::$scheduled as $action) {
            if ($action['hook'] !== $hook) {
                continue;
            }
            if ($args !== null && $action['args'] !== $args) {
                continue;
            }
            if ($group !== '' && $action['group'] !== $group) {
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * 取得符合條件的所有排程 IDs
     *
     * @param array<string, mixed> $query 查詢條件
     * @param string               $return_format 回傳格式（'ids' 或 'objects'）
     * @return array<int>
     */
    public static function get(array $query = [], string $return_format = 'ids'): array {
        $hook   = $query['hook'] ?? null;
        $result = [];
        foreach (self::$scheduled as $id => $action) {
            if ($hook !== null && $action['hook'] !== $hook) {
                continue;
            }
            $result[] = $id;
        }
        return $result;
    }

    /**
     * 取消所有符合 hook 的排程
     *
     * @param string       $hook  Hook 名稱
     * @param array<mixed>|null $args  參數（null 表示取消所有）
     * @param string       $group 群組
     * @return void
     */
    public static function unschedule_all(string $hook, ?array $args = null, string $group = ''): void {
        foreach (self::$scheduled as $id => $action) {
            if ($action['hook'] !== $hook) {
                continue;
            }
            if ($args !== null && $action['args'] !== $args) {
                continue;
            }
            if ($group !== '' && $action['group'] !== $group) {
                continue;
            }
            unset(self::$scheduled[$id]);
        }
    }

    /** 清除所有排程（測試 tear_down 使用） */
    public static function clear(): void {
        self::$scheduled = [];
    }
}

if (!function_exists('as_enqueue_async_action')) {
    /**
     * Action Scheduler stub：測試環境中不實際排程。
     *
     * @param string  $hook    Hook 名稱
     * @param array<mixed>   $args    參數
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
     * Action Scheduler stub：測試環境中追蹤排程但不實際執行。
     *
     * @param int     $timestamp Unix timestamp
     * @param string  $hook      Hook 名稱
     * @param array<mixed>   $args      參數
     * @param string  $group     群組
     * @param bool    $unique    是否唯一
     * @return int 虛擬 action ID（非零代表成功）
     */
    function as_schedule_single_action(int $timestamp, string $hook, array $args = [], string $group = '', bool $unique = false): int {
        // 回傳非零 ID 代表「排程成功」，模擬 Action Scheduler 成功建立排程並回傳 action ID。
        // WaitNode / WaitUntilNode / TimeWindowNode 依此判斷是否排程成功（非零即成功）。
        // 同時記錄至 ActionSchedulerTestRegistry，供 as_has_scheduled_action() 查詢。
        return ActionSchedulerTestRegistry::schedule($timestamp, $hook, $args, $group);
    }
}

if (!function_exists('as_next_scheduled_action')) {
    /**
     * Action Scheduler stub：回傳 false 表示無排程。
     *
     * @param string       $hook  Hook 名稱
     * @param array<mixed>|null $args  參數
     * @param string       $group 群組
     * @return bool|int
     */
    function as_next_scheduled_action(string $hook, ?array $args = null, string $group = ''): bool|int {
        return ActionSchedulerTestRegistry::has($hook, $args, $group) ? 1 : false;
    }
}

if (!function_exists('as_has_scheduled_action')) {
    /**
     * Action Scheduler stub：判斷是否有符合條件的排程。
     *
     * @param string       $hook  Hook 名稱
     * @param array<mixed>|null $args  參數（null 表示不過濾）
     * @param string       $group 群組
     * @return bool
     */
    function as_has_scheduled_action(string $hook, ?array $args = null, string $group = ''): bool {
        return ActionSchedulerTestRegistry::has($hook, $args, $group);
    }
}

if (!function_exists('as_get_scheduled_actions')) {
    /**
     * Action Scheduler stub：取得符合條件的排程。
     *
     * @param array<string, mixed> $args          查詢條件
     * @param string               $return_format 回傳格式
     * @return array<int>
     */
    function as_get_scheduled_actions(array $args = [], string $return_format = 'ids'): array {
        return ActionSchedulerTestRegistry::get($args, $return_format);
    }
}

if (!function_exists('as_unschedule_all_actions')) {
    /**
     * Action Scheduler stub：取消所有符合 hook 的排程。
     *
     * @param string       $hook  Hook 名稱
     * @param array<mixed>|null $args  參數（null 表示取消所有）
     * @param string       $group 群組
     * @return void
     */
    function as_unschedule_all_actions(string $hook, ?array $args = null, string $group = ''): void {
        ActionSchedulerTestRegistry::unschedule_all($hook, $args, $group);
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

// 若 Powerhouse FormFieldDTO 不存在，提供最小 stub 以支援 NodeDefinition 測試。
// powerhouse 外掛的 autoloader 在測試環境中可能無法正確載入此類別。
if (!class_exists('J7\Powerhouse\Contracts\DTOs\FormFieldDTO')) {
    // @phpcs:disable
    /**
     * FormFieldDTO 最小 stub。
     * 僅實作 NodeDefinition constructor 所需的屬性。
     */
    class FormFieldDTO_Stub {
        /** @var string 欄位名稱 */
        public string $name = '';
        /** @var string 欄位標籤 */
        public string $label = '';
        /** @var string 欄位類型 */
        public string $type = 'text';
        /** @var bool 是否必填 */
        public bool $required = false;
        /** @var string 佔位文字 */
        public string $placeholder = '';
        /** @var string 描述 */
        public string $description = '';
        /** @var int 排序 */
        public int $sort = 0;
        /** @var array<int, array{value: string, label: string}> 選項 */
        public array $options = [];

        /**
         * Constructor
         *
         * @param array<string, mixed> $args 參數
         */
        public function __construct( array $args = [] ) {
            foreach ($args as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }

        /**
         * 轉為陣列
         *
         * @return array<string, mixed>
         */
        public function to_array(): array {
            return [
                'name'        => $this->name,
                'label'       => $this->label,
                'type'        => $this->type,
                'required'    => $this->required,
                'placeholder' => $this->placeholder,
                'description' => $this->description,
                'sort'        => $this->sort,
                'options'     => $this->options,
            ];
        }
    }
    class_alias('FormFieldDTO_Stub', 'J7\Powerhouse\Contracts\DTOs\FormFieldDTO');
    // @phpcs:enable
}

// 若 WooCommerce WC_Order 不存在，提供最小 stub 以支援 ORDER_COMPLETED 觸發點測試。
// TriggerPointService::resolve_order_context() 和 ParamHelper::replace() 呼叫 wc_get_order()
// 並使用 WC_Order 的 getter 方法取得訂單欄位。
if (!class_exists('WC_Order')) {
    /**
     * WooCommerce WC_Order 最小 stub。
     *
     * 僅實作 resolve_order_context() 與 ParamHelper 所需的 getter 方法。
     * 測試時透過 WC_Order_Stub_Registry 註冊假訂單資料。
     */
    class WC_Order {
        /** @var array<string, mixed> 訂單資料 */
        private array $data;
        /** @var array<int, WC_Order_Item_Stub> 訂單商品項目 */
        private array $items;

        /**
         * Constructor
         *
         * @param array<string, mixed> $data  訂單資料
         * @param array<int, WC_Order_Item_Stub> $items 商品項目
         */
        public function __construct( array $data = [], array $items = [] ) {
            $this->data  = $data;
            $this->items = $items;
        }

        /** @return int 訂單 ID */
        public function get_id(): int { return (int) ( $this->data['id'] ?? 0 ); }
        /** @return string 訂單總金額 */
        public function get_total(): string { return (string) ( $this->data['total'] ?? '0' ); }
        /** @return string 帳單 Email */
        public function get_billing_email(): string { return (string) ( $this->data['billing_email'] ?? '' ); }
        /** @return int 客戶 ID */
        public function get_customer_id(): int { return (int) ( $this->data['customer_id'] ?? 0 ); }
        /** @return string 付款方式 */
        public function get_payment_method(): string { return (string) ( $this->data['payment_method'] ?? '' ); }
        /** @return string 帳單電話 */
        public function get_billing_phone(): string { return (string) ( $this->data['billing_phone'] ?? '' ); }
        /** @return string 格式化的配送地址 */
        public function get_formatted_shipping_address(): string { return (string) ( $this->data['shipping_address'] ?? '' ); }
        /** @return string 格式化的帳單地址 */
        public function get_formatted_billing_address(): string { return (string) ( $this->data['billing_address'] ?? '' ); }
        /** @return string 訂單狀態（不含 wc- 前綴） */
        public function get_status(): string { return (string) ( $this->data['status'] ?? '' ); }
        /** @return array<int, WC_Order_Item_Stub> 訂單商品項目 */
        public function get_items(): array { return $this->items; }
        /** @return \DateTimeImmutable|null 訂單建立日期 */
        public function get_date_created(): ?\DateTimeImmutable {
            $date = $this->data['date_created'] ?? null;
            if ($date instanceof \DateTimeImmutable) { return $date; }
            if (is_string($date) && $date !== '') {
                try { return new \DateTimeImmutable($date); } catch (\Exception $e) { return null; }
            }
            return null;
        }
    }

    /**
     * WC_Order_Item 最小 stub。
     */
    class WC_Order_Item_Stub {
        /** @var string 商品名稱 */
        private string $name;
        /** @var int 數量 */
        private int $quantity;

        /**
         * Constructor
         *
         * @param string $name     商品名稱
         * @param int    $quantity 數量
         */
        public function __construct( string $name, int $quantity ) {
            $this->name     = $name;
            $this->quantity = $quantity;
        }
        /** @return string 商品名稱 */
        public function get_name(): string { return $this->name; }
        /** @return int 數量 */
        public function get_quantity(): int { return $this->quantity; }
    }
}

// WooCommerce wc_get_order() stub：從測試註冊表取得假訂單。
// 測試中使用 WC_Order_Stub_Registry::register() 註冊假訂單。
if (!class_exists('WC_Order_Stub_Registry')) {
    /**
     * WC_Order stub 註冊表。
     * 測試可透過此類別註冊假的 WC_Order 物件，供 wc_get_order() 回傳。
     */
    class WC_Order_Stub_Registry {
        /** @var array<int, WC_Order> 已註冊的假訂單 */
        private static array $orders = [];

        /**
         * 註冊假訂單
         *
         * @param int      $order_id 訂單 ID
         * @param WC_Order $order    假訂單物件
         */
        public static function register( int $order_id, WC_Order $order ): void {
            self::$orders[ $order_id ] = $order;
        }

        /**
         * 取得假訂單
         *
         * @param int $order_id 訂單 ID
         * @return WC_Order|false
         */
        public static function get( int $order_id ): WC_Order|false {
            return self::$orders[ $order_id ] ?? false;
        }

        /** 清除所有已註冊的假訂單 */
        public static function clear(): void {
            self::$orders = [];
        }
    }
}

if (!function_exists('wc_get_order')) {
    /**
     * WooCommerce stub：從 stub 註冊表取得訂單。
     *
     * @param int|string $order_id 訂單 ID
     * @return WC_Order|false
     */
    function wc_get_order( $order_id ): WC_Order|false {
        return WC_Order_Stub_Registry::get( (int) $order_id );
    }
}

// 若 WC_Subscription 不存在，提供最小 stub 以支援訂閱觸發點測試。
// TriggerPointService 的訂閱 handlers 使用 instanceof WC_Subscription 進行型別判斷，
// resolve_subscription_context() 也使用 WC_Subscription 的 getter 方法。
if (!class_exists('WC_Subscription')) {
    /**
     * WooCommerce WC_Subscription 最小 stub。
     *
     * 僅實作 resolve_subscription_context() 與 handler 所需的 getter 方法。
     * 測試時透過 WC_Subscription_Stub_Registry 註冊假訂閱資料。
     */
    class WC_Subscription {
        /** @var array<string, mixed> 訂閱資料 */
        private array $data;

        /**
         * Constructor
         *
         * @param array<string, mixed> $data 訂閱資料
         */
        public function __construct( array $data = [] ) {
            $this->data = $data;
        }

        /** @return int 訂閱 ID */
        public function get_id(): int { return (int) ( $this->data['id'] ?? 0 ); }
        /** @return string 訂閱狀態（不含 wc- 前綴） */
        public function get_status(): string { return (string) ( $this->data['status'] ?? '' ); }
        /** @return int 客戶 ID */
        public function get_customer_id(): int { return (int) ( $this->data['customer_id'] ?? 0 ); }
        /** @return string 帳單 Email */
        public function get_billing_email(): string { return (string) ( $this->data['billing_email'] ?? '' ); }
        /** @return string 帳單名字 */
        public function get_billing_first_name(): string { return (string) ( $this->data['billing_first_name'] ?? '' ); }
        /** @return string 帳單姓氏 */
        public function get_billing_last_name(): string { return (string) ( $this->data['billing_last_name'] ?? '' ); }
        /** @return string 訂閱總金額 */
        public function get_total(): string { return (string) ( $this->data['total'] ?? '0' ); }
        /** @return string 付款方式 */
        public function get_payment_method(): string { return (string) ( $this->data['payment_method'] ?? '' ); }
    }
}

// WC_Subscription stub 註冊表。
if (!class_exists('WC_Subscription_Stub_Registry')) {
    /**
     * WC_Subscription stub 註冊表。
     * 測試可透過此類別註冊假的 WC_Subscription 物件，供 wcs_get_subscription() 回傳。
     */
    class WC_Subscription_Stub_Registry {
        /** @var array<int, WC_Subscription> 已註冊的假訂閱 */
        private static array $subscriptions = [];

        /**
         * 註冊假訂閱
         *
         * @param int             $subscription_id 訂閱 ID
         * @param WC_Subscription $subscription    假訂閱物件
         */
        public static function register( int $subscription_id, WC_Subscription $subscription ): void {
            self::$subscriptions[ $subscription_id ] = $subscription;
        }

        /**
         * 取得假訂閱
         *
         * @param int $subscription_id 訂閱 ID
         * @return WC_Subscription|false
         */
        public static function get( int $subscription_id ): WC_Subscription|false {
            return self::$subscriptions[ $subscription_id ] ?? false;
        }

        /** 清除所有已註冊的假訂閱 */
        public static function clear(): void {
            self::$subscriptions = [];
        }
    }
}

if (!function_exists('wcs_get_subscription')) {
    /**
     * WooCommerce Subscriptions stub：從 stub 註冊表取得訂閱。
     *
     * @param int|string $subscription_id 訂閱 ID
     * @return WC_Subscription|false
     */
    function wcs_get_subscription( $subscription_id ): WC_Subscription|false {
        return WC_Subscription_Stub_Registry::get( (int) $subscription_id );
    }
}

// 設定環境類型為 local，使 DTO 在驗證失敗時拋出例外（而非靜默記錄）。
// 這讓 RecordNodeExecutedAtTest::test_缺少node_id時記錄失敗 等測試能正確捕捉到驗證例外。
if (!defined('WP_ENVIRONMENT_TYPE')) {
    define('WP_ENVIRONMENT_TYPE', 'local');
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
