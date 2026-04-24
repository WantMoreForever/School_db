<?php
// API: student/api/announcement.php
// 获取学生可见的公告：课程公告 + 管理员投放的全体/学生/专业公告
require_once __DIR__ . '/helpers.php';
student_api_bootstrap();

require_once __DIR__ . '/../../components/db.php';
$pdo = student_api_require_pdo();

$uid = student_api_require_login();

try {
    // 获取学生所属专业（若有）
    $mstmt = $pdo->prepare('SELECT major_id FROM student WHERE user_id = ? LIMIT 1');
    $mstmt->execute([$uid]);
    $major_id = (int)$mstmt->fetchColumn();
    $major_name = '';
    if ($major_id) {
        try {
            $ms = $pdo->prepare('SELECT major_name FROM major WHERE major_id = ? LIMIT 1');
            $ms->execute([$major_id]);
            $major_name = (string)$ms->fetchColumn();
        } catch (Throwable $e) {
            $major_name = '';
        }
    }
    // 支持搜索与分页参数
    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    // 固定每页最多 10 条
    $per_page = (int)($_GET['per_page'] ?? 10);
    if ($per_page <= 0) $per_page = 10;
    $per_page = min(10, $per_page);
    $offset = ($page - 1) * $per_page;

        // 基本 FROM/JOINS
        $from = "FROM announcement a
            JOIN announcement_target t ON a.announcement_id = t.announcement_id
            JOIN user u ON u.user_id = a.author_user_id
            LEFT JOIN section sec ON t.target_type = 'section' AND sec.section_id = t.target_id
            LEFT JOIN course c ON sec.course_id = c.course_id
            LEFT JOIN takes mk ON mk.section_id = sec.section_id AND mk.student_id = ?";

    // 基本 WHERE 条件：已发布且为可见目标（全体/学生/专业/已选班级）
    $where = "a.status = 'published' AND (
                t.target_type IN ('all','students')
                OR (t.target_type = 'major' AND t.target_id = ?)
                OR (t.target_type = 'section' AND mk.student_id IS NOT NULL)
            )";

    $params = [$uid, $major_id];
    if ($q !== '') {
        $where .= " AND (a.title LIKE ? OR a.content LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    try {
        $sets = function_exists('app_call_multi_result_rows')
            ? app_call_multi_result_rows($pdo, 'sp_project_get_student_visible_announcements', [$uid, $q, $per_page, $offset])
            : [];
        $total = (int)($sets[0][0]['total'] ?? 0);
        $announcements = $sets[1] ?? [];
    } catch (Throwable $e) {
        // 统计总数
        $countSql = "SELECT COUNT(DISTINCT a.announcement_id) " . $from . " WHERE " . $where;
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // 分页查询（包含 targets 与课程信息以便前端显示接收群体）
        // 为兼容 sql_mode=ONLY_FULL_GROUP_BY，使用聚合函数/ANY_VALUE 选择非聚合字段
        $sql = "SELECT a.announcement_id,
            ANY_VALUE(a.title) AS title,
            ANY_VALUE(a.content) AS content,
            ANY_VALUE(a.is_pinned) AS is_pinned,
            ANY_VALUE(a.published_at) AS published_at,
            ANY_VALUE(u.name) AS teacher_name,
            ANY_VALUE(a.created_at) AS created_at,
            GROUP_CONCAT(DISTINCT CONCAT(t.target_type, ':', COALESCE(t.target_id, '')) SEPARATOR ',') AS targets,
            ANY_VALUE(c.name) AS course_name,
            ANY_VALUE(sec.semester) AS semester,
            ANY_VALUE(sec.year) AS year
            " . $from . " WHERE " . $where . " GROUP BY a.announcement_id ORDER BY MAX(a.is_pinned) DESC, MAX(a.published_at) DESC, MAX(a.created_at) DESC LIMIT ? OFFSET ?";
        $execParams = array_merge($params, [$per_page, $offset]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($execParams);
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 构建接收群体可读标签（基于返回的 targets）
    foreach ($announcements as $idx => $row) {
        $targetsStr = $row['targets'] ?? '';
        $label = '全体学生';
        if (!empty($targetsStr)) {
            $parts = array_unique(array_filter(array_map('trim', explode(',', $targetsStr))));
            foreach ($parts as $p) {
                [$tt, $tid] = array_pad(explode(':', $p, 2), 2, '');
                if ($tt === 'section') {
                    if (!empty($row['course_name'])) {
                        $label = '课程：' . $row['course_name'];
                    } else {
                        $label = '班级: ' . ($tid ? ('ID ' . (int)$tid) : '');
                    }
                    break;
                }
                if ($tt === 'major') {
                    $label = '专业';
                    if ((int)$tid === $major_id && $major_name !== '') {
                        $label = '专业：' . $major_name;
                    }
                    break;
                }
                if ($tt === 'students') { $label = '学生'; break; }
                if ($tt === 'all') { $label = '全体学生'; break; }
            }
        }
        $announcements[$idx]['receiver'] = $label;
    }

    $totalPages = $per_page > 0 ? (int)ceil($total / $per_page) : 0;
    // 返回兼容旧前端的 data 字段，同时保留 announcements 字段与 meta
    student_api_json_ok([
        'data' => $announcements,
        'announcements' => $announcements,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $totalPages,
            'q' => $q,
        ],
    ]);
} catch (Exception $e) {
    student_api_json_error('无法获取公告: ' . $e->getMessage());
}
