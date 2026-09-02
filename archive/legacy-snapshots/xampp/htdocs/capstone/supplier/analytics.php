<script>
// Change period
function changePeriod() {
    const period = document.getElementById('periodSelect').value;
    window.location.href = `analytics.php?period=${period}`;
}

// Sales Trend Chart — Professional, Clean, Business-Focused
const ctx = document.getElementById('salesChart');
<?php
// Extract real data from $sales_analytics['trend']
$labels = [];
$data = [];
$tooltips = [];

if (!empty($sales_analytics['trend'])) {
    foreach ($sales_analytics['trend'] as $point) {
        $labels[] = $point['label'] ?? '';
        $data[] = floatval($point['revenue'] ?? 0);
        $tooltips[] = '₱' . number_format($point['revenue'] ?? 0, 2);
    }
} else {
    $labels = ['No Data'];
    $data = [0];
    $tooltips = ['₱0.00'];
}
?>

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labels); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode($data); ?>,
            borderColor: '#4361ee',
            backgroundColor: 'rgba(67, 97, 238, 0.1)',
            borderWidth: 2.5,
            pointRadius: 5,
            pointHoverRadius: 7,
            pointBackgroundColor: '#4361ee',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#fff',
                bodyColor: '#fff',
                cornerRadius: 8,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return 'Revenue: ₱' + parseFloat(context.parsed.y).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                    title: function(context) {
                        return context[0].label;
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: '#6c757d' }
            },
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0, 0, 0, 0.05)' },
                ticks: {
                    color: '#6c757d',
                    callback: function(value) {
                        // Format currency properly
                        return '₱' + parseFloat(value).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                    }
                }
            }
        }
    }
});