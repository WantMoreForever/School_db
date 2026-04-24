<?php
/**
 * admin/api/announcement.php
 * 公告接口处理器：处理公告的新增、读取、更新、删除和置顶请求。
 */

if (!in_array($act, ['add_announcement', 'get_announcement', 'update_announcement', 'delete_announcement', 'pin_announcement'], true)) {
    return false;
}

switch ($act) {
    case 'add_announcement':
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $target = $_POST['target'] ?? 'all';
        $majorId = (int) ($_POST['major_id'] ?? 0);
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $status = $_POST['status'] ?? 'published';

        if ($title === '') {
            admin_api_error_response($isAjax, 'announcement.php', '标题不能为空');
        }
        if ($content === '') {
            admin_api_error_response($isAjax, 'announcement.php', '内容不能为空');
        }

        try {
            $pdo->beginTransaction();
            $author = $_SESSION['user_id'] ?? null;
            $stmt = $pdo->prepare('INSERT INTO announcement (author_user_id, title, content, status, is_pinned, published_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())');
            $stmt->execute([$author, $title, $content, $status, $isPinned]);
            $announcementId = (int) $pdo->lastInsertId();

            admin_api_store_announcement_target($pdo, $announcementId, $target, $majorId);
            $pdo->commit();

            if (function_exists('sys_log')) {
                $desc = '发布公告: ' . ($title ?: ('ID ' . $announcementId)) . ' (ID: ' . $announcementId . ')';
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'announcement', $announcementId);
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof InvalidArgumentException || $e instanceof RuntimeException) {
                admin_api_error_response($isAjax, 'announcement.php', $e->getMessage());
            }

            admin_api_error_response($isAjax, 'announcement.php', '发布公告失败', 500);
        }

        admin_api_success_response($isAjax, 'announcement.php?success=1', '发布成功');
        break;

    case 'get_announcement':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            admin_api_error_response($isAjax, 'announcement.php', '缺少公告 ID');
        }

        $stmt = $pdo->prepare('SELECT * FROM announcement WHERE announcement_id = ? LIMIT 1');
        $stmt->execute([$id]);
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$announcement) {
            admin_api_error_response($isAjax, 'announcement.php', '公告不存在', 404);
        }

        $targetStmt = $pdo->prepare('SELECT target_type, target_id FROM announcement_target WHERE announcement_id = ?');
        $targetStmt->execute([$id]);
        $targets = $targetStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($isAjax) {
            admin_api_json_response(true, [
                'message' => '获取成功',
                'announcement' => $announcement,
                'targets' => $targets,
                'data' => [
                    'announcement' => $announcement,
                    'targets' => $targets,
                ],
            ]);
        }

        admin_api_redirect('announcement.php');

    case 'update_announcement':
        $id = (int) ($_POST['announcement_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $target = $_POST['target'] ?? 'all';
        $majorId = (int) ($_POST['major_id'] ?? 0);
        $isPinned = (int) ($_POST['is_pinned'] ?? 0) === 1 ? 1 : 0;
        $status = $_POST['status'] ?? 'published';

        if ($id <= 0) {
            admin_api_error_response($isAjax, 'announcement.php', '缺少公告 ID');
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE announcement SET title = ?, content = ?, status = ?, is_pinned = ?, updated_at = NOW() WHERE announcement_id = ?');
            $stmt->execute([$title, $content, $status, $isPinned, $id]);

            admin_api_store_announcement_target($pdo, $id, $target, $majorId, true);
            $pdo->commit();

            if (function_exists('sys_log')) {
                sys_log($pdo, $_SESSION['user_id'] ?? null, sys_log_build('编辑公告', [
                    'announcement_id' => $id,
                    'title' => $title,
                ]), 'announcement', $id);
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            admin_api_error_response($isAjax, 'announcement.php', '更新失败: ' . $e->getMessage(), 500);
        }

        admin_api_success_response($isAjax, 'announcement.php?success=1', '更新成功');
        break;

    case 'delete_announcement':
        $id = (int) ($_POST['announcement_id'] ?? 0);
        if ($id <= 0) {
            admin_api_error_response($isAjax, 'announcement.php', '缺少公告 ID');
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM announcement_target WHERE announcement_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM announcement WHERE announcement_id = ?')->execute([$id]);
            $pdo->commit();

            if (function_exists('sys_log')) {
                sys_log($pdo, $_SESSION['user_id'] ?? null, sys_log_build('删除公告', [
                    'announcement_id' => $id,
                ]), 'announcement', $id);
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            admin_api_error_response($isAjax, 'announcement.php', '删除失败', 500);
        }

        admin_api_success_response($isAjax, 'announcement.php', '删除成功');
        break;

    case 'pin_announcement':
        $id = (int) ($_POST['announcement_id'] ?? 0);
        $pin = isset($_POST['pin']) ? (int) $_POST['pin'] : 0;
        if ($id <= 0) {
            admin_api_error_response($isAjax, 'announcement.php', '缺少公告 ID');
        }

        try {
            $pdo->prepare('UPDATE announcement SET is_pinned = ?, updated_at = NOW() WHERE announcement_id = ?')->execute([$pin, $id]);

            if (function_exists('sys_log')) {
                sys_log($pdo, $_SESSION['user_id'] ?? null, sys_log_build($pin ? '设为优先显示公告' : '取消优先显示公告', [
                    'announcement_id' => $id,
                ]), 'announcement', $id);
            }
        } catch (PDOException $e) {
            admin_api_error_response($isAjax, 'announcement.php', '操作失败', 500);
        }

        admin_api_success_response($isAjax, 'announcement.php', '操作成功');
        break;
}

return true;
