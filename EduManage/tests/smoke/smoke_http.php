<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, "The curl extension is required for smoke tests.\n");
    exit(1);
}

$baseUrl = $argv[1] ?? getenv('SMOKE_BASE_URL') ?: '';
if ($baseUrl === '') {
    fwrite(STDERR, "Usage: php tests/smoke/smoke_http.php <base-url>\n");
    exit(1);
}

$baseUrl = rtrim($baseUrl, '/');

final class SmokeFailure extends RuntimeException
{
}

final class HttpResponse
{
    public function __construct(
        public int $status,
        public string $body,
        public string $effectiveUrl
    ) {
    }

    public function json(): array
    {
        $decoded = json_decode($this->body, true);
        if (!is_array($decoded)) {
            throw new SmokeFailure('Expected JSON response but decode failed.');
        }

        return $decoded;
    }
}

final class SessionClient
{
    private string $cookieFile;

    public function __construct(private string $baseUrl)
    {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'smoke_cookie_');
        if ($this->cookieFile === false) {
            throw new RuntimeException('Unable to create temporary cookie file.');
        }
    }

    public function __destruct()
    {
        if (is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function get(string $path): HttpResponse
    {
        return $this->request('GET', $path);
    }

    public function getJson(string $path): HttpResponse
    {
        return $this->request('GET', $path, [
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-Requested-With: XMLHttpRequest',
            ],
        ]);
    }

    public function postForm(string $path, array $data, array $headers = []): HttpResponse
    {
        return $this->request('POST', $path, [
            CURLOPT_POSTFIELDS => http_build_query($data, '', '&'),
            CURLOPT_HTTPHEADER => array_merge(
                ['Accept: text/html,application/json'],
                $headers
            ),
        ]);
    }

    private function request(string $method, string $path, array $extraCurlOptions = []): HttpResponse
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize curl.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/json',
            ],
        ];

        foreach ($extraCurlOptions as $key => $value) {
            $options[$key] = $value;
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return new HttpResponse($status, $body, $effectiveUrl);
    }
}

function assertStatus(HttpResponse $response, int $expected, string $message): void
{
    if ($response->status !== $expected) {
        throw new SmokeFailure($message . " (expected HTTP {$expected}, got {$response->status})");
    }
}

function assertStatusIn(HttpResponse $response, array $expectedStatuses, string $message): void
{
    if (!in_array($response->status, $expectedStatuses, true)) {
        throw new SmokeFailure($message . ' (expected HTTP ' . implode('/', $expectedStatuses) . ", got {$response->status})");
    }
}

function assertContainsText(string $needle, string $haystack, string $message): void
{
    if (mb_strpos($haystack, $needle) === false) {
        throw new SmokeFailure($message . " (missing text: {$needle})");
    }
}

function assertNotContainsText(string $needle, string $haystack, string $message): void
{
    if (mb_strpos($haystack, $needle) !== false) {
        throw new SmokeFailure($message . " (unexpected text: {$needle})");
    }
}

function assertMatchesPattern(string $pattern, string $haystack, string $message): void
{
    if (preg_match($pattern, $haystack) !== 1) {
        throw new SmokeFailure($message . " (pattern not found: {$pattern})");
    }
}

function assertUrlContains(HttpResponse $response, string $needle, string $message): void
{
    if (strpos($response->effectiveUrl, $needle) === false) {
        throw new SmokeFailure($message . " (effective URL: {$response->effectiveUrl})");
    }
}

function assertJsonSuccess(HttpResponse $response, string $message): array
{
    assertStatus($response, 200, $message);
    $json = $response->json();
    assertJsonEnvelope($json, $message);

    $ok = null;
    if (array_key_exists('ok', $json)) {
        $ok = $json['ok'] === true;
    } elseif (array_key_exists('success', $json)) {
        $ok = $json['success'] !== false;
    }

    if ($ok !== true) {
        throw new SmokeFailure($message . ' (JSON payload indicates failure)');
    }

    return $json;
}

