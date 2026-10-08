const API_URL = window.location.origin + '/checker.php';
let lastResults = null;
let latestVersions = null;

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    loadVersionInfo();
    initEventListeners();
    document.getElementById('cardActions').style.display = 'none';
});

function initEventListeners() {
    document.getElementById('checkBtn').onclick = checkSites;
    document.getElementById('clearBtn').onclick = clearResults;
    document.getElementById('exportPdfBtn').onclick = exportToPDF;
    document.getElementById('emailBtn').onclick = openEmailModal;
    document.getElementById('cancelEmailBtn').onclick = closeEmailModal;
    document.getElementById('sendEmailBtn').onclick = sendEmail;
    document.getElementById('emailModal').onclick = function(e) {
        if (e.target === this) closeEmailModal();
    };
}

function loadVersionInfo() {
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_version_info' })
    })
        .then(response => {
            if (!response.ok) throw new Error('HTTP error! status: ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                latestVersions = data.latest_versions;
                displayVersionInfo(data);
            } else {
                throw new Error(data.error || 'Неизвестная ошибка');
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки:', error);
            document.getElementById('wpLatest').textContent = '6.7.2';
            document.getElementById('phpLatest').textContent = '8.3.10';
            document.getElementById('updateInfo').textContent = 'Обновлено: ' + new Date().toLocaleString('ru-RU');
        });
}

function displayVersionInfo(data) {
    document.getElementById('wpLatest').textContent = data.latest_versions.wordpress;
    document.getElementById('phpLatest').textContent = data.latest_versions.php_stable;
    document.getElementById('updateInfo').textContent = 'Обновлено: ' + data.updated_at + ' | Поддерживаемые PHP: ' + data.latest_versions.php_supported.join(', ');
    document.getElementById('versionInfoBlocks').style.display = 'block';
    document.getElementById('wpVersionNumber').textContent = 'v' + data.latest_versions.wordpress;
    document.getElementById('phpVersionNumber').textContent = 'v' + data.latest_versions.php_stable;

    const wpInfo = data.version_info.wordpress;
    document.getElementById('wordpressInfo').innerHTML =
        createInfoSection('Безопасность', wpInfo.security_fixes) +
        createInfoSection('Производительность', wpInfo.performance) +
        createInfoSection('Возможности', wpInfo.features);

    const phpInfo = data.version_info.php;
    document.getElementById('phpInfo').innerHTML =
        createInfoSection('Безопасность', phpInfo.security) +
        createInfoSection('Производительность', phpInfo.performance) +
        createInfoSection('Возможности', phpInfo.features);
}

function createInfoSection(title, items) {
    if (!items || items.length === 0) return '';
    let html = '<div class="info-section">';
    html += '<div class="info-section-title">' + title + '</div>';
    html += '<ul class="info-list">';
    items.slice(0, 5).forEach(item => {
        html += '<li>' + item + '</li>';
    });
    html += '</ul></div>';
    return html;
}

function getSpeedInfo(responseTime) {
    if (responseTime < 300) return { class: 'speed-fast', badgeClass: 'speed-fast-badge', label: 'Отлично', description: 'Сайт очень быстрый' };
    if (responseTime < 500) return { class: 'speed-fast', badgeClass: 'speed-fast-badge', label: 'Быстро', description: 'Хорошая скорость' };
    if (responseTime < 1000) return { class: 'speed-medium', badgeClass: 'speed-medium-badge', label: 'Средне', description: 'Приемлемая скорость' };
    if (responseTime < 2000) return { class: 'speed-slow', badgeClass: 'speed-slow-badge', label: 'Медленно', description: 'Нужна оптимизация' };
    return { class: 'speed-slow', badgeClass: 'speed-slow-badge', label: 'Очень медленно', description: 'Критически медленно' };
}

