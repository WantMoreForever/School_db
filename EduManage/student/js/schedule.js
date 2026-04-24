/* Frontend renderer for student/schedule.html
   - Fetches the student schedule API (credentials included)
   - Injects sidebar and alerts HTML
   - Renders timetable and overview list
*/

let TOTAL_WEEKS = 16;
let SEMESTER_START_STR = null;

(function(){
const API = window.studentGetApiUrl('schedule');

    function el(html){
        const div = document.createElement('div');
        div.innerHTML = html;
        return div.firstElementChild;
    }

    function timeToMins(t){ if(!t) return 0; const [h,m]=t.split(':').map(Number); return h*60+m; }
    function fmtTime(t){ if(!t) return '未知'; return t.substr(0,5); }

    function buildHeader(timetable, data){
        // th-gutter
        const gutter = document.createElement('div');
        gutter.className = 'th-gutter';
        gutter.innerHTML = '<div class="th-gutter-inner">时间</div>';
        timetable.appendChild(gutter);

        const DAYS_CN = [null,'周一','周二','周三','周四','周五','周六','周日'];
        const DAYS_EN = [null,'Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
        for(let d=1;d<=7;d++){
            const th = document.createElement('div');
            th.className = 'th-day';
            th.innerHTML = '<div class="day-name">'+DAYS_CN[d]+'</div><div class="day-en">'+DAYS_EN[d]+'</div>';
            timetable.appendChild(th);
        }
    }

    function buildGrid(timetable, cfg){
        const {grid_start_h, grid_end_h, row_px} = cfg;
        const totalRows = grid_end_h - grid_start_h;

        for(let row=0; row<totalRows; row++){
            const rowHour = grid_start_h + row;
            const isLast = (row === totalRows-1);
            const slotTime = document.createElement('div');
            slotTime.className = 'slot-time' + (isLast? ' last-row':'');
            slotTime.innerHTML = '<span class="st-label">'+String(rowHour).padStart(2,'0')+':00</span>';
            timetable.appendChild(slotTime);

            for(let d=1; d<=7; d++){
                const cell = document.createElement('div');
                cell.className = 'slot-cell' + (isLast? ' last-row':'');
                cell.dataset.day = d;
                cell.dataset.hour = rowHour;
                timetable.appendChild(cell);
            }
        }
    }

    function renderCourses(timetable, data, cfg, selectedWeek=0){
        const {grid_start_h, row_px} = cfg;
        // place course blocks into correct cell
        const blocks = [];
        Object.keys(data.grid).forEach(dayKey => {
            const list = data.grid[dayKey] || [];
            list.forEach(s => {
                if (!s.start_time || !s.end_time || !s.day_of_week) return; // skip unscheduled courses in timetable
                const startM = timeToMins(s.start_time);
                const endM = timeToMins(s.end_time);
                const rowHour = Math.floor(startM/60);
                const rowIndex = rowHour - grid_start_h;
                if (rowIndex < 0) return; // out of grid

                const minutes_into_hour = startM - rowHour*60;
                const topOffset = Math.round((minutes_into_hour/60)*row_px*10)/10;
                const heightPx = Math.max(24, Math.round(((endM - startM)/60)*row_px - 4));

                const colorC = 'cb-' + (s.color_idx || 0);
                const courseBlock = document.createElement('div');
                courseBlock.className = 'course-block '+colorC;
                courseBlock.style.top = topOffset + 'px';
                courseBlock.style.height = heightPx + 'px';
                courseBlock.dataset.weekStart = Number(s.week_start);
                courseBlock.dataset.weekEnd   = Number(s.week_end);
                courseBlock.dataset.course = JSON.stringify({name:s.course_name, room:s.location, teacher:s.teacher, time: fmtTime(s.start_time)+' ~ '+fmtTime(s.end_time), weekStart: s.week_start, weekEnd: s.week_end, day: s.day_of_week, credit: s.credit});

                let inner = '<div class="cb-name">'+escapeHtml(s.course_name)+'</div>';
                inner += '<div class="cb-time">🕒 '+fmtTime(s.start_time)+'~'+fmtTime(s.end_time)+'</div>';
                if (s.location) inner += '<div class="cb-room">📍 '+escapeHtml(s.location)+'</div>';
                inner += '<div class="cb-weeks-badge" data-weeks-label>第 '+Number(s.week_start)+'-'+Number(s.week_end)+' 周</div>';
                courseBlock.innerHTML = inner;
                courseBlock.style.cursor = 'pointer';

                // find the target cell for rowHour and day
                const selector = '.slot-cell[data-day="'+s.day_of_week+'"][data-hour="'+rowHour+'"]';
                const targetCell = timetable.querySelector(selector);
                if (targetCell) {
                    targetCell.appendChild(courseBlock);
                } else {
                    // fallback: append to timetable
                    timetable.appendChild(courseBlock);
                }

                blocks.push(courseBlock);
            });
        });

        blocks.forEach(b => {
            b.addEventListener('click', (ev)=>{
                ev.stopPropagation();
                const t = document.getElementById('tooltip');
                let info = {};
                try { info = JSON.parse(b.dataset.course || '{}'); } catch(e) { info = {}; }
                if (!t) return;

                if (t.classList.contains('visible') && t._openBlock === b) {
                    t.classList.remove('visible');
                    t.style.display = 'none';
                    t._openBlock = null;
                    return;
                }

                t._openBlock = b;
                t.style.display = 'block';
                t.querySelector('#tt-name').textContent = info.name || '';
                const DAYS_CN_SHORT = ['','周一','周二','周三','周四','周五','周六','周日'];
                t.querySelector('#tt-day').textContent = DAYS_CN_SHORT[Number(info.day) || 0] || '';
                t.querySelector('#tt-time').textContent = info.time || '';
                t.querySelector('#tt-room').textContent = info.room || '';
                t.querySelector('#tt-teacher').textContent = info.teacher || '';
                if (typeof currentWeek !== 'undefined' && currentWeek !== 0) {
                    t.querySelector('#tt-weeks').textContent = '第 ' + currentWeek + ' 周';
                } else {
                    t.querySelector('#tt-weeks').textContent = '第 '+info.weekStart+'-'+info.weekEnd+' 周';
                }
                t.querySelector('#tt-credit').textContent = (info.credit? ('★ '+info.credit+' 学分') : '');

                const r = b.getBoundingClientRect();
                const tipW = 280;
                let left = Math.max(8, r.right + 8);
                if (left + tipW > window.innerWidth) left = Math.max(8, r.left - tipW - 8);

                // Tooltip is positioned with CSS `position: fixed`, so compute
                // vertical placement using viewport coordinates (getBoundingClientRect).
                // Use the tooltip's rendered height if available, otherwise fall
                // back to a sensible default.
                const tipH = (t.offsetHeight && t.offsetHeight > 0) ? t.offsetHeight : 200;
                let top = r.top; // viewport-relative
                if (top + tipH > window.innerHeight) {
                    top = Math.max(8, r.bottom - tipH);
                }

                t.style.left = left + 'px';
                t.style.top = top + 'px';
                t.classList.add('visible');
            });
        });

        // week filter if selectedWeek > 0
        if (selectedWeek > 0){
            blocks.forEach(b=>{
                const ws = Number(b.dataset.weekStart||0), we = Number(b.dataset.weekEnd||0);
                b.style.display = (selectedWeek >= ws && selectedWeek <= we) ? '' : 'none';
            });
        }
    }

    function renderOverview(container, data){
        container.innerHTML = '';
        // group by course_id
        const grouped = {};
        Object.values(data.grid).flat().forEach(s=>{ if(!grouped[s.course_id]) grouped[s.course_id]=[]; grouped[s.course_id].push(s); });
        let idx = 0;
        for(const cid in grouped){
            const scheds = grouped[cid];
            const first = scheds[0];
            const colorC = 'cb-'+(first.color_idx||0);
            const item = document.createElement('div');
            item.className = 'ov-item fade-up';
            item.style.animationDelay = (idx*0.06)+'s';
            
            item.innerHTML = `
                <div class="ov-color-dot ${colorC}">📚</div>
                <div class="ov-body">
                    <div class="ov-name">${escapeHtml(first.course_name)}</div>
                    <div class="ov-tags">
                        <span class="ov-tag">👤 ${escapeHtml(first.teacher||'未知')}</span>
                        <span class="ov-tag">★ ${first.credit} 学分</span>
                        <span class="ov-weeks-tag">📅 第 ${first.week_start}-${first.week_end} 周</span>
                    </div>
                </div>
                <div class="ov-right">
                    <div class="ov-schedule-rows">
                        ${scheds.filter(sc => sc.day_of_week).map(sc=>`<div class="ov-sched-row"><div class="ov-time-str">${fmtTime(sc.start_time)}~${fmtTime(sc.end_time)}</div><div class="ov-day-badge ${colorC}">${['','周一','周二','周三','周四','周五','周六','周日'][sc.day_of_week] || '未知'}</div></div>`).join('')}
                        ${scheds.filter(sc => !sc.day_of_week).length > 0 ? `<div class="ov-sched-row"><div class="ov-time-str">未安排时间</div><div class="ov-day-badge ${colorC}">时间未定</div></div>` : ''}
                    </div>
                    <div class="ov-location">📍 ${escapeHtml(first.location||'未知')}</div>
                </div>
            `;
            container.appendChild(item);
            idx++;
        }
    }

    function escapeHtml(s){ if(!s) return ''; return String(s).replace(/[&<>"']/g,function(c){return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

    // week pills and navigation
    function buildWeekPills(pillsContainer, totalWeeks, onSelect){
        pillsContainer.innerHTML = '';
        const all = document.createElement('button'); all.className='week-pill all-pill active'; all.textContent='全部'; all.addEventListener('click', ()=>onSelect(0));
        pillsContainer.appendChild(all);
        for(let w=1; w<=totalWeeks; w++){
            const b = document.createElement('button'); b.className='week-pill'; b.textContent = w; b.addEventListener('click', ()=>onSelect(w));
            pillsContainer.appendChild(b);
        }
    }

    // main init (data fetch + render)
    function initScheduleData() {
        if (window.loadStudentSidebar) {
            window.loadStudentSidebar({ view: 'schedule' });
        }
        const alertsContainer = document.getElementById('alertsContainer');
        const timetable = document.getElementById('timetable');
        const semesterBanner = document.getElementById('semesterBanner');
        const semesterLabel = document.getElementById('semesterLabel');
        const scheduleToolbar = document.getElementById('scheduleToolbar');
        const viewTimetable = document.getElementById('view-timetable');
        const viewOverview = document.getElementById('view-overview');
        const overviewList = document.getElementById('overviewList');
        const weekPills = document.getElementById('week-pills');
        const weekLabel = document.getElementById('week-label');

        fetch(API, { credentials: 'include' }).then(r=>r.json()).then(resp=>{
            if(!resp || !resp.ok){ console.error('API error', resp); return; }
            const data = resp.data || {};
            if(alertsContainer) alertsContainer.innerHTML = resp.alerts_html || '';

            const hasSchedules = Object.values(data.grid||{}).flat().length > 0;
            if(!hasSchedules){ const noEl = document.getElementById('noStudentNotice'); if(noEl) noEl.style.display = ''; return; }

            if (semesterLabel) semesterLabel.textContent = data.semester_label || '';
            if (semesterBanner) semesterBanner.style.display = '';
            if (scheduleToolbar) scheduleToolbar.style.display = '';
            if (viewTimetable) viewTimetable.style.display = '';

            buildHeader(timetable, data);
            buildGrid(timetable, {grid_start_h: data.grid_start_h, grid_end_h: data.grid_end_h, row_px: data.row_px});
            renderCourses(timetable, data, {grid_start_h: data.grid_start_h, row_px: data.row_px}, 0);

            renderOverview(overviewList, data);

            // update TOTAL_WEEKS from server and build pills (if container exists)
            if (typeof TOTAL_WEEKS !== 'undefined') TOTAL_WEEKS = Number(data.total_weeks) || TOTAL_WEEKS;
            if (data.semester_start) SEMESTER_START_STR = data.semester_start;
            if (typeof updateWeekUI === 'function') updateWeekUI();
            if (weekPills) {
                buildWeekPills(weekPills, data.total_weeks, (selectedWeek)=>{
                    if (!weekPills) return;
                    [...weekPills.children].forEach(c=>c.classList.remove('active'));
                    const btn = [...weekPills.children].find(c=> (selectedWeek===0? c.classList.contains('all-pill') : c.textContent==String(selectedWeek)) );
                    if(btn) btn.classList.add('active');
                    // delegate actual week state change to global API so there's one source of truth
                    if (typeof setWeek === 'function') setWeek(selectedWeek);
                });
            }

            const btnTt = document.getElementById('btn-tt');
            const btnOv = document.getElementById('btn-ov');
            if (btnTt) btnTt.addEventListener('click', ()=>{
                if (typeof switchView === 'function') {
                    switchView('timetable');
                } else {
                    if(viewTimetable) viewTimetable.style.display='';
                    if(viewOverview) viewOverview.style.display='none';
                    btnTt.classList.add('active'); if(btnOv) btnOv.classList.remove('active');
                }
            });
            if (btnOv) btnOv.addEventListener('click', ()=>{
                if (typeof switchView === 'function') {
                    switchView('overview');
                } else {
                    if(viewTimetable) viewTimetable.style.display='none';
                    if(viewOverview) viewOverview.style.display='';
                    btnOv.classList.add('active'); if(btnTt) btnTt.classList.remove('active');
                }
            });

            const btnPrev = document.getElementById('btn-prev');
            const btnNext = document.getElementById('btn-next');
            // use global changeWeek so behaviour is consistent
            if (btnPrev) btnPrev.addEventListener('click', ()=>{ if (typeof changeWeek === 'function') changeWeek(-1); });
            if (btnNext) btnNext.addEventListener('click', ()=>{ if (typeof changeWeek === 'function') changeWeek(1); });

            if (typeof switchView === 'function') switchView('timetable');

        }).catch(err=>{ console.error('fetch error', err); });
    }

    if (window.__SINGLE_PAGE_APP) {
        window.initScheduleData = initScheduleData;
    } else {
        document.addEventListener('DOMContentLoaded', initScheduleData);
    }
})();
/* schedule.js 课程表渲染逻辑 */
let currentView = 'timetable';
let currentWeek = 0; // 0 = 全部

function initScheduleUI() {
    updateWeekUI();
    applyWeekFilter(); 
    setupTooltips();
    updateClock();
    setInterval(updateClock, 1000);
    try {
        const exportBtn = document.getElementById('exportScheduleBtn');
        if (exportBtn) exportBtn.addEventListener('click', exportScheduleToExcel);
    } catch (e) { /* noop */ }
}

if (window.__SINGLE_PAGE_APP) {
    window.initScheduleUI = initScheduleUI;
} else {
    document.addEventListener('DOMContentLoaded', initScheduleUI);
}

/* ===== 视图切换 ===== */
function switchView(v) {
    currentView = v;
    document.getElementById('view-timetable').style.display = v === 'timetable' ? '' : 'none';
    document.getElementById('view-overview').style.display  = v === 'overview'  ? '' : 'none';
    document.getElementById('week-controls').style.display  = v === 'timetable' ? 'flex' : 'none';
    document.getElementById('btn-tt').classList.toggle('active', v === 'timetable');
    document.getElementById('btn-ov').classList.toggle('active', v === 'overview');
}

/* ===== 周次控制 ===== */
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
        currentWeek === 0 ? '全部教学周' : '第 ' + currentWeek + ' 周';
        
    document.getElementById('btn-prev').disabled = (currentWeek === 0);
    document.getElementById('btn-next').disabled = (currentWeek === TOTAL_WEEKS);

    const DAYS_EN = [null, 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const thDays = document.querySelectorAll('.th-day');
    if (thDays.length === 7) {
        if (currentWeek > 0 && SEMESTER_START_STR) {
            const startParts = SEMESTER_START_STR.split('-');
            if (startParts.length === 3) {
                // Parse the start date as local time at noon to avoid timezone shift dropping a day
                const baseDate = new Date(parseInt(startParts[0]), parseInt(startParts[1])-1, parseInt(startParts[2]), 12, 0, 0);
                thDays.forEach((th, idx) => {
                    const d = idx + 1; // 1 to 7
                    // Calculate new date
                    const targetDate = new Date(baseDate.getTime() + ((currentWeek - 1) * 7 + (d - 1)) * 24 * 3600 * 1000);
                    const mm = String(targetDate.getMonth() + 1).padStart(2, '0');
                    const dd = String(targetDate.getDate()).padStart(2, '0');
                    const dayEn = th.querySelector('.day-en');
                    if (dayEn) dayEn.textContent = `${mm}-${dd}`;
                });
            }
        } else {
            // Restore English names when showing all weeks
            thDays.forEach((th, idx) => {
                const dayEn = th.querySelector('.day-en');
                if (dayEn) dayEn.textContent = DAYS_EN[idx + 1];
            });
        }
    }

    document.querySelectorAll('.week-pill').forEach(p => {
        let w = 0;
        if (p.dataset && p.dataset.week) {
            w = parseInt(p.dataset.week, 10);
        } else if (p.classList.contains('all-pill')) {
            w = 0;
        } else {
            w = parseInt(p.textContent, 10) || 0;
        }
        p.classList.toggle('active', w === currentWeek);
    });

    const note = document.getElementById('week-note-text');
    if (note) {
        note.textContent = currentWeek === 0
            ? '显示全部学周。由于课程可能会重叠，请注意查看。'
            : '第 ' + currentWeek + ' 周 - 仅显示本周开课的课程。';
    }
}

/* ===== 本周内课程筛选 ===== */
function applyWeekFilter() {
    document.querySelectorAll('.course-block').forEach(block => {
        const ws = parseInt(block.dataset.weekStart || '1', 10);
        const we = parseInt(block.dataset.weekEnd   || '16', 10);
        const wl = block.querySelector('[data-weeks-label]');

        if (currentWeek === 0) {
            block.classList.remove('hidden-week');
            if (wl) { wl.style.display = ''; wl.textContent = '第 '+ws+'-'+we+' 周'; }
        } else {
            const active = (currentWeek >= ws && currentWeek <= we);
            if (active) {
                block.classList.remove('hidden-week');
                if (wl) { wl.style.display = ''; wl.textContent = '第 '+currentWeek+' 周'; }
            } else {
                block.classList.add('hidden-week');
                if (wl) wl.style.display = 'none';
            }
        }
    });
}

/* ===== 交互提示 ===== */
function setupTooltips() {
    const tip = document.getElementById('tooltip');
    if (!tip) return;
    document.addEventListener('click', function(e){
        const clickedBlock = e.target.closest && e.target.closest('.course-block');
        const clickedTip = e.target.closest && e.target.closest('#tooltip');
        if (clickedBlock) return; 
        if (clickedTip) return;   
        if (tip.classList.contains('visible')) {
            tip.classList.remove('visible');
            tip.style.display = 'none';
            tip._openBlock = null;
        }
    });

    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' || e.key === 'Esc'){
            if (tip.classList.contains('visible')) {
                tip.classList.remove('visible');
                tip.style.display = 'none';
                tip._openBlock = null;
            }
        }
    });
}

/* ===== 实时时间 ===== */
function updateClock() {
    const el = document.getElementById('clock');
    if (!el) return;
    const now = new Date();
    const p = n => String(n).padStart(2, '0');
    el.textContent = now.getFullYear() + '-' + p(now.getMonth()+1) + '-' + p(now.getDate())
        + ' ' + p(now.getHours()) + ':' + p(now.getMinutes()) + ':' + p(now.getSeconds());
}

/* ===== 导出课程表为 Excel/CSV ===== */
function getDayNameCN(d){ return ['','周一','周二','周三','周四','周五','周六','周日'][d] || ''; }

function exportScheduleToExcel(){
    const blocks = Array.from(document.querySelectorAll('.course-block'));
    if (!blocks.length) { 
        showScheduleAlert('error', '没有可导出的课程信息'); 
        return; 
    }

    const rows = [['课程名称','教师','星期','开始','结束','地点','起始周','结束周','学分']];
    const seen = new Set();

    blocks.forEach(b=>{
        if (b.classList.contains('hidden-week')) return;
        let info = {};
        try { info = JSON.parse(b.dataset.course || '{}'); } catch(e){ info = {}; }
        const day = Number(info.day) || Number(b.dataset.day) || 0;
        const time = info.time || '';
        const times = time.split(/~|-|\//).map(s=>s.trim());
        const start = times[0] || '';
        const end = times[1] || '';
        const ws = b.dataset.weekStart || info.weekStart || '';
        const we = b.dataset.weekEnd || info.weekEnd || '';

        const key = [info.name, day, start, end, info.room, ws, we].join('|');
        if (seen.has(key)) return; 
        seen.add(key);

        rows.push([info.name||'', info.teacher||'', getDayNameCN(day), start, end, info.room||'', ws, we, info.credit||'']);
    });

    if (typeof XLSX !== 'undefined'){
        try{
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(rows);
            XLSX.utils.book_append_sheet(wb, ws, '课表');
            const now = new Date(); const pad = n=>String(n).padStart(2,'0');
            const fname = `课表.xlsx`;
            XLSX.writeFile(wb, fname);
            return;
        }catch(e){ console.error('XLSX export failed', e); }
    }

    try{
        let html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>课表</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table border="1">';
        rows.forEach(r => {
            html += '<tr>' + r.map(c => '<td>' + String(c || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</td>').join('') + '</tr>';
        });
        html += '</table></body></html>';
        
        const blob = new Blob([html], {type:'application/vnd.ms-excel;charset=utf-8;'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = '课表.xls'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    }catch(e){ showScheduleAlert('error', '导出失败，请在控制台查看详情'); console.error(e); }
}

function showScheduleAlert(type, text) {
    const container = document.getElementById('alertsContainer');
    if (!container) return;
    const cls = (type === 'success') ? 'alert-success fade-up' : 'alert-error fade-up';
    const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
    const div = document.createElement('div');
    div.className = cls;
    div.innerHTML = escapeHtml(text);
    container.insertBefore(div, container.firstChild);
    setTimeout(() => { if(div.parentNode) div.parentNode.removeChild(div); }, 4000);
}
