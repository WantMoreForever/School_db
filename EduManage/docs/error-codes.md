# EduManage 错误码规范

错误码用于机器判断，`message` 用于页面展示。所有错误响应都应同时包含 `ok: false`、`success: false`、`code`、`message` 和 `error`。

| Code | HTTP | 含义 | 常见场景 |
| --- | ---: | --- | --- |
| `OK` | 200 | 请求成功 | 新增、编辑、删除、查询成功 |
| `ERR_VALIDATION` | 400 | 参数或业务校验失败 | 缺少 ID、字段格式错误、非法操作 |
| `ERR_UNAUTHENTICATED` | 401 | 未登录 | session 失效、未通过登录入口访问 |
| `ERR_FORBIDDEN` | 403 | 无权限 | 学生访问后台 API、教师访问非本人班级 |
| `ERR_NOT_FOUND` | 404 | 资源不存在 | 公告、用户、课程不存在 |
| `ERR_CONFLICT` | 409 | 状态冲突 | 时间冲突、唯一键冲突、容量已满 |
| `ERR_CSRF` | 419 | CSRF 校验失败 | 写操作缺少或提交了无效 token |
| `ERR_DB` | 500 | 数据库异常 | 数据库连接失败、存储过程异常 |
| `ERR_SERVER` | 500 | 服务端异常 | 未捕获异常、JSON 编码失败、致命错误 |

## 使用建议

- 表单字段错误使用 `ERR_VALIDATION`。
- 权限不足使用 `ERR_FORBIDDEN`，未登录使用 `ERR_UNAUTHENTICATED`，不要混用。
- 可以在响应中额外保留旧字段，例如 `error: "unauthenticated"`，但 `code` 仍应使用规范错误码。
- PHP helper 会按 HTTP 状态自动补默认错误码；业务代码只在需要更准确语义时显式传入 `code`。
