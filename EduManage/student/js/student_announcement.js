// js/student_announcement.js

window.initAnnouncement = function (opts = {}) {
    const listEl = document.getElementById('annList');
    const emptyEl = document.getElementById('annEmptyState');
    const announcementApi = window.studentGetApiUrl('announcement');

    if (!listEl) return;

    listEl.innerHTML = '<div class="page-loading"><div class="spinner"></div>正在加载公告…</div>';
    emptyEl.style.display = 'none';

    const q = opts.q ?? (document.getElementById('annSearch') ? document.getElementById('annSearch').value.trim() : '');
    const page = opts.page ?? 1;
    // 固定每页最多 10 条
    const per_page = 10;

    const params = new URLSearchParams();
    if (q) params.set('q', q);
    params.set('page', page);
    params.set('per_page', per_page);

    fetch(announcementApi + '?' + params.toString())
        .then(res => res.json())
        .then(res => {
            if (res && res.success === false) {
                throw new Error(res.error || res.message || '加载失败');
            }
            const arr = Array.isArray(res) ? res : (res.data || res.announcements || []);
            const meta = res.meta || { total: arr.length, page: page, per_page: per_page, total_pages: Math.max(1, Math.ceil(arr.length / per_page)) };

            window._allAnnouncements = arr;
            window._annMeta = meta;
            renderAnnouncements(arr);
            renderAnnouncementPagination(meta, q);
        })
        .catch(err => {
            listEl.innerHTML = `<div class="empty-state" style="color:var(--red);">⚠️ 加载失败：${err.message}</div>`;
            const pag = document.getElementById('annPagination'); if (pag) pag.innerHTML = '';
        });
};

function renderAnnouncements(data) {
    const listEl = document.getElementById('annList');
    const emptyEl = document.getElementById('annEmptyState');

    if (!data || data.length === 0) {
        listEl.innerHTML = '';
        emptyEl.style.display = 'block';
        return;
    }

    emptyEl.style.display = 'none';
    let html = '';
    data.forEach((a, index) => {
        let date = a.published_at ? a.published_at.substring(0, 16) : '';
        let pin = a.is_pinned == 1 ? '<span style="display:inline-block;padding:2px 8px;border-radius:12px;background:var(--orange-alpha, #fff7ed);color:var(--orange, #f97316);font-size:12px;font-weight:600;margin-right:8px;vertical-align:middle;box-shadow:0 0 0 1px rgba(249,115,22,0.2);">📌 优先显示</span>' : '';
        let sender = escapeHtml(a.teacher_name || 'System');
        let receiver = escapeHtml(a.receiver || (a.course_name ? `课程：${a.course_name}` : '全体学生'));
        
        let contentPreview = escapeHtml(a.content).replace(/\n/g, ' ');
        if (contentPreview.length > 100) contentPreview = contentPreview.substring(0, 100) + '...';

        html += `
        <div class="card ann-card fade-up" tabindex="0" onclick="openAnnouncementModal(${index})" style="padding:24px;border-radius:16px;cursor:pointer;transition:all 0.2s ease;border:1px solid transparent;background:#fff;display:flex;flex-direction:column;gap:12px;box-shadow:0 2px 10px rgba(0,0,0,0.03);" onmouseover="this.style.boxShadow='0 8px 24px rgba(37,99,235,0.12)';this.style.transform='translateY(-2px)';this.style.borderColor='rgba(59,130,246,0.3)'" onmouseout="this.style.boxShadow='0 2px 10px rgba(0,0,0,0.03)';this.style.transform='translateY(0)';this.style.borderColor='transparent'">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div style="font-size:18px;font-weight:700;color:var(--ink);display:flex;align-items:center;">${pin}${escapeHtml(a.title)}</div>
                <div style="font-size:13px;color:var(--ink-muted);background:var(--bg-light);padding:4px 10px;border-radius:8px;">${date}</div>
            </div>
            <div style="font-size:14px;color:var(--ink-light);margin-bottom:4px;">
                <span style="font-weight:600;color:var(--ink);">👤 发送人：</span><span>${sender}</span>
                <span style="margin:0 12px;color:var(--border);">|</span>
                <span style="font-weight:600;color:var(--ink);">👥 接收群体：</span><span>${receiver}</span>
            </div>
            <div style="font-size:14px;color:var(--ink);line-height:1.6;opacity:0.8;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${contentPreview}</div>
        </div>`;
    });
    listEl.innerHTML = html;
}

