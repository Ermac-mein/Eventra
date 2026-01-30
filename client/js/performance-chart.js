/**
 * Event Performance Chart
 * Displays event analytics using Chart.js
 */

// Load Chart.js if not already loaded
if (typeof Chart === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
    script.onload = initializeChart;
    document.head.appendChild(script);
} else {
    initializeChart();
}

let performanceChart = null;

function initializeChart() {
    const chartContainer = document.querySelector('.chart-placeholder');
    if (!chartContainer) return;

    // Replace placeholder with canvas
    chartContainer.innerHTML = '<canvas id="performanceChart"></canvas>';
    
    // Load chart data
    loadChartData();
}

async function loadChartData() {
    try {
        const user = storage.get('user');
        if (!user) return;

        // Fetch chart data from new API
        const response = await fetch('../../api/stats/get-chart-data.php?period=30days');
        const result = await response.json();

        if (result.success && result.datasets) {
            renderChartFromAPI(result);
        } else {
            renderEmptyChart();
        }
    } catch (error) {
        console.error('Error loading chart data:', error);
        renderEmptyChart();
    }
}

function renderChartFromAPI(data) {
    const ctx = document.getElementById('performanceChart');
    if (!ctx) return;

    // Destroy existing chart if any
    if (performanceChart) {
        performanceChart.destroy();
    }

    // Create new chart with API data
    performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: data.datasets.map(dataset => ({
                ...dataset,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6
            }))
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: '700'
                    },
                    bodyFont: {
                        size: 13
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

function renderChart(events) {
    const ctx = document.getElementById('performanceChart');
    if (!ctx) return;

    // Prepare data: Group events by month
    const monthlyData = {};
    const currentYear = new Date().getFullYear();
    
    // Initialize last 6 months
    for (let i = 5; i >= 0; i--) {
        const date = new Date();
        date.setMonth(date.getMonth() - i);
        const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        monthlyData[monthKey] = {
            events: 0,
            tickets: 0,
            revenue: 0
        };
    }

    // Aggregate event data
    events.forEach(event => {
        const eventDate = new Date(event.event_date);
        const monthKey = `${eventDate.getFullYear()}-${String(eventDate.getMonth() + 1).padStart(2, '0')}`;
        
        if (monthlyData[monthKey]) {
            monthlyData[monthKey].events++;
            monthlyData[monthKey].tickets += parseInt(event.attendee_count || 0);
            monthlyData[monthKey].revenue += parseFloat(event.price || 0) * parseInt(event.attendee_count || 0);
        }
    });

    // Prepare chart data
    const labels = Object.keys(monthlyData).map(key => {
        const [year, month] = key.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
    });

    const eventCounts = Object.values(monthlyData).map(d => d.events);
    const ticketCounts = Object.values(monthlyData).map(d => d.tickets);
    const revenues = Object.values(monthlyData).map(d => d.revenue);

    // Destroy existing chart if any
    if (performanceChart) {
        performanceChart.destroy();
    }

    // Create new chart
    performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Events Created',
                    data: eventCounts,
                    borderColor: '#635bff',
                    backgroundColor: 'rgba(99, 91, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Tickets Sold',
                    data: ticketCounts,
                    borderColor: '#37d67a',
                    backgroundColor: 'rgba(55, 214, 122, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: '700'
                    },
                    bodyFont: {
                        size: 13
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
                            size: 11
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

function renderEmptyChart() {
    const ctx = document.getElementById('performanceChart');
    if (!ctx) return;

    // Destroy existing chart if any
    if (performanceChart) {
        performanceChart.destroy();
    }

    // Create empty chart with placeholder data
    performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [
                {
                    label: 'Events Created',
                    data: [0, 0, 0, 0, 0, 0],
                    borderColor: '#635bff',
                    backgroundColor: 'rgba(99, 91, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Tickets Sold',
                    data: [0, 0, 0, 0, 0, 0],
                    borderColor: '#37d67a',
                    backgroundColor: 'rgba(55, 214, 122, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    enabled: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10
                }
            }
        }
    });

    // Add "No data" overlay
    const chartContainer = ctx.parentElement;
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        pointer-events: none;
    `;
    overlay.innerHTML = `
        <div style="font-size: 3rem; margin-bottom: 0.5rem; opacity: 0.3;">📊</div>
        <div style="font-size: 1.1rem; font-weight: 600; color: #9ca3af;">No data yet</div>
        <div style="font-size: 0.9rem; color: #d1d5db; margin-top: 0.25rem;">Create events to see analytics</div>
    `;
    chartContainer.style.position = 'relative';
    chartContainer.appendChild(overlay);
}

// Refresh chart data periodically
setInterval(() => {
    if (document.getElementById('performanceChart')) {
        loadChartData();
    }
}, 60000); // Refresh every minute
