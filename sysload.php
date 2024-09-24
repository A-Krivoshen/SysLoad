<?php
/**
 * Plugin Name: SysLoad
 * Description: Плагин для отображения нагрузки процессора, использования памяти и времени работы сервера.
 * Version: 1.20
 * Author: Aleksey Krivoshein
 */

// Отображение статистики сервера
function display_server_stats() {
    ob_start();
    ?>
    <div id="server-stats" class="server-stats">
        <div class="charts-container">
            <div class="chart">
                <h3>Нагрузка процессора:</h3>
                <canvas id="cpu-load-chart" width="400" height="200"></canvas>
            </div>
            <div class="chart">
                <h3>Использование памяти:</h3>
                <canvas id="memory-usage-chart" width="400" height="200"></canvas>
            </div>
        </div>

        <h3>Статистика сервера:</h3>
        <table>
            <tr>
                <th>Параметр</th>
                <th>Значение</th>
            </tr>
            <tr>
                <td>Время работы сервера:</td>
                <td id="uptime-stats">Загрузка...</td>
            </tr>
            <tr>
                <td>Общая память:</td>
                <td id="total-memory-stats">Загрузка...</td>
            </tr>
            <tr>
                <td>Использованная память:</td>
                <td id="used-memory-stats">Загрузка...</td>
            </tr>
            <tr>
                <td>Нагрузка процессора:</td>
                <td id="cpu-load-stats">Загрузка...</td>
            </tr>
        </table>
    </div>
    <style>
        .charts-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        .chart {
            flex: 1;
            min-width: 300px;
            margin: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        @media (max-width: 768px) {
            .charts-container {
                flex-direction: column;
            }
            table {
                display: flex;
                flex-direction: column;
                border: none;
            }
            tr {
                display: flex;
                justify-content: space-between;
                border-bottom: 1px solid #ddd;
                padding: 8px 0;
            }
            th, td {
                flex: 1;
                text-align: left;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        jQuery(document).ready(function($) {
            let cpuData = [];
            let memoryUsedData = [];
            let totalMemory = 0;
            const cpuChartCtx = document.getElementById('cpu-load-chart').getContext('2d');
            const memoryChartCtx = document.getElementById('memory-usage-chart').getContext('2d');

            const cpuChart = new Chart(cpuChartCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Загрузка процессора (%)',
                        data: cpuData,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        fill: false,
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Процент'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Время'
                            }
                        }
                    }
                }
            });

            const memoryChart = new Chart(memoryChartCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Использование памяти (MB)',
                        data: memoryUsedData,
                        borderColor: 'rgba(153, 102, 255, 1)',
                        fill: false,
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'MB'
                            },
                            max: totalMemory, // Устанавливаем максимальное значение для графика памяти
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Время'
                            }
                        }
                    }
                }
            });

            function fetchServerStats() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: {
                        action: 'sysload_get_stats',
                    },
                    success: function(response) {
                        if (response.success) {
                            totalMemory = response.data.memory.total; // Обновляем общее количество памяти
                            memoryChart.options.scales.y.max = totalMemory; // Устанавливаем максимальное значение

                            cpuData.push(response.data.cpu_load);
                            memoryUsedData.push(response.data.memory.used);

                            const timestamp = new Date().toLocaleTimeString();
                            cpuChart.data.labels.push(timestamp);
                            memoryChart.data.labels.push(timestamp);

                            if (cpuData.length > 10) {
                                cpuData.shift();
                                memoryUsedData.shift();
                                cpuChart.data.labels.shift();
                                memoryChart.data.labels.shift();
                            }

                            cpuChart.update();
                            memoryChart.update();

                            $('#uptime-stats').html(response.data.uptime);
                            $('#total-memory-stats').html(response.data.memory.total + ' MB');
                            $('#used-memory-stats').html(response.data.memory.used + ' MB (' + response.data.memory.used_percent + '%)');
                            $('#cpu-load-stats').html(response.data.cpu_load + '%');
                        } else {
                            $('#uptime-stats').html('Ошибка при получении данных: ' + response.data.message);
                        }
                    },
                    error: function() {
                        $('#uptime-stats').html('Ошибка при получении данных.');
                    }
                });
            }

            fetchServerStats();
            setInterval(fetchServerStats, 10000);
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('server_stats', 'display_server_stats');

