document.addEventListener('DOMContentLoaded', function() {
  const ctx = document.getElementById('revenueChart');
  if (!ctx) return;

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: ['T2','T3','T4','T5','T6','T7','CN'],
      datasets: [{
        data: [1200000,1800000,900000,2500000,3000000,2700000,4000000],
        borderWidth: 2.2,
        borderColor: '#f5c518', // vàng đặc trưng VinCine
        backgroundColor: 'rgba(245,197,24,0.15)',
        fill: true,
        tension: 0.35,
        pointRadius: 4,
        pointBackgroundColor: '#e50914'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false, // 👈 quan trọng để không bị xẹp
      plugins: { legend: { display: false } },
      scales: {
        x: { display: false },
        y: { display: false }
      },
      animation: {
        duration: 900,
        easing: 'easeOutQuart' // 👈 animation mượt
      }
    }
  });
});
