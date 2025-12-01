// File: assets/js/pc-admin-charts.js
(function($){
    'use strict';

    var ajax = (window.pc_admin && window.pc_admin.ajax_url) ? window.pc_admin.ajax_url : '/wp-admin/admin-ajax.php';
    var nonce = (window.pc_admin && window.pc_admin.nonce) ? window.pc_admin.nonce : '';

    $(function(){
        var ctx = document.getElementById('pc-earnings-chart');
        if (!ctx) return;

        // Request earnings data
        $.post(ajax, { action: 'pc_admin_get_earnings', nonce: nonce })
            .done(function(res){
                if (!res.success) {
                    console.error('Failed to load earnings', res);
                    return;
                }
                var labels = res.data.labels || [];
                var data = res.data.data || [];

                // Create chart
                var chart = new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Earnings',
                            data: data,
                            fill: true,
                            tension: 0.3,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: { mode: 'index', intersect: false }
                        },
                        scales: {
                            x: {
                                display: true,
                            },
                            y: {
                                display: true,
                                beginAtZero: true
                            }
                        }
                    }
                });
            })
            .fail(function(err){
                console.error('Network error fetching earnings data', err);
            });
    });
})(jQuery);
