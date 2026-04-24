# EduManage API 响应契约

`EduManage` 项目 API 采用兼容式统一响应结构。新代码应返回标准 envelope，同时保留旧前端依赖的字段，例如 `ok`、`success`、顶层业务字段、`data` 和 `meta`。

## 标准 JSON Envelope

成功响应：

```json
{
  "ok": true,
  "success": true,
  "code": "OK",
  "message": "操作成功",
  "data": {},
  "meta": {}
}
```

失败响应：

```json
{
  "ok": false,
  "success": false,
  "code": "ERR_VALIDATION",
  "message": "请求参数不正确",
  "error": "请求参数不正确"
}
```

## 兼容规则

- `ok` 与 `success` 必须同时存在，且布尔值保持一致。
- `code` 必须存在；成功固定为 `OK`，失败使用 `docs/error-codes.md` 中的错误码。
- `message` 必须是可展示给用户的 UTF-8 文本。
- `error` 用于失败原因，兼容旧前端可继续读取 `error`。
- 查询列表应优先把业务数据放入 `data`，分页、筛选、统计信息放入 `meta`。
- 为兼容旧页面，可以继续保留旧顶层字段，例如 `student`、`stats`、`announcement`、`targets`、`announcements`。

## 当前公共出口

- 管理后台：`admin/api/shared.php` 的 `admin_api_json_response()`、`admin_api_success_response()`、`admin_api_error_response()`。
- 管理后台导入：`admin/api/import_common.php` 的 `admin_import_json_response()`。
- 学生端：`student/api/helpers.php` 的 `student_api_json_ok()`、`student_api_json_error()`、`student_api_json_send()`。
- 教师端：`teacher/api/config.php` 的 `json_ok()`、`json_err()`、`teacher_json_send()`。

## 前端读取规范

前端应优先使用 `window.AppApi`、`window.StudentApi` 或 `window.TeacherApi`：

- `isSuccess(payload)` 判断接口是否成功。
- `messageOf(payload, fallback)` 获取可展示消息。
- `dataOf(payload, fallback)` 获取标准 `data` 字段并兼容旧响应。

业务代码不应新增只判断 `ok` 或只判断 `success` 的分支。