function checkSites() {
    const urls = document.getElementById('urls').value;
    if (!urls.trim()) {
        alert('Введите хотя бы один URL');
        return;
    }

    const btn = document.getElementById('checkBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="icon icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Проверка...';

    document.getElementById('results').innerHTML = '<div class="card"><div class="loading-container"><div class="spinner"></div><div class="loading-text">Анализируем сайты...</div></div></div>';

    const urlList = urls.split('\n').filter(url => url.trim() !== '');

    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ urls: urlList })
    })
        .then(response => {
            if (!response.ok) throw new Error('HTTP error! status: ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                lastResults = data;
                latestVersions = data.latest_versions;
                displayResults(data.results, data.latest_versions);
                document.getElementById('cardActions').style.display = 'flex';
            } else {
                throw new Error(data.error || 'Неизвестная ошибка');
            }
        })
        .catch(error => {
            console.error('Ошибка проверки:', error);
            document.getElementById('results').innerHTML = '<div class="card"><p style="color: var(--danger);">Ошибка: ' + error.message + '</p></div>';
            document.getElementById('cardActions').style.display = 'none';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg class="icon icon-sm" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg> Начать проверку';
        });
}

function clearResults() {
    document.getElementById('urls').value = '';
    document.getElementById('results').innerHTML = '';
    lastResults = null;
    document.getElementById('cardActions').style.display = 'none';
}

// Функция для замера реальной скорости загрузки
function measureFullPageLoad(url) {
    return new Promise((resolve) => {
        const iframe = document.createElement('iframe');
        iframe.style.cssText = 'position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0;';
        iframe.src = url;

        const startTime = performance.now();
        let resolved = false;

        iframe.onload = function() {
            if (resolved) return;
            resolved = true;

            const loadTime = Math.round(performance.now() - startTime);

            try {
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                const images = iframeDoc.querySelectorAll('img');
                const scripts = iframeDoc.querySelectorAll('script[src]');
                const styles = iframeDoc.querySelectorAll('link[rel="stylesheet"]');
                const totalResources = images.length + scripts.length + styles.length;

                document.body.removeChild(iframe);

                resolve({
                    full_load_time_ms: loadTime,
                    full_load_time_sec: (loadTime / 1000).toFixed(2),
                    images: images.length,
                    scripts: scripts.length,
                    styles: styles.length,
                    total_resources: totalResources
                });
            } catch (error) {
                document.body.removeChild(iframe);
                resolve({
                    full_load_time_ms: loadTime,
                    full_load_time_sec: (loadTime / 1000).toFixed(2),
                    images: 0,
                    scripts: 0,
                    styles: 0,
                    total_resources: 0
                });
            }
        };

        iframe.onerror = function() {
            if (resolved) return;
            resolved = true;
            document.body.removeChild(iframe);
            resolve(null);
        };

        document.body.appendChild(iframe);

        setTimeout(() => {
            if (!resolved) {
                resolved = true;
                if (iframe.parentNode) document.body.removeChild(iframe);
                resolve(null);
            }
        }, 10000);
    });
}

