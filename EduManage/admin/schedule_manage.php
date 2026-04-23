<?php
/**
 * admin/schedule_manage.php
 * 排课管理页面：负责课程、教师、节次、周次和教室的排课维护。
 */
require 'common.php';
$pdo = app_require_pdo();
admin_auth();
$page_title = '排课管理 - 管理后台';
$adminApiUrl = app_catalog_url('admin', 'api', 'main');
$scheduleReference = admin_fetch_schedule_reference_data($pdo);
$courses = $scheduleReference['courses'];
$teachers = $scheduleReference['teachers'];
$classrooms = $scheduleReference['classrooms'];
$timeSlots = $scheduleReference['timeSlots'];
$maxWeeks = $scheduleReference['maxWeeks'];
$semesterOptions = app_enum_map('semester');
if ($semesterOptions === []) {
    $fallbackSemester = app_default_current_semester();
    $semesterOptions = [$fallbackSemester => app_semester_label($fallbackSemester, true)];
}
$defaultSemester = (string) array_key_first($semesterOptions);

$schedules = admin_fetch_schedules_for_management($pdo);
require 'layout_head.php';
?>

<div class="admin-page">
<section class="admin-page-header">
    <div>
        <h1 class="admin-page-title">排课管理</h1>

    </div>
    <div class="admin-page-actions">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">新增排课</button>
    </div>
</section>

<?php $pageAlert = admin_page_alert(); ?>
<?php if ($pageAlert): ?>
    <div class="alert alert-<?= h($pageAlert['type']) ?>"><?= h($pageAlert['message']) ?></div>
<?php endif; ?>

<section class="admin-section-card admin-table-card">
    <div class="admin-section-head">
        <div>
            <h2 class="admin-section-title">排课列表</h2>
            <p class="admin-section-meta">共 <?= count($schedules) ?> 条排课记录</p>
        </div>
    </div>
<div class="table-responsive">
    <table class="table table-bordered table-striped bg-white align-middle">
        <thead>
            <tr>
                <th>课程</th>
                <th>教师</th>
                <th>学年/学期</th>
                <th>星期</th>
                <th>节次</th>
                <th>具体时间</th>
                <th>周次</th>
                <th>教室</th>
                <th>容量</th>
                <th style="width: 220px;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedules as $row): ?>
                <?php
                $teacherLabel = $row['teacher_names'] ?: '未分配教师';
                $slotLabel = '未匹配节次';
                if (!empty($row['slot_start_name']) && !empty($row['slot_end_name'])) {
                    $slotLabel = $row['slot_start_name'];
                    if ((int) $row['slot_start_id'] !== (int) $row['slot_end_id']) {
                        $slotLabel .= ' - ' . $row['slot_end_name'];
                    }
                }
                $classroomLabel = (!empty($row['building']) && !empty($row['room_number']))
                    ? $row['building'] . '-' . $row['room_number']
                    : '未设置';
                ?>
                <tr>
                    <td><?= h($row['course_name']) ?></td>
                    <td><?= h($teacherLabel) ?></td>
                    <td><?= h((string) $row['year']) ?> / <?= h($row['semester']) ?></td>
                    <td><?= h(admin_schedule_day_name((int) $row['day_of_week'])) ?></td>
                    <td><?= h($slotLabel) ?></td>
                    <td><?= h(substr((string) $row['start_time'], 0, 5)) ?> - <?= h(substr((string) $row['end_time'], 0, 5)) ?></td>
                    <td>第 <?= h((string) $row['week_start']) ?> - <?= h((string) $row['week_end']) ?> 周</td>
                    <td><?= h($classroomLabel) ?></td>
                    <td><?= h((string) $row['capacity']) ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="btn btn-sm btn-secondary btn-edit-schedule"
                                data-schedule-id="<?= (int) $row['schedule_id'] ?>"
                                data-course-id="<?= (int) ($row['course_id'] ?? 0) ?>"
                                data-teacher-id="<?= (int) ($row['teacher_id'] ?? 0) ?>"
                                data-year="<?= h((string) $row['year']) ?>"
                                data-semester="<?= h($row['semester']) ?>"
                                data-capacity="<?= (int) $row['capacity'] ?>"
                                data-day-of-week="<?= (int) $row['day_of_week'] ?>"
                                data-slot-start-id="<?= (int) ($row['slot_start_id'] ?? 0) ?>"
                                data-slot-end-id="<?= (int) ($row['slot_end_id'] ?? 0) ?>"
                                data-week-start="<?= (int) $row['week_start'] ?>"
                                data-week-end="<?= (int) $row['week_end'] ?>"
                                data-classroom-id="<?= (int) $row['classroom_id'] ?>"
                            >
                                编辑
                            </button>
                            <form action="<?= h($adminApiUrl) ?>?act=del_schedule" method="post" class="delete-form d-inline">
                                <?= admin_csrf_input() ?>
                                <input type="hidden" name="schedule_id" value="<?= (int) $row['schedule_id'] ?>">
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-danger"
                                    data-confirm="确定删除这条排课记录吗？"
                                    data-confirm-title="删除排课"
                                    data-confirm-text="删除"
                                    data-confirm-class="btn-danger"
                                >
                                    删除
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($schedules)): ?>
                <tr>
                    <td colspan="10" class="admin-empty-row">暂无排课数据</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</section>
