// js/student_portal.js
// Fetch data from the student portal API and render the page

const STUDENT_PROFILE_API = window.studentGetApiUrl('profile');
const STUDENT_PORTAL_API = window.studentGetApiUrl('student_portal');
const STUDENT_LOGIN_URL = window.studentGetLoginUrl();

function scoreColor(score) {
    if (score === null || score === undefined || score === '') return '#94a3b8';
    const s = parseFloat(score);
    if (s >= 90) return '#22c55e';
    if (s >= 75) return '#3b82f6';
    if (s >= 60) return '#f59e0b';
    return '#ef4444';
}
function scoreLetter(score) {
    if (score === null || score === undefined || score === '') return '?';
    const s = parseFloat(score);
    if (s >= 90) return 'A';
    if (s >= 75) return 'B';
    if (s >= 60) return 'C';
    return 'F';
}

function loadPortal(){
    if (window.loadStudentSidebar) {
        window.loadStudentSidebar({ view: 'portal' });
    }

    function el(...ids){ for(const id of ids){ const e = document.getElementById(id); if (e) return e; } return null; }

    function fetchProfileFallback(reason){
        return fetch(STUDENT_PROFILE_API + '?__pf=' + Date.now(), {
            credentials: 'include',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        }).then(function(resp){
            var ct = (resp.headers.get && resp.headers.get('content-type')) || '';
            if (ct.indexOf('application/json') === -1) {
                if (resp.url && resp.url.indexOf(STUDENT_LOGIN_URL) !== -1) {
                    window.location.href = STUDENT_LOGIN_URL;
                    throw new Error('not-json-login');
                }
                throw new Error('profile-fallback-non-json:' + (reason || 'portal_error'));
            }
            return resp.text().then(function(t){
                var raw = t == null ? '' : String(t);
                if (!raw.trim()) throw new Error('profile-fallback-empty:' + (reason || 'portal_error'));
                try { return JSON.parse(raw); } catch (e) { throw new Error('profile-fallback-invalid-json:' + (reason || 'portal_error')); }
            });
        }).then(function(p){
            if (!p || !p.success) {
                if (p && p.error === 'unauthenticated') {
                    window.location.href = STUDENT_LOGIN_URL;
                    return p;
                }
                throw new Error('profile-fallback-failed:' + (reason || 'portal_error'));
            }
            return {
                success: true,
                student: p.student || null,
                stats: { gpa: 0, credits: 0, published: 0 },
                recent_grades: [],
                enrolled_count: 0,
                
                year_min: new Date().getFullYear(),
                
                alerts_html: p.alerts_html || ''
            };
        });
    }

    function fetchPortalData(attempt){
        const round = Number(attempt || 0);
        const sep = (round > 0) ? ("?__rt=" + Date.now()) : '';
        return fetch(STUDENT_PORTAL_API + sep, {
            credentials: 'include',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        }).then(resp => {
            const ct = resp.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                return resp.text().then(text => {
                    const brief = (text || '').replace(/\s+/g, ' ').trim().slice(0, 140);
                    if (resp.url && resp.url.indexOf(STUDENT_LOGIN_URL) !== -1) {
                        window.location.href = STUDENT_LOGIN_URL;
                        throw new Error('not-json-login');
                    }
                    if (round === 0) return fetchPortalData(1);
                    return fetchProfileFallback('invalid-content-type:' + brief);
                });
            }

            return resp.text().then(text => {
                const raw = text == null ? '' : String(text);
                if (!raw.trim()) {
                    if (round === 0) return fetchPortalData(1);
                    return fetchProfileFallback('empty-json-body');
                }
                try {
                    return JSON.parse(raw);
                } catch (e) {
                    if (round === 0) return fetchPortalData(1);
                    const brief = raw.replace(/\s+/g, ' ').trim().slice(0, 140);
                    return fetchProfileFallback('invalid-json-body:' + brief);
                }
            });
        });
    }

    fetchPortalData(0)
        .then(data => {
            if (!data || typeof data !== 'object') {
                throw new Error('invalid_payload');
            }

            if (!data.success) {
                if (data.error === 'unauthenticated') {
                    window.location.href = STUDENT_LOGIN_URL;
                    return;
                }
                const noStudent = el('noStudentNotice');
                if (noStudent) {
                    noStudent.style.display = '';
                    const titleEl = noStudent.querySelector('div:nth-child(2)');
                    const descEl = noStudent.querySelector('div:nth-child(3)');
                    if (titleEl) titleEl.textContent = '首页接口返回异常';
                    if (descEl) descEl.textContent = data.message || '请稍后刷新重试，或联系管理员检查接口日志';
                }
                return;
            }

            // Alerts HTML
            if (data.alerts_html) {
                const alerts = el('alertsContainer');
                if (alerts) alerts.innerHTML = data.alerts_html;
            }

            // If student data missing, show fallback notice (mirrors PHP behavior)
            const noStudent = el('noStudentNotice');
            const studentContent = el('studentContent');
            if (!data.student) {
                if (noStudent) noStudent.style.display = '';
                if (studentContent) {
                    studentContent.querySelectorAll('section').forEach(sec => { sec.style.display = 'none'; });
                }
                return;
            } else {
                if (noStudent) noStudent.style.display = 'none';
            }

            const student = (data.student && typeof data.student === 'object') ? data.student : {};
            const stats = (data.stats && typeof data.stats === 'object') ? data.stats : {};

            // Header / hero (prefer portal-prefixed IDs when present)
            const nameEl = el('portalStudentName','studentName'); if (nameEl) nameEl.textContent = student.name || '';
            const deptEl = el('portalDeptName','deptName'); if (deptEl) deptEl.textContent = student.dept_name || '';
            const majorEl = el('portalMajorName'); if (majorEl) majorEl.textContent = student.major_name || '';
            const gradeEl = el('portalGradeYear','gradeYear'); if (gradeEl) gradeEl.textContent = student.grade ? (student.grade + ' 级') : '';

            const gpaNum = Number(stats.gpa);
            const creditsNum = Number(stats.credits);
            const enrolledNum = Number(data.enrolled_count);
            const gpaEl = el('portalGpaVal','gpaVal'); if (gpaEl) gpaEl.textContent = (Number.isFinite(gpaNum) ? gpaNum : 0).toFixed(2);
            const creditsEl = el('portalCreditsVal','creditsVal'); if (creditsEl) creditsEl.textContent = (Number.isFinite(creditsNum) ? creditsNum : 0).toFixed(1);
            const enrolledEl = el('portalEnrolledVal','enrolledVal'); if (enrolledEl) enrolledEl.textContent = Number.isFinite(enrolledNum) ? enrolledNum : 0;

            

            // recent grades
            const tbody = el('portalRecentGradesTbody','recentGradesTbody');
            if (tbody) tbody.innerHTML = '';
            const rg = Array.isArray(data.recent_grades) ? data.recent_grades : [];
            const yearMinEl = el('portalYearMin','yearMin'); if (yearMinEl) yearMinEl.textContent = (data.year_min ? (data.year_min + ' 年起') : '');
            if (tbody) {
                if (rg.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="color:var(--ink-muted);padding:18px;text-align:center">近两年暂无已公布成绩</td></tr>';
                } else {
                    rg.forEach(r => {
                        const tr = document.createElement('tr');
                        const tdCourse = document.createElement('td'); tdCourse.textContent = r.course || '';
                        const tdCredit = document.createElement('td'); tdCredit.textContent = r.credit ?? '';
                        const tdSem = document.createElement('td'); tdSem.textContent = r.semester || '';
                        tdSem.style.fontFamily = "'JetBrains Mono',monospace";
                        const tdScore = document.createElement('td');

                        const sc = r.final_score;
                        const badge = document.createElement('span'); badge.className = 'score-badge';
                        const letter = document.createElement('span');
                        letter.className = 'score-letter';
                        letter.textContent = scoreLetter(sc);
                        letter.style.background = scoreColor(sc);
                        const val = document.createElement('span');
                        val.textContent = (sc === null || sc === undefined) ? '—' : (parseFloat(sc).toFixed(1));
                        val.style.color = scoreColor(sc);
                        badge.appendChild(letter);
                        badge.appendChild(val);
                        tdScore.appendChild(badge);

                        tr.appendChild(tdCourse);
                        tr.appendChild(tdCredit);
                        tr.appendChild(tdSem);
                        tr.appendChild(tdScore);
                        tbody.appendChild(tr);
                    });
                }
            }

            // info card / avatar
            const avatarArea = el('portalAvatarArea','avatarArea');
            if (avatarArea) avatarArea.innerHTML = '';
            if (avatarArea) {
                if (student.has_avatar) {
                    const img = document.createElement('img');
                    img.className = 'info-avatar-img';
                    img.src = student.avatar_path || '';
                    img.alt = '头像';
                    avatarArea.appendChild(img);
                    const nameBig = document.createElement('div'); nameBig.className = 'info-name-big'; nameBig.textContent = student.name || '';
                    avatarArea.appendChild(nameBig);
                    const idBadge = document.createElement('div'); idBadge.className = 'info-id-badge'; idBadge.textContent = student.student_id || '';
                    avatarArea.appendChild(idBadge);
                } else {
                    const initials = document.createElement('div'); initials.className = 'info-avatar'; initials.textContent = student.avatar_initials || '';
                    avatarArea.appendChild(initials);
                    const nameBig = document.createElement('div'); nameBig.className = 'info-name-big'; nameBig.textContent = student.name || '';
                    avatarArea.appendChild(nameBig);
                    const idBadge = document.createElement('div'); idBadge.className = 'info-id-badge'; idBadge.textContent = student.student_id || '';
                    avatarArea.appendChild(idBadge);
                }
            }

            // info list
            const infoDeptEl = el('portalInfoDept','infoDept'); if (infoDeptEl) infoDeptEl.textContent = student.dept_name || '';
            const infoGradeEl = el('portalInfoGrade','infoGrade'); if (infoGradeEl) infoGradeEl.textContent = student.grade_label || (student.grade || '');
            const infoEnrollEl = el('portalInfoEnrollYear','infoEnrollYear'); if (infoEnrollEl) infoEnrollEl.textContent = student.enrollment_year || '';
            const infoGenderEl = el('portalInfoGender','infoGender'); if (infoGenderEl) infoGenderEl.textContent = student.gender || '';
            const infoEmailEl = el('portalInfoEmail','infoEmail'); if (infoEmailEl) infoEmailEl.textContent = student.email || '';
            const infoPhoneEl = el('portalInfoPhone','infoPhone'); if (infoPhoneEl) infoPhoneEl.textContent = student.phone || '';

        })
        .catch(err => {
            if (err && err.message === 'not-json-login') return;
            const noStudent = el('noStudentNotice');
            if (noStudent) {
                noStudent.style.display = '';
                const titleEl = noStudent.querySelector('div:nth-child(2)');
                const descEl = noStudent.querySelector('div:nth-child(3)');
                if (titleEl) titleEl.textContent = '首页数据加载失败';
                if (descEl) {
                    const msg = (err && err.message) ? err.message : 'unknown_error';
                    descEl.textContent = '接口错误：' + msg;
                }
            }
            console.error(err);
        });
}

// expose for SPA usage or auto-run on normal pages
if (window.__SINGLE_PAGE_APP) {
    window.initStudentPortal = loadPortal;
} else {
    document.addEventListener('DOMContentLoaded', loadPortal);
}
