/* ============================================================
   js/exam_info.js  — 考试相关页交互脚本
   时钟由 index.js 的 updateClock / setInterval 统一处理，此处不重复。
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

    /* ── 分数环入场动画（IntersectionObserver） ──────────────
       PHP 已把目标 stroke-dashoffset 写入 style，
       这里先把它重置为"满圆（空弧）"，当卡片进入视口时
       再还原目标值，触发 CSS transition 动画。              */
    const cards = document.querySelectorAll('.exam-card');

    if (cards.length && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;

                const fg = entry.target.querySelector('.ring-fg');
                if (fg) {
                    const target = parseFloat(fg.style.strokeDashoffset);
                    const total  = parseFloat(fg.style.strokeDasharray);
                    // 先跳到"空"
                    fg.style.strokeDashoffset = total;
                    // 两帧后触发动画
                    requestAnimationFrame(() => requestAnimationFrame(() => {
                        fg.style.strokeDashoffset = target;
                    }));
                }
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.15 });

        cards.forEach(card => {
            // 初始化：将分数环先隐藏（避免直接显示终态）
            const fg = card.querySelector('.ring-fg');
            if (fg) {
                const total = parseFloat(fg.style.strokeDasharray);
                fg.style.strokeDashoffset = total;
            }
            observer.observe(card);
        });
    }

    /* ── 悬停高亮：同类型卡片保持高亮，其余降低透明度 ────── */
    cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            const type = card.dataset.type;
            cards.forEach(c => {
                c.style.opacity = (c === card || c.dataset.type === type) ? '1' : '0.5';
            });
        });
        card.addEventListener('mouseleave', () => {
            cards.forEach(c => { c.style.opacity = '1'; });
        });
    });

});