function displayResults(results, latestVersions) {
    let html = '';
    results.forEach(result => {
        // Если ошибка - выводим только ошибку
        if (result.status === 'error') {
            html += '<div class="result-item fade-in">';
            html += '<div class="result-header">';
            html += '<a href="' + result.url + '" target="_blank" class="url">' + result.url + '</a>';
            html += '<span class="status-badge status-error">Ошибка</span>';
            html += '</div>';
            html += '<div style="padding: 20px; background: rgba(248, 113, 113, 0.1); border: 1px solid rgba(248, 113, 113, 0.3); border-radius: 8px; text-align: center;">';
            html += '<div style="font-size: 20px; margin-bottom: 10px;">🔴</div>';
            html += '<div style="font-size: 16px; font-weight: 600; color: var(--danger); margin-bottom: 5px;">Сайт недоступен</div>';
            html += '<div style="font-size: 13px; color: var(--text-secondary);">Не удалось подключиться к сайту. Возможно, сайт не работает или блокирует запросы.</div>';
            if (result.error) {
                html += '<div style="font-size: 11px; color: var(--text-muted); margin-top: 8px;">' + result.error + '</div>';
            }
            html += '</div>';
            html += '</div>';
            return;
        }

        // Сайт доступен - выводим все блоки
        const statusClass = 'status-ok';
        const statusText = 'ОК';

        html += '<div class="result-item fade-in">';
        html += '<div class="result-header">';
        html += '<a href="' + result.url + '" target="_blank" class="url">' + result.url + '</a>';
        html += '<span class="status-badge ' + statusClass + '">' + statusText + '</span>';
        html += '</div>';
        html += '<div class="info-grid">';

        const wpVersion = result.wordpress_version || '—';
        let wpIndicator = 'version-ok';
        if (result.wordpress_version && latestVersions) {
            if (versionCompare(result.wordpress_version, latestVersions.wordpress) < 0) {
                wpIndicator = versionCompare(result.wordpress_version, '5.0') < 0 ? 'version-critical' : 'version-warning';
            }
        }
        html += '<div class="info-tile"><div class="info-label">WordPress</div><div class="info-value">' + wpVersion + '</div><span class="version-indicator ' + wpIndicator + '"></span></div>';

        const phpVersion = result.php_version || '—';
        let phpIndicator = 'version-ok';
        if (result.php_version && latestVersions) {
            if (versionCompare(result.php_version, '8.1') < 0) {
                phpIndicator = versionCompare(result.php_version, '7.4') < 0 ? 'version-critical' : 'version-warning';
            }
        }
        html += '<div class="info-tile"><div class="info-label">PHP</div><div class="info-value">' + phpVersion + '</div><span class="version-indicator ' + phpIndicator + '"></span></div>';

        const speedInfo = getSpeedInfo(result.response_time);
        html += '<div class="info-tile"><div class="info-label">Время ответа сервера</div><div class="info-value ' + speedInfo.class + '">' + result.response_time + 'ms</div><span class="speed-badge ' + speedInfo.badgeClass + '">' + speedInfo.label + '</span></div>';

        html += '<div class="info-tile"><div class="info-label">SSL</div><div class="info-value">' + (result.has_ssl ? 'Да' : 'Нет') + '</div></div>';
        html += '</div>';

        // Контейнер для скорости
        const speedId = 'speed-' + result.url.replace(/[^a-zA-Z0-9]/g, '');
        html += '<div id="' + speedId + '" style="margin: 16px 0; padding: 14px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 6px;">';
        html += '<div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 10px;">Скорость загрузки страницы</div>';
        html += '<div style="text-align: center; color: var(--text-muted); font-size: 13px;">⏳ Замеряем скорость...</div>';
        html += '</div>';

        if (result.vulnerable_files && result.vulnerable_files.length > 0) {
            html += '<div class="vulnerable-files-block"><div class="vulnerable-files-header">Обнаружены уязвимые файлы (' + result.vulnerable_files.length + ')</div>';
            result.vulnerable_files.forEach(file => {
                const riskClass = 'risk-' + file.risk;
                html += '<div class="vulnerable-file-item"><span class="risk-badge ' + riskClass + '">' + file.risk + '</span><span>' + file.path + '</span></div>';
            });
            html += '</div>';
        }

        if (result.analytics) {
            html += '<div style="margin: 16px 0; padding: 14px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 6px;">';
            html += '<div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 10px;">Системы аналитики</div>';
            html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px;">';
            const analyticsItems = [
                { name: 'Яндекс.Метрика', installed: result.analytics.yandex_metrika },
                { name: 'Google Analytics', installed: result.analytics.google_analytics },
                { name: 'Google Tag Manager', installed: result.analytics.google_tag_manager },
                { name: 'Facebook Pixel', installed: result.analytics.facebook_pixel }
            ];
            analyticsItems.forEach(item => {
                const dotColor = item.installed ? 'var(--success)' : 'var(--danger)';
                const textColor = item.installed ? 'var(--text-primary)' : 'var(--text-muted)';
                const bgColor = item.installed ? 'rgba(52, 211, 153, 0.05)' : 'rgba(248, 113, 113, 0.05)';
                const borderColor = item.installed ? 'rgba(52, 211, 153, 0.2)' : 'rgba(248, 113, 113, 0.2)';
                html += '<div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: ' + bgColor + '; border: 1px solid ' + borderColor + '; border-radius: 6px;">';
                html += '<span style="width: 8px; height: 8px; border-radius: 50%; background: ' + dotColor + '; flex-shrink: 0;"></span>';
                html += '<div><div style="font-size: 13px; font-weight: 500; color: ' + textColor + ';">' + item.name + '</div>';
                html += '<div style="font-size: 11px; color: var(--text-muted);">' + (item.installed ? 'Установлена' : 'Не установлена') + '</div></div></div>';
            });
            html += '</div></div>';
        }

        if (result.cookie_banner) {
            html += '<div style="margin: 16px 0; padding: 14px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 6px;">';
            html += '<div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 10px;">Cookie-баннер</div>';
            const cb = result.cookie_banner;
            html += '<div style="display: flex; flex-direction: column; gap: 4px; font-size: 13px;">';
            html += '<span style="color: ' + (cb.has_cookie_banner ? 'var(--success)' : 'var(--danger)') + ';">Баннер: ' + (cb.has_cookie_banner ? '✅' : '❌') + '</span>';
            if (cb.has_cookie_banner) {
                html += '<span>Кнопка "Принять": ' + (cb.has_accept_button ? '✅' : '❌') + '</span>';
                html += '<span>Кнопка "Отклонить": ' + (cb.has_reject_button ? '✅' : '❌') + '</span>';
                html += '<span>Политика cookie: ' + (cb.has_cookie_policy_link ? '✅' : '❌') + '</span>';
            }
            html += '</div></div>';
        }

        if (result.forms && result.forms.total_forms > 0) {
            html += '<div style="margin: 16px 0; padding: 14px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 6px;">';
            html += '<div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 10px;">Формы (' + result.forms.total_forms + ')</div>';
            html += '<div style="display: flex; flex-direction: column; gap: 4px; font-size: 13px;">';
            html += '<span>С капчей: ' + result.forms.forms_with_captcha + ' / ' + result.forms.total_forms + '</span>';
            html += '<span>С галочкой согласия: ' + result.forms.forms_with_consent_checkbox + ' / ' + result.forms.total_forms + '</span>';
            if (result.forms.forms_without_captcha > 0) {
                html += '<span style="color: var(--danger);">⚠️ ' + result.forms.forms_without_captcha + ' без капчи</span>';
            }
            if (result.forms.forms_without_consent > 0) {
                html += '<span style="color: var(--danger);">⚠️ ' + result.forms.forms_without_consent + ' без галочки согласия</span>';
            }
            html += '</div></div>';
        }

        if (result.recommendations && result.recommendations.length > 0) {
            const filteredRecommendations = result.recommendations.filter(rec => rec.type !== 'vulnerable_file');
            if (filteredRecommendations.length > 0) {
                html += '<div class="recommendations-grid">';
                filteredRecommendations.forEach(rec => {
                    const severityClass = 'severity-' + rec.severity;
                    const icon = rec.severity === 'critical' ? '🚨' : rec.severity === 'high' ? '⚠️' : 'ℹ️';
                    html += '<div class="recommendation-card ' + severityClass + '"><div class="recommendation-header"><span>' + icon + '</span><span style="font-size: 14px; font-weight: 500;">' + rec.message + '</span></div></div>';
                });
                html += '</div>';
            }
        }

        html += '</div>';
    });
    document.getElementById('results').innerHTML = html;

    // Замеряем скорость только для доступных сайтов
    results.forEach(result => {
        if (result.status === 'success') {
            const speedId = 'speed-' + result.url.replace(/[^a-zA-Z0-9]/g, '');
            const speedElement = document.getElementById(speedId);

            if (speedElement) {
                measureFullPageLoad(result.url).then(pageSpeed => {
                    if (pageSpeed && pageSpeed.full_load_time_ms) {
                        const loadTime = pageSpeed.full_load_time_ms;
                        const speedPercent = Math.max(0, Math.min(100, Math.round((3000 - loadTime) / 30)));
                        const speedColor = loadTime < 1000 ? 'var(--success)' : loadTime < 2000 ? 'var(--warning)' : 'var(--danger)';

                        let speedLabel = 'Отлично';
                        if (loadTime > 3000) speedLabel = 'Очень медленно';
                        else if (loadTime > 2000) speedLabel = 'Медленно';
                        else if (loadTime > 1000) speedLabel = 'Средне';
                        else if (loadTime > 500) speedLabel = 'Хорошо';

                        speedElement.innerHTML = `
                            <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 10px;">Скорость загрузки страницы</div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 12px;">
                                <div style="background: var(--bg-primary); padding: 10px; border-radius: 6px; text-align: center;">
                                    <div style="font-size: 11px; color: var(--text-muted);">Полная загрузка</div>
                                    <div style="font-size: 22px; font-weight: 700; color: ${speedColor};">${pageSpeed.full_load_time_sec} сек</div>
                                    <div style="font-size: 11px; color: var(--text-muted);">${loadTime} ms</div>
                                </div>
                                <div style="background: var(--bg-primary); padding: 10px; border-radius: 6px; text-align: center;">
                                    <div style="font-size: 11px; color: var(--text-muted);">Ресурсы</div>
                                    <div style="font-size: 22px; font-weight: 700;">${pageSpeed.total_resources}</div>
                                    <div style="font-size: 10px; color: var(--text-muted);">IMG: ${pageSpeed.images} | JS: ${pageSpeed.scripts} | CSS: ${pageSpeed.styles}</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="flex: 1; height: 10px; background: var(--bg-primary); border-radius: 5px; overflow: hidden;">
                                    <div style="width: ${speedPercent}%; height: 100%; background: ${speedColor}; border-radius: 5px; transition: width 0.5s;"></div>
                                </div>
                                <span style="font-size: 18px; font-weight: 700; color: ${speedColor};">${speedPercent}%</span>
                            </div>
                            <div style="margin-top: 8px; text-align: center; font-size: 13px; font-weight: 600; color: ${speedColor};">${speedLabel}</div>
                        `;
                    } else {
                        speedElement.innerHTML = `
                            <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 10px;">Скорость загрузки страницы</div>
                            <div style="text-align: center; color: var(--text-muted); font-size: 13px;">Не удалось замерить скорость (сайт блокирует загрузку)</div>
                        `;
                    }
                });
            }
        }
    });
}

