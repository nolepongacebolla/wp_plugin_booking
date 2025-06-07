jQuery(function($){
    if(typeof wpbStats === 'undefined') return;
    var ctx1 = document.getElementById('wpb-status-chart');
    if(ctx1){
        new Chart(ctx1, {
            type: 'pie',
            data: {
                labels: wpbStats.statusLabels,
                datasets: [{
                    data: wpbStats.statusCounts,
                    backgroundColor: ['#e74c3c','#f1c40f','#2ecc71','#3498db'],
                }]
            }
        });
    }
    var ctx2 = document.getElementById('wpb-revenue-chart');
    if(ctx2){
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: wpbStats.monthLabels,
                datasets: [{
                    label: wpbStats.revenueLabel,
                    data: wpbStats.monthRevenue,
                    borderColor: '#e67e22',
                    backgroundColor: 'rgba(230,126,34,0.2)',
                    fill: true
                }]
            },
            options:{
                scales:{
                    y:{ beginAtZero:true }
                }
            }
        });
    }
});
