<?php
// ============================================================
// components/grade_helpers.php
// 成绩相关共享辅助函数（student_portal.php & my_grades.php 共用）
// ============================================================
if (!function_exists('scoreToGpa')) {
    function scoreToGpa($score) {
        $s = (float)$score;
        if ($s <  60) return 0.0;
        if ($s < 64) return 1.0;
        if ($s < 67) return 1.3;
        if ($s < 70) return 1.7;
        if ($s < 74) return 2.0;
        if ($s < 77) return 2.3;
        if ($s < 80) return 2.7;
        if ($s < 84) return 3.0;
        if ($s < 87) return 3.3;
        if ($s < 90) return 3.7;
        return 4.0;
    }
}

if (!function_exists('gpaColor')) {
    function gpaColor($g) {
        $g = (float)$g;
        if ($g >= 3.7) return '#22c55e';
        if ($g >= 3.0) return '#3b82f6';
        if ($g >= 2.0) return '#f59e0b';
        if ($g >  0)   return '#ef4444';
        return '#94a3b8';
    }
}

if (!function_exists('scoreColor')) {
    function scoreColor($score) {
        if ($score === null || $score === '') return '#94a3b8';
        $s = (float)$score;
        if ($s >= 90) return '#22c55e';
        if ($s >= 75) return '#3b82f6';
        if ($s >= 60) return '#f59e0b';
        return '#ef4444';
    }
}

if (!function_exists('scoreLetter')) {
    function scoreLetter($score) {
        if ($score === null || $score === '') return '?';
        $s = (float)$score;
        if ($s >= 90) return 'A';
        if ($s >= 75) return 'B';
        if ($s >= 60) return 'C';
        return 'F';
    }
}

if (!function_exists('displayScore')) {
    function displayScore($score) {
        if ($score === null || $score === '') return null;
        if (is_numeric($score)) return number_format((float)$score, 1);
        return htmlspecialchars($score);
    }
}

/**
 * 给定 PDO 和 student_id，计算该学生全部考试记录的加权 GPA、统计学分、成绩条数
 * 与 my_grades.php 的计算逻辑完全一致：
 *   - 取 exam 表所有有分数的记录
 *   - GPA = Σ(credit × scoreToGpa(score)) / Σ(credit)
 *   - 已修学分 = final 类型且 score >= 60 的课程学分之和（每门课只计一次）
 *   - 已公布成绩数 = 有 final 类型成绩的课程数
 *
 * @return array ['gpa'=>float, 'credits'=>float, 'published'=>int, 'exam_count'=>int]
 */
if (!function_exists('calcStudentStats')) {
    function calcStudentStats(PDO $pdo, int $uid): array {
        // 取所有有分数的考试记录（同 my_grades.php 主查询）
            $st = $pdo->prepare("CALL sp_calc_student_stats(?)");
            $st->execute([$uid]);
            $row = $st->fetch();
            while ($st->nextRowset()) {}
            if (!$row) {
                return ['gpa'=>0.0, 'credits'=>0.0, 'published'=>0, 'exam_count'=>0];
            }
            return [
                'gpa'       => (float)$row['gpa'],
                'credits'   => (float)$row['credits'],
                'published' => (int)$row['published'],
                'exam_count'=> (int)$row['exam_count'],
            ];
    }
}
if (!function_exists('ensureUtf8')) {
    function ensureUtf8($s) {
        if ($s === null) return $s;
        if (!is_string($s)) return $s;
        if (function_exists('mb_check_encoding')) {
            if (mb_check_encoding($s, 'UTF-8')) return $s;
        } else {
            if (@preg_match('//u', $s)) return $s;
        }
        $froms = ['GBK', 'GB2312', 'CP936', 'BIG5', 'ISO-8859-1'];
        foreach ($froms as $from) {
            if (function_exists('mb_convert_encoding')) {
                $conv = @mb_convert_encoding($s, 'UTF-8', $from);
            } else {
                $conv = @iconv($from, 'UTF-8//IGNORE', $s);
            }
            if ($conv !== false && (function_exists('mb_check_encoding') ? mb_check_encoding($conv, 'UTF-8') : @preg_match('//u', $conv))) {
                return $conv;
            }
        }
        return $s;
    }
}

if (!function_exists('typeLabel')) {
    function typeLabel($t) {
        if ($t === 'final')   return ensureUtf8('期末');
        if ($t === 'midterm') return ensureUtf8('期中');
        if ($t === 'quiz')    return ensureUtf8('测验');
        return htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('examTypeLabel')) {
    function examTypeLabel($t) {
        if ($t === 'final')   return ensureUtf8('期末考试');
        if ($t === 'midterm') return ensureUtf8('期中考试');
        if ($t === 'quiz')    return ensureUtf8('平时测验');
        return htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('typeBadge')) {
    function typeBadge($t) {
        if ($t === 'final')   return 'badge-rose';
        if ($t === 'midterm') return 'badge-gold';
        if ($t === 'quiz')    return 'badge-jade';
        return '';
    }
}

if (!function_exists('scoreRing')) {
    function scoreRing($s) {
        if ($s === null) return 0;
        $v = (float)$s;
        if ($v > 100) return 100;
        if ($v < 0) return 0;
        return $v;
    }
}