function versionCompare(v1, v2) {
    const v1parts = v1.split('.').map(Number);
    const v2parts = v2.split('.').map(Number);
    for (let i = 0; i < Math.max(v1parts.length, v2parts.length); i++) {
        const part1 = v1parts[i] || 0;
        const part2 = v2parts[i] || 0;
        if (part1 > part2) return 1;
        if (part1 < part2) return -1;
    }
    return 0;
}

function exportToPDF() {
    if (!lastResults || !lastResults.results) {
        alert('Нет данных для экспорта');
        return;
    }
    generatePDF();
}

function generatePDF() {
    const loadingDiv = document.createElement('div');
    loadingDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #1a1e2a; color: #fff; padding: 30px 40px; border-radius: 12px; z-index: 9999; text-align: center;';
    loadingDiv.innerHTML = '<div style="font-size: 16px;">Создание PDF...</div>';
    document.body.appendChild(loadingDiv);

    if (typeof pdfMake === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js';
        script.onload = function() {
            const vfsScript = document.createElement('script');
            vfsScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.min.js';
            vfsScript.onload = function() {
                createBeautifulPDF(loadingDiv);
            };
            document.head.appendChild(vfsScript);
        };
        document.head.appendChild(script);
    } else {
        createBeautifulPDF(loadingDiv);
    }
}

