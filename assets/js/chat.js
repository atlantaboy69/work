// chat.js
// ===============================================
// UPLOAD "+" MENU
// ===============================================
let activeUploadBtn = null;

function toggleUploadMenu(event) {
    event.stopPropagation();
    const menu = document.getElementById('uploadMenu');
    const btn = event.currentTarget; 

    if (!menu || !btn) return;

    if (menu.parentNode !== document.body) {
        document.body.appendChild(menu);
    }

    if (menu.classList.contains('show')) {
        closeUploadMenu();
    } else {
        activeUploadBtn = btn;
        menu.classList.add('show');
        updateUploadMenuPosition();

        window.addEventListener('resize', updateUploadMenuPosition);
        window.addEventListener('scroll', updateUploadMenuPosition, true);
    }
}

function updateUploadMenuPosition() {
    const menu = document.getElementById('uploadMenu');
    if (!menu || !activeUploadBtn || !menu.classList.contains('show')) return;

    const rect = activeUploadBtn.getBoundingClientRect();
    menu.style.position = 'fixed';
    menu.style.left = rect.left + 'px';
    menu.style.bottom = (window.innerHeight - rect.top + 8) + 'px';
    menu.style.top = 'auto';
    menu.style.zIndex = '999999';
}

function closeUploadMenu() {
    const menu = document.getElementById('uploadMenu');
    if (menu && menu.classList.contains('show')) {
        menu.classList.remove('show');
        activeUploadBtn = null;
        window.removeEventListener('resize', updateUploadMenuPosition);
        window.removeEventListener('scroll', updateUploadMenuPosition, true);
    }
}

// ===============================================
// ФОНОВАЯ ЗАГРУЗКА И ПРЕВЬЮ ФАЙЛОВ С АНИМАЦИЕЙ
// ===============================================
let selectedFiles = []; 

// Векторная SVG-иконка документа
const DOCUMENT_SVG_ICON = '<svg class="file-icon-svg" viewBox="0 0 24 24" style="width:26px;height:26px;fill:var(--sber-blue, #333F48);"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>';

function updateTypingClass() {
    const ta = document.getElementById('user-query');
    const chatContainer = document.querySelector('.chat-container');
    if (!ta || !chatContainer) return;

    const val = ta.value || '';
    if (val.trim().length > 0 || selectedFiles.length > 0) {
        chatContainer.classList.add('typing');
    } else {
        chatContainer.classList.remove('typing');
    }
}

function updateMultilineMode() {
    const ta = document.getElementById('user-query');
    if (!ta) return;
    const wrapper = ta.closest('.chat-input-wrapper');
    if (!wrapper) return;

    const val = ta.value || '';
    const hasLineBreak = val.includes('\n');
    const isLongText = val.length > 32;
    const isScrollable = ta.scrollHeight > 44;

    if (hasLineBreak || isLongText || isScrollable) {
        wrapper.classList.add('multiline-mode');
    } else {
        wrapper.classList.remove('multiline-mode');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const ta = document.getElementById('user-query');
    if (ta) {
        ta.addEventListener('input', () => { updateTypingClass(); updateCaretVisibility(); updateMultilineMode(); });
        ta.addEventListener('focus', () => { updateTypingClass(); updateCaretVisibility(); updateMultilineMode(); });
        ta.addEventListener('blur', () => { updateTypingClass(); updateCaretVisibility(); updateMultilineMode(); });
    }
    updateTypingClass();
    updateMultilineMode();
});

// ==========================================================================
// УПРАВЛЕНИЕ МИГАЮЩИМ КУРСОРЕМ И АВТОФОКУСОМ (ПК vs МОБИЛЬНЫЙ)
// ==========================================================================
function updateCaretVisibility() {
    const uq = document.getElementById('user-query');
    if (!uq) return;

    let caret = document.getElementById('custom-chat-caret');
    if (!caret) {
        caret = document.createElement('span');
        caret.id = 'custom-chat-caret';
        caret.className = 'custom-chat-caret';

        let wrapper = uq.closest('.chat-input-textarea-wrapper');
        if (!wrapper && uq.parentNode) {
            wrapper = document.createElement('div');
            wrapper.className = 'chat-input-textarea-wrapper';
            uq.parentNode.insertBefore(wrapper, uq);
            wrapper.appendChild(uq);
        }
        if (wrapper) wrapper.appendChild(caret);
    }

    const isEmpty = (!uq.value || uq.value.trim() === '');
    const isFocused = (document.activeElement === uq);

    if (isEmpty && !isFocused) {
        const style = window.getComputedStyle(uq);
        const padLeft = (parseFloat(style.paddingLeft) || 12) + (parseFloat(style.marginLeft) || 0);
        caret.style.left = padLeft + 'px';
        caret.style.display = 'block';
    } else {
        caret.style.display = 'none';
    }
}
window.updateCaretVisibility = updateCaretVisibility;

function handleChatModeEntryFocus() {
    const uq = document.getElementById('user-query');
    if (!uq) return;

    if (window.innerWidth > 768) {
        uq.focus();
        setTimeout(() => { uq.focus(); }, 50);
    }
    updateCaretVisibility();
}
window.handleChatModeEntryFocus = handleChatModeEntryFocus;

function handleFileSelect(type) {
    const inputId = (type === 'file') ? 'file-input' : 'photo-input';
    const input = document.getElementById(inputId);

    if (input && input.files) {
        Array.from(input.files).forEach(file => {
            const fileObj = {
                id: Date.now() + Math.random().toString(36).substring(2, 9),
                file: file,
                status: 'uploading',
                loaded: 0,
                path: null,
                url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null
            };
            selectedFiles.push(fileObj);
            uploadFileAsync(fileObj);
        });
        renderFilePreviews();
        input.value = ''; 
    }
}

function updateOverallUploadProgress() {
    // Прогресс-бар удалён — загрузка отображается спиннером на каждом файле
}

function uploadFileAsync(fileObj) {
    const formData = new FormData();
    formData.append('async_upload', '1');
    formData.append('file', fileObj.file);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api_chat.php', true);

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            fileObj.loaded = e.loaded;
            updateOverallUploadProgress();
        }
    };

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.success) {
                    fileObj.status = 'uploaded';
                    fileObj.path = res.path;
                    fileObj.loaded = fileObj.file.size;
                } else { fileObj.status = 'error'; }
            } catch(e) { fileObj.status = 'error'; }
        } else { fileObj.status = 'error'; }
        updateOverallUploadProgress();
        renderFilePreviews();
        updateTypingClass();
    };

    xhr.onerror = function() {
        fileObj.status = 'error';
        updateOverallUploadProgress();
        renderFilePreviews();
        updateTypingClass();
    };

    xhr.send(formData);
    updateOverallUploadProgress();
}

function renderFilePreviews() {
    const grid = document.getElementById('file-preview-grid');
    if (!grid) return;

    if (selectedFiles.length === 0) {
        grid.classList.remove('has-files');
        grid.style.display = 'none';
        grid.innerHTML = '';
        updateTypingClass();
        return;
    }

    grid.style.display = 'flex';
    grid.innerHTML = '';

    selectedFiles.forEach((fileObj, index) => {
        const item = document.createElement('div');
        item.className = 'file-preview-item';

        if (fileObj.status === 'uploading') item.classList.add('is-processing');
        if (fileObj.status === 'error') item.classList.add('is-error');

        if (fileObj.file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = fileObj.url;
            item.appendChild(img);
        } else {
            const icon = document.createElement('span');
            icon.className = 'file-icon-placeholder';
            icon.innerHTML = DOCUMENT_SVG_ICON;
            item.appendChild(icon);
        }

        const tooltip = document.createElement('span');
        tooltip.className = 'file-name-tooltip';
        if (fileObj.status === 'error') {
            tooltip.innerText = 'Ошибка загрузки';
            tooltip.style.background = '#ef4444';
        } else if (fileObj.status === 'uploading') {
            const pct = fileObj.file.size > 0 ? Math.round((fileObj.loaded / fileObj.file.size) * 100) : 0;
            tooltip.innerText = `Загрузка ${pct}%...`;
        } else {
            tooltip.innerText = fileObj.file.name;
        }
        item.appendChild(tooltip);

        if (fileObj.status === 'uploading') {
            const spinner = document.createElement('div');
            spinner.className = 'file-spinner';
            item.appendChild(spinner);
        }

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-file-btn';
        removeBtn.innerText = '✕';
        removeBtn.onclick = function(e) {
            e.stopPropagation();
            selectedFiles.splice(index, 1);
            updateOverallUploadProgress();
            renderFilePreviews();
        };
        item.appendChild(removeBtn);

        grid.appendChild(item);
    });

    grid.classList.add('has-files');
    updateTypingClass(); 
}

