import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

async function copyDeploymentText(button) {
    const text = button.dataset.copyText || '';
    const originalLabel = button.dataset.copyOriginalLabel || button.textContent.trim();

    button.dataset.copyOriginalLabel = originalLabel;

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
        }

        button.textContent = 'Copied';
        button.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
        button.classList.remove('text-slate-700');

        window.setTimeout(() => {
            button.textContent = originalLabel;
            button.classList.remove('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
            button.classList.add('text-slate-700');
        }, 1800);
    } catch (error) {
        button.textContent = 'Copy failed';

        window.setTimeout(() => {
            button.textContent = originalLabel;
        }, 2200);
    }
}

document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-copy-text]');

    if (!button) {
        return;
    }

    copyDeploymentText(button);
});


if (window.Echo) {
    window.Echo.channel('monitors')
        .listen('.monitor.checked', (data) => {
            const card = document.querySelector(`[data-monitor-id="${data.id}"]`);

            if (!card) {
                return;
            }

            const status = card.querySelector('.status');
            const response = card.querySelector('.response');
            const statusCode = card.querySelector('.status-code');
            const uptime = card.querySelector('.uptime');
            const seoStatus = card.querySelector('.seo-status');
            const dot = card.querySelector('.status-dot');

            if (status) {
                status.textContent = data.is_up ? 'UP' : 'DOWN';
            }

            if (response) {
                response.textContent = `${Number(data.response_time || 0).toFixed(3)}s`;
            }

            if (statusCode) {
                statusCode.textContent = data.status_code ?? '-';
            }

            if (uptime) {
                uptime.textContent = `${data.uptime_24h ?? 0}%`;
            }

            if (seoStatus) {
                seoStatus.textContent = data.seo_suspicious ? 'SEO Alert' : 'SEO Clear';
                seoStatus.classList.toggle('bg-amber-400/15', Boolean(data.seo_suspicious));
                seoStatus.classList.toggle('text-amber-200', Boolean(data.seo_suspicious));
            }

            if (dot) {
                dot.classList.toggle('bg-green-300', Boolean(data.is_up));
                dot.classList.toggle('bg-red-300', !data.is_up);
                dot.parentElement.classList.toggle('text-green-300', Boolean(data.is_up));
                dot.parentElement.classList.toggle('text-red-300', !data.is_up);
            }

            const chart = window.monitorCharts?.[data.id];

            if (chart) {
                chart.data.labels.push(new Date().toLocaleTimeString());
                chart.data.datasets[0].data.push(data.response_time);

                if (chart.data.labels.length > 20) {
                    chart.data.labels.shift();
                    chart.data.datasets[0].data.shift();
                }

                chart.update();
            }
        });
}