function assertJsonEnvelope(array $json, string $message, ?bool $expectedOk = null): void
{
    foreach (['ok', 'success', 'code', 'message'] as $key) {
        if (!array_key_exists($key, $json)) {
            throw new SmokeFailure($message . " (JSON envelope missing {$key})");
        }
    }

    if (!is_bool($json['ok']) || !is_bool($json['success'])) {
        throw new SmokeFailure($message . ' (JSON envelope ok/success must be booleans)');
    }
    if ($json['ok'] !== $json['success']) {
        throw new SmokeFailure($message . ' (JSON envelope ok/success disagree)');
    }
    if ($expectedOk !== null && $json['ok'] !== $expectedOk) {
        throw new SmokeFailure($message . ' (JSON envelope success state mismatch)');
    }
    if (!is_string($json['code']) || $json['code'] === '') {
        throw new SmokeFailure($message . ' (JSON envelope code must be a non-empty string)');
    }
    if (!is_string($json['message']) || $json['message'] === '') {
        throw new SmokeFailure($message . ' (JSON envelope message must be a non-empty string)');
    }
}

function requireJsonData(HttpResponse $response, string $message): mixed
{
    $json = assertJsonSuccess($response, $message);
    if (!array_key_exists('data', $json)) {
        throw new SmokeFailure($message . ' (JSON payload missing data field)');
    }

    return $json['data'];
}

function assertJsonDecodes(HttpResponse $response, string $message): array
{
    assertStatus($response, 200, $message);
    $decoded = $response->json();
    if ($decoded === []) {
        throw new SmokeFailure($message . ' (JSON payload is empty)');
    }

    return $decoded;
}

function extractHiddenCsrfToken(string $html): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/u', $html, $matches) === 1) {
        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    throw new SmokeFailure('Unable to extract CSRF token from admin page.');
}

function extractFirstAnnouncementId(string $html): int
{
    if (preg_match('/data-id="(\d+)"/', $html, $matches) === 1) {
        return (int) $matches[1];
    }

    throw new SmokeFailure('Unable to extract announcement id from filtered announcement page.');
}

function extractStudentIdByNo(string $html, string $studentNo): int
{
    $pattern = '/<tr>\s*<td>\s*(\d+)\s*<\/td>\s*<td>\s*' . preg_quote($studentNo, '/') . '\s*<\/td>/u';
    if (preg_match($pattern, $html, $matches) === 1) {
        return (int) $matches[1];
    }

    throw new SmokeFailure("Unable to extract smoke student id for {$studentNo}.");
}

function extractFirstNumericOptionValue(string $html, string $selectName): int
{
    $pattern = '/<select[^>]+name="' . preg_quote($selectName, '/') . '"[^>]*>(.*?)<\/select>/isu';
    if (preg_match($pattern, $html, $matches) !== 1) {
        throw new SmokeFailure("Unable to find select {$selectName}.");
    }

    if (preg_match('/<option[^>]+value="(\d+)"/u', $matches[1], $optionMatches) === 1) {
        return (int) $optionMatches[1];
    }

    throw new SmokeFailure("Unable to find a numeric option for {$selectName}.");
}