function createBeautifulPDF(loadingDiv) {
    const content = [];

    content.push({
        table: {
            widths: ['*'],
            body: [[{
                text: 'ОТЧЁТ ПРОВЕРКИ БЕЗОПАСНОСТИ',
                alignment: 'center',
                fillColor: '#4f46e5',
                color: '#ffffff',
                fontSize: 18,
                bold: true,
                margin: [0, 15, 0, 5]
            }]]
        },
        layout: 'noBorders'
    });

    content.push({
        text: 'Дата: ' + new Date().toLocaleString('ru-RU') + ' | Источник: PHP-WP Checker',
        alignment: 'center',
        fontSize: 10,
        color: '#666666',
        margin: [0, 5, 0, 20]
    });

    lastResults.results.forEach((result, index) => {
        // Если ошибка - выводим только ошибку
        if (result.status === 'error') {
            content.push({
                table: {
                    widths: ['*'],
                    body: [[{
                        text: (index + 1) + '. ' + result.url + '\n\n❌ Сайт недоступен\n' + (result.error || ''),
                        fillColor: '#fef2f2',
                        color: '#dc2626',
                        fontSize: 11,
                        margin: [10, 10, 10, 10]
                    }]]
                },
                layout: 'noBorders',
                margin: [0, 0, 0, 15]
            });
            return;
        }

        const siteCard = [];

        siteCard.push({
            table: {
                widths: ['*'],
                body: [[{
                    text: (index + 1) + '. ' + result.url,
                    fillColor: '#f0f0f5',
                    color: '#1a1a2e',
                    fontSize: 13,
                    bold: true,
                    margin: [10, 8, 10, 8]
                }]]
            },
            layout: 'noBorders'
        });

        const paramsTable = {
            table: {
                widths: ['20%', '20%', '20%', '20%', '20%'],
                body: [[
                    {
                        text: 'WordPress\n' + (result.wordpress_version || '—'),
                        fontSize: 10,
                        bold: true,
                        alignment: 'center',
                        fillColor: result.wordpress_version && latestVersions && versionCompare(result.wordpress_version, latestVersions.wordpress) < 0 ? '#fde8e8' : '#f0fdf4',
                        color: result.wordpress_version && latestVersions && versionCompare(result.wordpress_version, latestVersions.wordpress) < 0 ? '#dc2626' : '#16a34a',
                        margin: [5, 8, 5, 8]
                    },
                    {
                        text: 'PHP\n' + (result.php_version || '—'),
                        fontSize: 10,
                        bold: true,
                        alignment: 'center',
                        fillColor: result.php_version && versionCompare(result.php_version, '8.1') < 0 ? '#fde8e8' : '#f0fdf4',
                        color: result.php_version && versionCompare(result.php_version, '8.1') < 0 ? '#dc2626' : '#16a34a',
                        margin: [5, 8, 5, 8]
                    },
                    {
                        text: 'Время ответа\n' + result.response_time + 'ms',
                        fontSize: 10,
                        bold: true,
                        alignment: 'center',
                        fillColor: result.response_time > 1000 ? '#fef3c7' : '#f0fdf4',
                        color: result.response_time > 1000 ? '#d97706' : '#16a34a',
                        margin: [5, 8, 5, 8]
                    },
                    {
                        text: 'SSL\n' + (result.has_ssl ? 'Есть' : 'НЕТ'),
                        fontSize: 10,
                        bold: true,
                        alignment: 'center',
                        fillColor: result.has_ssl ? '#f0fdf4' : '#fde8e8',
                        color: result.has_ssl ? '#16a34a' : '#dc2626',
                        margin: [5, 8, 5, 8]
                    },
                    {
                        text: 'WP движок\n' + (result.is_wordpress ? 'Да' : 'Нет'),
                        fontSize: 10,
                        bold: true,
                        alignment: 'center',
                        fillColor: '#f0fdf4',
                        color: '#16a34a',
                        margin: [5, 8, 5, 8]
                    }
                ]]
            },
            layout: {
                hLineWidth: function() { return 0.5; },
                vLineWidth: function() { return 0.5; },
                hLineColor: function() { return '#e5e7eb'; },
                vLineColor: function() { return '#e5e7eb'; }
            }
        };

        siteCard.push(paramsTable);

        const problems = [];
        const warnings = [];

        if (result.wordpress_version && latestVersions && versionCompare(result.wordpress_version, latestVersions.wordpress) < 0) {
            problems.push({ title: 'Устаревший WordPress', description: 'Версия ' + result.wordpress_version + ' → актуальная ' + latestVersions.wordpress });
        }

        if (result.php_version && versionCompare(result.php_version, '8.1') < 0) {
            problems.push({ title: 'Устаревший PHP', description: 'PHP ' + result.php_version + ' → обновите до 8.1+' });
        }

        if (!result.has_ssl) {
            warnings.push({ title: 'Нет HTTPS', description: 'Установите SSL-сертификат' });
        }

        if (result.vulnerable_files && result.vulnerable_files.length > 0) {
            const fileList = result.vulnerable_files.map(f => f.path).join(', ');
            const criticalFiles = result.vulnerable_files.filter(f => f.risk === 'critical' || f.risk === 'high');
            if (criticalFiles.length > 0) {
                problems.push({ title: 'Уязвимые файлы (' + result.vulnerable_files.length + ')', description: fileList });
            }
        }

        if (result.cookie_banner && !result.cookie_banner.has_cookie_banner) {
            warnings.push({ title: 'Нет Cookie-баннера', description: 'Нарушение GDPR' });
        }

        if (result.forms && result.forms.forms_without_captcha > 0) {
            warnings.push({ title: 'Формы без капчи', description: result.forms.forms_without_captcha + ' из ' + result.forms.total_forms + ' форм не защищены' });
        }

        if (result.forms && result.forms.forms_without_consent > 0) {
            warnings.push({ title: 'Формы без согласия', description: result.forms.forms_without_consent + ' из ' + result.forms.total_forms + ' форм без галочки' });
        }

        if (result.response_time > 1000) {
            warnings.push({ title: 'Медленный ответ сервера', description: 'Время ответа ' + result.response_time + 'ms' });
        }

        if (problems.length > 0) {
            siteCard.push({ text: 'КРИТИЧЕСКИЕ ПРОБЛЕМЫ: ' + problems.length, fontSize: 12, bold: true, color: '#dc2626', margin: [10, 10, 10, 5] });

            problems.forEach(problem => {
                siteCard.push({
                    table: {
                        widths: ['8%', '92%'],
                        body: [[
                            { canvas: [{ type: 'ellipse', x: 4, y: 8, r1: 4, r2: 4, color: '#dc2626', lineWidth: 0 }], margin: [5, 0, 0, 0] },
                            {
                                text: [
                                    { text: problem.title + '\n', bold: true, color: '#dc2626', fontSize: 11 },
                                    { text: problem.description, color: '#555555', fontSize: 9 }
                                ],
                                fillColor: '#fef2f2',
                                margin: [8, 6, 8, 6],
                                lineHeight: 1.3
                            }
                        ]]
                    },
                    layout: 'noBorders',
                    margin: [5, 0, 5, 5]
                });
            });
        }

        if (warnings.length > 0) {
            siteCard.push({ text: 'ВАЖНЫЕ ПРОБЛЕМЫ: ' + warnings.length, fontSize: 12, bold: true, color: '#d97706', margin: [10, 5, 10, 5] });

            warnings.forEach(warning => {
                siteCard.push({
                    table: {
                        widths: ['8%', '92%'],
                        body: [[
                            { canvas: [{ type: 'ellipse', x: 4, y: 8, r1: 4, r2: 4, color: '#f59e0b', lineWidth: 0 }], margin: [5, 0, 0, 0] },
                            {
                                text: [
                                    { text: warning.title + '\n', bold: true, color: '#d97706', fontSize: 11 },
                                    { text: warning.description, color: '#555555', fontSize: 9 }
                                ],
                                fillColor: '#fffbeb',
                                margin: [8, 6, 8, 6],
                                lineHeight: 1.3
                            }
                        ]]
                    },
                    layout: 'noBorders',
                    margin: [5, 0, 5, 5]
                });
            });
        }

        if (problems.length === 0 && warnings.length === 0) {
            siteCard.push({
                table: {
                    widths: ['8%', '92%'],
                    body: [[
                        { canvas: [{ type: 'ellipse', x: 4, y: 8, r1: 4, r2: 4, color: '#16a34a', lineWidth: 0 }], margin: [5, 0, 0, 0] },
                        { text: 'Проблем не найдено — сайт в порядке', bold: true, color: '#16a34a', fontSize: 11, fillColor: '#f0fdf4', margin: [8, 8, 8, 8] }
                    ]]
                },
                layout: 'noBorders',
                margin: [5, 5, 5, 5]
            });
        }

        content.push({
            table: { widths: ['*'], body: [[{ stack: siteCard }]] },
            layout: {
                hLineWidth: function() { return 1; },
                vLineWidth: function() { return 0; },
                hLineColor: function() { return '#e5e7eb'; },
                paddingBottom: function() { return 10; }
            },
            margin: [0, 0, 0, 15]
        });
    });

    const docDefinition = {
        pageSize: 'A4',
        pageMargins: [30, 20, 30, 30],
        content: content,
        footer: function(currentPage, pageCount) {
            return {
                text: 'PHP-WP Checker | example.com | Страница ' + currentPage + ' из ' + pageCount,
                alignment: 'center',
                fontSize: 8,
                color: '#999999',
                margin: [0, 10, 0, 0]
            };
        }
    };

    document.body.removeChild(loadingDiv);
    pdfMake.createPdf(docDefinition).download('site-check-' + new Date().toISOString().slice(0, 10) + '.pdf');
}