function clearFileSelect() {
    selectedFiles = [];
    const photo = document.getElementById('photo-input');
    const file  = document.getElementById('file-input');
    if (photo) photo.value = '';
    if (file)  file.value  = '';
    const grid = document.getElementById('file-preview-grid');
    if (grid) {
        grid.classList.remove('has-files');
        grid.style.display = 'none';
        grid.innerHTML = '';
    }
    updateOverallUploadProgress();
    updateTypingClass();
}

// ===============================================
// МОДАЛЬНОЕ ОКНО ПЕРЕКРЫТИЯ ЭКРАНА ПРИ ЛИМИТЕ
// ===============================================
function showAuthModal() {
    let modal = document.getElementById('guestLimitModal');
    if (!modal) {
        const modalHtml = 
        '<div id="guestLimitModal" class="modal" style="display: flex; position: fixed; z-index: 100000; left: 0; top: 0; width: 100vw; height: 100vh; background-color: rgba(15, 23, 42, 0.85); justify-content: center; align-items: center; backdrop-filter: blur(8px); animation: fadeIn 0.3s ease;">' +
            '<div class="auth-card" style="max-width: 420px; padding: 32px 28px; text-align: center; box-shadow: var(--shadow-lg); border: 2px solid var(--primary-color);" onclick="event.stopPropagation();">' +
                '<div style="margin-bottom: 16px;">' +
                    '<svg viewBox="0 0 24 24" style="width: 48px; height: 48px; fill: var(--primary-color);"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>' +
                '</div>' +
                '<h3 style="margin-bottom: 12px; color: var(--text-dark); font-size: 22px; font-weight: 800;">Лимит запросов исчерпан</h3>' +
                '<p style="color: var(--text-muted); font-size: 15px; line-height: 1.5; margin-bottom: 24px;">' +
                    'Вы использовали все 3 бесплатных гостевых запроса. Чтобы продолжить общение с MedAI и сохранить историю диалогов, войдите в аккаунт или зарегистрируйтесь.' +
                '</p>' +
                '<div style="display: flex; flex-direction: column; gap: 10px;">' +
                    '<a href="index.php?auth=1" class="btn btn-patient" style="width: 100%; padding: 12px; font-size: 15px; border-radius: 12px; text-decoration: none;">Войти в аккаунт</a>' +
                    '<a href="register.php" class="btn btn-student" style="width: 100%; padding: 12px; font-size: 15px; border-radius: 12px; text-decoration: none;">Зарегистрироваться</a>' +
                '</div>' +
            '</div>' +
        '</div>';
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    } else {
        modal.style.display = 'flex';
    }
}

// ===============================================
// ОТПРАВКА ДАННЫХ И ДИНАМИЧЕСКИЙ ВЫВОД ИСТОРИИ ЧАТА
// ===============================================
let isSendingMessage = false;

/**
 * Анимация трансформации формы ввода и кнопок при смене состояния пустого чата
 * Использует View Transitions API с фоллбэком на нативную JS FLIP-анимацию
 */
function animateChatEmptyStateChange(chatContainer, stateChangeCallback) {
    if (!chatContainer) {
        stateChangeCallback();
        return;
    }

    if (typeof document.startViewTransition === 'function') {
        document.startViewTransition(() => {
            stateChangeCallback();
        });
        return;
    }

    const selectors = [
        '.chat-input-wrapper',
        '.left-controls.upload-area',
        '.chat-input-textarea-wrapper',
        '.chat-input-actions'
    ];

    const elements = selectors.map(sel => chatContainer.querySelector(sel)).filter(Boolean);
    const firstMap = new Map();

    elements.forEach(el => {
        firstMap.set(el, el.getBoundingClientRect());
    });

    stateChangeCallback();

    void chatContainer.offsetHeight;

    elements.forEach(el => {
        const first = firstMap.get(el);
        const last = el.getBoundingClientRect();
        if (!first || !last) return;

        const deltaX = first.left - last.left;
        const deltaY = first.top - last.top;
        const scaleX = last.width ? first.width / last.width : 1;
        const scaleY = last.height ? first.height / last.height : 1;

        if (Math.abs(deltaX) > 0.5 || Math.abs(deltaY) > 0.5 || Math.abs(scaleX - 1) > 0.01 || Math.abs(scaleY - 1) > 0.01) {
            el.animate([
                {
                    transformOrigin: 'top left',
                    transform: `translate(${deltaX}px, ${deltaY}px) scale(${scaleX}, ${scaleY})`
                },
                {
                    transformOrigin: 'top left',
                    transform: 'none'
                }
            ], {
                duration: 380,
                easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
                fill: 'both'
            });
        }
    });
}

