import Chart from 'chart.js/auto';

const palette = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d'];

document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
    const config = JSON.parse(canvas.dataset.chart);
    const isPie = config.chart_type === 'pie';

    new Chart(canvas, {
        type: config.chart_type,
        data: {
            labels: config.labels,
            datasets: config.series.map((series, index) => ({
                label: series.name,
                data: series.data,
                backgroundColor: isPie
                    ? config.labels.map((_, colorIndex) => palette[colorIndex % palette.length])
                    : palette[index % palette.length],
                borderColor: isPie ? undefined : palette[index % palette.length],
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
        },
    });
});
