/* ============================================================
   js/my_grades.js  — 成绩查询页交互脚本
   时钟由 index.js 的 updateClock / setInterval 统一处理，此处不重复。
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

    /* ── GPA 弧线入场动画 ────────────────────────────────────
       PHP 已将目标 stroke-dashoffset 写入 style，
       先重置为"满圆（空弧）"，再在下一帧还原，触发 CSS transition。 */
    const arcFg = document.querySelector('.arc-fg');
    if (arcFg) {
        const total  = parseFloat(arcFg.style.strokeDasharray);
        const target = parseFloat(arcFg.style.strokeDashoffset);
        arcFg.style.strokeDashoffset = total;          // 先设空
        requestAnimationFrame(() => requestAnimationFrame(() => {
            arcFg.style.strokeDashoffset = target;     // 触发动画
        }));
    }

    /* ── 成绩行 stagger 渐入 ──────────────────────────────── */
    document.querySelectorAll('.grade-row').forEach((row, i) => {
        row.style.opacity   = '0';
        row.style.transform = 'translateX(-8px)';
        row.style.transition = `opacity .3s ease ${i * 0.05}s, transform .3s ease ${i * 0.05}s`;
        // 用 setTimeout 让浏览器先完成 layout，再触发动画
        setTimeout(() => {
            row.style.opacity   = '1';
            row.style.transform = 'translateX(0)';
        }, 30 + i * 50);
    });

    /* ── 键盘左/右方向键切换学期 tab ─────────────────────── */
    document.addEventListener('keydown', e => {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
        const tabs   = Array.from(document.querySelectorAll('.ftab'));
        if (!tabs.length) return;
        const active = tabs.findIndex(t => t.classList.contains('active'));
        if (e.key === 'ArrowRight' && active < tabs.length - 1) tabs[active + 1].click();
        if (e.key === 'ArrowLeft'  && active > 0)               tabs[active - 1].click();
    });

});