function sendMessage(mode) {
    if (isSendingMessage) return;

    const queryField = document.getElementById('user-query');
    if (!queryField) return;

    const queryText = queryField.value.trim();
    let universityText = '';
    const universityInput = document.getElementById('university-input');
    if (universityInput) universityText = universityInput.value.trim();

    if (selectedFiles.some(f => f.status === 'uploading')) {
        alert('Пожалуйста, дождитесь окончания загрузки файлов.');
        return;
    }

    if (!queryText && selectedFiles.filter(f => f.status === 'uploaded').length === 0) {
        return;
    }

    isSendingMessage = true;

    const chatIdField = document.getElementById('current-chat-id');
    const isNewChat = (!chatIdField || !chatIdField.value);

    if (window.medicalPhysicsInstance) {
        window.medicalPhysicsInstance.openTrapdoor();
    }
    const chatContainer = document.querySelector('.chat-container');
    if (chatContainer) {
        if (chatContainer.classList.contains('chat-is-empty')) {
            animateChatEmptyStateChange(chatContainer, () => {
                chatContainer.classList.remove('chat-is-empty');
                chatContainer.querySelectorAll('.prompts-carousel-container').forEach(el => el.style.display = 'none');
                chatContainer.querySelectorAll('.empty-center-header').forEach(el => el.style.display = 'none');
            });
        } else {
            chatContainer.querySelectorAll('.prompts-carousel-container').forEach(el => el.style.display = 'none');
            chatContainer.querySelectorAll('.empty-center-header').forEach(el => el.style.display = 'none');
        }
        
        const chatWindow = document.getElementById('chat-window');
        if (chatWindow) {
            chatWindow.style.opacity = '1';
            chatWindow.style.transform = 'translateY(0)';
        }
    }

    let filesHtml = '<div class="chat-message-files" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">';
    let hasAnyFiles = false;

    selectedFiles.forEach(fileObj => {
        if (fileObj.status !== 'uploaded') return; 
        hasAnyFiles = true;

        if (fileObj.file.type.startsWith('image/')) {
            filesHtml += '<div class="file-preview-item" onclick="openImageModal(\'' + fileObj.url + '\')" style="cursor: pointer;">' +
                '<img src="' + fileObj.url + '" alt="Фото">' +
                '<span class="file-name-tooltip">' + htmlspecialchars(fileObj.file.name) + '</span>' +
            '</div>';
        } else {
            filesHtml += '<div class="file-preview-item">' +
                '<span class="file-icon-placeholder">' + DOCUMENT_SVG_ICON + '</span>' +
                '<span class="file-name-tooltip">' + htmlspecialchars(fileObj.file.name) + '</span>' +
            '</div>';
        }
    });
    filesHtml += '</div>';
    if (!hasAnyFiles) filesHtml = '';

    const textHtml = queryText ? '<div style="' + (filesHtml ? 'margin-top:6px;' : '') + '">' + nl2br(htmlspecialchars(queryText)) + '</div>' : '';
    appendMessage(filesHtml + textHtml, 'user-msg', true);

    const formData = new FormData();
    formData.append('query', queryText);
    formData.append('mode', mode);
    formData.append('university', universityText);

    if (chatIdField && chatIdField.value) formData.append('chat_id', chatIdField.value);

    selectedFiles.forEach(fileObj => {
        if (fileObj.status === 'uploaded' && fileObj.path) {
            formData.append('uploaded_files[]', fileObj.path);
            formData.append('uploaded_names[]', fileObj.file.name); 
        }
    });

    queryField.value = '';
    const inputWrapper = document.querySelector('.chat-input-wrapper');
    if (inputWrapper) inputWrapper.classList.remove('multiline-mode', 'expanded-input-desktop-style');
    queryField.dispatchEvent(new Event('input'));
    clearFileSelect();
    if (window.innerWidth > 768) {
        queryField.focus();
    }
    updateCaretVisibility();
    updateMultilineMode();

    const loadingHtml = '<div class="medai-loading-status">' +
        '<span>MedAI</span>' +
        '<div class="status-carousel">' +
            '<div class="status-carousel-track">' +
                '<span>думает</span>' +
                '<span>исследует</span>' +
                '<span>ищет</span>' +
                '<span>всё ещё ищет</span>' +
                '<span>думает</span>' +
            '</div>' +
        '</div>' +
        '<div class="typing-indicator"><span></span><span></span><span></span></div>' +
    '</div>';
    const loadingId = appendMessage(loadingHtml, 'ai-msg', true, true);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api_chat.php', true);

    xhr.addEventListener('load', () => {
        isSendingMessage = false;
        removeMessage(loadingId);
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);

                // Если лимит гостя исчерпан — показываем полноэкранный модальный блок без авто-перехода
                if (data.require_auth) {
                    showAuthModal();
                    return;
                }

                if (data.response) {
                    if (data.interactive_terms) {
                        registerInteractiveTerms(data.interactive_terms);
                    }
                    const formattedResponse = formatMarkdown(data.response, data.interactive_terms);
                    const messageId = appendMessage(formattedResponse, 'ai-msg', true, false, data.response);
                    const targetMsgDiv = document.getElementById(messageId);

                    if (data.image_url && targetMsgDiv) {
                        const imgContainer = document.createElement('div');
                        imgContainer.style.marginTop = '12px';
                        imgContainer.innerHTML = '<img src="' + data.image_url + '" class="chat-generated-image" onclick="openImageModal(this.src)" alt="Изображение" style="max-width:100%;border-radius:var(--radius-md);cursor:pointer;">';
                        targetMsgDiv.appendChild(imgContainer);
                    }

                    if (targetMsgDiv && typeof renderMathInElement === 'function') {
                        renderMathInElement(targetMsgDiv, {
                            delimiters: [{left:'$$',right:'$$',display:true},{left:'$',right:'$',display:false}],
                            throwOnError: false
                        });
                    }

                    const chatWin = document.getElementById('chat-window');
                    if (chatWin) {
                        setTimeout(() => { chatWin.scrollTo({ top: chatWin.scrollHeight, behavior: 'smooth' }); }, 50);
                    }

                    if (data.chat_id && chatIdField && isNewChat) {
                        chatIdField.value = data.chat_id;
                        const newUrl = window.location.protocol + '//' + window.location.host + window.location.pathname + '?chat_id=' + data.chat_id;
                        window.history.pushState({ path: newUrl }, '', newUrl);

                        injectNewChatToSidebar(data.chat_id, queryText, mode);
                    }
                } else { appendMessage("<span style='color:#ef4444;'>Сервер вернул пустой ответ.</span>", 'ai-msg', true); }
            } catch (err) { appendMessage("<span style='color:#ef4444;'>Ошибка обработки ответа ИИ.</span>", 'ai-msg', true); }
        } else { appendMessage("<span style='color:#ef4444;'>Ошибка сервера. Код: " + xhr.status + "</span>", 'ai-msg', true); }
    });

    xhr.addEventListener('error', () => {
        isSendingMessage = false;
        removeMessage(loadingId);
        appendMessage("<span style='color:#ef4444;'>Ошибка сети. Попробуйте ещё раз.</span>", 'ai-msg', true);
    });

    xhr.send(formData);
}

window.interactiveTermsStore = window.interactiveTermsStore || {};

function registerInteractiveTerms(termsObj) {
    if (!termsObj || typeof termsObj !== 'object') return;
    Object.keys(termsObj).forEach(term => {
        window.interactiveTermsStore[term] = termsObj[term];
        window.interactiveTermsStore[term.toLowerCase()] = termsObj[term];
    });
}

function handleTermClick(element, event) {
    if (event) {
        if (typeof event.stopPropagation === 'function') event.stopPropagation();
        if (typeof event.preventDefault === 'function') event.preventDefault();
    }
    let imgUrl = typeof element === 'string' ? element : (element ? element.getAttribute('data-img') : '');
    let termKey = (typeof element === 'object' && element) ? (element.getAttribute('data-term') || element.innerText.trim()) : '';
    let title = (typeof element === 'object' && element) ? element.getAttribute('data-title') : '';
    let pageNum = (typeof element === 'object' && element) ? element.getAttribute('data-page') : '';

    if (!imgUrl || imgUrl === 'null' || imgUrl === 'undefined') {
        const storeData = (window.interactiveTermsStore && (window.interactiveTermsStore[termKey] || window.interactiveTermsStore[termKey.toLowerCase()]));
        if (storeData) {
            imgUrl = storeData.image_url;
            if (!title) title = storeData.title;
            if (!pageNum) pageNum = storeData.page_num;
        }
    }

    if (!imgUrl) return;

    // На мобильных устройствах открываем модальное окно на весь экран как сейчас
    if (window.innerWidth <= 768) {
        openImageModal(imgUrl);
        return;
    }

    // На десктопе - открываем или плавно обновляем выдвижной контейнер справа
    openTermAnnotationDrawer({
        imgUrl: imgUrl,
        termKey: termKey,
        title: title || termKey,
        pageNum: pageNum,
        element: typeof element === 'object' ? element : null
    });
}

let currentAnnotationWidth = parseInt(localStorage.getItem('annotation_sidebar_width')) || 360;
if (isNaN(currentAnnotationWidth) || currentAnnotationWidth < 320) currentAnnotationWidth = 360;
if (currentAnnotationWidth > 620) currentAnnotationWidth = 620;

function initAnnotationResize() {
    const handle = document.getElementById('annotationResizeHandle');
    const sidebar = document.getElementById('annotation-sidebar');
    if (!handle || !sidebar) return;

    let isDragging = false;
    let startX = 0;
    let startWidth = 0;

    const onMouseDown = (e) => {
        if (window.innerWidth <= 768) return;
        if (!sidebar.classList.contains('open')) return;
        isDragging = true;
        startX = e.clientX;
        startWidth = sidebar.getBoundingClientRect().width;
        document.body.classList.add('is-resizing-annotation');
        sidebar.classList.add('is-resizing');
        e.preventDefault();
    };

    const onMouseMove = (e) => {
        if (!isDragging) return;
        // Тянем левый край: движение влево (startX - clientX > 0) увеличивает ширину
        const delta = startX - e.clientX;
        let newWidth = startWidth + delta;

        const chatLayout = document.querySelector('.chat-layout');
        const layoutWidth = chatLayout ? chatLayout.getBoundingClientRect().width : window.innerWidth;
        const leftSidebar = document.querySelector('.chat-sidebar:not(.collapsed)');
        const leftSidebarWidth = (leftSidebar && window.innerWidth > 768) ? leftSidebar.getBoundingClientRect().width : 0;
        const maxAllowedWidth = Math.min(620, Math.max(380, layoutWidth - leftSidebarWidth - 420));

        if (newWidth < 320) newWidth = 320;
        if (newWidth > maxAllowedWidth) newWidth = maxAllowedWidth;

        sidebar.style.width = newWidth + 'px';
        currentAnnotationWidth = newWidth;
    };

    const onMouseUp = () => {
        if (!isDragging) return;
        isDragging = false;
        document.body.classList.remove('is-resizing-annotation');
        sidebar.classList.remove('is-resizing');
        try {
            localStorage.setItem('annotation_sidebar_width', currentAnnotationWidth);
        } catch (err) {}
    };

    handle.addEventListener('mousedown', onMouseDown);
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
}

