# System (系統)

## 描述
Power Funnel 工作流引擎後端系統。負責監聽業務事件、管理觸發點橋接、建立 Workflow 執行實例、逐節點執行、遞迴防護、Action Scheduler 排程。

## 關鍵屬性
- 透過 WordPress Hook 系統（add_action / do_action / apply_filters）協調各元件
- 使用 Action Scheduler 處理延遲執行與時間型觸發
- RecursionGuard 靜態深度計數器防護無限遞迴（MAX_DEPTH=3）
- 所有 Workflow / WorkflowRule 以 WordPress CPT（Custom Post Type）儲存
