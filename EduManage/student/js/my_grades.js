(function(){
    const API = window.studentGetApiUrl('my_grades');

    const state = {
        semester: 'all',
        sort: 'default',
        dir: 'desc'
    };

    let latestGrades = [];
    let uiBound = false;

    function qs(obj){
        return Object.keys(obj)
            .filter(k => obj[k] !== undefined && obj[k] !== null && obj[k] !== '')
            .map(k => encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]))
            .join('&');
    }

    function escapeHtml(s){
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[c]);
    }

    function semLabelFromKey(key){
        if (!key || key === 'all') return '全部学期';
        const parts = String(key).split('-', 2);
        if (parts.length < 2) return key;
        return window.studentFormatYearSemester(parts[0], parts[1], { withSuffix: false });
    }

    function semLabelFromRow(row){
        const y = row && row.year ? row.year : '';
        const sem = row && row.semester ? window.studentGetSemesterLabel(row.semester, { withSuffix: false }) : '';
        return `${y} ${sem}`.trim();
    }

    function showAlert(type, text){
        const container = document.getElementById('alertsContainer');
        if (!container) return;
        const cls = (type === 'success') ? 'alert-success' : 'alert-error';
        container.innerHTML = `<div class="alert ${cls}">${escapeHtml(text)}</div>`;
        setTimeout(() => {
            if (container.firstChild) container.removeChild(container.firstChild);
        }, 4000);
    }

    function getScoreStyle(score){
        if (score === null || score === undefined || score === '') {
            return { color: '#64748b', bg: '#f1f5f9', border: '#e2e8f0' };
        }
        const n = Number(score);
        if (n >= 90) return { color: '#166534', bg: '#dcfce7', border: '#86efac' };
        if (n >= 75) return { color: '#1d4ed8', bg: '#dbeafe', border: '#93c5fd' };
        if (n >= 60) return { color: '#b45309', bg: '#fef3c7', border: '#fcd34d' };
        return { color: '#b91c1c', bg: '#fee2e2', border: '#fca5a5' };
    }

    function getGpaStyle(gpa){
        if (gpa === null || gpa === undefined || gpa === '') {
            return { color: '#64748b', bg: '#f1f5f9', border: '#e2e8f0' };
        }
        const n = Number(gpa);
        if (n >= 3.7) return { color: '#166534', bg: '#dcfce7', border: '#86efac' };
        if (n >= 3.0) return { color: '#1d4ed8', bg: '#dbeafe', border: '#93c5fd' };
        if (n >= 2.0) return { color: '#b45309', bg: '#fef3c7', border: '#fcd34d' };
        return { color: '#b91c1c', bg: '#fee2e2', border: '#fca5a5' };
    }

    function examTypeInfo(examType){
        const t = String(examType || '').toLowerCase();
        if (t === 'midterm') return { label: '期中', cls: 'badge-gold' };
        if (t === 'quiz') return { label: '测验', cls: 'badge-jade' };
        return { label: '期末', cls: 'badge-rose' };
    }

    function renderTabs(semesters){
        const wrap = document.getElementById('filterTabs');
        if (!wrap) return;

        const list = [{ sem_key: 'all', label: '全部' }].concat(
            (semesters || []).map(s => ({
                sem_key: s.sem_key,
                label: window.studentFormatYearSemester(s.year, s.semester, { withSuffix: false })
            }))
        );

        wrap.innerHTML = list.map(item => {
            const active = item.sem_key === state.semester ? ' active' : '';
            return `<a href="#" class="ftab${active}" data-sem="${escapeHtml(item.sem_key)}">${escapeHtml(item.label)}</a>`;
        }).join('');

        wrap.querySelectorAll('.ftab').forEach(tab => {
            tab.addEventListener('click', function(e){
                e.preventDefault();
                state.semester = this.dataset.sem || 'all';
                load();
            });
        });
    }

    function renderSortState(){
        const resetBtn = document.getElementById('resetSortBtn');
        if (resetBtn) resetBtn.style.visibility = state.sort === 'default' ? 'hidden' : 'visible';

        document.querySelectorAll('.sort-th-link').forEach(link => {
            const col = link.dataset.col;
            const icon = link.querySelector('.sort-icon');
            if (!icon) return;

            if (state.sort === col) {
                icon.textContent = state.dir === 'asc' ? '↑' : '↓';
                icon.classList.remove('inactive');
                icon.classList.add('active');
            } else {
                icon.textContent = '↕';
                icon.classList.remove('active');
                icon.classList.add('inactive');
            }
        });
    }

    function renderSummary(data){
        const gpa = Number(data.gpa || 0);
        const gpaText = Number.isFinite(gpa) ? gpa.toFixed(2) : '0.00';

        const gpaVal = document.getElementById('gpaVal');
        if (gpaVal) {
            gpaVal.textContent = gpaText;
            gpaVal.style.color = data.gpaColorVal || '#94a3b8';
        }

        const semTag = document.getElementById('gpaSemTag');
        if (semTag) semTag.textContent = semLabelFromKey(state.semester);

        const totalCredit = document.getElementById('totalCredit');
        if (totalCredit) totalCredit.textContent = Number(data.total_credit || 0).toFixed(1);

        const finalCount = document.getElementById('finalCount');
        if (finalCount) finalCount.textContent = String(data.final_count || 0);

        const allCount = document.getElementById('allCount');
        if (allCount) allCount.textContent = String(data.grades_count || 0);

        const gradesCount = document.getElementById('gradesCount');
        if (gradesCount) gradesCount.textContent = String(data.grades_count || 0);

        const gpaSummaryCount = document.getElementById('gpaSummaryCount');
        if (gpaSummaryCount) gpaSummaryCount.textContent = String(data.grades_count || 0);

        const gpaSummaryFinalCount = document.getElementById('gpaSummaryFinalCount');
        if (gpaSummaryFinalCount) gpaSummaryFinalCount.textContent = String(data.final_count || 0);

        const gpaSummaryGpa = document.getElementById('gpaSummaryGpa');
        if (gpaSummaryGpa) gpaSummaryGpa.textContent = gpaText;

        const arc = document.getElementById('arc-fg');
        if (arc) {
            arc.style.stroke = data.gpaColorVal || '#94a3b8';
            const total = Number(typeof data.arcTotal !== 'undefined' ? data.arcTotal : 314.16);
            const offset = Number(typeof data.arcOffset !== 'undefined' ? data.arcOffset : total);
            arc.style.strokeDasharray = String(total);
            // set start position to full circle before animating
            arc.style.strokeDashoffset = String(total);
            // force style/layout flush then animate to target offset
            void arc.getBoundingClientRect();
            if (window.requestAnimationFrame) {
                requestAnimationFrame(() => { arc.style.strokeDashoffset = String(offset); });
            } else {
                setTimeout(() => { arc.style.strokeDashoffset = String(offset); }, 20);
            }
        }
    }

    function renderGrades(grades){
        const list = document.getElementById('gradesList');
        if (!list) return;

        list.innerHTML = '';

        if (!grades || grades.length === 0) {
            list.innerHTML = `<div class="empty-state"><div class="es-icon">📄</div><div>暂无成绩记录</div></div>`;
            return;
        }

        grades.forEach(row => {
            const type = examTypeInfo(row.exam_type);
            const scoreStyle = getScoreStyle(row.score);
            const gpaStyle = getGpaStyle(row.gpa_point);

            const scoreText = row.score === null || row.score === undefined ? '-' : Number(row.score).toFixed(1);
            const gpaText = row.gpa_point === null || row.gpa_point === undefined ? '-' : Number(row.gpa_point).toFixed(1);

            const item = document.createElement('div');
            item.className = 'grade-row';
            item.innerHTML = `
                <div class="col-center"><span class="badge ${type.cls}">${type.label}</span></div>
                <div>
                    <div class="gr-course-name">${escapeHtml(row.course_name || '')}</div>
                    <div class="gr-sem">${escapeHtml(semLabelFromRow(row))}</div>
                </div>
                <div class="col-center"><span class="credit-chip">${escapeHtml(row.credit || '0')}</span></div>
                <div class="col-center"><span class="score-chip" style="color:${scoreStyle.color};background:${scoreStyle.bg};border-color:${scoreStyle.border};">${scoreText}</span></div>
                <div class="col-center"><span class="gpa-chip" style="color:${gpaStyle.color};background:${gpaStyle.bg};border-color:${gpaStyle.border};">${gpaText}</span></div>
            `;
            list.appendChild(item);
        });
    }

    function bindSortEvents(){
        document.querySelectorAll('.sort-th-link').forEach(link => {
            link.addEventListener('click', function(e){
                e.preventDefault();
                const col = this.dataset.col;
                if (!col) return;

                if (state.sort === col) {
                    state.dir = state.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sort = col;
                    state.dir = 'desc';
                }
                load();
            });
        });

        const resetBtn = document.getElementById('resetSortBtn');
        if (resetBtn) {
            resetBtn.addEventListener('click', function(e){
                e.preventDefault();
                state.sort = 'default';
                state.dir = 'desc';
                load();
            });
        }
    }

