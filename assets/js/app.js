/**
 * Simple Secure File Share - WordPress Plugin JavaScript
 * Version: 4.1.0
 */

(function() {
    'use strict';

    // ============================================
    // Global Variables
    // ============================================
    
    let allFiles = [];

    // 暗号学的に安全な乱数（偏りなし）
    function secureRandomInt(max) {
        const limit = Math.floor(0x100000000 / max) * max;
        const buf = new Uint32Array(1);
        do {
            window.crypto.getRandomValues(buf);
        } while (buf[0] >= limit);
        return buf[0] % max;
    }

    function secureShuffle(str) {
        const arr = str.split('');
        for (let i = arr.length - 1; i > 0; i--) {
            const j = secureRandomInt(i + 1);
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
        return arr.join('');
    }
    let currentView = 'list'; // 'list' or 'grid'
    let currentSort = { column: 'name', direction: 'asc' };

    // ============================================
    // Toast Notification
    // ============================================
    
    window.sfsShowToast = function(message, type = 'success') {
        const existingToast = document.querySelector('.sfs-toast');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        toast.className = 'sfs-toast';
        
        const iconColor = type === 'success' ? '#4ade80' : type === 'error' ? '#f87171' : '#fbbf24';
        const icon = type === 'success' ? 'dashicons-yes' : type === 'error' ? 'dashicons-no' : 'dashicons-warning';
        
        const iconEl = document.createElement('span');
        iconEl.className = 'dashicons ' + icon;
        iconEl.style.color = iconColor;
        toast.appendChild(iconEl);
        toast.appendChild(document.createTextNode(' ' + String(message)));
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('hiding');
            toast.addEventListener('animationend', () => toast.remove());
        }, 3000);
    };

    // ============================================
    // Clipboard Copy
    // ============================================
    
    window.sfsCopyToClipboard = function(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                sfsShowToast(sfsI18n.urlCopied || 'URL copied!');
            }, function(err) {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    };

    function fallbackCopy(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            sfsShowToast(sfsI18n.urlCopied || 'URL copied!');
        } catch (err) {
            sfsShowToast(sfsI18n.copyFailed || 'Copy failed', 'error');
        }
        document.body.removeChild(textArea);
    }

    // ============================================
    // Password Toggle
    // ============================================
    
    window.sfsTogglePassword = function(inputId, buttonSelector) {
        const input = document.getElementById(inputId);
        const button = document.querySelector(buttonSelector);
        if (!input || !button) return;

        const icon = button.querySelector('.dashicons');
        const isMasked = input.classList.contains('sfs-masked');
        
        if (isMasked) {
            input.classList.remove('sfs-masked');
            if (icon) {
                icon.classList.remove('dashicons-visibility');
                icon.classList.add('dashicons-hidden');
            }
            button.title = sfsI18n.hidePassword || 'Hide password';
        } else {
            input.classList.add('sfs-masked');
            if (icon) {
                icon.classList.remove('dashicons-hidden');
                icon.classList.add('dashicons-visibility');
            }
            button.title = sfsI18n.showPassword || 'Show password';
        }
    };

    // ============================================
    // Password Toggle Visibility
    // ============================================
    
    window.sfsTogglePasswordVisibility = function(show) {
        const container = document.getElementById('sfs-password-container');
        const input = document.getElementById('sfs-password-input');
        if (!container || !input) return;
        
        if (show) {
            container.classList.remove('sfs-hidden');
            input.required = true;
            input.value = '';
            input.classList.add('sfs-masked');
        } else {
            container.classList.add('sfs-hidden');
            input.required = false;
            input.value = '';
        }
    };

    // ============================================
    // Password Generator
    // ============================================
    
    window.sfsGeneratePassword = function() {
        const length = 16;
        const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
        let retVal = "";
        const types = ["abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "0123456789", "!@#$%^&*()_+"];
        
        types.forEach(t => {
            retVal += t.charAt(secureRandomInt(t.length));
        });
        for (let i = 0; i < length - types.length; ++i) {
            retVal += charset.charAt(secureRandomInt(charset.length));
        }
        retVal = secureShuffle(retVal);

        const input = document.getElementById('sfs-password-input');
        const toggleBtn = document.getElementById('sfs-toggle-password');
        
        if (input) {
            input.classList.remove('sfs-masked');
            input.value = retVal;
        }
        
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('.dashicons');
            if (icon) {
                icon.classList.remove('dashicons-visibility');
                icon.classList.add('dashicons-hidden');
            }
        }

        navigator.clipboard.writeText(retVal).then(function() {
            sfsShowToast(sfsI18n.passwordCopied || 'Password copied!');
        }, function(err) {
            console.error('Copy failed: ', err);
        });
    };

    // ============================================
    // View Toggle (List/Grid)
    // ============================================

    window.sfsSetView = function(view) {
        currentView = view;
        
        // Update toggle buttons
        document.querySelectorAll('.sfs-view-toggle-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.view === view) {
                btn.classList.add('active');
            }
        });
        
        // Update file list display
        sfsUpdateFileList();
        
        // Save preference
        localStorage.setItem('sfs_view_preference', view);
    };

    // ============================================
    // File Management
    // ============================================

    window.sfsAddFiles = function(files) {
        const newFiles = Array.from(files);
        newFiles.forEach(file => {
            const exists = allFiles.some(f => f.name === file.name && f.size === file.size);
            if (!exists) {
                // Generate preview for images
                if (file.type.startsWith('image/')) {
                    file.preview = URL.createObjectURL(file);
                }
                allFiles.push(file);
            }
        });
        sfsUpdateFileList();
    };

    window.sfsRemoveFile = function(index) {
        // Revoke object URL if exists
        if (allFiles[index] && allFiles[index].preview) {
            URL.revokeObjectURL(allFiles[index].preview);
        }
        allFiles.splice(index, 1);
        sfsUpdateFileList();
    };

    window.sfsClearAllFiles = function() {
        // Revoke all object URLs
        allFiles.forEach(file => {
            if (file.preview) {
                URL.revokeObjectURL(file.preview);
            }
        });
        allFiles = [];
        sfsUpdateFileList();
    };

    window.sfsGetAllFiles = function() {
        return allFiles;
    };

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const iconMap = {
            'jpg': { icon: 'dashicons-format-image', color: '#a855f7' },
            'jpeg': { icon: 'dashicons-format-image', color: '#a855f7' },
            'png': { icon: 'dashicons-format-image', color: '#a855f7' },
            'gif': { icon: 'dashicons-format-image', color: '#a855f7' },
            'webp': { icon: 'dashicons-format-image', color: '#a855f7' },
            'svg': { icon: 'dashicons-format-image', color: '#a855f7' },
            'pdf': { icon: 'dashicons-media-document', color: '#ef4444' },
            'zip': { icon: 'dashicons-media-archive', color: '#eab308' },
            'rar': { icon: 'dashicons-media-archive', color: '#eab308' },
            '7z': { icon: 'dashicons-media-archive', color: '#eab308' },
            'doc': { icon: 'dashicons-media-document', color: '#3b82f6' },
            'docx': { icon: 'dashicons-media-document', color: '#3b82f6' },
            'xls': { icon: 'dashicons-media-spreadsheet', color: '#22c55e' },
            'xlsx': { icon: 'dashicons-media-spreadsheet', color: '#22c55e' },
            'ppt': { icon: 'dashicons-media-interactive', color: '#f97316' },
            'pptx': { icon: 'dashicons-media-interactive', color: '#f97316' },
            'mp3': { icon: 'dashicons-format-audio', color: '#06b6d4' },
            'wav': { icon: 'dashicons-format-audio', color: '#06b6d4' },
            'mp4': { icon: 'dashicons-format-video', color: '#8b5cf6' },
            'mov': { icon: 'dashicons-format-video', color: '#8b5cf6' },
            'avi': { icon: 'dashicons-format-video', color: '#8b5cf6' },
        };
        return iconMap[ext] || { icon: 'dashicons-media-default', color: 'var(--sfs-color-text-muted)' };
    }

    function isImageFile(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext);
    }

    window.sfsUpdateFileList = function() {
        const container = document.getElementById('sfs-file-list-container');
        const fileList = document.getElementById('sfs-file-list');
        if (!container || !fileList) return;

        if (allFiles.length === 0) {
            container.classList.add('sfs-hidden');
            return;
        }
        container.classList.remove('sfs-hidden');
        fileList.innerHTML = '';

        if (currentView === 'grid') {
            fileList.className = 'sfs-file-grid';
            allFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'sfs-file-grid-item';
                
                const { icon, color } = getFileIcon(file.name);
                const isImage = isImageFile(file.name);
                
                let previewHtml;
                if (isImage && file.preview) {
                    previewHtml = `<img src="${escapeHtml(file.preview)}" alt="${escapeHtml(file.name)}">`;
                } else {
                    previewHtml = `<span class="dashicons ${icon} sfs-file-icon-large" style="color: ${color};"></span>`;
                }
                
                item.innerHTML = `
                    <button type="button" class="sfs-file-grid-remove" onclick="sfsRemoveFile(${index})" title="${sfsI18n.delete || 'Delete'}">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                    <div class="sfs-file-grid-preview">
                        ${previewHtml}
                    </div>
                    <div class="sfs-file-grid-name" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
                    <div class="sfs-file-grid-size">${formatFileSize(file.size)}</div>
                `;
                fileList.appendChild(item);
            });
        } else {
            fileList.className = 'sfs-file-list';
            allFiles.forEach((file, index) => {
                const li = document.createElement('li');
                li.className = 'sfs-file-item';
                
                const { icon, color } = getFileIcon(file.name);
                const isImage = isImageFile(file.name);
                
                let previewHtml;
                if (isImage && file.preview) {
                    previewHtml = `<img src="${escapeHtml(file.preview)}" alt="" style="width:32px;height:32px;object-fit:cover;border-radius:4px;">`;
                } else {
                    previewHtml = `<span class="dashicons ${icon}" style="color: ${color}; font-size: 24px; width: 24px; height: 24px;"></span>`;
                }
                
                li.innerHTML = `
                    <div class="sfs-file-info">
                        ${previewHtml}
                        <div style="min-width: 0;">
                            <div class="sfs-file-name sfs-truncate" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
                            <div class="sfs-file-size">${formatFileSize(file.size)}</div>
                        </div>
                    </div>
                    <button type="button" class="sfs-file-remove" onclick="sfsRemoveFile(${index})" title="${sfsI18n.delete || 'Delete'}">
                        <span class="dashicons dashicons-dismiss"></span>
                    </button>
                `;
                fileList.appendChild(li);
            });
        }
    };

    function escapeHtml(text) {
        // 属性値でも安全なよう引用符もエスケープする
        return String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // ============================================
    // Table Sorting
    // ============================================

    window.sfsSortTable = function(tableId, column) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const headers = table.querySelectorAll('th');
        
        // Determine sort direction
        let direction = 'asc';
        headers.forEach(th => {
            if (th.dataset.column === column) {
                if (th.classList.contains('sort-asc')) {
                    direction = 'desc';
                    th.classList.remove('sort-asc');
                    th.classList.add('sort-desc');
                } else {
                    th.classList.remove('sort-desc');
                    th.classList.add('sort-asc');
                }
            } else {
                th.classList.remove('sort-asc', 'sort-desc');
            }
        });

        // Get column index
        const columnIndex = Array.from(headers).findIndex(th => th.dataset.column === column);
        if (columnIndex === -1) return;

        // Sort rows
        rows.sort((a, b) => {
            let aVal = a.cells[columnIndex].textContent.trim();
            let bVal = b.cells[columnIndex].textContent.trim();

            // Try to parse as number
            const aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
            const bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return direction === 'asc' ? aNum - bNum : bNum - aNum;
            }

            // String comparison
            return direction === 'asc' 
                ? aVal.localeCompare(bVal, 'ja') 
                : bVal.localeCompare(aVal, 'ja');
        });

        // Re-append sorted rows
        rows.forEach(row => tbody.appendChild(row));
    };

    // ============================================
    // Dark Mode Toggle
    // ============================================

    window.sfsToggleDarkMode = function() {
        const html = document.documentElement;
        const currentTheme = html.dataset.theme || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        html.dataset.theme = newTheme;
        localStorage.setItem('sfs_theme', newTheme);
        
        // Update toggle button icon
        const toggleBtn = document.querySelector('.sfs-theme-toggle-btn.active');
        if (toggleBtn) {
            toggleBtn.classList.remove('active');
        }
        const newActiveBtn = document.querySelector(`.sfs-theme-toggle-btn[data-theme="${newTheme}"]`);
        if (newActiveBtn) {
            newActiveBtn.classList.add('active');
        }
    };

    // ============================================
    // Initialize Theme
    // ============================================

    function initTheme() {
        const saved = localStorage.getItem('sfs_theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = saved || (prefersDark ? 'dark' : 'light');
        document.documentElement.dataset.theme = theme;
    }

    // ============================================
    // Initialize View Preference
    // ============================================

    function initViewPreference() {
        const saved = localStorage.getItem('sfs_view_preference');
        if (saved && (saved === 'list' || saved === 'grid')) {
            currentView = saved;
            document.querySelectorAll('.sfs-view-toggle-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.view === saved) {
                    btn.classList.add('active');
                }
            });
        }
    }

    // ============================================
    // Initialize Drag & Drop
    // ============================================

    function initDragDrop() {
        const dropZone = document.getElementById('sfs-drop-zone');
        const fileTrigger = document.getElementById('sfs-file-trigger');
        
        if (!dropZone || !fileTrigger) return;

        // File input change (click to select)
        fileTrigger.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                sfsAddFiles(this.files);
                this.value = '';
            }
        });

        // Disable drag & drop on the hidden file input to prevent double handling
        fileTrigger.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
        fileTrigger.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            // Let the dropZone handle the drop
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                sfsAddFiles(e.dataTransfer.files);
            }
        });

        // Drag & Drop events on dropZone
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eName => {
            dropZone.addEventListener(eName, e => { 
                e.preventDefault(); 
                e.stopPropagation(); 
            }, false);
        });

        ['dragenter', 'dragover'].forEach(eName => {
            dropZone.addEventListener(eName, () => {
                dropZone.classList.add('sfs-drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eName => {
            dropZone.addEventListener(eName, () => {
                dropZone.classList.remove('sfs-drag-over');
            }, false);
        });

        dropZone.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files && files.length > 0) {
                sfsAddFiles(files);
            }
        });
    }

    // ============================================
    // Initialize Event Listeners
    // ============================================

    function initEventListeners() {
        // Clear all button
        const clearBtn = document.getElementById('sfs-clear-all');
        if (clearBtn) {
            clearBtn.addEventListener('click', sfsClearAllFiles);
        }

        // View toggle buttons
        document.querySelectorAll('.sfs-view-toggle-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                sfsSetView(this.dataset.view);
            });
        });

        // Password toggle
        const togglePasswordBtn = document.getElementById('sfs-toggle-password');
        if (togglePasswordBtn) {
            togglePasswordBtn.addEventListener('click', function() {
                sfsTogglePassword('sfs-password-input', '#sfs-toggle-password');
            });
        }

        // Generate password button
        const generatePasswordBtn = document.getElementById('sfs-generate-password');
        if (generatePasswordBtn) {
            generatePasswordBtn.addEventListener('click', sfsGeneratePassword);
        }

        // Password enable/disable radios
        const enablePasswordRadios = document.getElementsByName('enable_password');
        enablePasswordRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                sfsTogglePasswordVisibility(this.value === '1');
            });
        });

        // Sortable table headers
        document.querySelectorAll('.sfs-table th.sortable').forEach(th => {
            th.addEventListener('click', function() {
                const table = this.closest('.sfs-table');
                if (table && table.id) {
                    sfsSortTable(table.id, this.dataset.column);
                }
            });
        });
    }

    // ============================================
    // Upload Form Handler
    // ============================================

    window.sfsInitUploadForm = function(ajaxUrl) {
        const form = document.getElementById('sfs-upload-form');
        if (!form) return;

        const progressWrapper = document.getElementById('sfs-progress-wrapper');
        const progressBar = document.getElementById('sfs-progress-bar');
        const progressText = document.getElementById('sfs-progress-text');
        const submitBtn = document.getElementById('sfs-submit-btn');
        const successModal = document.getElementById('sfs-success-modal');
        const titleInput = document.getElementById('sfs-title-input');
        const passwordInput = document.getElementById('sfs-password-input');
        const enablePasswordRadios = document.getElementsByName('enable_password');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            if (allFiles.length === 0) {
                alert(sfsI18n.selectFiles || 'Please select at least one file.');
                return;
            }
            if (!titleInput.value.trim()) {
                alert(sfsI18n.enterTitle || 'Please enter a title.');
                return;
            }

            // Check password
            let isPasswordEnabled = false;
            enablePasswordRadios.forEach(r => {
                if (r.checked && r.value === '1') isPasswordEnabled = true;
            });
            if (isPasswordEnabled && !passwordInput.value.trim()) {
                alert(sfsI18n.enterPassword || 'Please enter a password.');
                return;
            }

            // UI Reset
            submitBtn.disabled = true;
            submitBtn.innerText = sfsI18n.uploading || 'Uploading...';
            progressWrapper.classList.remove('sfs-hidden');
            progressBar.style.width = '0%';
            progressText.innerText = '0%';

            const formData = new FormData();
            formData.append('action', 'sfs_async_upload');
            const nonce = form.querySelector('input[name="sfs_nonce"]').value;
            formData.append('sfs_nonce', nonce);
            formData.append('share_title', titleInput.value);

            if (isPasswordEnabled) {
                formData.append('share_password', passwordInput.value);
            }

            allFiles.forEach(file => {
                formData.append('files[]', file);
            });

            const xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxUrl, true);

            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressText.innerText = percentComplete + '%';
                }
            };

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            progressBar.style.width = '100%';
                            progressText.innerText = '100%';
                            // Show toast and reload page
                            sfsShowToast(sfsI18n.uploadComplete || 'Upload complete!', 'success');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                            alert((sfsI18n.error || 'Error: ') + (response.data || 'Unknown error'));
                            resetFormState();
                        }
                    } catch (e) {
                        console.error('JSON Parse Error', e);
                        alert(sfsI18n.parseError || 'Failed to parse server response.');
                        resetFormState();
                    }
                } else {
                    alert((sfsI18n.networkError || 'Network error. Status: ') + xhr.status);
                    resetFormState();
                }
            };

            xhr.onerror = function() {
                alert(sfsI18n.networkError || 'Network error occurred.');
                resetFormState();
            };

            xhr.send(formData);
        });

        function resetFormState() {
            submitBtn.disabled = false;
            submitBtn.innerText = sfsI18n.uploadButton || 'Upload and Create Share Link';
            progressWrapper.classList.add('sfs-hidden');
            progressBar.style.width = '0%';
        }
    };

    // ============================================
    // Initialize on DOM Ready
    // ============================================

    document.addEventListener('DOMContentLoaded', function() {
        initTheme();
        initViewPreference();
        // NOTE: initDragDrop() と initEventListeners() は管理画面では
        // PHPインラインスクリプトが処理するため、ここでは呼び出さない。
        // フロントエンドでも必要な場合は別途呼び出すこと。
    });

})();

// Default i18n strings (overridden by localized version)
window.sfsI18n = window.sfsI18n || {
    urlCopied: 'URL copied!',
    copyFailed: 'Copy failed',
    passwordCopied: 'Password copied!',
    hidePassword: 'Hide password',
    showPassword: 'Show password',
    delete: 'Delete',
    selectFiles: 'Please select at least one file.',
    enterTitle: 'Please enter a title.',
    enterPassword: 'Please enter a password.',
    uploading: 'Uploading...',
    uploadButton: 'Upload and Create Share Link',
    uploadComplete: 'Upload complete!',
    error: 'Error: ',
    parseError: 'Failed to parse server response.',
    networkError: 'Network error occurred.'
};
