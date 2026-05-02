import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();


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
