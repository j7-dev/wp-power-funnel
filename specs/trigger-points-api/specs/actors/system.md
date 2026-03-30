# 系統

## 描述
Power Funnel 後端系統，負責從 ETriggerPoint enum 與 apply_filters hook 收集所有已註冊的觸發點。

## 關鍵屬性
- 從 ETriggerPoint enum 取得預設觸發點
- 透過 apply_filters('power_funnel/workflow_rule/trigger_points') 允許第三方擴充
- 回傳 TriggerPointDTO 陣列（含 hook 與 name）