// AJAX обработчик
function sysload_get_stats() {
    $cpu_load = sys_getloadavg();
    if (!$cpu_load) {
        wp_send_json_error(['message' => 'Не удалось получить нагрузку процессора.']);
        return;
    }

    $cpu_percent = round($cpu_load[0] / (count($cpu_load) * 1.0) * 100, 2);
    $memory = shell_exec('free -m');
    if (!$memory) {
        wp_send_json_error(['message' => 'Не удалось получить данные о памяти.']);
        return;
    }

    $memory = explode("\n", $memory);
    $memory_info = preg_split('/\s+/', $memory[1]);
    $uptime = shell_exec('uptime -p');
    if (!$uptime) {
        wp_send_json_error(['message' => 'Не удалось получить время работы.']);
        return;
    }

    $response = array(
        'cpu_load' => $cpu_percent,
        'memory' => array(
            'total' => (int)$memory_info[1],
            'used' => (int)$memory_info[2],
            'used_percent' => round(($memory_info[2] / $memory_info[1]) * 100, 2),
        ),
        'uptime' => trim($uptime),
    );

    wp_send_json_success($response);
}
add_action('wp_ajax_sysload_get_stats', 'sysload_get_stats');
add_action('wp_ajax_nopriv_sysload_get_stats', 'sysload_get_stats');

// Добавляем виджет на главную страницу админки
function sysload_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'sysload_dashboard_widget',
        'Статистика сервера',
        'sysload_dashboard_widget_display'
    );
}
add_action('wp_dashboard_setup', 'sysload_add_dashboard_widget');

// Функция для отображения содержимого виджета
function sysload_dashboard_widget_display() {
    ?>
    <div id="sysload-dashboard-stats">
        <h3>Статистика сервера</h3>
        <p>Загрузка процессора: <span id="dashboard-cpu-load">Загрузка...</span></p>
        <p>Использованная память: <span id="dashboard-used-memory">Загрузка...</span></p>
        <p>Общая память: <span id="dashboard-total-memory">Загрузка...</span></p>
        <p>Время работы сервера: <span id="dashboard-uptime">Загрузка...</span></p>

        <div>
            <canvas id="dashboard-cpu-load-chart" width="400" height="200"></canvas>
            <canvas id="dashboard-memory-usage-chart" width="400" height="200"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        jQuery(document).ready(function($) {
            let cpuData = [];
            let memoryUsedData = [];
            let totalMemory = 0;
            const cpuChartCtx = document.getElementById('dashboard-cpu-load-chart').getContext('2d');
            const memoryChartCtx = document.getElementById('dashboard-memory-usage-chart').getContext('2d');

            const cpuChart = new Chart(cpuChartCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Загрузка процессора (%)',
                        data: cpuData,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        fill: false,
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Процент'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Время'
                            }
                        }
                    }
                }
            });

            const memoryChart = new Chart(memoryChartCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Использование памяти (MB)',
                        data: memoryUsedData,
                        borderColor: 'rgba(153, 102, 255, 1)',
                        fill: false,
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'MB'
                            },
                            max: totalMemory, // Устанавливаем максимальное значение для графика памяти
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Время'
                            }
                        }
                    }
                }
            });

            function fetchDashboardStats() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: {
                        action: 'sysload_get_stats',
                    },
                    success: function(response) {
                        if (response.success) {
                            totalMemory = response.data.memory.total; // Обновляем общее количество памяти
                            memoryChart.options.scales.y.max = totalMemory; // Устанавливаем максимальное значение

                            cpuData.push(response.data.cpu_load);
                            memoryUsedData.push(response.data.memory.used);

                            const timestamp = new Date().toLocaleTimeString();
                            cpuChart.data.labels.push(timestamp);
                            memoryChart.data.labels.push(timestamp);

                            if (cpuData.length > 10) {
                                cpuData.shift();
                                memoryUsedData.shift();
                                cpuChart.data.labels.shift();
                                memoryChart.data.labels.shift();
                            }

                            cpuChart.update();
                            memoryChart.update();

                            $('#dashboard-uptime').html(response.data.uptime);
                            $('#dashboard-total-memory').html(response.data.memory.total + ' MB');
                            $('#dashboard-used-memory').html(response.data.memory.used + ' MB (' + response.data.memory.used_percent + '%)');
                            $('#dashboard-cpu-load').html(response.data.cpu_load + '%');
                        } else {
                            $('#dashboard-uptime').html('Ошибка при получении данных: ' + response.data.message);
                        }
                    },
                    error: function() {
                        $('#dashboard-uptime').html('Ошибка при получении данных.');
                    }
                });
            }

            fetchDashboardStats();
            setInterval(fetchDashboardStats, 10000);
        });
    </script>
    <?php
}

// Добавьте следующий код, чтобы создать новый виджет на главной странице админки
add_action('wp_dashboard_setup', 'sysload_add_dashboard_widget');

?>