function openTermAnnotationDrawer(data) {
    const sidebar = document.getElementById('annotation-sidebar');
    if (!sidebar) {
        openImageModal(data.imgUrl);
        return;
    }

    // Подсветка активного термина в тексте сообщения
    document.querySelectorAll('.interactive-term.term-active').forEach(el => el.classList.remove('term-active'));
    if (data.element) {
        data.element.classList.add('term-active');
    }

    const titleEl = document.getElementById('annotationSidebarTitle');
    const latinTagEl = document.getElementById('annotationSidebarLatin');
    const latinValEl = document.getElementById('annotationSidebarLatinVal');
    const imgEl = document.getElementById('annotationSidebarImg');
    const metaEl = document.getElementById('annotationSidebarMeta');
    const pageEl = document.getElementById('annotationSidebarPage');
    const innerEl = document.getElementById('annotationContentInner');

    const updateContent = () => {
        if (titleEl) titleEl.textContent = data.title || data.termKey || 'Анатомическая иллюстрация';
        if (latinTagEl && latinValEl) {
            if (data.termKey) {
                latinValEl.textContent = data.termKey;
                latinTagEl.style.display = 'inline-flex';
            } else {
                latinTagEl.style.display = 'none';
            }
        }
        if (imgEl) {
            imgEl.src = data.imgUrl;
            imgEl.alt = data.title || data.termKey || 'Иллюстрация';
        }
        if (metaEl && pageEl) {
            const pNum = parseInt(data.pageNum);
            if (pNum && pNum > 0) {
                pageEl.textContent = `Страница ${pNum} — Атлас анатомии`;
                metaEl.style.display = 'flex';
            } else {
                metaEl.style.display = 'none';
            }
        }
    };

    const isOpen = sidebar.classList.contains('open');

    if (isOpen) {
        // Если сайдбар уже открыт: плавно обновляем контент внутри без анимации открытия/закрытия панели
        if (innerEl) {
            innerEl.style.opacity = '0.25';
            setTimeout(() => {
                updateContent();
                innerEl.style.opacity = '1';
            }, 120);
        } else {
            updateContent();
        }
    } else {
        // Если сайдбар закрыт: заполняем контент и плавно выдвигаем с сохраненной пользователем шириной
        updateContent();
        const widthToApply = Math.min(Math.max(currentAnnotationWidth, 320), 620);
        sidebar.style.width = widthToApply + 'px';
        if (innerEl) innerEl.style.opacity = '1';
        sidebar.classList.add('open');
    }
}

function closeAnnotationDrawer() {
    const sidebar = document.getElementById('annotation-sidebar');
    if (sidebar) {
        sidebar.classList.remove('open');
        sidebar.style.width = '';
    }
    document.querySelectorAll('.interactive-term.term-active').forEach(el => el.classList.remove('term-active'));
}

function openTermModal(element) {
    handleTermClick(element);
}

function closeTermModal() {
    closeAnnotationDrawer();
    const modal = document.getElementById('termModal');
    if (modal) modal.style.display = 'none';
}

function openImageModal(src) {
    const modal = document.getElementById('imageModal');
    const img   = document.getElementById('fullSizeImage');
    if (modal && img) { img.src = src; modal.style.display = 'flex'; }
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) modal.style.display = 'none';
}

function injectNewChatToSidebar(chatId, queryText, mode) {
    const sidebar = document.querySelector('.chat-sidebar');
    if (!sidebar) return;

    const emptyTextNodes = Array.from(sidebar.childNodes).filter(node => node.nodeType === Node.ELEMENT_NODE && node.textContent.includes('Нет прошлых'));
    emptyTextNodes.forEach(node => node.remove());

    let chatTitle = queryText ? (queryText.substring(0, 30) + (queryText.length > 30 ? '...' : '')) : 'Фото-анализ...';

    const chatUrl = '?chat_id=' + encodeURIComponent(chatId);
    const deleteUrl = 'delete_chat.php?chat_id=' + encodeURIComponent(chatId);

    const newChatHtml = '<div class="history-item-wrapper" style="animation: geminiFadeBlur 0.3s ease-out forwards;">' +
        '<a href="' + chatUrl + '" class="history-item active" title="' + htmlspecialchars(chatTitle) + '" style="margin-bottom: 0; padding-right: 40px; width: 100%;">' +
            '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>' +
            '<span>' + htmlspecialchars(chatTitle) + '</span>' +
        '</a>' +
        '<a href="' + deleteUrl + '" class="delete-chat-btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: #fecaca; z-index: 10; text-decoration: none; display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px;" title="Удалить диалог">' +
            '<svg style="width: 16px; height: 16px;" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-3.5l-1-1h-5l-1 1H5v2h14M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12z"/></svg>' +
        '</a>' +
    '</div>';

    const labels = Array.from(sidebar.querySelectorAll('div'));
    const headerLabel = labels.find(el => el.textContent.toUpperCase().includes('ВАШИ') || el.textContent.toUpperCase().includes('ИСТОРИЯ'));

    if (headerLabel) {
        headerLabel.insertAdjacentHTML('afterend', newChatHtml);
    } else {
        sidebar.insertAdjacentHTML('beforeend', newChatHtml);
    }
}

