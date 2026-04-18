/* schedule.js — 课程表交互逻辑 */

const TOTAL_WEEKS = 16;
let currentView = 'timetable';
let currentWeek = 0; // 0 = 全部学期

document.addEventListener('DOMContentLoaded', () => {
    buildWeekPills();
    updateWeekUI();
    applyWeekFilter(); // 初始状态：全部显示
    setupTooltips();
    updateClock();
    setInterval(updateClock, 1000);
});

/* ===== 视图切换 ===== */
function switchView(v) {
    currentView = v;
    document.getElementById('view-timetable').style.display = v === 'timetable' ? '' : 'none';
    document.getElementById('view-overview').style.display  = v === 'overview'  ? '' : 'none';
    document.getElementById('week-controls').style.display  = v === 'timetable' ? 'flex' : 'none';
    document.getElementById('btn-tt').classList.toggle('active', v === 'timetable');
    document.getElementById('btn-ov').classList.toggle('active', v === 'overview');
}

/* ===== 周导航 ===== */
function changeWeek(delta) {
    const next = currentWeek + delta;
    if (next < 0 || next > TOTAL_WEEKS) return;
    currentWeek = next;
    updateWeekUI();
    applyWeekFilter();
}

function setWeek(w) {
    currentWeek = w;
    updateWeekUI();
    applyWeekFilter();
}

function updateWeekUI() {
    document.getElementById('week-label').textContent =
        currentWeek === 0 ? '全部学期' : '第 ' + currentWeek + ' 周';

    document.getElementById('btn-prev').disabled = (currentWeek === 0);
    document.getElementById('btn-next').disabled = (currentWeek === TOTAL_WEEKS);

    // 更新小方块高亮
    document.querySelectorAll('.week-pill').forEach(p => {
        p.classList.toggle('active', parseInt(p.dataset.week) === currentWeek);
    });

    // 更新底部说明
    const note = document.getElementById('week-note-text');
    if (note) {
        note.textContent = currentWeek === 0
            ? '显示全部学期 — 各课程块标注起止周数'
            : '第 ' + currentWeek + ' 周 — 不在本周开课的课程已隐藏';
    }
}

function buildWeekPills() {
    const c = document.getElementById('week-pills');
    if (!c) return;

    // "全部"胶囊
    const all = makePill('全部', 0, true);
    all.classList.add('all-pill');
    c.appendChild(all);

    for (let w = 1; w <= TOTAL_WEEKS; w++) {
        c.appendChild(makePill(String(w), w, false));
    }
}

function makePill(label, week, isActive) {
    const p = document.createElement('button');
    p.className = 'week-pill' + (isActive ? ' active' : '');
    p.dataset.week = String(week);
    p.textContent = label;
    p.title = week === 0 ? '显示全部学期' : '第 ' + week + ' 周';
    p.onclick = () => setWeek(week);
    return p;
}

/* ===== 周筛选核心逻辑 =====
   - currentWeek === 0：全部显示，显示 [起止周] 标签
   - currentWeek > 0：只显示该周有课的课程块，隐藏 [起止周] 标签（因为已在导航栏体现）
*/
function applyWeekFilter() {
    document.querySelectorAll('.course-block').forEach(block => {
        const ws = parseInt(block.dataset.weekStart || '1');
        const we = parseInt(block.dataset.weekEnd   || '16');

        if (currentWeek === 0) {
            // 全部显示
            block.classList.remove('hidden-week');
            // 显示起止周标签
            const wl = block.querySelector('[data-weeks-label]');
            if (wl) wl.style.display = '';
        } else {
            // 判断该课程在本周是否开课
            const active = (currentWeek >= ws && currentWeek <= we);
            if (active) {
                block.classList.remove('hidden-week');
            } else {
                block.classList.add('hidden-week');
            }
            // 指定周时隐藏起止周标签
            const wl = block.querySelector('[data-weeks-label]');
            if (wl) wl.style.display = 'none';
        }
    });
}

/* ===== 悬浮提示框 ===== */
function setupTooltips() {
    const tip = document.getElementById('tooltip');
    if (!tip) return;

    // 使用事件委托，避免被 hidden-week 的 display:none 影响
    document.addEventListener('mouseover', e => {
        const block = e.target.closest('.course-block:not(.hidden-week)');
        if (!block) return;
        try {
            const d = JSON.parse(block.dataset.course);
            document.getElementById('tt-name').textContent    = d.name    || '';
            document.getElementById('tt-day').textContent     = '📆 ' + (d.day   || '');
            document.getElementById('tt-time').textContent    = '⏰ ' + (d.time  || '');
            document.getElementById('tt-room').textContent    = '📍 ' + (d.room  || '待定');
            document.getElementById('tt-teacher').textContent = '👤 ' + (d.teacher || '待定');
            document.getElementById('tt-weeks').textContent   = '📅 第 ' + d.weekStart + '–' + d.weekEnd + ' 周';
            document.getElementById('tt-credit').textContent  = '⭐ ' + d.credit + ' 学分';
            tip.classList.add('visible');
        } catch(e) {}
    });

    document.addEventListener('mousemove', e => {
        if (!tip.classList.contains('visible')) return;
        let x = e.clientX + 18;
        let y = e.clientY + 18;
        if (x + 270 > window.innerWidth)  x = e.clientX - 274;
        if (y + 200 > window.innerHeight) y = e.clientY - 204;
        tip.style.left = x + 'px';
        tip.style.top  = y + 'px';
    });

    document.addEventListener('mouseout', e => {
        const block = e.target.closest('.course-block');
        if (block && !block.contains(e.relatedTarget)) {
            tip.classList.remove('visible');
        }
    });
}

/* ===== 实时时钟 ===== */
function updateClock() {
    const el = document.getElementById('clock');
    if (!el) return;
    const now = new Date();
    const p = n => String(n).padStart(2, '0');
    el.textContent = now.getFullYear() + '-' + p(now.getMonth()+1) + '-' + p(now.getDate())
        + ' ' + p(now.getHours()) + ':' + p(now.getMinutes()) + ':' + p(now.getSeconds());
}