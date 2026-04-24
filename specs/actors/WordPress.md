# WordPress

## 描述
WordPress 核心系統，提供 Custom Post Type（CPT）、wp_options、wp_mail、REST API、Action Scheduler 等基礎設施。Power Funnel 使用 4 個 CPT 儲存業務資料：pf_promo_link、pf_registration、pf_workflow_rule、pf_workflow。

## 關鍵屬性
- 提供 CPT 做為資料儲存層（替代自訂 DB table）
- 提供 transition_post_status hook 管理狀態生命週期
- 提供 wp_mail 發送 Email
- 提供 REST API 基礎設施（register_rest_route）