function openEmailModal() {
    document.getElementById('emailModal').classList.add('active');
}

function closeEmailModal() {
    document.getElementById('emailModal').classList.remove('active');
}

function sendEmail() {
    const email = document.getElementById('emailInput').value;
    if (!email || !email.includes('@')) {
        alert('Введите корректный email адрес');
        return;
    }
    if (!lastResults) {
        alert('Нет данных для отправки');
        return;
    }

    const emailHtml = generateEmailHtml(lastResults);

    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'send_email',
            email: email,
            html: emailHtml,
            subject: 'Отчет WP Checker - ' + new Date().toLocaleString('ru-RU')
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Отчет успешно отправлен на ' + email);
                closeEmailModal();
                document.getElementById('emailInput').value = '';
            } else {
                alert('Ошибка отправки: ' + (data.error || 'неизвестная ошибка'));
            }
        })
        .catch(error => {
            console.error('Ошибка отправки:', error);
            alert('Ошибка отправки: ' + error.message);
        });
}

function generateEmailHtml(data) {
    let html = '<html><body style="font-family: Arial, sans-serif; background-color: #f5f5f5; padding: 20px;">';
    html += '<div style="max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px;">';
    html += '<h1 style="color: #333; text-align: center;">Отчет проверки безопасности</h1>';
    html += '<p style="text-align: center; color: #666;">Дата: ' + new Date().toLocaleString('ru-RU') + '</p>';
    html += '<hr style="border: 1px solid #ddd;">';

    data.results.forEach(result => {
        if (result.status === 'error') {
            html += '<div style="margin-bottom: 20px; padding: 15px; background: #fee; border-radius: 5px;">';
            html += '<h2 style="color: #d32f2f;">' + result.url + '</h2>';
            html += '<p style="color: #d32f2f;">❌ Сайт недоступен</p>';
            html += '</div>';
            return;
        }

        html += '<div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">';
        html += '<h2 style="color: #444; font-size: 18px;">' + result.url + '</h2>';
        html += '<table style="width: 100%; font-size: 14px;">';
        html += '<tr><td><strong>WordPress:</strong></td><td>' + (result.wordpress_version || 'Не найдена') + '</td></tr>';
        html += '<tr><td><strong>PHP:</strong></td><td>' + (result.php_version || 'Не найдена') + '</td></tr>';
        html += '<tr><td><strong>Время ответа:</strong></td><td>' + result.response_time + 'ms</td></tr>';
        html += '<tr><td><strong>SSL:</strong></td><td>' + (result.has_ssl ? 'Да' : 'Нет') + '</td></tr>';
        html += '</table>';

        if (result.vulnerable_files && result.vulnerable_files.length > 0) {
            html += '<h3 style="color: #d32f2f;">Уязвимые файлы:</h3><ul>';
            result.vulnerable_files.forEach(file => {
                html += '<li>' + file.path + ' (' + file.risk + ')</li>';
            });
            html += '</ul>';
        }

        if (result.recommendations && result.recommendations.length > 0) {
            html += '<h3>Рекомендации:</h3><ul>';
            result.recommendations.forEach(rec => {
                if (rec.type !== 'vulnerable_file') {
                    html += '<li>' + rec.message + '</li>';
                }
            });
            html += '</ul>';
        }

        html += '</div>';
    });

    html += '</div></body></html>';
    return html;
}