function htmlspecialchars(text) {
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
function nl2br(str) {
    return str.replace(/(?:\r\n|\r|\n)/g, '<br>');
}

// ===============================================
// MESSAGE HELPERS
// ===============================================
window.copyMessageText = function(btn) {
    const textToCopy = btn.getAttribute('data-text') || '';
    navigator.clipboard.writeText(textToCopy).then(() => {
        const originalSVG = btn.innerHTML;
        btn.innerHTML = '<svg style="width:16px;height:16px;color:#10b981;" viewBox="0 0 24 24"><path fill="currentColor" d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z"/></svg>';
        btn.style.pointerEvents = 'none';
        setTimeout(() => { btn.innerHTML = originalSVG; btn.style.pointerEvents = 'auto'; }, 2000);
    });
};

function appendMessage(text, className, isHtml, skipCopyBtn = false, rawText = '') {
    const chatWindow = document.getElementById('chat-window');
    if (!chatWindow) return null;

    const emptyState = chatWindow.querySelector('.empty-state');
    if (emptyState) emptyState.remove();

    const div = document.createElement('div');
    const uid = 'msg-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
    div.id = uid;
    div.className = 'msg ' + className;

    if (isHtml) {
        div.innerHTML = text;
        div.style.whiteSpace = 'normal';
    } else {
        div.textContent = text;
        div.style.whiteSpace = 'pre-line';
    }

    if (className === 'ai-msg' && !skipCopyBtn) {
        const copyBtn = document.createElement('button');
        copyBtn.className = 'copy-msg-btn';
        copyBtn.title = 'Копировать текст';
        const copyText = rawText || div.innerText || div.textContent || '';
        copyBtn.setAttribute('data-text', copyText);
        copyBtn.setAttribute('onclick', 'copyMessageText(this)');
        copyBtn.innerHTML = '<svg style="width:16px;height:16px;" viewBox="0 0 24 24"><path fill="currentColor" d="M19,21H8V7H19M19,5H8A2,2 0 0,0 6,7V21A2,2 0 0,0 8,23H19A2,2 0 0,0 21,21V7A2,2 0 0,0 19,5M16,1H4A2,2 0 0,0 2,3V17H4V3H16V1Z"/></svg>';
        div.appendChild(copyBtn);
    }

    chatWindow.appendChild(div);
    chatWindow.scrollTo({ top: chatWindow.scrollHeight, behavior: 'smooth' });
    return uid;
}

function removeMessage(id) {
    const el = document.getElementById(id);
    if (el) {
        el.remove();
    }
}

function formatInlineMarkdown(str) {
    if (!str) return '';
    str = str.replace(/\*\*\*(.*?)\*\*\*/g, '<strong><em>$1</em></strong>');
    str = str.replace(/___(.*?)___/g, '<strong><em>$1</em></strong>');
    str = str.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    str = str.replace(/__(.*?)__/g, '<strong>$1</strong>');
    str = str.replace(/`([^`\n]+)`/g, '<code class="markdown-inline-code">$1</code>');
    str = str.replace(/\*([^\*\n]+)\*/g, '<em>$1</em>');
    str = str.replace(/_([^_\n]+)_/g, '<em>$1</em>');
    str = str.replace(/~~(.*?)~~/g, '<del>$1</del>');
    return str;
}

function formatMarkdown(text, interactiveTerms) {
    if (!text) return '';
    if (typeof text !== 'string') {
        if (Array.isArray(text)) {
            text = text.map(item => (typeof item === 'string' ? item : (item.text || ''))).join('');
        } else {
            text = String(text);
        }
    }

    let clean = text.trim();
    clean = clean.replace(/<(think|thought)>[\s\S]*?<\/\1>/gi, '');

    if (clean.startsWith('{') && clean.endsWith('}')) {
        try {
            const parsed = JSON.parse(clean);
            if (parsed.action === 'dalle.text2im' || parsed.image_url) {
                let out = '';
                if (parsed.thought) out += '<div class="md-line" style="color:var(--text-muted);font-style:italic;padding-left:12px;border-left:3px solid var(--primary-color);">' + parsed.thought + '</div>';
                if (parsed.image_url) out += '<img src="' + parsed.image_url + '" class="chat-generated-image" onclick="openImageModal(this.src)" style="max-width:100%;border-radius:var(--radius-md);cursor:pointer;">';
                return out;
            }
        } catch(e) {}
    }

    // 1. ИЗВЛЕКАЕМ МАТЕМАТИЧЕСКИЕ БЛОКИ ($$ ... $$ или \[ ... \]) В ЗАГЛУШКИ ДО ЭКРАНИРОВАНИЯ
    const mathBlocks = [];
    clean = clean.replace(/(\$\$[\s\S]*?\$\$|\\\[[\s\S]*?\\\])/g, (match) => {
        const placeholder = '@@@MATH_BLOCK_' + mathBlocks.length + '@@@';
        mathBlocks.push(match);
        return placeholder;
    });

    // 2. ИЗВЛЕКАЕМ БЛОКИ КОДА (``` ... ```) В ЗАГЛУШКИ
    const codeBlocks = [];
    clean = clean.replace(/```([a-zA-Z0-9_+-]*)[ \t]*\r?\n?([\s\S]*?)```/g, (match, lang, code) => {
        const placeholder = '@@@CODE_BLOCK_' + codeBlocks.length + '@@@';
        codeBlocks.push({ lang: lang.trim() || 'code', code: code.trim() });
        return placeholder;
    });

    let html = clean.replace(/</g, '&lt;').replace(/>/g, '&gt;');

    const lines = html.split('\n');
    let inTable = false, tableRows = [], processedLines = [];

    for (let line of lines) {
        const trimmed = line.trim();

        if (trimmed.includes('@@@CODE_BLOCK_') || trimmed.includes('@@@MATH_BLOCK_')) {
            processedLines.push(trimmed);
            continue;
        }

        if (trimmed === '*' || trimmed === '-' || trimmed === '***') continue;

        const pipes = (trimmed.match(/\|/g) || []).length;
        if (pipes >= 2) {
            if (trimmed.replace(/[\s|:-]/g, '') === '') continue;
            let cells = trimmed.split('|');
            if (cells[0].trim() === '') cells.shift();
            if (cells.length && cells[cells.length - 1].trim() === '') cells.pop();
            cells = cells.map(c => c.trim());

            if (!inTable) {
                inTable = true;
                tableRows.push('<thead><tr>');
                cells.forEach(c => tableRows.push('<th>' + formatInlineMarkdown(c) + '</th>'));
                tableRows.push('</tr></thead><tbody>');
            } else {
                tableRows.push('<tr>');
                cells.forEach(c => tableRows.push('<td>' + formatInlineMarkdown(c) + '</td>'));
                tableRows.push('</tr>');
            }
            continue;
        } else if (inTable) {
            tableRows.push('</tbody>');
            processedLines.push('<div class="table-container"><table class="markdown-table">' + tableRows.join('') + '</table></div>');
            inTable = false;
            tableRows = [];
        }

        if (/^#{1,6}\s*(.*?)$/.test(trimmed)) {
            const m = trimmed.match(/^(#{1,6})\s*(.*?)$/);
            const level = m[1].length;
            const title = formatInlineMarkdown(m[2].trim());
            processedLines.push(`<h${level} class="chat-h${level}">${title}</h${level}>`);
            continue;
        }
        if (/^---$/.test(trimmed)) {
            processedLines.push('<hr class="markdown-hr">');
            continue;
        }
        if (/^&gt;[ \t]*(.*?)$/.test(trimmed)) {
            let quoteText = trimmed.replace(/^&gt;[ \t]*(.*?)$/, '$1');
            processedLines.push('<blockquote class="markdown-blockquote">' + formatInlineMarkdown(quoteText) + '</blockquote>');
            continue;
        }

        if (/^[\*\-\+\u2022\u2013\u2014]\s*(.*)$/.test(trimmed)) {
            let itemText = trimmed.replace(/^[\*\-\+\u2022\u2013\u2014]\s*(.*)$/, '$1');
            itemText = formatInlineMarkdown(itemText);
            processedLines.push('<div class="md-line">• ' + itemText + '</div>');
            continue;
        }

        if (/^(\d+\.)\s*(.*)$/.test(trimmed)) {
            let match = trimmed.match(/^(\d+\.)\s*(.*)$/);
            let num = match[1];
            let itemText = formatInlineMarkdown(match[2]);
            processedLines.push('<div class="md-line"><strong>' + num + '</strong> ' + itemText + '</div>');
            continue;
        }

        if (trimmed !== '') {
            let lineText = formatInlineMarkdown(trimmed);
            processedLines.push('<div class="md-line">' + lineText + '</div>');
        }
    }

    if (inTable) {
        tableRows.push('</tbody>');
        processedLines.push('<div class="table-container"><table class="markdown-table">' + tableRows.join('') + '</table></div>');
    }

    let finalText = processedLines.join('');

    // ВОССТАНАВЛИВАЕМ МАТЕМАТИЧЕСКИЕ БЛОКИ
    mathBlocks.forEach((block, index) => {
        const mathSnippet = '<div class="math-block-container" style="margin: 10px 0; overflow-x: auto;">' + block + '</div>';
        finalText = finalText.replace('@@@MATH_BLOCK_' + index + '@@@', mathSnippet);
    });

    // ВОССТАНАВЛИВАЕМ БЛОКИ КОДА
    codeBlocks.forEach((block, index) => {
        const safeData = block.code.replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const safeView = block.code.replace(/</g, '&lt;').replace(/>/g, '&gt;');

        const snippet = '<div class="code-block-wrapper">' +
            '<div class="code-block-header">' +
                '<span class="code-lang">' + block.lang + '</span>' +
                '<button class="code-copy-btn" onclick="copyCodeSnippet(this)" data-code="' + safeData + '">' +
                    '<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg> ' +
                    'Копировать' +
                '</button>' +
            '</div>' +
            '<pre class="code-block-body"><code>' + safeView + '</code></pre>' +
        '</div>';
        finalText = finalText.replace('@@@CODE_BLOCK_' + index + '@@@', snippet);
    });

    const activeTerms = interactiveTerms || window.interactiveTermsStore;
    if (activeTerms && typeof activeTerms === 'object') {
        const sortedKeys = Object.keys(activeTerms).sort((a, b) => b.length - a.length);
        const termPlaceholders = [];

        sortedKeys.forEach(termKey => {
            if (!termKey || termKey.length < 3) return;
            const termData = activeTerms[termKey] || {};
            const esc = termKey.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp('(?<!<[^>]*)\\b(' + esc + ')\\b(?![^<]*>)', 'gi');
            const safeImg = (termData.image_url || '').replace(/"/g, '&quot;');
            const safeTitle = (termData.title || termKey).replace(/"/g, '&quot;');
            const pageNum = termData.page_num || 0;
            const safeTerm = termKey.replace(/"/g, '&quot;');

            finalText = finalText.replace(regex, (match, matchedTerm) => {
                const ph = '@@@INTERACTIVE_TERM_' + termPlaceholders.length + '@@@';
                const badge = `<span class="interactive-term" data-term="${safeTerm}" data-img="${safeImg}" data-title="${safeTitle}" data-page="${pageNum}" onclick="handleTermClick(this, event)">${matchedTerm}</span>`;
                termPlaceholders.push({ ph: ph, html: badge });
                return ph;
            });
        });

        termPlaceholders.forEach(item => {
            finalText = finalText.replace(item.ph, item.html);
        });

        // Пост-обработка: нахождение скобок вокруг интерактивных плашек (включая <em>/<strong>) и объединение в .interactive-term-group
        finalText = finalText.replace(/\(\s*([^\)]*?<span class="interactive-term"[^>]*>[\s\S]*?<\/span>[^\(]*?)\s*\)/gi, (match, inner) => {
            return `<span class="interactive-term-group">(${inner.trim()})</span>`;
        });
    }

    return finalText;
}

window.copyCodeSnippet = function(btn) {
    const code = btn.getAttribute('data-code');
    const decodedCode = code.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&amp;/g, '&');

    navigator.clipboard.writeText(decodedCode).then(() => {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:#81c995;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> <span style="color:#81c995">Скопировано</span>';
        setTimeout(() => { btn.innerHTML = originalHTML; }, 2000);
    });
};

function openImageModal(src) {
    const modal = document.getElementById('imageModal');
    const img   = document.getElementById('fullSizeImage');
    if (modal && img) { img.src = src; modal.style.display = 'flex'; }
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) modal.style.display = 'none';
}

function toggleSidebar(event) {
    if (event) {
        if (typeof event.stopPropagation === 'function') event.stopPropagation();
        if (typeof event.preventDefault === 'function') event.preventDefault();
    }
    document.documentElement.classList.remove('sidebar-init-collapsed');

    const sidebar = document.querySelector('.chat-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (!sidebar) return;

    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('open');
        if (overlay) overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
    } else {
        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        try {
            localStorage.setItem('sidebar_collapsed', isCollapsed ? '1' : '0');
        } catch (e) {}
    }
}
window.toggleSidebar = toggleSidebar;
window.toggleMobileSidebar = toggleSidebar;

document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth > 768) {
        const sidebar = document.querySelector('.chat-sidebar');
        if (sidebar) {
            try {
                const state = localStorage.getItem('sidebar_collapsed');
                if (state === '1' || state === null) {
                    sidebar.classList.add('collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                }
            } catch (e) {}
        }
        setTimeout(function() {
            document.documentElement.classList.remove('sidebar-init-collapsed');
        }, 50);
    }
});

function toggleProfileMenu(event) {
    if (event && typeof event.stopPropagation === 'function') event.stopPropagation();
    const menu = document.getElementById('profileDropdownMenu');
    if (menu) menu.classList.toggle('show');
}

window.addEventListener('click', function (e) {
    if (e && e.target && (e.target.closest('.menu-toggle-btn') || e.target.closest('.chat-sidebar'))) {
        return;
    }
    const profileMenu = document.getElementById('profileDropdownMenu');
    if (profileMenu && profileMenu.classList.contains('show')) profileMenu.classList.remove('show');

    closeUploadMenu();

    const sidebar = document.querySelector('.chat-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        if (overlay) overlay.style.display = 'none';
    }
});

(function() {
    window.addEventListener('mousemove', (e) => {
        const distanceToRightEdge = window.innerWidth - e.clientX;
        if (distanceToRightEdge <= 20) {
            document.body.classList.add('show-right-scrollbar');
        } else {
            document.body.classList.remove('show-right-scrollbar');
        }
    });

    document.body.addEventListener('mouseleave', () => {
        document.body.classList.remove('show-right-scrollbar');
    });
})();

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const imageModal = document.getElementById('imageModal');
        if (imageModal && imageModal.style.display === 'flex') {
            closeImageModal();
        } else {
            closeAnnotationDrawer();
        }
    }
});

let activeDeleteChatId = null;
let activeDeleteWrapper = null;

function ensureConfirmModalExists() {
    let modal = document.getElementById('customConfirmModal');
    if (!modal) {
        const modalHtml = '<div id="customConfirmModal" class="modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.7); justify-content: center; align-items: center; backdrop-filter: blur(4px);">' +
            '<div class="auth-card" style="max-width: 400px; padding: 30px; text-align: left;" onclick="event.stopPropagation();">' +
                '<h3 style="margin-bottom: 12px; color: var(--text-dark); font-size: 20px; font-weight: 700; text-align: left;">Удалить диалог?</h3>' +
                '<p style="color: var(--text-muted); font-size: 14px; line-height: 1.5; margin-bottom: 24px; text-align: left;">Вы действительно хотите удалить этот диалог? Все сообщения и вложения будут стерты без возможности восстановления.</p>' +
                '<div style="display: flex; gap: 12px; justify-content: flex-end;">' +
                    '<button type="button" class="btn btn-patient" id="cancelConfirmBtn" style="width: auto; padding: 10px 20px; font-size: 14px; border-radius: 12px; cursor: pointer;">Отмена</button>' +
                    '<button type="button" class="btn" id="submitConfirmBtn" style="width: auto; padding: 10px 20px; font-size: 14px; border-radius: 12px; background-color: #ef4444; color: white; border: none; cursor: pointer;">Удалить</button>' +
                '</div>' +
            '</div>' +
        '</div>';
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const cancelBtn = document.getElementById('cancelConfirmBtn');
        const confirmModal = document.getElementById('customConfirmModal');
        const submitBtn = document.getElementById('submitConfirmBtn');

        if (cancelBtn) cancelBtn.addEventListener('click', closeConfirmModal);
        if (confirmModal) confirmModal.addEventListener('click', closeConfirmModal);
        if (submitBtn) {
            submitBtn.addEventListener('click', executeChatDeletion);
        }
    }
    return document.getElementById('customConfirmModal');
}

function executeChatDeletion() {
    if (!activeDeleteChatId) return;

    const chatId = activeDeleteChatId;
    const wrapper = activeDeleteWrapper;
    closeConfirmModal();

    if (wrapper) {
        wrapper.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        wrapper.style.transform = 'translateX(-105%)';
        wrapper.style.opacity = '0';
    }

    fetch('delete_chat.php?chat_id=' + encodeURIComponent(chatId) + '&ajax=1')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                setTimeout(() => {
                    if (wrapper) wrapper.remove();

                    const currentChatIdInput = document.getElementById('current-chat-id');
                    if (currentChatIdInput && currentChatIdInput.value === chatId) {
                        triggerNewChatCleanState();
                    }
                }, 300);
            } else {
                if (wrapper) { wrapper.style.transform = 'none'; wrapper.style.opacity = '1'; }
                alert('Ошибка сервера: ' + data.error);
            }
        })
        .catch(() => {
            if (wrapper) { wrapper.style.transform = 'none'; wrapper.style.opacity = '1'; }
            alert('Ошибка соединения с сервером.');
        });
}

document.addEventListener('click', (e) => {
    const deleteBtn = e.target.closest('.delete-chat-btn');
    if (deleteBtn) {
        e.preventDefault();
        e.stopPropagation();

        const href = deleteBtn.getAttribute('href');
        if (!href) return;
        const urlParams = new URLSearchParams(href.split('?')[1]);
        const chatId = urlParams.get('chat_id');
        const wrapper = deleteBtn.closest('.history-item-wrapper');

        if (chatId) {
            activeDeleteChatId = chatId;
            activeDeleteWrapper = wrapper;
            const modal = ensureConfirmModalExists();
            if (modal) modal.style.display = 'flex';
        }
        return;
    }

    const newChatBtn = e.target.closest('.btn-new-chat');
    const historyItem = e.target.closest('.history-item');

    if (newChatBtn || historyItem) {
        closeMobileSidebar();
    }

    if (newChatBtn) {
        e.preventDefault();
        e.stopPropagation();

        const queryField = document.getElementById('user-query');
        const queryVal = queryField ? queryField.value.trim() : '';
        const hasFiles = (typeof selectedFiles !== 'undefined' && Array.isArray(selectedFiles) && selectedFiles.length > 0);
        const chatContainer = document.querySelector('.chat-container');
        const isAlreadyEmpty = chatContainer && chatContainer.classList.contains('chat-is-empty');
        const urlParams = new URLSearchParams(window.location.search);
        const hasChatId = urlParams.has('chat_id') && urlParams.get('chat_id') !== '';

        // Если поле ввода пустое, нет файлов и мы УЖЕ в новом пустом чате без chat_id - НИЧЕГО НЕ ДЕЛАЕМ!
        if (isAlreadyEmpty && !hasChatId && queryVal === '' && !hasFiles) {
            return;
        }

        triggerNewChatCleanState();
        return;
    }
}, true);

function closeMobileSidebar() {
    const sidebar = document.querySelector('.chat-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) {
        overlay.classList.remove('active');
        overlay.style.display = 'none';
    }
    document.body.classList.remove('sidebar-open');
}
window.closeMobileSidebar = closeMobileSidebar;

function closeConfirmModal() {
    const modal = document.getElementById('customConfirmModal');
    if (modal) modal.style.display = 'none';
    activeDeleteChatId = null;
    activeDeleteWrapper = null;
}

function triggerNewChatCleanState() {
    const currentChatIdInput = document.getElementById('current-chat-id');
    if (!currentChatIdInput) return;

    document.querySelectorAll('.history-item').forEach(item => {
        item.classList.remove('active');
    });

    currentChatIdInput.value = '';

    const chatContainer = document.querySelector('.chat-container');
    if (chatContainer) {
        animateChatEmptyStateChange(chatContainer, () => {
            chatContainer.classList.add('chat-is-empty');
            chatContainer.classList.remove('typing');
            chatContainer.querySelectorAll('.prompts-carousel-container').forEach(el => el.style.display = '');
            chatContainer.querySelectorAll('.empty-center-header').forEach(el => el.style.display = '');
        });
    }

    const chatWindow = document.getElementById('chat-window');
    if (chatWindow) {
        chatWindow.innerHTML = '';
        chatWindow.style.opacity = '0';
    }

    const queryField = document.getElementById('user-query');
    if (queryField) {
        queryField.value = '';
        queryField.dispatchEvent(new Event('input'));
        handleChatModeEntryFocus();
    }
    clearFileSelect();

    if (window.medicalPhysicsInstance && typeof window.medicalPhysicsInstance.destroy === 'function') {
        window.medicalPhysicsInstance.destroy();
    }

    if (typeof MedicalPhysics === 'function') {
        window.medicalPhysicsInstance = new MedicalPhysics('.chat-container');
    }

    const isPatient = window.location.pathname.includes('patient_chat');
    const cleanUrl = isPatient ? 'patient_chat.php' : 'student_chat.php';
    window.history.pushState({ path: cleanUrl }, '', cleanUrl);
}

// ==========================================================================
// БЕСШОВНЫЙ SPA-РОУТИНГ ДЛЯ DASHBOARD И ЧАТОВ
// ==========================================================================
async function navigateToPage(url, pushHistory = true) {
    if (!url) return;
    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) {
            window.location.href = url;
            return;
        }

        const htmlText = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlText, 'text/html');
        const newBody = doc.body;
        if (!newBody) {
            window.location.href = url;
            return;
        }

        document.body.style.transition = 'opacity 0.15s ease-out';
        document.body.style.opacity = '0';

        setTimeout(() => {
            if (window.medicalPhysicsInstance && typeof window.medicalPhysicsInstance.destroy === 'function') {
                window.medicalPhysicsInstance.destroy();
            }

            closeUploadMenu();
            closeUniversityModal();
            closeConfirmModal();

            document.body.className = newBody.className;
            document.body.style.cssText = newBody.style.cssText;
            document.body.innerHTML = newBody.innerHTML;

            // Перезапуск скриптов из нового body
            const scripts = document.body.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                if (oldScript.parentNode) {
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                }
            });

            document.body.style.opacity = '1';

            if (pushHistory) {
                window.history.pushState({ path: url }, '', url);
            }

            initChatPage();
            window.scrollTo(0, 0);
        }, 150);
    } catch (e) {
        window.location.href = url;
    }
}
window.navigateToPage = navigateToPage;

function initChatPage() {
    const ta = document.getElementById('user-query');
    const chatContainer = document.querySelector('.chat-container');

    if (ta) {
        ta.removeEventListener('input', updateTypingClass);
        ta.removeEventListener('focus', updateTypingClass);
        ta.removeEventListener('blur', updateTypingClass);

        ta.addEventListener('input', () => { updateTypingClass(); updateCaretVisibility(); });
        ta.addEventListener('focus', () => { updateTypingClass(); updateCaretVisibility(); });
        ta.addEventListener('blur', () => { updateTypingClass(); updateCaretVisibility(); });

        handleChatModeEntryFocus();
    } else {
        updateTypingClass();
    }

    const cw = document.getElementById('chat-window');
    if (cw) { cw.scrollTop = cw.scrollHeight; }

    const sidebar = document.querySelector('.chat-sidebar');
    if (sidebar && window.innerWidth > 768) {
        try {
            const state = localStorage.getItem('sidebar_collapsed');
            if (state === '1' || state === null) {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
        } catch (e) {}
    }

    if (chatContainer && chatContainer.classList.contains('chat-is-empty')) {
        if (typeof MedicalPhysics === 'function') {
            window.medicalPhysicsInstance = new MedicalPhysics('.chat-container');
        }
        initPromptsCarousel();
    } else {
        if (window.medicalPhysicsInstance && typeof window.medicalPhysicsInstance.destroy === 'function') {
            window.medicalPhysicsInstance.destroy();
        }
    }
}

// Обработка кликов по кнопкам создания нового диалога и ссылкам истории


document.addEventListener('keydown', function(event) {
    if (event.target && event.target.id === 'user-query') {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            const form = event.target.closest('form');
            const isPatient = window.location.pathname.includes('patient_chat') ||
                              (form && form.getAttribute('onsubmit') && form.getAttribute('onsubmit').includes('patient'));
            const mode = isPatient ? 'patient' : 'student';
            sendMessage(mode);
            return;
        }
    }
    if (event.key === "Escape") {
        closeConfirmModal();
        closeUniversityModal();
    }
});

// ==========================================================================
// КУРСОР И ВОЛНИСТЫЙ ГРАДИЕНТ ДЛЯ CHAT-INPUT-CONTAINER
// ==========================================================================
(function initChatInputEffects() {
    document.addEventListener('DOMContentLoaded', () => {
        const inputContainer = document.querySelector('.chat-input-container');
        const inputWrapper = document.querySelector('.chat-input-wrapper');
        const userQuery = document.getElementById('user-query');

        if (userQuery) {
            handleChatModeEntryFocus();
        }
        window.addEventListener('resize', updateCaretVisibility);

        // Инициализация бейджа выбранного вуза
        const universityInput = document.getElementById('university-input');
        const badge = document.getElementById('selected-university-badge');
        if (universityInput && badge && universityInput.value.trim()) {
            badge.querySelector('.badge-text').textContent = universityInput.value.trim();
            badge.style.display = 'inline-flex';
        }

        if (!inputContainer) return;

        inputContainer.addEventListener('mousemove', (e) => {
            const rect = inputContainer.getBoundingClientRect();

            // Расчет процентных координат курсора
            const xPct = Math.min(Math.max(((e.clientX - rect.left) / rect.width) * 100, 0), 100);
            const yPct = Math.min(Math.max(((e.clientY - rect.top) / rect.height) * 100, 0), 100);

            inputContainer.style.setProperty('--mouse-x-pct', `${xPct}%`);
            inputContainer.style.setProperty('--mouse-y-pct', `${yPct}%`);

            // Проверка: наведен ли курсор прямо на внутренний фрейм ввода
            if (inputWrapper) {
                const wrapperRect = inputWrapper.getBoundingClientRect();
                const isHoveredDirect = (
                    e.clientX >= wrapperRect.left &&
                    e.clientX <= wrapperRect.right &&
                    e.clientY >= wrapperRect.top &&
                    e.clientY <= wrapperRect.bottom
                );
                if (isHoveredDirect) {
                    inputContainer.classList.add('hovered-direct');
                } else {
                    inputContainer.classList.remove('hovered-direct');
                }
            }
        });

        inputContainer.addEventListener('mouseleave', () => {
            // Плавно возвращаем в центр за счет CSS-перехода
            inputContainer.style.setProperty('--mouse-x-pct', '50%');
            inputContainer.style.setProperty('--mouse-y-pct', '50%');
            inputContainer.classList.remove('hovered-direct');
        });
    });
})();

// ==========================================================================
// МОДАЛЬНОЕ ОКНО ВЫБОРА ВУЗА
// ==========================================================================
function openUniversityModal(event) {
    if (event) event.stopPropagation();
    let modal = document.getElementById('universityModal');
    if (!modal) {
        const modalHtml = `
        <div id="universityModal" class="modal" onclick="closeUniversityModal(event)" style="display: flex; position: fixed; z-index: 100000; left: 0; top: 0; width: 100vw; height: 100vh; background-color: rgba(15, 23, 42, 0.75); justify-content: center; align-items: center; backdrop-filter: blur(8px); animation: fadeIn 0.2s ease;">
            <div class="auth-card" style="max-width: 480px; width: 90%; padding: 28px; background: #ffffff; border-radius: 24px; box-shadow: var(--shadow-lg); border: 2px solid var(--primary-color);" onclick="event.stopPropagation();">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="margin: 0; color: var(--text-dark); font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 10px;">
                        <svg style="width:24px;height:24px;fill:var(--primary-hover);" viewBox="0 0 24 24"><path d="M12 3L1 9l11 6 9-4.91V17h-2v-5.77l-7 3.82-7-3.82V17H5V11.23L12 15l11-6-11-6z"/></svg>
                        Выбор ВУЗа
                    </h3>
                    <span style="font-size: 28px; cursor: pointer; color: var(--text-muted);" onclick="closeUniversityModal()">&times;</span>
                </div>
                <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px; line-height: 1.4;">
                    Выберите интересующие медицинские ВУЗы для учета особенностей их учебных программ и материалов:
                </p>
                <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; max-height: 260px; overflow-y: auto; padding-right: 4px;">
                    <!-- ТВГМУ Active Option -->
                    <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 14px; background: var(--primary-light); border: 2px solid var(--primary-color); cursor: pointer;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <input type="checkbox" id="univ-tvgmu" value="ТВГМУ" checked style="width: 18px; height: 18px; accent-color: var(--primary-hover); cursor: pointer;">
                            <div>
                                <div style="font-weight: 700; color: var(--text-dark); font-size: 15px;">ТВГМУ</div>
                                <div style="font-size: 12px; color: var(--text-muted);">Тверской государственный медицинский университет</div>
                            </div>
                        </div>
                        <span style="background: var(--primary-color); color: var(--text-dark); font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 20px;">Учебники загружены</span>
                    </label>

                    <!-- Sechenov Dimmed -->
                    <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0; opacity: 0.55; cursor: not-allowed;" title="Учебники пока не загружены">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <input type="checkbox" disabled style="width: 18px; height: 18px;">
                            <div>
                                <div style="font-weight: 700; color: var(--text-dark); font-size: 15px;">Сеченовский Университет</div>
                                <div style="font-size: 12px; color: var(--text-muted);">ПМГМУ им. И.М. Сеченова</div>
                            </div>
                        </div>
                        <span style="background: #e2e8f0; color: #64748b; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px;">Скоро</span>
                    </label>

                    <!-- Pirogov Dimmed -->
                    <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0; opacity: 0.55; cursor: not-allowed;" title="Учебники пока не загружены">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <input type="checkbox" disabled style="width: 18px; height: 18px;">
                            <div>
                                <div style="font-weight: 700; color: var(--text-dark); font-size: 15px;">РНИМУ им. Н.И. Пирогова</div>
                                <div style="font-size: 12px; color: var(--text-muted);">Российский национальный исследовательский мед. университет</div>
                            </div>
                        </div>
                        <span style="background: #e2e8f0; color: #64748b; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px;">Скоро</span>
                    </label>

                    <!-- SPbGPMU Dimmed -->
                    <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0; opacity: 0.55; cursor: not-allowed;" title="Учебники пока не загружены">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <input type="checkbox" disabled style="width: 18px; height: 18px;">
                            <div>
                                <div style="font-weight: 700; color: var(--text-dark); font-size: 15px;">СПбГПМУ</div>
                                <div style="font-size: 12px; color: var(--text-muted);">Санкт-Петербургский государственный педиатрический мед. университет</div>
                            </div>
                        </div>
                        <span style="background: #e2e8f0; color: #64748b; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px;">Скоро</span>
                    </label>
                </div>
                <button type="button" onclick="saveUniversitySelection()" class="btn-submit" style="width: 100%; padding: 12px; font-size: 15px; border-radius: 14px; background: var(--primary-hover); color: var(--text-dark); font-weight: 700; border: none; cursor: pointer;">Сохранить выбор</button>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('universityModal');
    }
    modal.style.display = 'flex';
}

