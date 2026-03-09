<?php
require_once 'function.php';

// Récupérer les mesures
$mesures = get_mesures($conn);
$chartData = prepareChartData($mesures);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Mesures Arduino</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h1>Température et Distance</h1>
    <canvas id="graph" width="800" height="400"></canvas>

    <script>
        const ctx = document.getElementById('graph').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartData['dates']); ?>,
                datasets: [
                    {
                        label: 'Température (°C)',
                        data: <?php echo json_encode($chartData['temps']); ?>,
                        borderColor: 'red',
                        fill: false,
                    },
                    {
                        label: 'Distance (cm)',
                        data: <?php echo json_encode($chartData['distances']); ?>,
                        borderColor: 'blue',
                        fill: false,
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: { display: true, title: { display: true, text: 'Temps' } },
                    y: { display: true, title: { display: true, text: 'Valeur' } }
                }
            }
        });
    </script>
</body>
</html>