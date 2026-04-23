(function(){
    const API = window.studentGetApiUrl('course_select');

    function qs(obj){ return Object.keys(obj).map(k=>encodeURIComponent(k)+'='+encodeURIComponent(obj[k])).join('&'); }
    function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

    function showAlert(type, text){
        if (window.StudentApi && window.StudentApi.showAlert) {
            window.StudentApi.showAlert(type, text, document.getElementById('alertsContainer'));
            return;
        }
        const container = document.getElementById('alertsContainer');
        if (!container) return;
        const cls = (type==='success') ? 'alert-success' : 'alert-error';
        container.innerHTML = `<div class="alert ${cls}">${escapeHtml(text)}</div>`;
        setTimeout(()=>{ if(container.firstChild) container.removeChild(container.firstChild); }, 4000);
    }

    function inlineConfirm(triggerBtn, message) {
        if (window.StudentApi && window.StudentApi.confirm) {
            return window.StudentApi.confirm(message);
        }

        return new Promise(resolve => {
            const overlay = document.createElement('div');
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100vw';
            overlay.style.height = '100vh';
            overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.4)';
            overlay.style.zIndex = '9999';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.opacity = '0';
            overlay.style.transition = 'all 0.2s ease-in-out';

            const modal = document.createElement('div');
            modal.style.backgroundColor = '#fff';
            modal.style.borderRadius = '12px';
            modal.style.padding = '24px';
            modal.style.minWidth = '320px';
            modal.style.boxShadow = '0 10px 25px rgba(0,0,0,0.1)';
            modal.style.transform = 'translateY(-20px)';
            modal.style.transition = 'all 0.2s ease-in-out';

            const title = document.createElement('div');
            title.textContent = '操作确认';
            title.style.fontSize = '18px';
            title.style.fontWeight = '600';
            title.style.marginBottom = '12px';
            title.style.color = 'var(--ink-color)';

            const msg = document.createElement('div');
            msg.textContent = message;
            msg.style.fontSize = '15px';
            msg.style.color = 'var(--ink-muted)';
            msg.style.marginBottom = '24px';
            msg.style.lineHeight = '1.5';

            const actionRow = document.createElement('div');
            actionRow.style.display = 'flex';
            actionRow.style.justifyContent = 'flex-end';
            actionRow.style.gap = '12px';

            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'btn btn-outline';
            cancelBtn.textContent = '取消';
            cancelBtn.style.padding = '8px 20px';

            const okBtn = document.createElement('button');
            okBtn.className = 'btn btn-primary';
            okBtn.textContent = '确认';
            okBtn.style.padding = '8px 24px';
            okBtn.style.background = 'linear-gradient(135deg, #3b82f6, #2563eb)';
            okBtn.style.boxShadow = '0 4px 12px rgba(37, 99, 235, 0.25)';
            okBtn.style.border = 'none';

            actionRow.appendChild(cancelBtn);
            actionRow.appendChild(okBtn);

            modal.appendChild(title);
            modal.appendChild(msg);
            modal.appendChild(actionRow);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            void overlay.offsetWidth;
            overlay.style.opacity = '1';
            modal.style.transform = 'translateY(0)';

            const cleanup = (res) => {
                overlay.style.opacity = '0';
                modal.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    if (overlay.parentNode) document.body.removeChild(overlay);
                    resolve(res);
                }, 200);
            };

            okBtn.onclick = () => cleanup(true);
            cancelBtn.onclick = () => cleanup(false);

            overlay.onclick = (e) => {
                if (e.target === overlay) cleanup(false);
            };

            setTimeout(() => { try { okBtn.focus(); } catch(e){} }, 50);
        });
    }

    async function apiFetch(method='GET', body=null){
        const opts = { method, credentials: 'include' };
        if (body && method.toUpperCase() === 'POST') {
            // If available, attach global CSRF token exposed by profile API
            try { if (window && window.__CSRF_TOKEN) body.csrf_token = window.__CSRF_TOKEN; } catch(e) {}
            opts.headers = {'Content-Type':'application/x-www-form-urlencoded'};
            opts.body = qs(body);
        }
        const r = await fetch(API, opts);
        const data = await r.json().catch(() => ({ ok: false, message: '请求未能返回 JSON 响应' }));
        if (!r.ok && window.StudentApi) {
            data.ok = false;
            data.success = false;
            data.message = window.StudentApi.messageOf(data, '请求失败');
        }
        return data;
    }

    function renderOverview(data){
        const myCoursesCount = Array.isArray(data.my_courses) ? data.my_courses.length : 0;
        const myCourseCountEl = document.getElementById('myCourseCount');
        if (myCourseCountEl) myCourseCountEl.textContent = String(myCoursesCount);

        const myCreditsEl = document.getElementById('myCredits');
        if (myCreditsEl) myCreditsEl.textContent = (typeof data.my_credits !== 'undefined' && data.my_credits !== null) ? Number(data.my_credits).toFixed(1) : '0.0';

        const availEl = document.getElementById('availableCount');
        if (availEl) availEl.textContent = String(Array.isArray(data.available_sections) ? data.available_sections.length : 0);

        const ovDescEl = document.getElementById('ovDesc');
        if (ovDescEl) ovDescEl.textContent = window.studentFormatYearSemester(data.current_year, data.current_semester);

        const semesterLabelEl = document.getElementById('semesterLabel') || document.getElementById('yearSemester');
        if (semesterLabelEl) semesterLabelEl.textContent = window.studentFormatYearSemester(data.current_year, data.current_semester);

        try { document.title = '学生门户'; } catch(e){}
    }

    function createCell(html){ const td = document.createElement('td'); td.innerHTML = html; return td; }

    function formatSchedules(schedules) {
        if (!schedules || schedules.length === 0) return "未安排时间";
        const days = ["","周一","周二","周三","周四","周五","周六","周日"];
        return schedules.map(s => {
            const st = (s.start_time||"").substring(0,5);
            const ed = (s.end_time||"").substring(0,5);
            const loc = escapeHtml(s.location||"");
            return days[s.day_of_week] + " " + st + "-" + ed + (loc ? " ("+loc+")" : "");
        }).join("<br>");
    }

    function parseDateTime(raw) {
        if (!raw) return NaN;
        return Date.parse(String(raw).replace(/-/g, '/'));
    }

    function getWindowState(start, end) {
        const startTs = parseDateTime(start);
        const endTs = parseDateTime(end);
        if (!Number.isFinite(startTs) || !Number.isFinite(endTs)) return 'not_started';
        const nowTs = Date.now();
        if (nowTs < startTs) return 'not_started';
        if (nowTs > endTs) return 'closed';
        return 'open';
    }

    function renderAvailable(data){
        const tbody = document.getElementById('availableTbody'); tbody.innerHTML='';
        (data.available_sections||[]).forEach(sec=>{
            const tr = document.createElement('tr');
            // name
            const tdName = document.createElement('td');
            const schedHtml = `<div style="font-size:12px;color:var(--ink-muted);margin-top:5px;line-height:1.4;">${formatSchedules(sec.schedules)}</div>`;
            tdName.innerHTML = `<div style="font-weight:500;margin-bottom:2px;">${escapeHtml(sec.course_name||'')}</div>` + schedHtml + ((parseInt(sec.conflict_flag) === 1 && parseInt(sec.is_my_course) === 0) ? `<div style="font-size:12px;color:#b42318;margin-top:4px;font-weight:500;">时间冲突</div>` : '');
            tr.appendChild(tdName);
            // teacher
            tr.appendChild(createCell(escapeHtml(sec.teacher_name || '未知')));
            // credit
            tr.appendChild(createCell(`<span style="font-family:'JetBrains Mono';font-weight:600;">${escapeHtml(sec.credit||'0')}</span>`));
            // capacity
            const capHtml = (sec.enrolled_count >= sec.capacity) ? '<span class="cs-badge badge-red">已满员</span>' : `<span class="capacity-num"><span class="capacity-avail">${sec.capacity - sec.enrolled_count}</span> / ${sec.capacity}</span>`;
            tr.appendChild(createCell(capHtml));
            // time hint
            const windowState = getWindowState(sec.enrollment_start, sec.enrollment_end);
            const startTs = parseDateTime(sec.enrollment_start);
            const endTs = parseDateTime(sec.enrollment_end);
            let timeHint = '<span style="color:var(--ink-muted);font-size:12px;">未开始</span>';
            if (windowState === 'not_started') {
                if (Number.isFinite(startTs)) {
                    timeHint = `<span style="color:#b45309;font-size:12px;">未开始<br>${new Date(startTs).toISOString().slice(5,16).replace('T',' ')} 开始</span>`;
                }
            } else if (windowState === 'closed') {
                if (Number.isFinite(endTs)) {
                    timeHint = `<span style="color:#b42318;font-size:12px;">已截止<br>${new Date(endTs).toISOString().slice(5,16).replace('T',' ')}</span>`;
                } else {
                    timeHint = '<span style="color:#b42318;font-size:12px;">已截止</span>';
                }
            } else {
                if (Number.isFinite(endTs)) {
                    timeHint = `<span style="color:#027a48;font-size:12px;">进行中<br>截止 ${new Date(endTs).toISOString().slice(5,16).replace('T',' ')}</span>`;
                } else {
                    timeHint = '<span style="color:#027a48;font-size:12px;">进行中</span>';
                }
            }
            tr.appendChild(createCell(timeHint));
            // action
            const actionTd = document.createElement('td');
            if (parseInt(sec.is_my_course) === 1) {
                actionTd.innerHTML = `<span class="cs-badge badge-gray cs-btn-like">已选此课</span>`;
            } else if (windowState === 'closed') {
                actionTd.innerHTML = `<button class="cs-btn-disabled" disabled>已截止</button>`;
            } else if (windowState === 'not_started') {
                actionTd.innerHTML = `<button class="cs-btn-disabled" disabled>未开始</button>`;
            } else if (sec.enrolled_count >= sec.capacity) {
                actionTd.innerHTML = `<button class="cs-btn-disabled" disabled>名额已满</button>`;
            } else if (parseInt(sec.conflict_flag) === 1) {
                actionTd.innerHTML = `<button class="cs-btn-disabled" disabled>时间冲突</button>`;
            } else {
                actionTd.innerHTML = `<button class="cs-btn-enroll" data-section-id="${sec.section_id}">选课</button>`;
            }
            tr.appendChild(actionTd);

            tbody.appendChild(tr);
        });

        // bind enroll buttons
        document.querySelectorAll('.cs-btn-enroll').forEach(btn=>{
            btn.addEventListener('click', async e => {
                const sid = btn.dataset.sectionId;
                const ok = await inlineConfirm(btn, '确认选修该课程？');
                if (!ok) return;
                btn.disabled = true; btn.textContent = '处理中...';
                const ret = await apiFetch('POST', { action: 'enroll', section_id: sid });
                if (window.StudentApi ? window.StudentApi.isSuccess(ret) : (ret && ret.ok)) { showAlert('success', (window.StudentApi && window.StudentApi.messageOf(ret, '选课成功')) || ret.message || '选课成功'); load(); }
                else { showAlert('error', window.StudentApi ? window.StudentApi.messageOf(ret, '选课失败') : (ret && ret.message ? ret.message : '选课失败')); load(); }
            });
        });
    }

    function renderMyCourses(data){
        const container = document.getElementById('myCourseContainer'); container.innerHTML='';
        if (!data.my_courses || data.my_courses.length === 0) {
            container.innerHTML = `<div class="empty-state"><div class="es-icon">📦</div><div style="margin-top:8px;">您还没有选修任何课程</div></div>`;
            return;
        }
        const wrapper = document.createElement('div'); wrapper.className = 'my-course-list';
        data.my_courses.forEach(mc => {
            const dropWindowState = getWindowState(mc.enrollment_start, mc.enrollment_end);
            const item = document.createElement('div'); item.className = 'my-course-item';
            item.innerHTML = `
                <div class="mci-info">
                    <div class="mci-title">${escapeHtml(mc.course_name||'')}</div>
                    <div class="mci-meta">教师: ${escapeHtml(mc.teacher_name||'未知')} &nbsp;|&nbsp; 学分: ${escapeHtml(mc.credit||'0')}</div>
                    <div style="font-size:12px;color:rgba(15,23,42,0.6);margin-top:6px;line-height:1.4;">时间: ${formatSchedules(mc.schedules)}</div>
                    <div style="margin-top:8px;"><span class="cs-badge badge-green">已确认</span></div>
                </div>
                <div class="mci-action">
                    ${dropWindowState === 'open' ? `<button class="cs-btn-drop" data-section-id="${mc.section_id}" title="退课">退课</button>` : `<button class="cs-btn-disabled" disabled title="当前不在退课时间范围内">不可退课</button>`}
                </div>
            `;
            wrapper.appendChild(item);
        });
        container.appendChild(wrapper);

        container.querySelectorAll('.cs-btn-drop').forEach(btn => {
            btn.addEventListener('click', async e => {
                const sid = btn.dataset.sectionId;
                const ok = await inlineConfirm(btn, '确认退课？');
                if (!ok) return;
                btn.disabled = true; btn.textContent = '处理中...';
                const ret = await apiFetch('POST', { action: 'drop', section_id: sid });
                if (window.StudentApi ? window.StudentApi.isSuccess(ret) : (ret && ret.ok)) { showAlert('success', (window.StudentApi && window.StudentApi.messageOf(ret, '退课成功')) || ret.message || '退课成功'); load(); }
                else { showAlert('error', window.StudentApi ? window.StudentApi.messageOf(ret, '退课失败') : (ret && ret.message ? ret.message : '退课失败')); load(); }
            });
        });
    }

    async function load(){
        try{
            if (window.loadStudentSidebar) {
                window.loadStudentSidebar({ view: 'course' });
            }
            const res = await apiFetch('GET');
            if (!(window.StudentApi ? window.StudentApi.isSuccess(res) : (res && res.ok))) { showAlert('error', '请求失败'); console.error(res); return; }
            const d = window.StudentApi ? window.StudentApi.dataOf(res, {}) : (res.data || {});
            if (res.alerts_html) {
                const ac = document.getElementById('alertsContainer'); if (ac) ac.innerHTML = res.alerts_html;
            }

            renderOverview(d);
            renderAvailable(d);
            renderMyCourses(d);
        }catch(err){ console.error(err); showAlert('error',"请求失败"); }
    }

    if (window.__SINGLE_PAGE_APP) {
        window.initCourseSelect = load;
    } else {
        document.addEventListener('DOMContentLoaded', ()=>{ load(); });
    }
})();
