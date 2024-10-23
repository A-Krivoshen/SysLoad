jQuery(document).ready(function($) {
    let cpuData = [];
    let memoryUsedData = [];
    let networkInData = [];
    let networkOutData = [];
    let totalMemory = 0;

    // Исправлены ID для совпадения с PHP
    const cpuChart = echarts.init(document.getElementById('cpu-load-container'));
    const memoryChart = echarts.init(document.getElementById('memory-usage-container'));
    const networkInChart = echarts.init(document.getElementById('network-in-container'));
    const networkOutChart = echarts.init(document.getElementById('network-out-container'));

    const commonChartOptions = {
        backgroundColor: '#000000', // Черный фон для всех графиков
        grid: {
            left: '15%',  // Увеличиваем отступ слева
            right: '15%', // Увеличиваем отступ справа
            top: '15%',   // Отступ сверху
            bottom: '20%' // Увеличиваем отступ снизу
        },
        xAxis: {
            type: 'category',
            data: [],
            axisLine: {
                lineStyle: {
                    color: '#ffffff' // Белый цвет линии оси X
                }
            },
            axisLabel: {
                color: '#ffffff' // Белый цвет подписей оси X
            }
        },
        yAxis: {
            type: 'value',
            axisLine: {
                lineStyle: {
                    color: '#ffffff' // Белый цвет линии оси Y
                }
            },
            axisLabel: {
                color: '#ffffff' // Белый цвет подписей оси Y
            }
        },
        series: [{
            data: [],
            type: 'line',
            smooth: true
        }]
    };

    const cpuChartOptions = $.extend(true, {}, commonChartOptions, {
        title: {
            text: 'Нагрузка процессора (%)',
            left: 'center',
            textStyle: {
                color: '#ffffff'
            }
        },
        yAxis: {
            type: 'value',
            min: 0,
            max: 100,
            axisLabel: {
                formatter: '{value} %'
            }
        },
        series: [{
            data: [],
            type: 'line',
            smooth: true,
            lineStyle: {
                color: '#ff5733' // Красный график
            }
        }]
    });

    const memoryChartOptions = $.extend(true, {}, commonChartOptions, {
        title: {
            text: 'Использование памяти (MB)',
            left: 'center',
            textStyle: {
                color: '#ffffff'
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: '{value} MB'
            }
        },
        series: [{
            data: [],
            type: 'line',
            smooth: true,
            lineStyle: {
                color: '#33ff57' // Зеленый график
            }
        }]
    });

    const networkInChartOptions = $.extend(true, {}, commonChartOptions, {
        title: {
            text: 'Входящий трафик (GB)',
            left: 'center',
            textStyle: {
                color: '#ffffff'
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: '{value} GB'
            }
        },
        series: [{
            data: [],
            type: 'line',
            smooth: true,
            lineStyle: {
                color: '#3398ff' // Синий график
            }
        }]
    });

    const networkOutChartOptions = $.extend(true, {}, commonChartOptions, {
        title: {
            text: 'Исходящий трафик (GB)',
            left: 'center',
            textStyle: {
                color: '#ffffff'
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: '{value} GB'
            }
        },
        series: [{
            data: [],
            type: 'line',
            smooth: true,
            lineStyle: {
                color: '#ff33ff' // Розовый график
            }
        }]
    });

    cpuChart.setOption(cpuChartOptions);
    memoryChart.setOption(memoryChartOptions);
    networkInChart.setOption(networkInChartOptions);
    networkOutChart.setOption(networkOutChartOptions);

    function fetchServerStats() {
        $.ajax({
            url: sysload_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'sysload_get_stats',
            },
            success: function(response) {
                if (response.success) {
                    const timestamp = new Date().toLocaleTimeString();

                    cpuData.push(response.data.cpu_load);
                    memoryUsedData.push(response.data.memory.used);
                    networkInData.push(response.data.network.in);
                    networkOutData.push(response.data.network.out);

                    totalMemory = response.data.memory.total;
                    memoryChartOptions.yAxis.max = totalMemory;

                    if (cpuData.length > 10) {
                        cpuData.shift();
                        memoryUsedData.shift();
                        networkInData.shift();
                        networkOutData.shift();
                        cpuChartOptions.xAxis.data.shift();
                        memoryChartOptions.xAxis.data.shift();
                        networkInChartOptions.xAxis.data.shift();
                        networkOutChartOptions.xAxis.data.shift();
                    }

                    cpuChartOptions.xAxis.data.push(timestamp);
                    memoryChartOptions.xAxis.data.push(timestamp);
                    networkInChartOptions.xAxis.data.push(timestamp);
                    networkOutChartOptions.xAxis.data.push(timestamp);

                    cpuChartOptions.series[0].data = cpuData;
                    memoryChartOptions.series[0].data = memoryUsedData;
                    networkInChartOptions.series[0].data = networkInData;
                    networkOutChartOptions.series[0].data = networkOutData;

                    cpuChart.setOption(cpuChartOptions);
                    memoryChart.setOption(memoryChartOptions);
                    networkInChart.setOption(networkInChartOptions);
                    networkOutChart.setOption(networkOutChartOptions);

                    $('#uptime-stats').html(response.data.uptime);
                    $('#total-memory-stats').html(response.data.memory.total + ' MB');
                    $('#used-memory-stats').html(response.data.memory.used + ' MB (' + response.data.memory.used_percent + '%)');
                    $('#cpu-load-stats').html(response.data.cpu_load + '%');
                    $('#network-in-stats').html(response.data.network.in + ' GB');
                    $('#network-out-stats').html(response.data.network.out + ' GB');
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
