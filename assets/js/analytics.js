/**
 * BizAutoPro Advanced Analytics Dashboard
 * Enhanced data visualization with Chart.js integration
 */

class AnalyticsDashboard {
    constructor() {
        this.charts = {};
        this.colors = {
            primary: '#4f46e5',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#3b82f6',
            light: '#f3f4f6',
            dark: '#1f2937'
        };
        this.init();
    }

    init() {
        this.loadChartLibrary(() => {
            this.renderAllCharts();
            this.setupRealTimeUpdates();
            this.setupDateRangeFilter();
            this.setupThemeAwareness();
        });
    }

    setupThemeAwareness() {
        // Listen for theme changes and update charts accordingly
        document.addEventListener('themeChanged', () => {
            this.updateChartColorsForTheme();
        });
        
        // Initial theme setup
        this.updateChartColorsForTheme();
    }

    updateChartColorsForTheme() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        
        // Update color scheme based on theme
        if (isDark) {
            this.colors = {
                primary: '#6366f1',
                success: '#22c55e',
                warning: '#f59e0b',
                danger: '#ef4444',
                info: '#3b82f6',
                light: '#374151',
                dark: '#f3f4f6'
            };
        } else {
            this.colors = {
                primary: '#4f46e5',
                success: '#10b981',
                warning: '#f59e0b',
                danger: '#ef4444',
                info: '#3b82f6',
                light: '#f3f4f6',
                dark: '#1f2937'
            };
        }

