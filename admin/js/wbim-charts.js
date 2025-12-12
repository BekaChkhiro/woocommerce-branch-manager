/**
 * WBIM Charts JavaScript
 *
 * Handles chart initialization and configuration for reports.
 *
 * @package WBIM
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Chart.js default configuration
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = '"Noto Sans Georgian", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#666';
    }

    /**
     * WBIM Charts object
     */
    var WBIMCharts = {

        // Color palette
        colors: [
            '#4e73df',
            '#1cc88a',
            '#36b9cc',
            '#f6c23e',
            '#e74a3b',
            '#858796',
            '#5a5c69',
            '#6f42c1'
        ],

        // Chart instances storage
        instances: {},

        /**
         * Initialize charts on page
         */
        init: function() {
            this.initLineCharts();
            this.initBarCharts();
            this.initDoughnutCharts();
            this.initPieCharts();
        },

        /**
         * Initialize line charts
         */
        initLineCharts: function() {
            var self = this;

            $('[data-chart="line"]').each(function() {
                var canvas = $(this);
                var chartId = canvas.attr('id');
                var dataAttr = canvas.data('chart-data');

                if (!dataAttr) return;

                self.createLineChart(chartId, dataAttr);
            });
        },

        /**
         * Initialize bar charts
         */
        initBarCharts: function() {
            var self = this;

            $('[data-chart="bar"]').each(function() {
                var canvas = $(this);
                var chartId = canvas.attr('id');
                var dataAttr = canvas.data('chart-data');

                if (!dataAttr) return;

                self.createBarChart(chartId, dataAttr);
            });
        },

        /**
         * Initialize doughnut charts
         */
        initDoughnutCharts: function() {
            var self = this;

            $('[data-chart="doughnut"]').each(function() {
                var canvas = $(this);
                var chartId = canvas.attr('id');
                var dataAttr = canvas.data('chart-data');

                if (!dataAttr) return;

                self.createDoughnutChart(chartId, dataAttr);
            });
        },

        /**
         * Initialize pie charts
         */
        initPieCharts: function() {
            var self = this;

            $('[data-chart="pie"]').each(function() {
                var canvas = $(this);
                var chartId = canvas.attr('id');
                var dataAttr = canvas.data('chart-data');

                if (!dataAttr) return;

                self.createPieChart(chartId, dataAttr);
            });
        },

        /**
         * Create line chart
         */
        createLineChart: function(canvasId, data) {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            // Destroy existing chart
            if (this.instances[canvasId]) {
                this.instances[canvasId].destroy();
            }

            var chartData = this.processChartData(data, 'line');

            this.instances[canvasId] = new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    var label = context.dataset.label || '';
                                    var value = context.parsed.y;

                                    if (context.dataset.isCurrency) {
                                        return label + ': ₾' + value.toLocaleString();
                                    }

                                    return label + ': ' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                borderDash: [2, 2]
                            },
                            ticks: {
                                callback: function(value) {
                                    if (data.isCurrency) {
                                        return '₾' + value.toLocaleString();
                                    }
                                    return value.toLocaleString();
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });

            return this.instances[canvasId];
        },

        /**
         * Create bar chart
         */
        createBarChart: function(canvasId, data) {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            // Destroy existing chart
            if (this.instances[canvasId]) {
                this.instances[canvasId].destroy();
            }

            var chartData = this.processChartData(data, 'bar');
            var isStacked = data.stacked || false;

            this.instances[canvasId] = new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            stacked: isStacked,
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            stacked: isStacked,
                            beginAtZero: true,
                            grid: {
                                borderDash: [2, 2]
                            }
                        }
                    }
                }
            });

            return this.instances[canvasId];
        },

        /**
         * Create doughnut chart
         */
        createDoughnutChart: function(canvasId, data) {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            // Destroy existing chart
            if (this.instances[canvasId]) {
                this.instances[canvasId].destroy();
            }

            var chartData = this.processChartData(data, 'doughnut');

            this.instances[canvasId] = new Chart(ctx, {
                type: 'doughnut',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.parsed;
                                    var total = context.dataset.data.reduce(function(a, b) {
                                        return a + b;
                                    }, 0);
                                    var percentage = ((value / total) * 100).toFixed(1);

                                    return label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            return this.instances[canvasId];
        },

        /**
         * Create pie chart
         */
        createPieChart: function(canvasId, data) {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            // Destroy existing chart
            if (this.instances[canvasId]) {
                this.instances[canvasId].destroy();
            }

            var chartData = this.processChartData(data, 'pie');

            this.instances[canvasId] = new Chart(ctx, {
                type: 'pie',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.parsed;
                                    var total = context.dataset.data.reduce(function(a, b) {
                                        return a + b;
                                    }, 0);
                                    var percentage = ((value / total) * 100).toFixed(1);

                                    return label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            return this.instances[canvasId];
        },

        /**
         * Process chart data
         */
        processChartData: function(data, type) {
            var self = this;

            if (type === 'line' || type === 'bar') {
                // Multi-dataset format
                if (data.datasets) {
                    data.datasets.forEach(function(dataset, index) {
                        if (!dataset.borderColor) {
                            dataset.borderColor = self.colors[index % self.colors.length];
                        }
                        if (!dataset.backgroundColor) {
                            if (type === 'line') {
                                dataset.backgroundColor = dataset.borderColor + '20';
                            } else {
                                dataset.backgroundColor = dataset.borderColor;
                            }
                        }
                        if (type === 'line') {
                            dataset.tension = 0.3;
                            dataset.fill = true;
                        }
                    });
                }
            } else if (type === 'doughnut' || type === 'pie') {
                // Single dataset format
                if (data.datasets && data.datasets[0]) {
                    if (!data.datasets[0].backgroundColor) {
                        data.datasets[0].backgroundColor = self.colors.slice(0, data.labels.length);
                    }
                }
            }

            return data;
        },

        /**
         * Update chart data
         */
        updateChart: function(canvasId, newData) {
            if (!this.instances[canvasId]) return;

            var chart = this.instances[canvasId];
            chart.data = newData;
            chart.update();
        },

        /**
         * Destroy chart
         */
        destroyChart: function(canvasId) {
            if (this.instances[canvasId]) {
                this.instances[canvasId].destroy();
                delete this.instances[canvasId];
            }
        },

        /**
         * Get color by index
         */
        getColor: function(index) {
            return this.colors[index % this.colors.length];
        },

        /**
         * Get colors array
         */
        getColors: function(count) {
            return this.colors.slice(0, count);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WBIMCharts.init();
    });

    // Expose to global scope
    window.WBIMCharts = WBIMCharts;

})(jQuery);