</div>

<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">新增排课</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= h($adminApiUrl) ?>?act=add_schedule" method="post" class="ajax-form schedule-form">
                <div class="modal-body">
                    <div class="alert alert-danger d-none modal-error" role="alert"></div>
                    <?= admin_csrf_input() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">课程</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">请选择课程</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= (int) $course['course_id'] ?>"><?= h($course['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">教师</label>
                            <select name="teacher_id" class="form-select" required>
                                <option value="">请选择教师</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= (int) $teacher['user_id'] ?>">
                                        <?= h($teacher['name']) ?>（<?= h($teacher['dept_name']) ?>）
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">学年</label>
                            <input type="number" name="year" class="form-control" min="2000" max="2099" value="<?= date('Y') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">学期</label>
                            <select name="semester" class="form-select" required>
                                <?php foreach ($semesterOptions as $semesterValue => $semesterLabel): ?>
                                    <option value="<?= h($semesterValue) ?>"><?= h($semesterValue) ?> / <?= h($semesterLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">容量</label>
                            <input type="number" name="capacity" class="form-control" min="1" value="50" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">星期</label>
                            <select name="day_of_week" class="form-select" required>
                                <?php for ($day = 1; $day <= 7; $day++): ?>
                                    <option value="<?= $day ?>"><?= h(admin_schedule_day_name($day)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">开始节次</label>
                            <select name="slot_start_id" class="form-select slot-start" required>
                                <?php foreach ($timeSlots as $slot): ?>
                                    <option value="<?= (int) $slot['slot_id'] ?>">
                                        <?= h($slot['slot_name']) ?>（<?= h(substr((string) $slot['start_time'], 0, 5)) ?>）
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">结束节次</label>
                            <select name="slot_end_id" class="form-select slot-end" required>
                                <?php foreach ($timeSlots as $slot): ?>
                                    <option value="<?= (int) $slot['slot_id'] ?>">
                                        <?= h($slot['slot_name']) ?>（<?= h(substr((string) $slot['end_time'], 0, 5)) ?>）
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">开始周</label>
                            <input type="number" name="week_start" class="form-control" min="1" max="<?= $maxWeeks ?>" value="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">结束周</label>
                            <input type="number" name="week_end" class="form-control" min="1" max="<?= $maxWeeks ?>" value="<?= min(16, $maxWeeks) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">教室</label>
                            <select name="classroom_id" class="form-select" required>
                                <option value="">请选择教室</option>
                                <?php foreach ($classrooms as $classroom): ?>
                                    <option value="<?= (int) $classroom['classroom_id'] ?>">
                                        <?= h($classroom['building']) ?>-<?= h($classroom['room_number']) ?>（容量 <?= (int) $classroom['capacity'] ?>）
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">编辑排课</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= h($adminApiUrl) ?>?act=update_schedule" method="post" class="ajax-form schedule-form">
                <div class="modal-body">
                    <div class="alert alert-danger d-none modal-error" role="alert"></div>
                    <?= admin_csrf_input() ?>
                    <input type="hidden" name="schedule_id" id="edit_schedule_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">课程</label>
                            <select name="course_id" id="edit_course_id" class="form-select" required>
                                <option value="">请选择课程</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= (int) $course['course_id'] ?>"><?= h($course['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">教师</label>
                            <select name="teacher_id" id="edit_teacher_id" class="form-select" required>
                                <option value="">请选择教师</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= (int) $teacher['user_id'] ?>">
                                        <?= h($teacher['name']) ?>（<?= h($teacher['dept_name']) ?>）
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">学年</label>
                            <input type="number" name="year" id="edit_year" class="form-control" min="2000" max="2099" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">学期</label>
                            <select name="semester" id="edit_semester" class="form-select" required>
                                <?php foreach ($semesterOptions as $semesterValue => $semesterLabel): ?>
                                    <option value="<?= h($semesterValue) ?>"><?= h($semesterValue) ?> / <?= h($semesterLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">容量</label>
                            <input type="number" name="capacity" id="edit_capacity" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">星期</label>
                            <select name="day_of_week" id="edit_day_of_week" class="form-select" required>
                                <?php for ($day = 1; $day <= 7; $day++): ?>
                                    <option value="<?= $day ?>"><?= h(admin_schedule_day_name($day)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">开始节次</label>
                            <select name="slot_start_id" id="edit_slot_start_id" class="form-select slot-start" required>
                                <?php foreach ($timeSlots as $slot): ?>
                                    <option value="<?= (int) $slot['slot_id'] ?>">
                                        <?= h($slot['slot_name']) ?>（<?= h(substr((string) $slot['start_time'], 0, 5)) ?>）
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">结束节次</label>
                            <select name="slot_end_id" id="edit_slot_end_id" class="form-select slot-end" required>
                                <?php foreach ($timeSlots as $slot): ?>
                                    <option value="<?= (int) $slot['slot_id'] ?>">
                                        <?= h($slot['slot_name']) ?>（<?= h(substr((string) $slot['end_time'], 0, 5)) ?>）
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">开始周</label>
                            <input type="number" name="week_start" id="edit_week_start" class="form-control" min="1" max="<?= $maxWeeks ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">结束周</label>
                            <input type="number" name="week_end" id="edit_week_end" class="form-control" min="1" max="<?= $maxWeeks ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">教室</label>
                            <select name="classroom_id" id="edit_classroom_id" class="form-select" required>
                                <option value="">请选择教室</option>
                                <?php foreach ($classrooms as $classroom): ?>
                                    <option value="<?= (int) $classroom['classroom_id'] ?>">
                                        <?= h($classroom['building']) ?>-<?= h($classroom['room_number']) ?>（容量 <?= (int) $classroom['capacity'] ?>）
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">保存修改</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function syncSlotRange(form) {
        const startEl = form.querySelector('.slot-start');
        const endEl = form.querySelector('.slot-end');
        if (!startEl || !endEl) return;

        const startValue = Number(startEl.value || 0);
        Array.from(endEl.options).forEach(function (option) {
            option.disabled = Number(option.value) < startValue;
        });

        if (Number(endEl.value || 0) < startValue) {
            endEl.value = startEl.value;
        }
    }

    document.querySelectorAll('.schedule-form').forEach(function (form) {
        syncSlotRange(form);
        const startEl = form.querySelector('.slot-start');
        if (startEl) {
            startEl.addEventListener('change', function () {
                syncSlotRange(form);
            });
        }
    });

    document.querySelectorAll('.btn-edit-schedule').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('edit_schedule_id').value = this.dataset.scheduleId || '';
            document.getElementById('edit_course_id').value = this.dataset.courseId || '';
            document.getElementById('edit_teacher_id').value = this.dataset.teacherId || '';
            document.getElementById('edit_year').value = this.dataset.year || '';
            document.getElementById('edit_semester').value = this.dataset.semester || <?= json_encode($defaultSemester, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            document.getElementById('edit_capacity').value = this.dataset.capacity || '';
            document.getElementById('edit_day_of_week').value = this.dataset.dayOfWeek || '1';
            document.getElementById('edit_slot_start_id').value = this.dataset.slotStartId || '';
            document.getElementById('edit_slot_end_id').value = this.dataset.slotEndId || '';
            document.getElementById('edit_week_start').value = this.dataset.weekStart || '1';
            document.getElementById('edit_week_end').value = this.dataset.weekEnd || '16';
            document.getElementById('edit_classroom_id').value = this.dataset.classroomId || '';

            syncSlotRange(document.querySelector('#editModal form'));
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
    });

});
</script>

<?php include 'footer.php'; ?>
