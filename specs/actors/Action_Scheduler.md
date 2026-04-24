# Action Scheduler

## 描述
WordPress 背景排程引擎（Action Scheduler 套件）。用於處理工作流中的延遲節點（WaitNode），在指定時間戳記到達時觸發 `power_funnel/workflow/running` action 以恢復工作流執行。

## 關鍵屬性
- WordPress 背景排程系統
- 使用 `as_schedule_single_action` 排程單次任務
- 使用 `as_enqueue_async_action` 排程非同步任務
