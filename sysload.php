<?php
/**
 * Plugin Name: SysLoad
 * Description: Плагин для отображения нагрузки процессора, использования памяти и времени работы сервера.
 * Version: 1.24
 * Author: Aleksey Krivoshein
 */
function sysload_enqueue_scripts() {
    // Подключение CSS
    wp_enqueue_style('sysload-css', plugin_dir_url(__FILE__) . 'assets/css/sysload.css');
    
    // Подключение JS
    wp_enqueue_script('sysload-js', plugin_dir_url(__FILE__) . 'assets/js/sysload.js', array('jquery'), null, true);
    
    // Передача переменных в JS
    wp_localize_script('sysload-js', 'sysload_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'sysload_enqueue_scripts');

// Отображение статистики сервера
function display_server_stats() {
    $enable_charts = get_option('sysload_enable_charts', true);
    ob_start();
    ?>
    <div id="server-stats" class="server-stats">
        <?php if ($enable_charts) : ?>
        <div class="charts-container">
            <div class="chart" id="cpu-load-container"></div>
            <div class="chart" id="memory-usage-container"></div>
            <div class="chart" id="network-in-container"></div>
            <div class="chart" id="network-out-container"></div>
        </div>
        <?php endif; ?>

        <h4>Статистика сервера:</h4>
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
            <tr>
                <td>Сетевой трафик (входящий):</td>
                <td id="network-in-stats">Загрузка...</td>
            </tr>
            <tr>
                <td>Сетевой трафик (исходящий):</td>
                <td id="network-out-stats">Загрузка...</td>
            </tr>
        </table>
    </div>
    <style>
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .chart {
            position: relative;
            width: 100%;
            height: 300px;
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
    </style>
    <script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>
    <script>
        jQuery(document).ready(function($) {
            let totalMemory = 0;

            const cpuChart = echarts.init(document.getElementById('cpu-load-container'));
            const memoryChart = echarts.init(document.getElementById('memory-usage-container'));
            const networkInChart = echarts.init(document.getElementById('network-in-container'));
            const networkOutChart = echarts.init(document.getElementById('network-out-container'));

            const cpuChartOptions = {
                title: { text: 'Нагрузка процессора (%)' },
                xAxis: { type: 'category', data: [] },
                yAxis: { type: 'value', min: 0, max: 100 },
                series: [{ data: [], type: 'line', smooth: true }]
            };

            const memoryChartOptions = {
                title: { text: 'Использование памяти (MB)' },
                xAxis: { type: 'category', data: [] },
                yAxis: { type: 'value', min: 0, max: totalMemory },
                series: [{ data: [], type: 'line', smooth: true }]
            };

            const networkInChartOptions = {
                title: { text: 'Входящий трафик (GB)' },
                xAxis: { type: 'category', data: [] },
                yAxis: { type: 'value', min: 0 },
                series: [{ data: [], type: 'line', smooth: true }]
            };

            const networkOutChartOptions = {
                title: { text: 'Исходящий трафик (GB)' },
                xAxis: { type: 'category', data: [] },
                yAxis: { type: 'value', min: 0 },
                series: [{ data: [], type: 'line', smooth: true }]
            };

            cpuChart.setOption(cpuChartOptions);
            memoryChart.setOption(memoryChartOptions);
            networkInChart.setOption(networkInChartOptions);
            networkOutChart.setOption(networkOutChartOptions);

            function fetchServerStats() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: { action: 'sysload_get_stats' },
                    success: function(response) {
                        if (response.success) {
                            const timestamp = new Date().toLocaleTimeString();

                            totalMemory = response.data.memory.total;

                            cpuChartOptions.xAxis.data.push(timestamp);
                            memoryChartOptions.xAxis.data.push(timestamp);
                            networkInChartOptions.xAxis.data.push(timestamp);
                            networkOutChartOptions.xAxis.data.push(timestamp);

                            cpuChartOptions.series[0].data.push(response.data.cpu_load);
                            memoryChartOptions.series[0].data.push(response.data.memory.used);
                            networkInChartOptions.series[0].data.push((response.data.network.in / 1024 / 1024 / 1024).toFixed(2));
                            networkOutChartOptions.series[0].data.push((response.data.network.out / 1024 / 1024 / 1024).toFixed(2));

                            cpuChart.setOption(cpuChartOptions);
                            memoryChart.setOption(memoryChartOptions);
                            networkInChart.setOption(networkInChartOptions);
                            networkOutChart.setOption(networkOutChartOptions);

                            $('#uptime-stats').html(response.data.uptime);
                            $('#total-memory-stats').html(response.data.memory.total + ' MB');
                            $('#used-memory-stats').html(response.data.memory.used + ' MB (' + response.data.memory.used_percent + '%)');
                            $('#cpu-load-stats').html(response.data.cpu_load + '%');
                            $('#network-in-stats').html((response.data.network.in / 1024 / 1024 / 1024).toFixed(2) + ' GB');
                            $('#network-out-stats').html((response.data.network.out / 1024 / 1024 / 1024).toFixed(2) + ' GB');
                        } else {
                            $('#uptime-stats').html('Ошибка');
                        }
                    },
                    error: function() {
                        $('#uptime-stats').html('Ошибка');
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

    $cpu_percent = round($cpu_load[0] * 100, 2);
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

    $network_in = (int)shell_exec("cat /sys/class/net/eth0/statistics/rx_bytes");
    $network_out = (int)shell_exec("cat /sys/class/net/eth0/statistics/tx_bytes");

    $response = [
        'cpu_load' => $cpu_percent,
        'memory' => [
            'total' => (int)$memory_info[1],
            'used' => (int)$memory_info[2],
            'used_percent' => round(($memory_info[2] / $memory_info[1]) * 100, 2),
        ],
        'uptime' => trim($uptime),
        'network' => [
            'in' => $network_in,
            'out' => $network_out,
        ],
    ];

    
    wp_send_json_success($response);
    if (get_option('sysload_console_output', 0)) {
        error_log('Server Stats: ' . print_r($response, true)); // Output server stats to console
    }
    
}
add_action('wp_ajax_sysload_get_stats', 'sysload_get_stats');
add_action('wp_ajax_nopriv_sysload_get_stats', 'sysload_get_stats');

// Добавляем опции для включения/отключения графиков и отображения статистики в консоли
function sysload_settings_page() {
    ?>
    <div class="wrap">
        <h1>Настройки SysLoad</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('sysload_settings_group');
            do_settings_sections('sysload_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                <th scope="row">Включить графики</th>
                <td><input type="checkbox" name="sysload_enable_charts" value="1" <?php echo checked(1, get_option('sysload_enable_charts', 1), false); ?> /></td>
                </tr>
                <tr valign="top">
                <th scope="row">Отображать статистику в консоли</th>
                <td><input type="checkbox" name="sysload_show_in_console" value="1" <?php echo checked(1, get_option('sysload_show_in_console', 1), false); ?> /></td>
                </tr>
            </table>
            
    <tr valign="top">
    <th scope="row">Выводить статистику в консоль WordPress</th>
    <td><input type="checkbox" name="sysload_console_output" value="1" <?php echo checked(1, get_option('sysload_console_output', 0), false); ?> /></td>
    </tr>
    <?php submit_button(); ?>
    
        </form>
    </div>
    <?php
}

function sysload_register_settings() {
    
    register_setting('sysload_settings_group', 'sysload_enable_charts');
    register_setting('sysload_settings_group', 'sysload_console_output'); // Add new setting for console output
    
    register_setting('sysload_settings_group', 'sysload_show_in_console');
}

add_action('admin_menu', function() {
    add_options_page('SysLoad Settings', 'SysLoad', 'manage_options', 'sysload-settings', 'sysload_settings_page');
});
add_action('admin_init', 'sysload_register_settings');

// Добавляем виджет в консоль WordPress
function sysload_add_dashboard_widgets() {
    if (get_option('sysload_show_in_console')) {
        wp_add_dashboard_widget(
            'sysload_dashboard_widget',
            'Статистика сервера (SysLoad)',
            'sysload_display_dashboard_stats'
        );
    }
}

add_action('wp_dashboard_setup', 'sysload_add_dashboard_widgets');

// Функция отображения виджета в консоли WordPress
function sysload_display_dashboard_stats() {
    ?>
    <h3>Статистика сервера:</h3>
    <table>
        <tr>
            <th>Время работы сервера</th>
            <td id="uptime-stats-console">Загрузка...</td>
        </tr>
        <tr>
            <th>Общая память</th>
            <td id="total-memory-stats-console">Загрузка...</td>
        </tr>
        <tr>
            <th>Использованная память</th>
            <td id="used-memory-stats-console">Загрузка...</td>
        </tr>
        <tr>
            <th>Нагрузка процессора</th>
            <td id="cpu-load-stats-console">Загрузка...</td>
        </tr>
    </table>

    <script>
        jQuery(document).ready(function($) {
            function fetchServerStatsConsole() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: { action: 'sysload_get_stats' },
                    success: function(response) {
                        if (response.success) {
                            $('#uptime-stats-console').html(response.data.uptime);
                            $('#total-memory-stats-console').html(response.data.memory.total + ' MB');
                            $('#used-memory-stats-console').html(response.data.memory.used + ' MB (' + response.data.memory.used_percent + '%)');
                            $('#cpu-load-stats-console').html(response.data.cpu_load + '%');
                        } else {
                            $('#uptime-stats-console').html('Ошибка при получении данных.');
                        }
                    },
                    error: function() {
                        $('#uptime-stats-console').html('Ошибка при получении данных.');
                    }
                });
            }

            fetchServerStatsConsole();
            setInterval(fetchServerStatsConsole, 10000);
        });
    </script>
    <?php
}
?>