function exportToExcel(){
        if (!latestGrades || latestGrades.length === 0) {
            showAlert('error', '暂无可导出的成绩数据');
            return;
        }

        const header = ['类型', '课程名称', '学分', '成绩', '绩点', '学期'];    
        const rows = latestGrades.map(g => {
            const type = examTypeInfo(g.exam_type).label;
            const term = semLabelFromRow(g);
            return [
                type,
                g.course_name || '',
                g.credit || '',
                g.score == null ? '' : g.score,
                g.gpa_point == null ? '' : g.gpa_point,
                term
            ];
        });

        if (typeof XLSX !== 'undefined') {
            const wsData = [header].concat(rows);
            const ws = XLSX.utils.aoa_to_sheet(wsData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "成绩单");
            const fileName = `成绩单_${state.semester === 'all' ? '全部学期' : state.semester}.xlsx`;
            XLSX.writeFile(wb, fileName);
        } else {
            const wsData = [header].concat(rows);
            let html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>成绩单</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table border="1">';
            wsData.forEach(r => {
                html += '<tr>' + r.map(c => '<td>' + String(c || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</td>').join('') + '</tr>';
            });
            html += '</table></body></html>';
            
            const blob = new Blob([html], {type:'application/vnd.ms-excel;charset=utf-8;'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a'); a.href = url; a.download = `成绩单_${state.semester === 'all' ? '全部学期' : state.semester}.xls`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
        }
    }

    function bindExport(){
        const btn = document.getElementById('exportGradesBtn');
        if (!btn) return;
        btn.addEventListener('click', exportToExcel);
    }

    async function load(){
        try {
            if (window.loadStudentSidebar) {
                window.loadStudentSidebar({ view: 'grades' });
            }

            const params = {
                sort: state.sort,
                dir: state.dir
            };
            if (state.semester && state.semester !== 'all') {
                params.semester = state.semester;
            }

            const query = qs(params);
            const res = await fetch(API + (query ? '?' + query : ''), { credentials: 'include' });
            const payload = await res.json();

            if (!payload || !payload.ok) {
                showAlert('error', (payload && payload.message) ? payload.message : '成绩数据加载失败');
                return;
            }

            if (payload.alerts_html) {
                const ac = document.getElementById('alertsContainer');
                if (ac) ac.innerHTML = payload.alerts_html;
            }

            const data = payload.data || {};
            state.semester = data.filter_sem || state.semester || 'all';
            state.sort = data.sort_by || state.sort || 'default';
            state.dir = data.sort_dir || state.dir || 'desc';

            latestGrades = Array.isArray(data.grades) ? data.grades : [];

            renderTabs(Array.isArray(data.semesters) ? data.semesters : []);
            renderSummary(data);
            renderGrades(latestGrades);
            renderSortState();

            try { document.title = '学生门户'; } catch (e) {}
        } catch (e) {
            console.error(e);
            showAlert('error', '成绩页面加载异常');
        }
    }

    function initMyGrades(){
        if (!uiBound) {
            bindSortEvents();
            bindExport();
            uiBound = true;
        }
        load();
    }

    if (window.__SINGLE_PAGE_APP) {
        window.initGradesData = async function() { return load(); };
        window.initGradesUI = function() {
            if (!uiBound) {
                bindSortEvents();
                bindExport();
                uiBound = true;
            }
        };
    } else {
        document.addEventListener('DOMContentLoaded', initMyGrades);
    }
})();