function renderAnnouncementPagination(meta, q) {
    const container = document.getElementById('annPagination');
    if (!container) return;
    container.innerHTML = '';
    if (!meta || (meta.total_pages || 0) <= 1) {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'flex';
    const page = meta.page || 1;
    const totalPages = meta.total_pages || 1;
    let start = Math.max(1, page - 3);
    let end = Math.min(totalPages, page + 3);
    // adjust to show up to 7 pages
    if (end - start < 6) {
        start = Math.max(1, page - 3);
        end = Math.min(totalPages, start + 6);
    }

    const createBtn = (label, p, active) => `<button class="pag-btn ${active? 'active':''}" data-page="${p}" style="padding:6px 10px;border-radius:6px;border:1px solid var(--border);background:${active? 'var(--bg-light)':'#fff'};cursor:pointer">${label}</button>`;
    let html = '';
    if (page > 1) html += createBtn('上一页', page-1, false);

    if (start > 1) {
        html += createBtn('1', 1, false);
        if (start > 2) html += `<span style="padding:6px 8px;">…</span>`;
    }

    for (let i = start; i <= end; i++) {
        html += createBtn(String(i), i, i === page);
    }

    if (end < totalPages) {
        if (end < totalPages - 1) html += `<span style="padding:6px 8px;">…</span>`;
        html += createBtn(String(totalPages), totalPages, false);
    }

    if (page < totalPages) html += createBtn('下一页', page+1, false);

    container.innerHTML = html;
    container.querySelectorAll('.pag-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const p = parseInt(this.getAttribute('data-page'), 10) || 1;
            initAnnouncement({ q: q || (document.getElementById('annSearch') ? document.getElementById('annSearch').value.trim() : ''), page: p, per_page: meta.per_page });
        });
    });
}

window.openAnnouncementModal = function(index) {
    if (!window._allAnnouncements || !window._allAnnouncements[index]) return;
    const a = window._allAnnouncements[index];
    
    document.getElementById('annModalTitle').innerHTML = (a.is_pinned == 1 ? '📌 ' : '') + escapeHtml(a.title);
    document.getElementById('annModalSender').innerText = escapeHtml(a.teacher_name || 'System');
    document.getElementById('annModalReceiver').innerText = escapeHtml(a.receiver || (a.course_name ? `课程：${a.course_name}` : '全体学生'));
    document.getElementById('annModalDate').innerText = a.published_at ? a.published_at.substring(0, 16) : '';
    document.getElementById('annModalContent').innerHTML = escapeHtml(a.content);
    
    const modalEl = document.getElementById('announcementModal');
    modalEl.style.display = 'flex';
    // Small timeout for fade-in animation
    requestAnimationFrame(() => {
        modalEl.querySelector('.custom-modal-content').style.opacity = '1';
        modalEl.querySelector('.custom-modal-content').style.transform = 'translateY(0)';
    });
};

window.hideAnnouncementModal = function() {
    const modalEl = document.getElementById('announcementModal');
    modalEl.style.display = 'none';
};

document.addEventListener('DOMContentLoaded', () => {
    // Init search/pagination controls for announcements
    const searchBtn = document.getElementById('annSearchBtn');
    const searchInput = document.getElementById('annSearch');
    if (searchBtn && searchInput) {
        searchBtn.addEventListener('click', function () {
            initAnnouncement({ q: searchInput.value.trim(), page: 1 });
        });
    }
    if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                initAnnouncement({ q: searchInput.value.trim(), page: 1 });
            }
        });
    }
    // 每页已固定为 10，不提供更改
    // kick off initial load when SPA shows announcement page (or immediately if already visible)
    if (document.getElementById('page-announcement') && document.getElementById('page-announcement').style.display !== 'none') {
        initAnnouncement();
    }
});

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(s) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[s];
    });
}
