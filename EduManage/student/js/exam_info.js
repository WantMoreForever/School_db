(function(){
    const API = window.studentGetApiUrl('exam_info');
    let activeStatus = 'upcoming'; // 'upcoming': 未开始/进行中; 'past': 已结束
    let latestExams = [];

    function escapeHtml(s){ if(s===null||s===undefined) return ''; return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    function formatYMD(dstr){ 
        if(!dstr) return ''; 
        const d = new Date(dstr + 'T00:00:00'); 
        if(isNaN(d)) return dstr; 
        const y=d.getFullYear(); 
        const m=String(d.getMonth()+1).padStart(2,'0'); 
        const dd=String(d.getDate()).padStart(2,'0'); 
        return `${y}年${m}月${dd}日`; 
    }

    function typeBadgeClass(t){ 
        if(t==='final') return 'badge-rose'; 
        if(t==='midterm') return 'badge-gold'; 
        if(t==='quiz') return 'badge-jade'; 
        return 'badge-gray'; 
    }
    
    function typeLabel(t){ 
        if(t==='final') return '期末'; 
        if(t==='midterm') return '期中'; 
        if(t==='quiz') return '测验'; 
        return t; 
    }

    function renderSemesters(semesters, current){
        const c = document.getElementById('filterTabsSemesters'); c.innerHTML='';
        const allA = document.createElement('a');
        allA.className = 'ftab' + (current === 'all' ? ' active' : '');
        allA.href = '#';
        allA.textContent = '全部';
        allA.dataset.sem = 'all';
        allA.addEventListener('click', e=>{ e.preventDefault(); load({ semester: 'all' }); });
        c.appendChild(allA);

        (semesters||[]).forEach(s=>{
            const a = document.createElement('a');
            a.className = 'ftab' + (current===s.sem_key ? ' active' : '');
            a.href = '#';
            a.textContent = (String(s.year) + ' ' + String(s.semester));
            a.dataset.sem = s.sem_key;
            a.addEventListener('click', e=>{ e.preventDefault(); load({semester: s.sem_key}); });
            c.appendChild(a);
        });
    }

    function renderStatusFilter(current){
        const c = document.getElementById('filterTabsStatus');
        if(!c) return;
        c.innerHTML = '';
        const opts = [
            { key: 'upcoming', label: '未开始' },
            { key: 'past', label: '已终止' }
        ];
        opts.forEach(o => {
            const a = document.createElement('a');
            a.className = 'ftab' + (current === o.key ? ' active' : '');
            a.href = '#';
            a.textContent = o.label;
            a.dataset.status = o.key;
            a.addEventListener('click', (e) => {
                e.preventDefault();
                if (activeStatus === o.key) return;
                activeStatus = o.key;
                [...c.children].forEach(ch => ch.classList.toggle('active', ch.dataset.status === o.key));
                renderExamList(latestExams);
            });
            c.appendChild(a);
        });
    }

    function currentSemester(){ const s = document.querySelector('#filterTabsSemesters .ftab.active'); return s ? s.dataset.sem : 'all'; }

    function renderStats(stats){
        document.getElementById('statsTotal').textContent = Number(stats.total || 0);
        document.getElementById('statsUpcoming').textContent = Number(stats.upcoming || 0);
        document.getElementById('statFinal').textContent = Number(stats.final || 0);
        document.getElementById('statMidterm').textContent = Number(stats.midterm || 0);
        document.getElementById('statQuiz').textContent = Number(stats.quiz || 0);
        document.getElementById('statUpcoming').textContent = Number(stats.upcoming || 0);
    }

    function renderExamList(exams){
        const container = document.getElementById('examList'); container.innerHTML = '';
        const empty = document.getElementById('emptyState');
        const todayStr = (new Date()).toISOString().slice(0,10);

        let items = (exams || []).slice();
        if (activeStatus === 'upcoming') {
            items = items.filter(ex => ex.exam_date >= todayStr);
        } else if (activeStatus === 'past') {
            items = items.filter(ex => ex.exam_date < todayStr);
        }

        if(!items || items.length === 0){ empty.style.display='block'; document.getElementById('filterCount').textContent = 0; return; } else { empty.style.display='none'; }

        items.forEach(ex=>{
            const dateStr = formatYMD(ex.exam_date);
            const isPast = ex.exam_date < todayStr;
            const isToday = ex.exam_date === todayStr;
            const card = document.createElement('div');
            card.className = 'exam-card ' + (isPast ? 'past' : (isToday ? 'today' : ''));
            card.dataset.type = ex.exam_type || '';

            const left = document.createElement('div'); left.className = 'exam-type-col';
            const badge = document.createElement('span'); badge.className = 'badge ' + typeBadgeClass(ex.exam_type); badge.textContent = typeLabel(ex.exam_type);
            left.appendChild(badge);

            const mid = document.createElement('div'); mid.className = 'exam-info';
            const cname = document.createElement('div'); cname.className = 'exam-course';
            cname.textContent = ex.course_name || '';
            const meta = document.createElement('div'); meta.className = 'exam-meta';
            const mdate = document.createElement('span'); mdate.className = 'exam-meta-item'; mdate.textContent = '时间： ' + dateStr;
            meta.appendChild(mdate);
            if (ex.location) { const mloc = document.createElement('span'); mloc.className = 'exam-meta-item'; mloc.textContent = '地点： ' + ex.location; meta.appendChild(mloc); }
            mid.appendChild(cname); mid.appendChild(meta);

            const right = document.createElement('div'); right.className = 'exam-right';
            if(isToday){ const s = document.createElement('span'); s.className='today-badge'; s.textContent='今天考试'; right.appendChild(s); }
            else if(isPast){ const s = document.createElement('span'); s.className='past-badge'; s.textContent='已完成'; right.appendChild(s); }
            else { const s = document.createElement('span'); s.className='upcoming-badge'; s.textContent='即将到来'; right.appendChild(s); }

            card.appendChild(left); card.appendChild(mid); card.appendChild(right);
            container.appendChild(card);
        });
        
        initExamCards();
        document.getElementById('filterCount').textContent = items.length;
    }

    function setHeaderSemester(p_year, p_sem){
        const el = document.getElementById('semTitle');
        if (!el) return;
        if (p_year && p_sem) el.textContent = String(p_year) + ' ' + String(p_sem);
        else el.textContent = '全部';
    }

    function load(opts){
        opts = opts || {};
        if (window.loadStudentSidebar) {
            window.loadStudentSidebar({ view: 'exam' });
        }
        const urlParams = new URLSearchParams(window.location.search);
        const semester = opts.semester !== undefined ? opts.semester : (urlParams.get('semester') || 'all');
        const url = API + '?' + new URLSearchParams({ semester: semester }).toString();
        
        fetch(url, { credentials: 'include' }).then(r=>r.json()).then(resp=>{
            if(!resp || !resp.ok){ console.error('exam_info API error', resp); return; }
            const data = resp.data || {};
            const ac = document.getElementById('alertsContainer'); if(ac) ac.innerHTML = resp.alerts_html || '';

            latestExams = data.exams || [];
            renderSemesters(data.semesters || [], data.filter_sem || 'all');
            renderStatusFilter(activeStatus);
            renderStats(data.stats || {});
            setHeaderSemester(data.p_year, data.p_sem);
            renderExamList(latestExams);

            const qs = new URLSearchParams(); qs.set('semester', data.filter_sem ?? 'all');
            history.replaceState({}, '', '?' + qs.toString());
        }).catch(err=>{ console.error('fetch error', err); });
    }

    if (window.__SINGLE_PAGE_APP) {
        window.initExamPage = function(opts){ return load(opts); };
    } else {
        document.addEventListener('DOMContentLoaded', ()=>{ load(); });
    }

})();

function initExamCards(){
    const cards = document.querySelectorAll('.exam-card');
    if(!cards || cards.length === 0) return;

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                const fg = entry.target.querySelector('.ring-fg');
                if (fg) {
                    const target = parseFloat(fg.style.strokeDashoffset || 0);
                    const total  = parseFloat(fg.style.strokeDasharray || 0);
                    fg.style.strokeDashoffset = total;
                    requestAnimationFrame(() => requestAnimationFrame(() => {
                        fg.style.strokeDashoffset = target;
                    }));
                }
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.15 });

        cards.forEach(card => {
            const fg = card.querySelector('.ring-fg');
            if (fg) {
                const total = parseFloat(fg.style.strokeDasharray || 0);
                if (!isNaN(total)) fg.style.strokeDashoffset = total;
            }
            observer.observe(card);
        });
    }
}
