/**
 * Admin Chart JavaScript
 * Handles Chart.js visualization for admin dashboard
 */

let adminChart = null;

async function initAdminChart(period = '7days') {
    try {
        const response = await fetch(`../../api/stats/get-chart-data.php?period=${period}`);
        const result = await response.json();

        if (!result.success) {
            console.error('Failed to load chart data:', result.message);
            return;
        }

        const ctx = document.getElementById('adminChart');
        if (!ctx) {
            console.error('Chart canvas not found');
            return;
        }

        // Destroy existing chart if it exists
        if (adminChart) {
            adminChart.destroy();
        }

        // Create new chart
        adminChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: result.labels,
                datasets: result.datasets.map(dataset => ({
                    ...dataset,
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                size: 12,
                                family: 'Inter, sans-serif'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 13,
                            family: 'Inter, sans-serif'
                        },
                        bodyFont: {
                            size: 12,
                            family: 'Inter, sans-serif'
                        },
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.parsed.y;
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                size: 11,
                                family: 'Inter, sans-serif'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 11,
                                family: 'Inter, sans-serif'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

    } catch (error) {
        console.error('Error initializing chart:', error);
    }
}

// Initialize chart period selector
document.addEventListener('DOMContentLoaded', () => {
    const periodSelector = document.getElementById('chartPeriodSelector');
    if (periodSelector) {
        periodSelector.addEventListener('change', (e) => {
            initAdminChart(e.target.value);
        });
    }

    // Initialize with default period
    initAdminChart('7days');
});
