# Admin (管理員)

## 描述
WordPress 後台管理員，具有 `manage_options` 權限。負責建立、編輯、發布與刪除 WorkflowRule。透過 ReactFlow 節點編輯器設計工作流程。

## 關鍵屬性
- 具有 `manage_options` capability
- 透過 `X-WP-Nonce` header 驗證 REST API 權限
- 使用後台 SPA (React + Refine.dev) 操作工作流管理介面
