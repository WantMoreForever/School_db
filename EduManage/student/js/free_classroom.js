(function () {
    const API = window.studentGetApiUrl('free_classroom');
    let initialized = false;
    let metadataLoaded = false;

    function $(id) {
        return document.getElementById(id);
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[char];
        });
    }

    function roomTypeLabel(type) {
        const map = {
            normal: '普通教室',
            multimedia: '多媒体教室',
            lab: '实验室'
        };
        return map[type] || type || '未标注';
    }

    function setSearchingState(searching) {
        const submitBtn = $('freeRoomSubmit');
        if (!submitBtn) return;
        submitBtn.disabled = !!searching;
        submitBtn.textContent = searching ? '检测中...' : '开始检测';
    }

    function renderLoadingState() {
        const resultsEl = $('freeRoomResults');
        const summaryEl = $('freeRoomSummary');
        if (summaryEl) summaryEl.innerHTML = '';
        if (!resultsEl) return;

        resultsEl.innerHTML =
            '<div class="free-room-loading-card">' +
            '  <div class="free-room-loading-bar wide"></div>' +
            '  <div class="free-room-loading-bar"></div>' +
            '  <div class="free-room-loading-grid">' +
            '    <div class="free-room-loading-block"></div>' +
            '    <div class="free-room-loading-block"></div>' +
            '    <div class="free-room-loading-block"></div>' +
            '  </div>' +
            '</div>';
    }

    function syncSlotRange() {
        const startEl = $('frSlotStart');
        const endEl = $('frSlotEnd');
        if (!startEl || !endEl) return;

        const startValue = Number(startEl.value || 0);
        Array.from(endEl.options).forEach(function (option) {
            option.disabled = Number(option.value) < startValue;
        });

        if (Number(endEl.value || 0) < startValue) {
            endEl.value = startEl.value;
        }
    }

    function fillWeekOptions(totalWeeks) {
        const weekEl = $('frWeek');
        if (!weekEl) return;
        weekEl.innerHTML = '';

        for (let week = 1; week <= totalWeeks; week += 1) {
            const option = document.createElement('option');
            option.value = String(week);
            option.textContent = '第' + week + '周';
            if (week === 1) {
                option.selected = true;
            }
            weekEl.appendChild(option);
        }
    }

    function fillSlotOptions(slots) {
        const startEl = $('frSlotStart');
        const endEl = $('frSlotEnd');
        if (!startEl || !endEl) return;

        startEl.innerHTML = '';
        endEl.innerHTML = '';

        slots.forEach(function (slot, index) {
            const startOption = document.createElement('option');
            startOption.value = String(slot.slot_id);
            startOption.textContent = slot.slot_name + '（' + String(slot.start_time || '').slice(0, 5) + '）';
            if (index === 0) {
                startOption.selected = true;
            }
            startEl.appendChild(startOption);

            const endOption = document.createElement('option');
            endOption.value = String(slot.slot_id);
            endOption.textContent = slot.slot_name + '（' + String(slot.end_time || '').slice(0, 5) + '）';
            if (index === 0) {
                endOption.selected = true;
            }
            endEl.appendChild(endOption);
        });

        syncSlotRange();
    }

    function fillClassrooms(classrooms) {
        const selectEl = $('frClassroom');
        if (!selectEl) return;

        selectEl.innerHTML = '<option value="0">全部教室</option>';
        classrooms.forEach(function (room) {
            const option = document.createElement('option');
            option.value = String(room.classroom_id);
            option.textContent = room.building + '-' + room.room_number + '（容量 ' + room.capacity + '）';
            selectEl.appendChild(option);
        });
    }

    function renderSummary(search, selectedClassroomId) {
        const summaryEl = $('freeRoomSummary');
        if (!summaryEl) return;
        if (!search) {
            summaryEl.innerHTML = '';
            return;
        }

        const isSingle = Number(selectedClassroomId || 0) > 0;
        const main = isSingle ? '已完成指定教室空闲检测' : '已完成空闲教室检索';
        const slotText = search.slot_start_name === search.slot_end_name
            ? search.slot_start_name
            : (search.slot_start_name + ' - ' + search.slot_end_name);

        summaryEl.innerHTML =
            '<div class="free-room-summary-card">' +
            '  <div class="free-room-summary-top">' +
            '    <div>' +
            '      <div class="free-room-summary-title">' + escapeHtml(main) + '</div>' +
            '      <div class="free-room-summary-meta">第' + escapeHtml(search.week) + '周 ' + escapeHtml(search.day_label) + ' ' +
            escapeHtml(String(search.start_time).slice(0, 5)) + '-' + escapeHtml(String(search.end_time).slice(0, 5)) + '</div>' +
            '    </div>' +
            '    <div class="free-room-summary-chip-row">' +
            '      <span class="free-room-chip">' + escapeHtml(slotText) + '</span>' +
            '      <span class="free-room-chip ' + (isSingle ? 'accent' : '') + '">' +
            escapeHtml(isSingle ? '指定教室模式' : '全部教室模式') +
            '</span>' +
            '    </div>' +
            '  </div>' +
            '  <div class="free-room-stat-row">' +
            '    <div class="free-room-stat"><span class="num">' + escapeHtml(search.free_count) + '</span><span class="label">空闲</span></div>' +
            '    <div class="free-room-stat"><span class="num">' + escapeHtml(search.occupied_count) + '</span><span class="label">占用中</span></div>' +
            '  </div>' +
            '</div>';
    }

    function renderSingleResult(result) {
        const resultsEl = $('freeRoomResults');
        if (!resultsEl) return;
        if (!result) {
            resultsEl.innerHTML = '<div class="free-room-result-card"><div class="free-room-empty">没有查到对应教室的信息，请重新选择后再试。</div></div>';
            return;
        }

        const badgeClass = result.is_free ? 'free-room-badge free' : 'free-room-badge busy';
        let html = '' +
            '<div class="free-room-result-card">' +
            '  <div class="free-room-result-head">' +
            '    <div>' +
            '      <div class="free-room-room-name">' + escapeHtml(result.room_label) + '</div>' +
            '      <div class="free-room-room-meta">容量 ' + escapeHtml(result.capacity) + ' · 类型 ' + escapeHtml(roomTypeLabel(result.type)) + '</div>' +
            '    </div>' +
            '    <span class="' + badgeClass + '">' + escapeHtml(result.status_text) + '</span>' +
            '  </div>';

        if (result.is_free) {
            html += '' +
                '<div class="free-room-success-panel">' +
                '  <div class="free-room-success-title">可以使用</div>' +
                '  <div class="free-room-success-text">这间教室在所选时间段内没有课程安排，适合临时自习、讨论或短时借用。</div>' +
                '</div>';
        } else {
            html += '<div class="free-room-conflict-list">';
            result.conflicts.forEach(function (conflict) {
                html += '' +
                    '<div class="free-room-conflict-item">' +
                    '  <div class="free-room-conflict-title">' + escapeHtml(conflict.course_name) + '</div>' +
                    '  <div class="free-room-conflict-meta">教师：' + escapeHtml(conflict.teacher_names) + '</div>' +
                    '  <div class="free-room-conflict-meta">时间：' +
                    escapeHtml(String(conflict.start_time).slice(0, 5)) + '-' +
                    escapeHtml(String(conflict.end_time).slice(0, 5)) +
                    '，周次：第' + escapeHtml(conflict.week_start) + '-' + escapeHtml(conflict.week_end) + '周</div>' +
                    '</div>';
            });
            html += '</div>';
        }

        html += '</div>';
        resultsEl.innerHTML = html;
    }

    function renderMultipleResults(results) {
        const resultsEl = $('freeRoomResults');
        if (!resultsEl) return;

        const freeRooms = results.filter(function (item) { return !!item.is_free; });
        const occupiedRooms = results.filter(function (item) { return !item.is_free; });

        let html = '' +
            '<div class="free-room-grid">' +
            '  <div class="free-room-panel">' +
            '    <div class="free-room-panel-title">空闲教室</div>';

        if (freeRooms.length === 0) {
            html += '<div class="free-room-empty">当前时间段没有查到空闲教室。</div>';
        } else {
            html += '<div class="free-room-card-list">';
            freeRooms.forEach(function (room) {
                html += '' +
                    '<div class="free-room-quick-card">' +
                    '  <div class="free-room-quick-head">' +
                    '    <div>' +
                    '      <div class="free-room-quick-name">' + escapeHtml(room.room_label) + '</div>' +
                    '      <div class="free-room-room-meta">容量 ' + escapeHtml(room.capacity) + ' · 类型 ' + escapeHtml(roomTypeLabel(room.type)) + '</div>' +
                    '    </div>' +
                    '    <span class="free-room-badge free">空闲</span>' +
                    '  </div>' +
                    '</div>';
            });
            html += '</div>';
        }

        html +=
            '  </div>' +
            '  <div class="free-room-panel">' +
            '    <div class="free-room-panel-title">占用中的教室</div>';

        if (occupiedRooms.length === 0) {
            html += '<div class="free-room-empty">当前筛选下没有占用中的教室。</div>';
        } else {
            html += '<div class="free-room-occupied-list">';
            occupiedRooms.forEach(function (room) {
                html += '' +
                    '<div class="free-room-occupied-item">' +
                    '  <div class="free-room-occupied-head">' +
                    '    <div>' +
                    '      <span class="free-room-room-name">' + escapeHtml(room.room_label) + '</span>' +
                    '      <div class="free-room-room-meta">容量 ' + escapeHtml(room.capacity) + ' · 类型 ' + escapeHtml(roomTypeLabel(room.type)) + '</div>' +
                    '    </div>' +
                    '    <span class="free-room-badge busy">占用中</span>' +
                    '  </div>';

                room.conflicts.forEach(function (conflict) {
                    html += '<div class="free-room-conflict-meta">《' + escapeHtml(conflict.course_name) + '》 / ' +
                        escapeHtml(conflict.teacher_names) + ' / ' +
                        escapeHtml(String(conflict.start_time).slice(0, 5)) + '-' +
                        escapeHtml(String(conflict.end_time).slice(0, 5)) +
                        ' / 第' + escapeHtml(conflict.week_start) + '-' + escapeHtml(conflict.week_end) + '周</div>';
                });

                html += '</div>';
            });
            html += '</div>';
        }

        html += '</div></div>';
        resultsEl.innerHTML = html;
    }

    function renderResults(payload) {
        const search = payload && payload.data ? payload.data.search : null;
        const results = payload && payload.data ? (payload.data.results || []) : [];
        const selectedClassroomId = search ? Number(search.classroom_id || 0) : 0;

        renderSummary(search, selectedClassroomId);

        if (selectedClassroomId > 0) {
            renderSingleResult(results[0] || null);
            return;
        }

        renderMultipleResults(results);
    }

    async function fetchJson(url) {
        const response = await fetch(url, {
            credentials: 'include',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        });
        const data = await response.json();
        if (!response.ok || !(window.StudentApi ? window.StudentApi.isSuccess(data) : (!data || data.success !== false))) {
            throw new Error(window.StudentApi ? window.StudentApi.messageOf(data, '请求失败') : ((data && (data.message || data.error)) || '请求失败'));
        }
        return data;
    }

    async function loadMetadata(force) {
        if (metadataLoaded && !force) return;

        const loadingEl = $('freeRoomLoading');
        if (loadingEl) loadingEl.style.display = '';

        try {
            const data = await fetchJson(API + '?_t=' + Date.now());
            const payload = data.data || {};
            fillWeekOptions(Number(payload.total_weeks || 16));
            fillSlotOptions(payload.time_slots || []);
            fillClassrooms(payload.classrooms || []);

            if ($('freeRoomTermHint')) {
                $('freeRoomTermHint').textContent = '当前查询学期：' + String(payload.term_label || '当前学期');
            }

            metadataLoaded = true;
        } finally {
            if (loadingEl) loadingEl.style.display = 'none';
        }
    }

    async function handleSearch(event) {
        if (event) event.preventDefault();
        renderLoadingState();
        setSearchingState(true);

        try {
            const params = new URLSearchParams({
                action: 'search',
                week: String(($('frWeek') && $('frWeek').value) || '1'),
                day_of_week: String(($('frDay') && $('frDay').value) || '1'),
                slot_start_id: String(($('frSlotStart') && $('frSlotStart').value) || ''),
                slot_end_id: String(($('frSlotEnd') && $('frSlotEnd').value) || ''),
                classroom_id: String(($('frClassroom') && $('frClassroom').value) || '0'),
                _t: String(Date.now())
            });

            const data = await fetchJson(API + '?' + params.toString());
            renderResults(data);
        } catch (error) {
            console.error(error);
            if (window.StudentApi && window.StudentApi.showAlert) {
                window.StudentApi.showAlert('error', error && error.message ? error.message : '请求失败');
            }
        } finally {
            setSearchingState(false);
        }
    }

    function bindEvents() {
        const form = $('freeRoomForm');
        const startEl = $('frSlotStart');
        const resetBtn = $('freeRoomReset');

        if (form && !form.dataset.bound) {
            form.dataset.bound = '1';
            form.addEventListener('submit', handleSearch);
        }

        if (startEl && !startEl.dataset.bound) {
            startEl.dataset.bound = '1';
            startEl.addEventListener('change', syncSlotRange);
        }

        if (resetBtn && !resetBtn.dataset.bound) {
            resetBtn.dataset.bound = '1';
            resetBtn.addEventListener('click', function () {
                if (!metadataLoaded) {
                    return;
                }

                loadMetadata(true).then(function () {
                    const dayEl = $('frDay');
                    const startEl2 = $('frSlotStart');
                    const endEl2 = $('frSlotEnd');
                    const classroomEl = $('frClassroom');
                    const resultsEl = $('freeRoomResults');
                    const summaryEl = $('freeRoomSummary');

                    if (dayEl) dayEl.value = '1';
                    if (startEl2 && startEl2.options.length) startEl2.selectedIndex = 0;
                    if (endEl2 && endEl2.options.length) endEl2.selectedIndex = 0;
                    if (classroomEl) classroomEl.value = '0';

                    syncSlotRange();

                    if (resultsEl) resultsEl.innerHTML = '';
                    if (summaryEl) summaryEl.innerHTML = '';
                }).catch(function (error) {
                    console.error(error);
                });
            });
        }
    }

    async function init() {
        bindEvents();
        await loadMetadata(false);
    }

    window.initFreeClassroomPage = function () {
        if (!initialized) {
            initialized = true;
        }
        return init();
    };
})();