function queryPath(string $path, array $params = []): string
{
    if ($params === []) {
        return $path;
    }

    return $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function requirePositiveId(int $value, string $message): int
{
    if ($value <= 0) {
        throw new SmokeFailure($message);
    }

    return $value;
}

function firstSectionId(array $sections, string $message): int
{
    foreach ($sections as $section) {
        $sectionId = (int) ($section['section_id'] ?? 0);
        if ($sectionId > 0) {
            return $sectionId;
        }
    }

    throw new SmokeFailure($message);
}

function firstStudentId(array $students, string $message): int
{
    foreach ($students as $student) {
        $studentId = (int) ($student['user_id'] ?? $student['student_id'] ?? 0);
        if ($studentId > 0) {
            return $studentId;
        }
    }

    throw new SmokeFailure($message);
}

function runStep(string $label, callable $fn): void
{
    echo "[RUN ] {$label}\n";
    $fn();
    echo "[ OK ] {$label}\n";
}

$admin = new SessionClient($baseUrl);
$teacher = new SessionClient($baseUrl);
$student = new SessionClient($baseUrl);
$teacherSectionId = 0;
$teacherScheduleId = 0;
$teacherStudentId = 0;
$teacherScheduleProbe = null;
$studentSlotId = 0;
$smokeStudentId = 0;
$smokeStudentCsrfToken = '';
$smokeFailed = false;

try {
    runStep('登录页可访问', function () use ($admin): void {
        $response = $admin->get('/login/login.php');
        assertStatus($response, 200, 'Login page must be reachable');
        assertContainsText('登录系统', $response->body, 'Login page should render the form');
    });

    runStep('管理员可登录', function () use ($admin): void {
        $response = $admin->postForm('/login/login.php', [
            'email' => 'admin@school.edu',
            'password' => '123456',
        ]);
        assertStatus($response, 200, 'Admin login should complete');
        assertUrlContains($response, '/admin/index.php', 'Admin login should redirect to admin index');
        assertContainsText('管理后台总览', $response->body, 'Admin landing page should render');
    });

    runStep('管理员可打开课程页面', function () use ($admin): void {
        $response = $admin->get('/admin/course.php');
        assertStatus($response, 200, 'Admin course page should load');
        assertContainsText('课程管理', $response->body, 'Admin course page should contain its title');
    });

    runStep('API 安全失败返回统一 JSON 结构', function () use ($admin, $baseUrl): void {
        $anonymous = new SessionClient($baseUrl);
        $unauthenticated = $anonymous->getJson('/admin/api/index.php?act=get_announcement&id=1');
        assertStatus($unauthenticated, 401, 'Unauthenticated admin API request should return 401');
        assertJsonEnvelope($unauthenticated->json(), 'Unauthenticated admin API request should return a JSON envelope', false);

        $missingCsrf = $admin->postForm(
            '/admin/api/index.php?act=add_announcement',
            [
                'title' => 'SMOKE 缺失 CSRF',
                'target' => 'all',
                'content' => 'This request should fail before writing.',
            ],
            ['X-Requested-With: XMLHttpRequest']
        );
        assertStatus($missingCsrf, 419, 'Missing CSRF admin write should return 419');
        $csrfJson = $missingCsrf->json();
        assertJsonEnvelope($csrfJson, 'Missing CSRF admin write should return a JSON envelope', false);
        if (($csrfJson['code'] ?? '') !== 'ERR_CSRF') {
            throw new SmokeFailure('Missing CSRF admin write should use ERR_CSRF.');
        }
    });

    runStep('管理员主要页面均可访问', function () use ($admin): void {
        $pages = [
            '/admin/student.php' => '学生管理',
            '/admin/teacher.php' => '教师管理',
            '/admin/course.php' => '课程管理',
            '/admin/schedule_manage.php' => '排课管理',
            '/admin/classroom.php' => '教室管理',
            '/admin/department.php' => '院系管理',
            '/admin/major.php' => '专业管理',
            '/admin/syslog.php' => '系统日志',
            '/admin/profile.php' => '个人信息',
        ];

        foreach ($pages as $path => $title) {
            $response = $admin->get($path);
            assertStatus($response, 200, "Admin page {$path} should load");
            assertContainsText($title, $response->body, "Admin page {$path} should contain its title");
            assertNotContainsText('404 Not Found', $response->body, "Admin page {$path} should not render a 404");
        }

        $response = $admin->get('/admin/admin_manage.php');
        assertStatusIn($response, [200, 403], 'Admin manage page should either load or deny by role cleanly');
        assertNotContainsText('404 Not Found', $response->body, 'Admin manage page should not render a 404');
    });

    runStep('管理员学生新增/编辑/删除接口可用', function () use ($admin, &$smokeStudentId, &$smokeStudentCsrfToken): void {
        $page = $admin->get('/admin/student.php');
        assertStatus($page, 200, 'Admin student page should load before student write smoke');
        $smokeStudentCsrfToken = extractHiddenCsrfToken($page->body);
        $deptId = extractFirstNumericOptionValue($page->body, 'dept_id');

        $suffix = date('His') . (string) random_int(10, 99);
        $studentNo = substr($suffix, -8);
        $email = 'smoke.student.' . date('YmdHis') . bin2hex(random_bytes(2)) . '@school.edu';
        $phone = '139' . $studentNo;
        $name = 'SMOKE 学生 ' . $studentNo;

        $create = $admin->postForm(
            '/admin/api/index.php?act=add_student',
            [
                'csrf_token' => $smokeStudentCsrfToken,
                'name' => $name,
                'email' => $email,
                'pwd' => '123456',
                'student_no' => $studentNo,
                'dept_id' => (string) $deptId,
                'gender' => 'other',
                'phone' => $phone,
            ],
            ['X-Requested-With: XMLHttpRequest']
        );
        assertJsonSuccess($create, 'Admin student create should complete');

        $list = $admin->get('/admin/student.php');
        assertStatus($list, 200, 'Admin student page should load after create');
        assertContainsText($studentNo, $list->body, 'Created smoke student should appear in list');
        $smokeStudentId = extractStudentIdByNo($list->body, $studentNo);

        $updatedName = $name . ' 编辑';
        $update = $admin->postForm(
            '/admin/api/index.php?act=update_student',
            [
                'csrf_token' => $smokeStudentCsrfToken,
                'user_id' => (string) $smokeStudentId,
                'name' => $updatedName,
                'email' => $email,
                'pwd' => '',
                'student_no' => $studentNo,
                'dept_id' => (string) $deptId,
                'gender' => 'other',
                'phone' => $phone,
            ],
            ['X-Requested-With: XMLHttpRequest']
        );
        assertJsonSuccess($update, 'Admin student update should complete');

        $afterUpdate = $admin->get('/admin/student.php');
        assertStatus($afterUpdate, 200, 'Admin student page should load after update');
        assertContainsText($updatedName, $afterUpdate->body, 'Updated smoke student name should appear in list');

        $delete = $admin->get(queryPath('/admin/api/index.php', [
            'act' => 'del_student',
            'id' => $smokeStudentId,
            'csrf_token' => $smokeStudentCsrfToken,
        ]));
        assertStatus($delete, 200, 'Admin student delete should redirect back to a reachable page');
        $smokeStudentId = 0;

        $afterDelete = $admin->get('/admin/student.php');
        assertStatus($afterDelete, 200, 'Admin student page should load after delete');
        assertNotContainsText($studentNo, $afterDelete->body, 'Smoke student should be deleted');
    });

    runStep('管理员可打开公告页面', function () use ($admin): void {
        $response = $admin->get('/admin/announcement.php');
        assertStatus($response, 200, 'Admin announcement page should load');
        assertContainsText('公告管理', $response->body, 'Admin announcement page should contain its title');
    });

    runStep('管理员公告新增/读取/编辑/置顶/删除接口可用', function () use ($admin): void {
        $page = $admin->get('/admin/announcement.php');
        assertStatus($page, 200, 'Admin announcement page should load before publishing');
        $csrfToken = extractHiddenCsrfToken($page->body);

        $title = 'SMOKE 公告 ' . date('YmdHis') . '-' . bin2hex(random_bytes(2));
        $content = '自动化冒烟测试公告，请忽略。';
        $updatedTitle = $title . ' 已编辑';

        $publish = $admin->postForm(
            '/admin/api/index.php?act=add_announcement',
            [
                'csrf_token' => $csrfToken,
                'title' => $title,
                'target' => 'all',
                'content' => $content,
            ],
            ['X-Requested-With: XMLHttpRequest']
        );
        assertJsonSuccess($publish, 'Admin announcement publish should complete');

        $search = $admin->get(queryPath('/admin/announcement.php', ['q' => $title]));
        assertStatus($search, 200, 'Admin announcement search page should load');
        assertContainsText($title, $search->body, 'Published announcement should be searchable');

        $announcementId = extractFirstAnnouncementId($search->body);

        $detail = $admin->getJson(queryPath('/admin/api/index.php', [
            'act' => 'get_announcement',
            'id' => $announcementId,
        ]));
        $detailJson = assertJsonSuccess($detail, 'Admin announcement detail should return a JSON envelope');
        if (!isset($detailJson['announcement']['announcement_id'])) {
            throw new SmokeFailure('Admin announcement detail should include the legacy announcement field.');
        }
        if (!isset($detailJson['data']['announcement'])) {
            throw new SmokeFailure('Admin announcement detail should include the standard data.announcement field.');
        }

        $update = $admin->postForm(
            '/admin/api/index.php?act=update_announcement',
            [
                'csrf_token' => $csrfToken,
                'announcement_id' => (string) $announcementId,
                'title' => $updatedTitle,
                'target' => 'all',
                'content' => $content . ' 已编辑。',
                'status' => 'published',
                'is_pinned' => '0',
            ],
            ['X-Requested-With: XMLHttpRequest']
        );
        assertJsonSuccess($update, 'Admin announcement update should complete');

        $pin = $admin->postForm(
            '/admin/api/index.php?act=pin_announcement',
            [
                'csrf_token' => $csrfToken,
                'announcement_id' => (string) $announcementId,
                'pin' => '1',
            ],
            ['X-Requested-With: XMLHttpRequest']
        );
        assertJsonSuccess($pin, 'Admin announcement pin should complete');

        $unpin = $admin->postForm(
            '/admin/api/index.php?act=pin_announcement',
            [
                'csrf_token' => $csrfToken,
                'announcement_id' => (string) $announcementId,
                'pin' => '0',
            ],
            ['X-Requested-With: XMLHttpRequest']
        );
        assertJsonSuccess($unpin, 'Admin announcement unpin should complete');

        $delete = $admin->postForm(
            '/admin/api/index.php?act=delete_announcement',
            [
                'csrf_token' => $csrfToken,
                'announcement_id' => (string) $announcementId,
            ],
            ['X-Requested-With: XMLHttpRequest']
        );
        assertJsonSuccess($delete, 'Smoke announcement cleanup should complete');

        $afterDelete = $admin->get(queryPath('/admin/announcement.php', ['q' => $updatedTitle]));
        assertStatus($afterDelete, 200, 'Admin announcement search page should load after cleanup');
        assertMatchesPattern('/共\s*<strong>\s*0\s*<\/strong>\s*条公告/u', $afterDelete->body, 'Smoke announcement should be cleaned up after publish test');
    });

    runStep('管理员可打开日志页面', function () use ($admin): void {
        $response = $admin->get('/admin/syslog.php');
        assertStatus($response, 200, 'Admin syslog page should load');
        assertContainsText('系统日志', $response->body, 'Admin syslog page should contain its title');
    });

    runStep('教师可登录', function () use ($teacher): void {
        $response = $teacher->postForm('/login/login.php', [
            'email' => 'teacher@school.edu',
            'password' => '123456',
        ]);
        assertStatus($response, 200, 'Teacher login should complete');
        assertUrlContains($response, '/teacher/index.php', 'Teacher login should redirect to teacher portal');
        assertContainsText('data-view="grades"', $response->body, 'Teacher portal should render the grades view');
        assertContainsText('data-view="exams"', $response->body, 'Teacher portal should render the exams view');
        assertContainsText('data-view="attendance"', $response->body, 'Teacher portal should render the attendance view');
    });

    runStep('教师基础资料/仪表盘/班级接口可访问', function () use ($teacher, &$teacherSectionId, &$teacherStudentId): void {
        $profile = requireJsonData(
            $teacher->get('/teacher/api/teacher.php?action=get_profile'),
            'Teacher profile API should respond successfully'
        );
        if (!is_array($profile) || !array_key_exists('user_id', $profile)) {
            throw new SmokeFailure('Teacher profile API should return a teacher profile object.');
        }

        $dashboard = requireJsonData(
            $teacher->get('/teacher/api/teacher.php?action=get_dashboard'),
            'Teacher dashboard API should respond successfully'
        );
        if (!is_array($dashboard) || !array_key_exists('stats', $dashboard)) {
            throw new SmokeFailure('Teacher dashboard API should return stats.');
        }

        $sections = requireJsonData(
            $teacher->get('/teacher/api/teacher.php?action=get_sections'),
            'Teacher sections API should respond successfully'
        );
        if (!is_array($sections)) {
            throw new SmokeFailure('Teacher sections API should return an array.');
        }
        $teacherSectionId = firstSectionId($sections, 'Demo seed is incomplete: teacher has no section.');

        $students = requireJsonData(
            $teacher->get(queryPath('/teacher/api/teacher.php', [
                'action' => 'get_section_students',
                'section_id' => $teacherSectionId,
            ])),
            'Teacher section students API should respond successfully'
        );
        if (!is_array($students)) {
            throw new SmokeFailure('Teacher section students API should return an array.');
        }
        $teacherStudentId = firstStudentId($students, 'Demo seed is incomplete: teacher section has no student.');
    });

    runStep('教师成绩接口可访问', function () use ($teacher, &$teacherStudentId): void {
        $data = requireJsonData(
            $teacher->get(queryPath('/teacher/api/grades.php', [
                'action' => 'get_student_gpa',
                'student_id' => requirePositiveId($teacherStudentId, 'Demo seed is incomplete: missing teacher student id.'),
            ])),
            'Teacher grades API should respond successfully'
        );
        if (!is_array($data)) {
            throw new SmokeFailure('Teacher grades API should return a data object.');
        }
    });

    runStep('教师成绩统计接口可访问', function () use ($teacher, &$teacherSectionId, &$teacherStudentId): void {
        $sectionId = requirePositiveId($teacherSectionId, 'Demo seed is incomplete: missing teacher section id.');
        $studentId = requirePositiveId($teacherStudentId, 'Demo seed is incomplete: missing teacher student id.');

        foreach ([
            'get_final_scores',
            'get_course_avg',
            'get_grade_distribution',
            'get_exam_comparison',
        ] as $action) {
            $data = requireJsonData(
                $teacher->get(queryPath('/teacher/api/grades.php', [
                    'action' => $action,
                    'section_id' => $sectionId,
                ])),
                "Teacher grades action {$action} should respond successfully"
            );
            if (!is_array($data)) {
                throw new SmokeFailure("Teacher grades action {$action} should return data.");
            }
        }

        $data = requireJsonData(
            $teacher->get(queryPath('/teacher/api/grades.php', [
                'action' => 'get_student_course_avg',
                'student_id' => $studentId,
            ])),
            'Teacher student course average API should respond successfully'
        );
        if (!is_array($data)) {
            throw new SmokeFailure('Teacher student course average API should return data.');
        }
    });

    runStep('教师考试接口可访问', function () use ($teacher, &$teacherSectionId): void {
        $data = requireJsonData(
            $teacher->get(queryPath('/teacher/api/teacher.php', [
                'action' => 'get_section_exams',
                'section_id' => requirePositiveId($teacherSectionId, 'Demo seed is incomplete: missing teacher section id.'),
            ])),
            'Teacher exams API should respond successfully'
        );
        if (!is_array($data)) {
            throw new SmokeFailure('Teacher exams API should return a data array.');
        }
    });

    runStep('教师考勤接口可访问', function () use ($teacher, &$teacherSectionId): void {
        $data = requireJsonData(
            $teacher->get(queryPath('/teacher/api/attendance.php', [
                'action' => 'get_section_schedules',
                'section_id' => requirePositiveId($teacherSectionId, 'Demo seed is incomplete: missing teacher section id.'),
            ])),
            'Teacher attendance API should respond successfully'
        );
        if (!is_array($data)) {
            throw new SmokeFailure('Teacher attendance API should return a data array.');
        }
    });

    runStep('教师课表/考勤/工作量/公告只读接口可访问', function () use ($teacher, &$teacherSectionId, &$teacherScheduleId, &$teacherScheduleProbe): void {
        $sectionId = requirePositiveId($teacherSectionId, 'Demo seed is incomplete: missing teacher section id.');

        $allSchedules = requireJsonData(
            $teacher->get('/teacher/api/schedule.php?action=get_teacher_schedule'),
            'Teacher schedule list API should respond successfully'
        );
        if (!is_array($allSchedules)) {
            throw new SmokeFailure('Teacher schedule list API should return an array.');
        }
        if ($allSchedules !== []) {
            $teacherScheduleId = (int)($allSchedules[0]['schedule_id'] ?? 0);
            $teacherScheduleProbe = $allSchedules[0];
        }

        $sectionSchedules = requireJsonData(
            $teacher->get(queryPath('/teacher/api/schedule.php', [
                'action' => 'get_schedule',
                'section_id' => $sectionId,
            ])),
            'Teacher section schedule API should respond successfully'
        );
        if (!is_array($sectionSchedules)) {
            throw new SmokeFailure('Teacher section schedule API should return an array.');
        }
        if ($teacherScheduleId <= 0 && $sectionSchedules !== []) {
            $teacherScheduleId = (int)($sectionSchedules[0]['schedule_id'] ?? 0);
            $teacherScheduleProbe = $sectionSchedules[0];
        }

        foreach ([
            '/teacher/api/schedule.php?action=get_week_range',
            '/teacher/api/schedule.php?action=get_weekly_schedule&week=1',
            '/teacher/api/workload.php?action=get_semesters',
            '/teacher/api/workload.php?action=get_summary',
            '/teacher/api/workload.php?action=get_by_section',
            '/teacher/api/application.php?action=get_courses_to_apply',
            '/teacher/api/application.php?action=get_teaching_overview',
            '/teacher/api/application.php?action=get_enrollment_stats',
            '/teacher/api/application.php?action=get_advisor_students',
            '/teacher/api/announcement.php?action=get_teacher_announcements',
            '/teacher/api/announcement.php?action=get_inbox',
        ] as $path) {
            $data = requireJsonData($teacher->get($path), "Teacher readonly API {$path} should respond successfully");
            if (!is_array($data)) {
                throw new SmokeFailure("Teacher readonly API {$path} should return data.");
            }
        }

        $sectionAnnouncements = requireJsonData(
            $teacher->get(queryPath('/teacher/api/announcement.php', [
                'action' => 'get_section_announcements',
                'section_id' => $sectionId,
            ])),
            'Teacher section announcements API should respond successfully'
        );
        if (!is_array($sectionAnnouncements)) {
            throw new SmokeFailure('Teacher section announcements API should return an array.');
        }

        if ($teacherScheduleId > 0) {
            $attendanceRows = requireJsonData(
                $teacher->get(queryPath('/teacher/api/attendance.php', [
                    'action' => 'get_schedule_attendance',
                    'schedule_id' => $teacherScheduleId,
                    'week' => 1,
                ])),
                'Teacher schedule attendance API should respond successfully'
            );
            if (!is_array($attendanceRows)) {
                throw new SmokeFailure('Teacher schedule attendance API should return an array.');
            }
        }
    });

    runStep('教师冲突检测接口可访问', function () use ($teacher, &$teacherSectionId, &$teacherScheduleProbe): void {
        if (!is_array($teacherScheduleProbe)) {
            return;
        }

        $data = requireJsonData(
            $teacher->get(queryPath('/teacher/api/schedule.php', [
                'action' => 'check_conflicts',
                'section_id' => $teacherSectionId > 0 ? $teacherSectionId : (int)($teacherScheduleProbe['section_id'] ?? 0),
                'day_of_week' => (int)($teacherScheduleProbe['day_of_week'] ?? 1),
                'start_time' => (string)($teacherScheduleProbe['start_time'] ?? '08:00:00'),
                'end_time' => (string)($teacherScheduleProbe['end_time'] ?? '09:00:00'),
                'classroom_id' => (int)($teacherScheduleProbe['classroom_id'] ?? 1),
            ])),
            'Teacher conflict check API should respond successfully'
        );
        if (!is_array($data) || !array_key_exists('has_conflicts', $data)) {
            throw new SmokeFailure('Teacher conflict check API should return conflict fields.');
        }
    });

    runStep('学生可登录', function () use ($student): void {
        $response = $student->postForm('/login/login.php', [
            'email' => 'student@school.edu',
            'password' => '123456',
        ]);
        assertStatus($response, 200, 'Student login should complete');
        assertUrlContains($response, '/student/spa.html', 'Student login should redirect to student SPA');
    });

    runStep('学生访问后台 API 会被明确拒绝', function () use ($student): void {
        $response = $student->getJson('/admin/api/index.php?act=get_announcement&id=1');
        assertStatus($response, 403, 'Student session should not access admin API');
        assertJsonEnvelope($response->json(), 'Student session forbidden admin API response should use JSON envelope', false);
    });

    runStep('学生端可打开选课/成绩/公告视图', function () use ($student): void {
        $response = $student->get('/student/spa.html');
        assertStatus($response, 200, 'Student SPA should load');
        assertContainsText('#course', $response->body, 'Student SPA should contain the course route');
        assertContainsText('#grades', $response->body, 'Student SPA should contain the grades route');
        assertContainsText('#announcement', $response->body, 'Student SPA should contain the announcement route');
    });

    runStep('学生首页/配置/个人信息接口可访问', function () use ($student): void {
        assertJsonDecodes($student->get('/student/api/config.php'), 'Student config API should respond with JSON');

        $portal = assertJsonSuccess($student->get('/student/api/student_portal.php'), 'Student portal API should respond successfully');
        foreach (['student', 'stats', 'recent_grades', 'enrolled_count'] as $key) {
            if (!array_key_exists($key, $portal)) {
                throw new SmokeFailure("Student portal API should include {$key}.");
            }
        }

        $profile = assertJsonSuccess($student->get('/student/api/profile.php'), 'Student profile API should respond successfully');
        if (!array_key_exists('student', $profile)) {
            throw new SmokeFailure('Student profile API should include student.');
        }

        $sidebar = assertJsonSuccess($student->get('/student/api/sidebar.php'), 'Student sidebar API should respond successfully');
        if (!array_key_exists('student', $sidebar) || !array_key_exists('meta', $sidebar)) {
            throw new SmokeFailure('Student sidebar API should include student and meta.');
        }
    });

    runStep('学生课表/考试/公告接口可访问', function () use ($student): void {
        foreach ([
            '/student/api/schedule.php',
            '/student/api/exam_info.php',
        ] as $path) {
            assertJsonSuccess($student->get($path), "Student API {$path} should respond successfully");
        }

        $announcement = assertJsonDecodes(
            $student->get('/student/api/announcement.php'),
            'Student announcement API should respond with JSON'
        );
        if (!array_key_exists('data', $announcement) || !array_key_exists('meta', $announcement)) {
            throw new SmokeFailure('Student announcement API should include data and meta.');
        }
    });

    runStep('学生选课接口可访问', function () use ($student): void {
        $response = $student->get('/student/api/course_select.php');
        assertJsonSuccess($response, 'Student course selection API should respond successfully');
    });

    runStep('学生成绩接口可访问', function () use ($student): void {
        $response = $student->get('/student/api/my_grades.php');
        assertJsonSuccess($response, 'Student grades API should respond successfully');
    });

    runStep('学生空闲教室接口可访问', function () use ($student, &$studentSlotId): void {
        $base = assertJsonSuccess($student->get('/student/api/free_classroom.php'), 'Student free classroom base API should respond successfully');
        $data = $base['data'] ?? null;
        if (!is_array($data) || empty($data['time_slots'])) {
            throw new SmokeFailure('Student free classroom base API should include time slots.');
        }

        $studentSlotId = (int)($data['time_slots'][0]['slot_id'] ?? 0);
        if ($studentSlotId <= 0) {
            throw new SmokeFailure('Student free classroom time slot id should be available.');
        }

        $search = assertJsonSuccess(
            $student->get(queryPath('/student/api/free_classroom.php', [
                'action' => 'search',
                'week' => 1,
                'day_of_week' => 1,
                'slot_start_id' => $studentSlotId,
                'slot_end_id' => $studentSlotId,
                'classroom_id' => 0,
            ])),
            'Student free classroom search API should respond successfully'
        );
        $searchData = $search['data'] ?? null;
        if (!is_array($searchData) || !array_key_exists('results', $searchData)) {
            throw new SmokeFailure('Student free classroom search API should include results.');
        }
    });

} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] " . $e->getMessage() . PHP_EOL);
    $smokeFailed = true;
} finally {
    if ($smokeStudentId > 0) {
        try {
            $token = $smokeStudentCsrfToken;
            if ($token === '') {
                $page = $admin->get('/admin/student.php');
                if ($page->status === 200) {
                    $token = extractHiddenCsrfToken($page->body);
                }
            }
            if ($token !== '') {
                $admin->get(queryPath('/admin/api/index.php', [
                    'act' => 'del_student',
                    'id' => $smokeStudentId,
                    'csrf_token' => $token,
                ]));
            }
        } catch (Throwable $cleanupError) {
            fwrite(STDERR, "[WARN] Smoke student cleanup failed: " . $cleanupError->getMessage() . PHP_EOL);
        }
    }
}

if ($smokeFailed) {
    exit(1);
}

echo "[DONE] Smoke test suite passed.\n";
exit(0);
