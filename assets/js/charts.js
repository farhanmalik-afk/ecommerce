/* =========================================================
   Chart settings shared by all charts
   ========================================================= */
const GREEN = "#4caf50";
const PRIMARY_COLOR = "#5e72e4";

Chart.defaults.font.family = "Inter, system-ui, sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = "#7b809a";

const baseOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { display: false } },
    y: { grid: { color: "#eef0f2", drawBorder: false }, border: { display: false } },
  },
};

/* Helper: builds a line chart so line charts do not repeat code */
function createLineChart(canvasId, labels, data, lineColor = GREEN) {
  new Chart(document.getElementById(canvasId), {
    type: "line",
    data: {
      labels,
      datasets: [{
        data,
        borderColor: lineColor,
        backgroundColor: lineColor,
        borderWidth: 2,
        tension: 0.35,
        pointRadius: 3,
      }],
    },
    options: baseOptions,
  });
}

/* =========================================================
   Charts Configuration
   ========================================================= */

// 1. Website Views (bar chart)
if (document.getElementById("viewsChart")) {
  new Chart(document.getElementById("viewsChart"), {
    type: "bar",
    data: {
      labels: ["M", "T", "W", "T", "F", "S", "S"],
      datasets: [{
        data: [50, 45, 22, 28, 50, 60, 76],
        backgroundColor: GREEN,
        borderRadius: 4,
        barPercentage: 0.7,
      }],
    },
    options: {
      ...baseOptions,
      scales: { ...baseOptions.scales, y: { ...baseOptions.scales.y, min: 0, max: 100 } },
    },
  });
}

// 2. Daily Sales (line chart)
if (document.getElementById("salesChart")) {
  createLineChart(
    "salesChart",
    ["J", "F", "M", "A", "M", "J", "J", "A", "S", "O", "N", "D"],
    [120, 230, 130, 440, 250, 360, 270, 180, 90, 300, 310, 220],
    GREEN
  );
}

// 3. Completed Tasks (line chart)
if (document.getElementById("tasksChart")) {
  createLineChart(
    "tasksChart",
    ["Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
    [50, 40, 300, 220, 500, 250, 400, 230, 500],
    GREEN
  );
}

// 4. Customer Growth / Registrations (Naya Chart Add kiya gaya hai)
if (document.getElementById("customerGrowthChart")) {
  createLineChart(
    "customerGrowthChart",
    ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
    [10, 25, 40, 35, 60, 85, 110, 95, 130, 160, 190, 230],
    PRIMARY_COLOR
  );
}