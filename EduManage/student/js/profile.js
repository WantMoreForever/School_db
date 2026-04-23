// student/js/profile.js
// 学生个人信息页：加载资料、上传头像、更新手机号。

(function () {
    var profileApi = window.studentGetApiUrl('profile');
    var loginUrl = window.studentGetLoginUrl();
    var uiBound = false;
    var initialPhone = '';

    function byId(id) {
        return document.getElementById(id);
    }

    function showElement(el, display) {
        if (!el) {
            return;
        }

        el.style.display = display || '';
    }

    function hideElement(el) {
        if (!el) {
            return;
        }

        el.style.display = 'none';
    }

    function setText(id, value) {
        var el = byId(id);
        if (el) {
            el.textContent = value == null ? '' : String(value);
        }
    }

    function showLoading(visible) {
        var loading = byId('profileLoading');
        if (!loading) {
            return;
        }

        loading.style.display = visible ? '' : 'none';
    }

    function showNoStudentState() {
        hideElement(byId('studentContent'));
        showElement(byId('noStudentNotice'));
    }

    function hideNoStudentState() {
        showElement(byId('studentContent'));
        hideElement(byId('noStudentNotice'));
    }

    function showAlert(containerId, type, message) {
        var container = byId(containerId);
        if (!container) {
            return;
        }

        if (!message) {
            container.innerHTML = '';
            return;
        }

        var cls = type === 'success' ? 'alert-success' : 'alert-error';
        container.innerHTML = '<div class="alert ' + cls + '">' + escapeHtml(message) + '</div>';
    }

    function routeMessage(message, type) {
        if (!message) {
            return;
        }

        if (message.indexOf('头像') !== -1) {
            showAlert('alertsContainer', type, message);
            return;
        }

        showAlert('contactAlerts', type, message);
    }

    function clearProfileAlerts() {
        showAlert('alertsContainer', 'success', '');
        showAlert('contactAlerts', 'success', '');
    }

    function escapeHtml(value) {
        if (value == null) {
            return '';
        }

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

    function ensurePhoneHint() {
        var phoneInput = byId('phoneInput');
        if (!phoneInput) {
            return null;
        }

        var phoneHint = byId('phoneHint');
        if (!phoneHint) {
            phoneHint = document.createElement('div');
            phoneHint.id = 'phoneHint';
            phoneHint.style.cssText = 'font-size:12px;margin-top:4px;min-height:16px;';
            phoneInput.parentNode.insertBefore(phoneHint, phoneInput.nextSibling);
        }

        return phoneHint;
    }

    function validatePhone(value) {
        return /^\d{11}$/.test((value || '').trim());
    }

    function checkPhone() {
        var phoneInput = byId('phoneInput');
        var phoneHint = ensurePhoneHint();
        if (!phoneInput || !phoneHint) {
            return true;
        }

        var value = phoneInput.value.trim();
        if (!value) {
            phoneHint.textContent = '';
            phoneInput.classList.remove('input-ok', 'input-error');
            return true;
        }

        if (validatePhone(value)) {
            phoneHint.textContent = '';
            phoneInput.classList.remove('input-error');
            phoneInput.classList.add('input-ok');
            return true;
        }

        phoneHint.textContent = '请输入11位数字手机号';
        phoneInput.classList.remove('input-ok');
        phoneInput.classList.add('input-error');
        return false;
    }

    function resetInfoForm() {
        var phoneInput = byId('phoneInput');
        if (!phoneInput) {
            return;
        }

        phoneInput.value = initialPhone || '';
        checkPhone();
    }

    function applyStatus(student) {
        var topStatusEl = byId('ptStatus');
        var statusTextEl = byId('ptStatusText');
        var pillEl = byId('statusPill');
        var isNormal = student.status_raw === 'active' || student.status === '正常';

        if (statusTextEl) {
            statusTextEl.textContent = student.status || '';
        }

        if (topStatusEl) {
            topStatusEl.classList.toggle('inactive', !isNormal);
        }

        if (pillEl) {
            pillEl.textContent = student.status || '';
            pillEl.classList.toggle('inactive', !isNormal);
            pillEl.classList.toggle('active', isNormal);
        }
    }

    function applyAvatar(student) {
        var avatarImg = byId('avatarPreview');
        var avatarPlaceholder = byId('avatarPlaceholder');

        if (student.has_avatar) {
            if (avatarImg) {
                avatarImg.src = student.avatar_path || '';
                showElement(avatarImg);
            }
            hideElement(avatarPlaceholder);
            return;
        }

        hideElement(avatarImg);
        if (avatarPlaceholder) {
            avatarPlaceholder.textContent = student.avatar_initials || '';
            showElement(avatarPlaceholder);
        }
    }

    function populateFromApi(data) {
        showLoading(false);
        hideNoStudentState();

        if (data && data.csrf_token) {
            window.__CSRF_TOKEN = data.csrf_token;
        }

        clearProfileAlerts();
        routeMessage(data && data.success_msg, 'success');
        routeMessage(data && data.error_msg, 'error');

        if (!data || !data.student) {
            showNoStudentState();
            return;
        }

        var student = data.student;

        setText('ptName', student.name || '');
        setText('ptId', student.student_id || '');
        setText('ptDept', student.dept_name || '');

        setText('igName', student.name || '');
        setText('igStudentId', student.student_id || '');
        setText('igGender', student.gender || '');
        setText('igDept', student.dept_name || '');
        setText('igGrade', student.grade_label || '');
        setText('igEnrollYear', student.enrollment_year || '');

        applyStatus(student);
        applyAvatar(student);

        var phoneInput = byId('phoneInput');
        if (phoneInput) {
            phoneInput.value = student.phone || '';
            initialPhone = student.phone || '';
            checkPhone();
        }

        if (student.name) {
            document.title = '学生门户';
        }
    }

    function handleProfileError(message) {
        showLoading(false);
        hideNoStudentState();
        showAlert('contactAlerts', 'error', message || '个人信息加载失败，请稍后重试。');
    }

    function fetchProfileJson(options) {
        options = options || {};

        return fetch(profileApi, options).then(function (resp) {
            if (resp.redirected) {
                window.location.href = resp.url;
                throw new Error('redirect');
            }

            var contentType = (resp.headers.get('content-type') || '').toLowerCase();
            if (resp.url && resp.url.indexOf(loginUrl) !== -1) {
                window.location.href = loginUrl;
                throw new Error('redirect');
            }

            if (contentType.indexOf('application/json') === -1) {
                throw new Error('not-json');
            }

            return resp.json();
        });
    }

    function loadProfileData() {
        showLoading(true);

        return fetchProfileJson({
            credentials: 'include',
            cache: 'no-store'
        }).then(function (data) {
            if (data && data.error === 'unauthenticated') {
                window.location.href = loginUrl;
                return null;
            }

            if (!data || data.success === false) {
                handleProfileError((data && (data.error_msg || data.message)) || '个人信息加载失败，请稍后重试。');
                return null;
            }

            populateFromApi(data);
            return data;
        }).catch(function (err) {
            if (err && (err.message === 'redirect')) {
                return null;
            }

            console.error(err);
            handleProfileError('个人信息加载失败，请稍后重试。');
            return null;
        });
    }

    function resetAvatarForm() {
        var avatarForm = byId('avatarForm');
        var avatarInput = byId('avatarInput');
        var avatarSubmit = byId('avatarSubmit');
        var avatarPreviewRow = byId('avatarPreviewRow');
        var avatarNewPreview = byId('avatarNewPreview');
        var avatarFileName = byId('avatarFileName');

        if (avatarForm) {
            avatarForm.classList.remove('visible');
        }
        if (avatarInput) {
            avatarInput.value = '';
        }
        if (avatarSubmit) {
            avatarSubmit.disabled = true;
        }
        if (avatarPreviewRow) {
            avatarPreviewRow.style.display = 'none';
        }
        if (avatarNewPreview) {
            avatarNewPreview.src = '';
        }
        if (avatarFileName) {
            avatarFileName.textContent = '';
        }
    }

    function bindAvatarUi() {
        var avatarWrap = document.querySelector('.profile-avatar-wrap');
        var avatarForm = byId('avatarForm');
        var avatarInput = byId('avatarInput');
        var avatarSubmit = byId('avatarSubmit');
        var avatarCancel = byId('avatarCancel');
        var avatarFileLabel = document.querySelector('.avatar-file-label');
        var avatarPreviewRow = byId('avatarPreviewRow');
        var avatarNewPreview = byId('avatarNewPreview');
        var avatarFileName = byId('avatarFileName');

        if (avatarWrap && avatarForm && avatarInput) {
            avatarWrap.addEventListener('click', function () {
                avatarForm.classList.add('visible');
                avatarInput.click();
            });

            avatarWrap.setAttribute('role', 'button');
            avatarWrap.setAttribute('tabindex', '0');
            avatarWrap.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ' || ev.key === 'Spacebar') {
                    ev.preventDefault();
                    avatarForm.classList.add('visible');
                    avatarInput.click();
                }
            });
        }

        if (avatarFileLabel && avatarInput) {
            avatarFileLabel.addEventListener('click', function () {
                avatarInput.click();
            });
        }

        if (avatarInput) {
            avatarInput.addEventListener('change', function () {
                var file = avatarInput.files && avatarInput.files[0];
                if (!file) {
                    return;
                }

                if (avatarFileName) {
                    avatarFileName.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                }

                var reader = new FileReader();
                reader.onload = function (event) {
                    if (avatarNewPreview) {
                        avatarNewPreview.src = event.target.result;
                    }
                    if (avatarPreviewRow) {
                        avatarPreviewRow.style.display = 'flex';
                    }
                };
                reader.readAsDataURL(file);

                if (avatarSubmit) {
                    avatarSubmit.disabled = false;
                }
                if (avatarForm) {
                    avatarForm.classList.add('visible');
                }
            });
        }

        if (avatarCancel) {
            avatarCancel.addEventListener('click', function () {
                resetAvatarForm();
            });
        }

        if (window.location.hash === '#avatar' && avatarForm) {
            avatarForm.classList.add('visible');
            setTimeout(function () {
                avatarForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 300);
        }

        if (avatarForm && avatarInput) {
            avatarForm.addEventListener('submit', function (ev) {
                ev.preventDefault();

                if (!avatarInput.files || avatarInput.files.length === 0) {
                    showAlert('alertsContainer', 'error', '请先选择头像图片。');
                    return;
                }

                if (avatarSubmit) {
                    avatarSubmit.disabled = true;
                }

                var formData = new FormData(avatarForm);
                if (window.__CSRF_TOKEN) {
                    formData.append('csrf_token', window.__CSRF_TOKEN);
                }

                fetchProfileJson({
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                }).then(function (data) {
                    if (!data) {
                        return;
                    }

                    if (data.error === 'unauthenticated') {
                        window.location.href = loginUrl;
                        return;
                    }

                    populateFromApi(data);
                    if (data.success) {
                        resetAvatarForm();
                        if (window.loadStudentSidebar) {
                            window.loadStudentSidebar({ force: true, view: 'profile' });
                        }
                    } else if (avatarSubmit) {
                        avatarSubmit.disabled = false;
                    }
                }).catch(function (err) {
                    if (err && err.message === 'redirect') {
                        return;
                    }

                    console.error(err);
                    showAlert('alertsContainer', 'error', '头像上传失败，请稍后重试。');
                    if (avatarSubmit) {
                        avatarSubmit.disabled = false;
                    }
                });
            });
        }
    }

    function bindInfoForm() {
        var phoneInput = byId('phoneInput');
        var infoForm = byId('infoForm');

        if (phoneInput) {
            phoneInput.addEventListener('input', checkPhone);
        }

        if (!infoForm) {
            return;
        }

        infoForm.addEventListener('submit', function (ev) {
            ev.preventDefault();

            if (!checkPhone()) {
                if (phoneInput) {
                    phoneInput.focus();
                }
                return;
            }

            var submitBtn = infoForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            var formData = new FormData(infoForm);
            if (window.__CSRF_TOKEN) {
                formData.append('csrf_token', window.__CSRF_TOKEN);
            }

            fetchProfileJson({
                method: 'POST',
                body: formData,
                credentials: 'include'
            }).then(function (data) {
                if (!data) {
                    return;
                }

                if (data.error === 'unauthenticated') {
                    window.location.href = loginUrl;
                    return;
                }

                populateFromApi(data);
            }).catch(function (err) {
                if (err && err.message === 'redirect') {
                    return;
                }

                console.error(err);
                showAlert('contactAlerts', 'error', '个人信息保存失败，请稍后重试。');
            }).finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
        });
    }

    function bindUiOnce() {
        if (uiBound) {
            return;
        }

        uiBound = true;
        window.resetInfoForm = resetInfoForm;
        ensurePhoneHint();
        bindAvatarUi();
        bindInfoForm();
    }

    function init() {
        bindUiOnce();

        if (window.loadStudentSidebar) {
            window.loadStudentSidebar({ view: 'profile' });
        }

        window.refreshProfileData = loadProfileData;
        loadProfileData();
    }

    if (window.__SINGLE_PAGE_APP) {
        window.initProfilePage = init;
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