        // Update existing charts if they exist
        if (this.charts) {
            Object.values(this.charts).forEach(chart => {
                if (chart && chart.update) {
                    chart.update('none');
                }
            });
        }
    }

    loadChartLibrary(callback) {
        if (typeof Chart !== 'undefined') {
            callback();
            return;
        }
        
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js';
        script.onload = callback;
        document.head.appendChild(script);
    }

    renderAllCharts() {
        this.loadRealTimeData();
    }

    async loadRealTimeData() {
        try {
            // Load all chart data from API
            const [revenue, inventory, leads, workflow, userActivity, performance, predictive] = await Promise.all([
                this.fetchAnalyticsData('revenue'),
                this.fetchAnalyticsData('inventory'),
                this.fetchAnalyticsData('leads'),
                this.fetchAnalyticsData('workflows'),
                this.fetchAnalyticsData('user_activity'),
                this.fetchAnalyticsData('performance'),
                this.fetchAnalyticsData('predictive')
            ]);

            // Render charts with real data
            this.renderRevenueChart(revenue);
            this.renderInventoryChart(inventory);
            this.renderLeadsChart(leads);
            this.renderWorkflowChart(workflow);
            this.renderUserActivityChart(userActivity);
            this.renderPerformanceMetrics(performance);
            this.renderPredictiveAnalytics(predictive);
        } catch (error) {
            console.warn('Failed to load real-time data, using fallback data:', error);
            // Fallback to static data
            this.renderRevenueChart();
            this.renderInventoryChart();
            this.renderLeadsChart();
            this.renderWorkflowChart();
            this.renderUserActivityChart();
            this.renderPerformanceMetrics();
            this.renderPredictiveAnalytics();
        }
    }

    async fetchAnalyticsData(type, range = '7d') {
        try {
            const response = await fetch(`api/analytics.php?type=${type}&range=${range}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`Error fetching ${type} data:`, error);
            return null;
        }
    }

    renderRevenueChart(data = null) {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return;

        // Always prefer real data, even if it's zeros - no more fabricated fallbacks
        let chartData;
        
        if (data && data.labels && data.datasets) {
            // Use real data from API
            console.log('Revenue chart: Using real data from API', data);
            chartData = data;
        } else {
            // If no data from API, show zeros instead of fake data
            console.warn('No revenue data received from API, showing authentic zeros');
            chartData = {
                labels: this.getLast7Days(),
                datasets: [{
                    label: 'Daily Revenue (₦)',
                    data: [0, 0, 0, 0, 0, 0, 0], // Real zeros instead of fake data
                    borderColor: this.colors.primary,
                    backgroundColor: this.colors.primary + '20',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Target Revenue (₦)',
                    data: [0, 0, 0, 0, 0, 0, 0], // Real zeros instead of fake targets
                    borderColor: this.colors.warning,
                    borderDash: [5, 5],
                    backgroundColor: 'transparent',
                    tension: 0
                }]
            };
        }

        this.charts.revenue = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 15,
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        titleFont: {
                            size: 12
                        },
                        bodyFont: {
                            size: 11
                        },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₦' + (context.parsed.y || 0).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: {
                                size: 10
                            },
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    renderInventoryChart(data = null) {
        const ctx = document.getElementById('inventoryChart');
        if (!ctx) return;

        // Always prefer real data, show zeros if no real data available
        let chartData;
        
        if (data && data.labels && data.datasets) {
            // Use real data from API
            console.log('Inventory chart: Using real data from API', data);
            chartData = data;
        } else {
            // If no data from API, show zeros instead of fake data
            console.warn('No inventory data received from API, showing authentic zeros');
            chartData = {
                labels: ['In Stock', 'Low Stock', 'Out of Stock', 'On Order'],
                datasets: [{
                    data: [0, 0, 0, 0], // Real zeros instead of fake data
                    backgroundColor: [
                        this.colors.success,
                        this.colors.warning,
                        this.colors.danger,
                        this.colors.info
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            };
        }

        this.charts.inventory = new Chart(ctx, {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            font: {
                                size: 10
                            }
                        }
                    },
                    tooltip: {
                        titleFont: {
                            size: 11
                        },
                        bodyFont: {
                            size: 10
                        },
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed * 100) / total).toFixed(1);
                                return context.label + ': ' + context.parsed + ' items (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    renderLeadsChart(data = null) {
        const ctx = document.getElementById('leadsChart');
        if (!ctx) return;

        // Always prefer real data, show zeros if no real data available
        let chartData;
        
        if (data && data.labels && data.datasets) {
            // Use real data from API
            console.log('Leads chart: Using real data from API', data);
            chartData = data;
        } else {
            // If no data from API, show zeros instead of fake data
            console.warn('No leads data received from API, showing authentic zeros');
            chartData = {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'New Leads',
                    data: [0, 0, 0, 0, 0, 0], // Real zeros instead of fake data
                    backgroundColor: this.colors.info + '80',
                    borderColor: this.colors.info,
                    borderWidth: 1
                }, {
                    label: 'Converted',
                    data: [0, 0, 0, 0, 0, 0], // Real zeros instead of fake data
                    backgroundColor: this.colors.success + '80',
                    borderColor: this.colors.success,
                    borderWidth: 1
                }]
            };
        }

        this.charts.leads = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            font: {
                                size: 10
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });
    }

    renderWorkflowChart(data = null) {
        const ctx = document.getElementById('workflowChart');
        if (!ctx) return;

        // Always prefer real data, show zeros if no real data available
        let chartData;
        
        if (data && data.labels && data.datasets) {
            // Use real data from API
            console.log('Workflow chart: Using real data from API', data);
            chartData = data;
        } else {
            // If no data from API, show zeros instead of fake data
            console.warn('No workflow data received from API, showing authentic zeros');
            chartData = {
                labels: ['Completed', 'In Progress', 'Pending', 'Cancelled'],
                datasets: [{
                    data: [0, 0, 0, 0], // Real zeros instead of fake data
                    backgroundColor: [
                        this.colors.success + '80',
                        this.colors.info + '80',
                        this.colors.warning + '80',
                        this.colors.danger + '80'
                    ],
                    borderColor: [
                        this.colors.success,
                        this.colors.info,
                        this.colors.warning,
                        this.colors.danger
                    ],
                    borderWidth: 2
                }]
            };
        }

        this.charts.workflow = new Chart(ctx, {
            type: 'polarArea',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 8,
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });
    }

    renderUserActivityChart(data = null) {
        const ctx = document.getElementById('userActivityChart');
        if (!ctx) return;

        // Always prefer real data, show zeros if no real data available
        let chartData;
        
        if (data && data.labels && data.datasets) {
            // Use real data from API
            console.log('User activity chart: Using real data from API', data);
            chartData = data;
        } else {
            // If no data from API, show zeros instead of fake data
            console.warn('No user activity data received from API, showing authentic zeros');
            chartData = {
                labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00', '24:00'],
                datasets: [{
                    label: 'Active Users',
                    data: [0, 0, 0, 0, 0, 0, 0], // Real zeros instead of fake data
                    borderColor: this.colors.success,
                    backgroundColor: this.colors.success + '20',
                    tension: 0.4,
                    fill: true
                }]
            };
        }

        this.charts.userActivity = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    renderPerformanceMetrics(data = null) {
        const ctx = document.getElementById('performanceChart');
        if (!ctx) return;

        // Always prefer real data, show zeros if no real data available
        let chartData;
        
        if (data && data.labels && data.datasets) {
            // Use real data from API
            console.log('Performance chart: Using real data from API', data);
            chartData = data;
        } else {
            // If no data from API, show zeros instead of fake data
            console.warn('No performance data received from API, showing authentic zeros');
            chartData = {
                labels: ['Sales', 'Inventory', 'Leads', 'Workflows', 'Users', 'Revenue'],
                datasets: [{
                    label: 'Current Performance',
                    data: [0, 0, 0, 0, 0, 0], // Real zeros instead of fake data
                    borderColor: this.colors.primary,
                    backgroundColor: this.colors.primary + '20',
                    pointBackgroundColor: this.colors.primary,
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: this.colors.primary
                }, {
                    label: 'Target Performance',
                    data: [0, 0, 0, 0, 0, 0], // Real zeros instead of fake targets
                    borderColor: this.colors.warning,
                    backgroundColor: this.colors.warning + '10',
                    pointBackgroundColor: this.colors.warning,
                    pointBorderColor: '#fff',
                    borderDash: [5, 5]
                }]
            };
        }

        this.charts.performance = new Chart(ctx, {
            type: 'radar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 10,
                            padding: 8,
                            font: {
                                size: 9
                            }
                        }
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20,
                            font: {
                                size: 9
                            }
                        },
                        pointLabels: {
                            font: {
                                size: 9
                            }
                        }
                    }
                }
            }
        });
    }

    renderPredictiveAnalytics(data = null) {
        const ctx = document.getElementById('predictiveChart');
        if (!ctx) return;

        // Use real data structure from API, or fallback to authentic zeros
        let chartData, chartLabels;
        
        if (data && data.datasets) {
            // Real data from API - use the complete datasets structure
            chartData = data.datasets;
            chartLabels = data.labels;
            console.log('Predictive Analytics - Using real API data:', data);
        } else {
            // Fallback to authentic zeros for empty state
            chartLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5 (Forecast)', 'Week 6 (Forecast)'];
            chartData = [{
                label: 'Actual Revenue',
                data: [0, 0, 0, 0, null, null],
                borderColor: this.colors.success,
                backgroundColor: this.colors.success + '20',
                tension: 0.4,
                fill: true
            }, {
                label: 'Predicted Revenue',
                data: [null, null, null, 0, 0, 0],
                borderColor: this.colors.warning,
                backgroundColor: this.colors.warning + '20',
                borderDash: [5, 5],
                tension: 0.4,
                fill: true
            }];
            console.log('Predictive Analytics - Using fallback zeros');
        }

        this.charts.predictive = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: chartData
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₦' + (context.parsed.y || 0).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    setupRealTimeUpdates() {
        // Update charts every 30 seconds with new data
        setInterval(() => {
            this.updateChartsWithLiveData();
        }, 30000);
    }

    setupDateRangeFilter() {
        const dateFilter = document.getElementById('dateRangeFilter');
        if (dateFilter) {
            dateFilter.addEventListener('change', (e) => {
                this.filterDataByDateRange(e.target.value);
            });
        }
    }

    async updateChartsWithLiveData() {
        try {
            // Fetch fresh data for revenue chart
            const revenueData = await this.fetchAnalyticsData('revenue');
            if (revenueData && this.charts.revenue) {
                this.charts.revenue.data = revenueData;
                this.charts.revenue.update('active');
            }

            // Update dashboard summary if available
            const summary = await this.fetchAnalyticsData('dashboard_summary');
            if (summary) {
                this.updateDashboardMetrics(summary);
            }

            console.log('Charts updated with live data at', new Date().toLocaleTimeString());
        } catch (error) {
            console.error('Error updating charts with live data:', error);
        }
    }

    updateDashboardMetrics(summary) {
        // Update metric cards with real-time data
        const metricCards = document.querySelectorAll('.metric-card');
        if (metricCards.length >= 4) {
            // Update revenue
            const revenueValue = metricCards[0].querySelector('.metric-value');
            if (revenueValue) {
                revenueValue.textContent = '₦' + summary.total_revenue.toLocaleString();
            }

            // Update inventory
            const inventoryValue = metricCards[1].querySelector('.metric-value');
            if (inventoryValue) {
                inventoryValue.textContent = summary.active_inventory;
            }

            // Update leads
            const leadsValue = metricCards[2].querySelector('.metric-value');
            if (leadsValue) {
                leadsValue.textContent = summary.new_leads;
            }

            // Update efficiency
            const efficiencyValue = metricCards[3].querySelector('.metric-value');
            if (efficiencyValue) {
                efficiencyValue.textContent = summary.system_efficiency + '%';
            }
        }
    }

    async filterDataByDateRange(range) {
        console.log('Filtering data for range:', range);
        
        // Re-fetch data with new date range
        try {
            const [revenue, leads] = await Promise.all([
                this.fetchAnalyticsData('revenue', range),
                this.fetchAnalyticsData('leads', range)
            ]);

            // Update charts with filtered data
            if (revenue && this.charts.revenue) {
                this.charts.revenue.data = revenue;
                this.charts.revenue.update();
            }

            if (leads && this.charts.leads) {
                this.charts.leads.data = leads;
                this.charts.leads.update();
            }
        } catch (error) {
            console.error('Error filtering data:', error);
        }
    }

    getLast7Days() {
        const days = [];
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            days.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        }
        return days;
    }

    exportChartData(chartType) {
        if (this.charts[chartType]) {
            const chart = this.charts[chartType];
            const csv = this.convertChartToCSV(chart);
            this.downloadCSV(csv, `${chartType}_data.csv`);
        }
    }

    convertChartToCSV(chart) {
        let csv = 'Label,Value\n';
        chart.data.labels.forEach((label, index) => {
            chart.data.datasets.forEach(dataset => {
                csv += `${label},${dataset.data[index]}\n`;
            });
        });
        return csv;
    }

    downloadCSV(csv, filename) {
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        window.URL.revokeObjectURL(url);
    }
}

// Initialize Analytics Dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.analytics-dashboard')) {
        new AnalyticsDashboard();
    }
});

// Export for global access
window.AnalyticsDashboard = AnalyticsDashboard;