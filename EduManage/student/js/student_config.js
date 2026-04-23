// Fetch student config and populate elements with `data-config` attributes.
(function(){
    var configUrl = window.studentGetApiUrl('config');

    function getNested(obj, path) {
        if (!obj) return undefined;
        var parts = path.split('.');
        var cur = obj;
        for (var i = 0; i < parts.length; i++) {
            if (cur == null) return undefined;
            cur = cur[parts[i]];
        }
        return cur;
    }

    fetch(configUrl, {cache: 'no-store'})
        .then(function(r){ return r.json(); })
        .then(function(cfg){
            window.STUDENT_CONFIG = cfg;
            document.querySelectorAll('[data-config]').forEach(function(el){
                var key = el.getAttribute('data-config');
                if (!key) return;
                var val = getNested(cfg, key);
                if (val === undefined || val === null) return;
                el.textContent = val;
            });

            // ovDesc convenience (term display)
            var ov = document.getElementById('ovDesc');
            if (ov && cfg.term) {
                var year = cfg.term.current_year;
                var semcn = cfg.term.current_semester_cn || window.studentGetSemesterLabel(cfg.term.current_semester);
                ov.textContent = year + ' 年 ' + semcn;
            }

            // portal enroll banner
            var enroll = (cfg.enroll || {});
            var banner = document.getElementById('portalEnrollBanner');
            if (banner) {
                var endEl = document.getElementById('portalEnrollEndText');
                if (endEl) endEl.textContent = '截止时间：' + (enroll.end || '');
                if (enroll.open) banner.style.display = '';
                else banner.style.display = 'none';
            }
        })
        .catch(function(){ /* ignore */ });
})();
