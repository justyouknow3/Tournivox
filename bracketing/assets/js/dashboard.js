/**
 * TOURNIVOX Bracketing Manager - Dashboard Charts
 */

function initDashboardCharts(stats) {
    // Tournament status chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Upcoming', 'Finished'],
                datasets: [{
                    data: [stats.active || 0, stats.upcoming || 0, stats.finished || 0],
                    backgroundColor: ['#3B82F6', '#F59E0B', '#64748B'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94A3B8', padding: 15 } }
                },
                cutout: '65%',
            }
        });
    }

    // Matches per month chart
    const matchesCtx = document.getElementById('matchesChart');
    if (matchesCtx && stats.monthly) {
        new Chart(matchesCtx, {
            type: 'bar',
            data: {
                labels: stats.monthly.labels,
                datasets: [{
                    label: 'Matches',
                    data: stats.monthly.data,
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(51,65,85,0.3)' }, ticks: { color: '#94A3B8' } },
                    y: { grid: { color: 'rgba(51,65,85,0.3)' }, ticks: { color: '#94A3B8' }, beginAtZero: true }
                }
            }
        });
    }
}
