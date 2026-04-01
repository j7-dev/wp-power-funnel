# 工作流引擎 — 設計原則

## 最高指導原則：Serializable Context Callable

### 核心理念

> 任何業務事件的 context 都必須以「可序列化的 callable + params」形式儲存。
> 儲存的不是「資料」，而是「取得資料的方法」。

工作流引擎的 context 傳遞機制遵循**延遲求值（Deferred Evaluation）**模式。觸發事件時，不將業務資料快照存入 DB，而是儲存一組「如何取得資料」的方法引用。每個節點執行時才即時呼叫取得最新值。

### 資料結構

```php
/** @var array{callable: string|string[], params: array<int, scalar|array>} */
$context_callable_set = [
    'callable' => [TriggerPointService::class, 'resolve_registration_context'],
    'params'   => [ $post_id ],
];
```

- **callable**：`string`（全域函數名）或 `string[]`（`[ClassName::class, 'methodName']`）
- **params**：純值陣列（int / string / float / bool / array），每個元素都必須可被 `serialize()`

### 強制規則

| # | 規則 | 原因 |
|---|------|------|
| 1 | callable 必須為 `string` 或 `string[]` | PHP Closure 無法被 `serialize()`，會在 DB 持久化時靜默丟失 |
| 2 | 禁止使用 Closure（anonymous function） | WaitNode 恢復執行時從 `wp_postmeta` 反序列化，Closure 會導致 `is_callable()` 回傳 false，context 變成空陣列 |
| 3 | params 禁止包含物件 | 物件序列化脆弱（class 改名、屬性變更都會破壞反序列化），只存 ID 等純值 |
| 4 | Context 在節點執行時才求值 | 確保 WaitNode 延遲數小時/數天後仍取得最新資料，而非觸發時的快照 |
| 5 | callable 方法必須為 public static | 確保在任何 context 下都能被 `call_user_func_array()` 呼叫 |

### 正確範例

```php
// ✅ 靜態方法引用（推薦）
[
    'callable' => [TriggerPointService::class, 'resolve_registration_context'],
    'params'   => [ 42 ],
]

// ✅ 全域函數名
[
    'callable' => 'my_plugin_resolve_context',
    'params'   => [ 'order_123' ],
]
```

### 禁止範例

```php
// ❌ Closure — 無法 serialize
[
    'callable' => static function ( int $id ): array { return [...]; },
    'params'   => [ 42 ],
]

// ❌ 物件實例 — 序列化脆弱
[
    'callable' => [$serviceInstance, 'resolve'],
    'params'   => [ 42 ],
]

// ❌ 直接儲存完整 context — 會過期
[
    'email' => 'user@example.com',
    'name'  => 'Alice',
]
```

### 生命週期

```
業務事件發生
    │
    ▼
TriggerPointService 監聽 → 組裝 context_callable_set
    │                        callable: [Class, 'method']
    │                        params:   [$id]
    ▼
do_action('pf/trigger/xxx', $context_callable_set)
    │
    ▼
WorkflowRuleDTO::register() 攔截 → Repository::create_from()
    │                                  ↓
    │                        wp_insert_post(meta_input: [
    │                            'context_callable_set' => $context_callable_set
    │                        ])
    │                                  ↓
    │                        WordPress serialize() 存入 wp_postmeta ← 此處 Closure 會壞掉
    ▼
Workflow 逐節點執行
    │
    ├─ 節點 A 執行 → call_user_func_array(callable, params) → 取得最新 context
    ├─ WaitNode → Action Scheduler 排程 → 暫停（可能數小時/數天）
    ├─ Action Scheduler 恢復 → get_post_meta() → unserialize() → 取回 callable + params
    └─ 節點 B 執行 → call_user_func_array(callable, params) → 取得最新 context（非快照）
```

### resolve 方法命名慣例

所有 context resolver 遵循 `resolve_{domain}_context()` 命名，置於對應的 Service class 中作為 `public static` 方法：

| Service | resolve 方法 | context keys |
|---------|-------------|-------------|
| `TriggerPointService` | `resolve_registration_context(int $post_id)` | registration_id, identity_id, identity_provider, activity_id, promo_link_id |
| `TriggerPointService` | `resolve_line_context(string $line_user_id, string $event_type, string $message_text)` | line_user_id, event_type, message_text |
| `TriggerPointService` | `resolve_workflow_context(string $workflow_id)` | workflow_id, workflow_rule_id, trigger_point |
| `TriggerPointService` | `resolve_user_tagged_context(string $user_id, string $tag_name)` | user_id, tag_name |
| `ActivitySchedulerService` | `resolve_activity_started_context(string $activity_id)` | activity_id, event_type |
| `ActivitySchedulerService` | `resolve_activity_before_start_context(string $activity_id, string $workflow_rule_id)` | activity_id, workflow_rule_id, event_type |
