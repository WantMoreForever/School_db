<?php
/**
 * admin/syslog.php
 * 系统日志页面：展示后台操作记录并做基础可视化映射。
 */
require 'common.php';
$pdo = app_require_pdo();
admin_auth();

$logs = admin_fetch_recent_system_logs($pdo);
?>
<?php $page_title = '系统日志 - 管理后台'; require 'layout_head.php'; ?>

<div class="admin-page">
    <section class="admin-page-header">
        <div>
            <h1 class="admin-page-title">系统日志</h1>

        </div>
    </section>

    <section class="admin-section-card admin-table-card">
        <div class="admin-section-head">
            <div>
                <h2 class="admin-section-title">最新日志</h2>
                <p class="admin-section-meta">最近 <?= count($logs) ?> 条操作记录</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>时间</th>
                        <th>操作人</th>
                        <th>动作描述</th>
                        <th>受影响数据表模块</th>
                        <th>模块记录ID</th>
                    </tr>
                </thead>
                <tbody id="logTableBody"></tbody>
            </table>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 将后端取出的原始数据传入前端，由前端处理并显示
    const logsData = <?= json_encode($logs, JSON_UNESCAPED_UNICODE) ?>;
    
    // 数据字典：将英文表名映射为中文
    const tableMap = {
        'user': '系统用户',
        'student': '学生',
        'teacher': '教师',
        'course': '课程',
        'section': '开课班级',
        'takes': '选课记录',
        'department': '院系',
        'major': '专业',
        'classroom': '教室'
    };

    // 数据字典：用户角色转换
    const roleMap = {
        'admin': '管理员',
        'teacher': '教师',
        'student': '学生',
        'login': '登录'
    };

    const tbody = document.getElementById('logTableBody');
    
    if (!logsData || logsData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">暂无可显示的系统日志记录</td></tr>';
        return;
    }

    // 遍历数据进行前端组装与美化
    logsData.forEach(log => {
        const tr = document.createElement('tr');
        
        // 1. 时间解析与灰度微缩显示
        const timeStr = log.created_at || '-';
        const timeHtml = `<span class="text-muted"><small>${timeStr}</small></span>`;
        
        // 2. 操作人组合显示 (带角色标签)
        let operatorHtml = '<span class="text-muted">系统 / 未知</span>';
        if (log.user_name) {
            let roleClass = 'bg-secondary';
            if (log.user_role === 'admin') roleClass = 'bg-danger';
            else if (log.user_role === 'teacher') roleClass = 'bg-warning text-dark';
            else if (log.user_role === 'student') roleClass = 'bg-info text-dark';
            else if (log.user_role === 'login') roleClass = 'bg-primary';

            // 如果没有 role 信息则不显示徽章
            const roleName = roleMap[log.user_role] || log.user_role || '';
            const badge = roleName ? `<span class="badge ${roleClass} ms-1">${roleName}</span>` : '';
            operatorHtml = `<strong>${log.user_name}</strong>${badge}<br><small class="text-muted">UID: ${log.user_id}</small>`;
        } else if (log.user_id) {
             operatorHtml = `<small class="text-muted">UID: ${log.user_id}</small>`;
        }
        
        // 3. 动作：如果包含成功/失败采用不同颜色高亮
        let action = log.action || '';
        if (action.includes('成功') || action.toUpperCase().includes('SUCCESS')) {
            action = `<span class="text-success fw-bold">${action}</span>`;
        } else if (action.includes('失败') || action.includes('异常') || action.includes('错误') || action.toUpperCase().includes('FAIL')) {
            action = `<span class="text-danger fw-bold">${action}</span>`;
        }
        
        // 4. 数据表名本地化映射
        const table = log.target_table || '';
        const mappedTable = tableMap[table] ? `${tableMap[table]} <small class="text-muted">(${table})</small>` : (table || '-');
        
        // 5. Target ID 处理
        const targetId = log.target_id || '-';
        
        // 组装最终行
        tr.innerHTML = `
            <td>${timeHtml}</td>
            <td>${operatorHtml}</td>
            <td>${action}</td>
            <td>${mappedTable}</td>
            <td>${targetId}</td>
        `;
        tbody.appendChild(tr);
    });
});
</script>

<?php include 'footer.php'; ?>