function closeUniversityModal() {
    const modal = document.getElementById('universityModal');
    if (modal) modal.style.display = 'none';
}

function saveUniversitySelection() {
    const tvgmu = document.getElementById('univ-tvgmu');
    const input = document.getElementById('university-input');
    const badge = document.getElementById('selected-university-badge');
    const val = (tvgmu && tvgmu.checked) ? 'ТВГМУ' : '';
    if (input) {
        input.value = val;
    }
    if (badge) {
        if (val) {
            badge.querySelector('.badge-text').textContent = val;
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    }
    closeUniversityModal();
}

function removeUniversitySelection(event) {
    if (event) event.stopPropagation();
    const input = document.getElementById('university-input');
    const badge = document.getElementById('selected-university-badge');
    const tvgmu = document.getElementById('univ-tvgmu');
    if (input) input.value = '';
    if (badge) badge.style.display = 'none';
    if (tvgmu) tvgmu.checked = false;
}

function usePromptText(el) {
    if (!el) return;
    const text = el.innerText.trim();
    const uq = document.getElementById('user-query');
    if (uq) {
        uq.value = text;
        uq.dispatchEvent(new Event('input'));
        uq.focus();
        setTimeout(() => {
            uq.scrollTop = uq.scrollHeight;
        }, 50);
    }
}
window.usePromptText = usePromptText;

/**
 * Инициализация прокрутки промт-карусели
 * Автопрокрутка + колесо мыши при наведении на ПК + свайп пальцем на смартфонах без полосы прокрутки
 */
function initPromptsCarousel() {
    const containers = document.querySelectorAll('.prompts-carousel-container');
    containers.forEach(container => {
        if (container.dataset.carouselInited === 'true') return;
        container.dataset.carouselInited = 'true';

        let isHovered = false;
        let isInteracting = false;
        let resumeTimeout = null;
        let animFrameId = null;
        let scrollPos = container.scrollLeft || 0;
        let lastTime = performance.now();

        const speedPxPerSec = 35; // Скорость авто-прокрутки в пикселях в секунду

        function step(now) {
            if (!container.isConnected) {
                if (animFrameId) cancelAnimationFrame(animFrameId);
                return;
            }

            const dt = (now - lastTime) / 1000;
            lastTime = now;

            if (!isHovered && !isInteracting) {
                const move = speedPxPerSec * Math.min(dt, 0.1);
                scrollPos += move;

                const track = container.querySelector('.prompts-carousel-track');
                const halfWidth = track ? (track.scrollWidth / 2) : (container.scrollWidth / 2);

                if (halfWidth > 0 && scrollPos >= halfWidth) {
                    scrollPos -= halfWidth;
                }

                container.scrollLeft = scrollPos;
            } else {
                scrollPos = container.scrollLeft;
            }

            animFrameId = requestAnimationFrame(step);
        }

        // Прокрутка колесом мыши по горизонтали при наведении
        container.addEventListener('wheel', (e) => {
            if (e.deltaY !== 0 || e.deltaX !== 0) {
                e.preventDefault();
                const delta = e.deltaY !== 0 ? e.deltaY : e.deltaX;
                container.scrollLeft += delta;
                scrollPos = container.scrollLeft;
                pauseAutoScroll();
            }
        }, { passive: false });

        // Пауза при наведении мыши
        container.addEventListener('mouseenter', () => {
            isHovered = true;
        });
        container.addEventListener('mouseleave', () => {
            isHovered = false;
            scheduleResume();
        });

        // Свайп на смартфонах и планшетах
        container.addEventListener('touchstart', () => {
            isInteracting = true;
        }, { passive: true });

        container.addEventListener('touchend', () => {
            scrollPos = container.scrollLeft;
            scheduleResume();
        }, { passive: true });

        function pauseAutoScroll() {
            isInteracting = true;
            scheduleResume();
        }

        function scheduleResume() {
            clearTimeout(resumeTimeout);
            resumeTimeout = setTimeout(() => {
                isInteracting = false;
                scrollPos = container.scrollLeft;
            }, 1200);
        }

        animFrameId = requestAnimationFrame((now) => {
            lastTime = now;
            step(now);
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initPromptsCarousel();
        initAnnotationResize();
    });
} else {
    initPromptsCarousel();
    initAnnotationResize();
}
window.initPromptsCarousel = initPromptsCarousel;
window.initAnnotationResize = initAnnotationResize;

