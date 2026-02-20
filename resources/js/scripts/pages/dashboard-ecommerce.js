/*=========================================================================================
    File Name: dashboard-ecommerce.js
    Description: dashboard ecommerce page content with Apexchart Examples
    ----------------------------------------------------------------------------------------
    Item Name: Vuexy  - Vuejs, HTML & Laravel Admin Dashboard Template
    Author: PIXINVENT
    Author URL: http://www.themeforest.net/user/pixinvent
==========================================================================================*/

$(window).on('load', function () {
  'use strict';

  var $barColor = '#f3f3f3';
  var $trackBgColor = '#EBEBEB';
  var $textMutedColor = '#b9b9c3';
  var $budgetStrokeColor2 = '#dcdae3';
  var $goalStrokeColor2 = '#51e5a8';
  var $strokeColor = '#ebe9f1';
  var $textHeadingColor = '#5e5873';
  var $earningsStrokeColor2 = '#28c76f66';
  var $earningsStrokeColor3 = '#28c76f33';

  var $statisticsOrderChart = document.querySelector('#statistics-order-chart');
  var $statisticsProfitChart = document.querySelector('#statistics-profit-chart');
  var $earningsChart = document.querySelector('#earnings-chart');
  var $revenueReportChart = document.querySelector('#revenue-report-chart');
  var $budgetChart = document.querySelector('#budget-chart');
  var $browserStateChartPrimary = document.querySelector('#browser-state-chart-primary');
  var $browserStateChartWarning = document.querySelector('#browser-state-chart-warning');
  var $browserStateChartSecondary = document.querySelector('#browser-state-chart-secondary');
  var $browserStateChartInfo = document.querySelector('#browser-state-chart-info');
  var $browserStateChartDanger = document.querySelector('#browser-state-chart-danger');
  var $goalOverviewChart = document.querySelector('#goal-overview-radial-bar-chart');

  var statisticsOrderChartOptions;
  var statisticsProfitChartOptions;
  var earningsChartOptions;
  var revenueReportChartOptions;
  var budgetChartOptions;
  var browserStatePrimaryChartOptions;
  var browserStateWarningChartOptions;
  var browserStateSecondaryChartOptions;
  var browserStateInfoChartOptions;
  var browserStateDangerChartOptions;
  var goalOverviewChartOptions;

  var statisticsOrderChart;
  var statisticsProfitChart;
  var earningsChart;
  var revenueReportChart;
  var budgetChart;
  var browserStatePrimaryChart;
  var browserStateDangerChart;
  var browserStateInfoChart;
  var browserStateSecondaryChart;
  var browserStateWarningChart;
  var goalOverviewChart;
  var isRtl = $('html').attr('data-textdirection') === 'rtl';

  // On load Toast
  setTimeout(function () {
    toastr['success'](
      'Uspješna prijava na eNalog.app!',
      '👋 Dobrodošli!',
      {
        closeButton: true,
        tapToDismiss: false,
        rtl: isRtl
      }
    );
  }, 2000);

  //------------ Statistics Bar Chart ------------
  //----------------------------------------------
  statisticsOrderChartOptions = {
    chart: {
      height: 70,
      type: 'bar',
      stacked: true,
      toolbar: {
        show: false
      }
    },
    grid: {
      show: false,
      padding: {
        left: 0,
        right: 0,
        top: -15,
        bottom: -15
      }
    },
    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: '20%',
        startingShape: 'rounded',
        colors: {
          backgroundBarColors: [$barColor, $barColor, $barColor, $barColor, $barColor],
          backgroundBarRadius: 5
        }
      }
    },
    legend: {
      show: false
    },
    dataLabels: {
      enabled: false
    },
    colors: [window.colors.solid.warning],
    series: [
      {
        name: '2020',
        data: [45, 85, 65, 45, 65]
      }
    ],
    xaxis: {
      labels: {
        show: false
      },
      axisBorder: {
        show: false
      },
      axisTicks: {
        show: false
      }
    },
    yaxis: {
      show: false
    },
    tooltip: {
      x: {
        show: false
      }
    }
  };
  statisticsOrderChart = new ApexCharts($statisticsOrderChart, statisticsOrderChartOptions);
  statisticsOrderChart.render();

  //------------ Statistics Line Chart ------------
  //-----------------------------------------------
  statisticsProfitChartOptions = {
    chart: {
      height: 70,
      type: 'line',
      toolbar: {
        show: false
      },
      zoom: {
        enabled: false
      }
    },
    grid: {
      borderColor: $trackBgColor,
      strokeDashArray: 5,
      xaxis: {
        lines: {
          show: true
        }
      },
      yaxis: {
        lines: {
          show: false
        }
      },
      padding: {
        top: -30,
        bottom: -10
      }
    },
    stroke: {
      width: 3
    },
    colors: [window.colors.solid.info],
    series: [
      {
        data: [0, 20, 5, 30, 15, 45]
      }
    ],
    markers: {
      size: 2,
      colors: window.colors.solid.info,
      strokeColors: window.colors.solid.info,
      strokeWidth: 2,
      strokeOpacity: 1,
      strokeDashArray: 0,
      fillOpacity: 1,
      discrete: [
        {
          seriesIndex: 0,
          dataPointIndex: 5,
          fillColor: '#ffffff',
          strokeColor: window.colors.solid.info,
          size: 5
        }
      ],
      shape: 'circle',
      radius: 2,
      hover: {
        size: 3
      }
    },
    xaxis: {
      labels: {
        show: true,
        style: {
          fontSize: '0px'
        }
      },
      axisBorder: {
        show: false
      },
      axisTicks: {
        show: false
      }
    },
    yaxis: {
      show: false
    },
    tooltip: {
      x: {
        show: false
      }
    }
  };
  statisticsProfitChart = new ApexCharts($statisticsProfitChart, statisticsProfitChartOptions);
  statisticsProfitChart.render();

  //--------------- Earnings Chart ---------------
  //----------------------------------------------
  earningsChartOptions = {
    chart: {
      type: 'donut',
      height: 120,
      toolbar: {
        show: false
      }
    },
    dataLabels: {
      enabled: false
    },
    series: [53, 16, 31],
    legend: { show: false },
    comparedResult: [2, -3, 8],
    labels: ['Proizvodnja', 'Usluge', 'Mašine'],
    stroke: { width: 0 },
    colors: [$earningsStrokeColor2, $earningsStrokeColor3, window.colors.solid.success],
    grid: {
      padding: {
        right: -20,
        bottom: -8,
        left: -20
      }
    },
    plotOptions: {
      pie: {
        startAngle: -10,
        donut: {
          labels: {
            show: true,
            name: {
              offsetY: 15
            },
            value: {
              offsetY: -15,
              formatter: function (val) {
                return parseInt(val) + '%';
              }
            },
            total: {
              show: true,
              offsetY: 15,
              label: 'Proizvodnja',
              formatter: function (w) {
                return '53%';
              }
            }
          }
        }
      }
    },
    responsive: [
      {
        breakpoint: 1325,
        options: {
          chart: {
            height: 100
          }
        }
      },
      {
        breakpoint: 1200,
        options: {
          chart: {
            height: 120
          }
        }
      },
      {
        breakpoint: 1045,
        options: {
          chart: {
            height: 100
          }
        }
      },
      {
        breakpoint: 992,
        options: {
          chart: {
            height: 120
          }
        }
      }
    ]
  };
  earningsChart = new ApexCharts($earningsChart, earningsChartOptions);
  earningsChart.render();

  //------------ Revenue Report Chart ------------
  //----------------------------------------------
  var dashboardRoot = document.querySelector('#dashboard-ecommerce');
  var workOrdersYearlySummaryApi =
    (dashboardRoot && dashboardRoot.getAttribute('data-work-orders-yearly-summary-url')) ||
    assetPath + 'api/work-orders-yearly-summary';
  var dashboardCurrentYear = Number(
    (dashboardRoot && dashboardRoot.getAttribute('data-current-year')) || new Date().getFullYear()
  );
  var dashboardDefaultCompareYear = Number(
    (dashboardRoot && dashboardRoot.getAttribute('data-default-compare-year')) || dashboardCurrentYear - 1
  );
  var $reportLoader = $('#dashboard-report-loader');

  if (!Number.isFinite(dashboardDefaultCompareYear) || dashboardDefaultCompareYear >= dashboardCurrentYear) {
    dashboardDefaultCompareYear = dashboardCurrentYear - 1;
  }
  if (dashboardDefaultCompareYear < 2022) {
    dashboardDefaultCompareYear = 2022;
  }

  var reportMonthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];
  var reportState = {
    currentYear: dashboardCurrentYear,
    compareYear: dashboardDefaultCompareYear,
    currentSeries: new Array(12).fill(0),
    compareSeries: new Array(12).fill(0),
    currentTotal: 0,
    compareTotal: 0
  };

  function formatCountNumber(value) {
    var normalized = Number(value);

    if (!Number.isFinite(normalized)) {
      return '0';
    }

    return new Intl.NumberFormat('bs-BA', { maximumFractionDigits: 0 }).format(Math.round(normalized));
  }

  function emptyMonthlySeries() {
    return new Array(12).fill(0);
  }

  function normalizeSeries(values) {
    var fallback = emptyMonthlySeries();

    if (!Array.isArray(values)) {
      return fallback;
    }

    return fallback.map(function (_, index) {
      return Number(values[index]) || 0;
    });
  }

  function calculateYearTotal(values) {
    return (values || []).reduce(function (sum, value) {
      return sum + (Number(value) || 0);
    }, 0);
  }

  function updateReportSummary() {
    var compareDiff = reportState.currentTotal - reportState.compareTotal;
    var compareDiffPercent =
      reportState.compareTotal > 0 ? ((compareDiff / reportState.compareTotal) * 100).toFixed(1) + '%' : 'n/a';
    var signedDiff = (compareDiff > 0 ? '+' : '') + formatCountNumber(compareDiff);
    var deltaText = 'Razlika: ' + signedDiff + ' (' + compareDiffPercent + ')';
    var $deltaBadge = $('#work-orders-delta');

    $('#dashboard-report-year-toggle').text(reportState.compareYear);
    $('#revenue-current-label').text(String(reportState.currentYear));
    $('#revenue-compare-label').text(String(reportState.compareYear));
    $('#work-orders-total-primary').text(formatCountNumber(reportState.currentTotal) + ' naloga');
    $('#work-orders-total-subtitle').text('Teku\u0107a godina: ' + reportState.currentYear);
    $('#work-orders-total-compare-label').text('Pore\u0111enje sa ' + reportState.compareYear + ':');
    $('#work-orders-total-compare').text(formatCountNumber(reportState.compareTotal) + ' naloga');

    $deltaBadge
      .removeClass('badge-light-success badge-light-danger badge-light-warning')
      .addClass(compareDiff > 0 ? 'badge-light-success' : compareDiff < 0 ? 'badge-light-danger' : 'badge-light-warning')
      .text(deltaText);

    $('#dashboard-report-year-menu .dropdown-item').removeClass('active');
    $('#dashboard-report-year-menu .dropdown-item[data-year="' + reportState.compareYear + '"]').addClass('active');
  }

  function updateReportCharts() {
    var compareSeriesNegative = reportState.compareSeries.map(function (value) {
      return -Math.abs(Number(value) || 0);
    });

    reportState.currentTotal = calculateYearTotal(reportState.currentSeries);
    reportState.compareTotal = calculateYearTotal(reportState.compareSeries);

    revenueReportChart.updateSeries(
      [
        {
          name: String(reportState.currentYear),
          data: reportState.currentSeries
        },
        {
          name: String(reportState.compareYear),
          data: compareSeriesNegative
        }
      ],
      true
    );

    budgetChart.updateSeries(
      [
        {
          name: String(reportState.currentYear),
          data: reportState.currentSeries
        },
        {
          name: String(reportState.compareYear),
          data: reportState.compareSeries
        }
      ],
      true
    );

    updateReportSummary();
  }

  function setReportLoadingState(isLoading) {
    $('#dashboard-report-year-toggle').prop('disabled', !!isLoading);

    if ($reportLoader.length) {
      $reportLoader.toggleClass('is-hidden', !isLoading);
    }
  }

  function fetchYearSummary(compareYear) {
    return $.ajax({
      url: workOrdersYearlySummaryApi,
      method: 'GET',
      dataType: 'json',
      data: {
        current_year: reportState.currentYear,
        compare_year: compareYear
      }
    }).then(
      function (response) {
        var payload = response && response.data ? response.data : {};
        var currentYear = Number(payload.current_year);
        var compareYearValue = Number(payload.compare_year);
        var series = payload && payload.series ? payload.series : {};

        return {
          currentYear: Number.isFinite(currentYear) ? currentYear : reportState.currentYear,
          compareYear: Number.isFinite(compareYearValue) ? compareYearValue : compareYear,
          currentSeries: normalizeSeries(series.current),
          compareSeries: normalizeSeries(series.compare)
        };
      },
      function () {
        return {
          currentYear: reportState.currentYear,
          compareYear: compareYear,
          currentSeries: emptyMonthlySeries(),
          compareSeries: emptyMonthlySeries()
        };
      }
    );
  }

  function loadReportData(compareYear) {
    setReportLoadingState(true);

    return fetchYearSummary(compareYear).then(function (summary) {
      reportState.currentYear = summary.currentYear;
      reportState.compareYear = summary.compareYear;
      reportState.currentSeries = summary.currentSeries;
      reportState.compareSeries = summary.compareSeries;
      updateReportCharts();
      setReportLoadingState(false);

      return summary;
    });
  }

  revenueReportChartOptions = {
    chart: {
      height: 230,
      stacked: true,
      type: 'bar',
      toolbar: { show: false }
    },
    plotOptions: {
      bar: {
        columnWidth: '17%',
        endingShape: 'rounded'
      },
      distributed: true
    },
    colors: [window.colors.solid.warning, $budgetStrokeColor2],
    series: [
      {
        name: String(reportState.currentYear),
        data: reportState.currentSeries
      },
      {
        name: String(reportState.compareYear),
        data: reportState.compareSeries
      }
    ],
    dataLabels: {
      enabled: false
    },
    legend: {
      show: false
    },
    grid: {
      padding: {
        top: -20,
        bottom: -10
      },
      yaxis: {
        lines: { show: false }
      }
    },
    xaxis: {
      categories: reportMonthLabels,
      labels: {
        style: {
          colors: $textMutedColor,
          fontSize: '0.86rem'
        }
      },
      axisTicks: {
        show: false
      },
      axisBorder: {
        show: false
      }
    },
    yaxis: {
      labels: {
        style: {
          colors: $textMutedColor,
          fontSize: '0.86rem'
        }
      }
    },
    tooltip: {
      shared: true,
      intersect: false,
      y: {
        formatter: function (value) {
          return formatCountNumber(Math.abs(value)) + ' naloga';
        }
      }
    }
  };
  revenueReportChart = new ApexCharts($revenueReportChart, revenueReportChartOptions);
  revenueReportChart.render();

  //---------------- Budget Chart ----------------
  //----------------------------------------------
  budgetChartOptions = {
    chart: {
      height: 80,
      toolbar: { show: false },
      zoom: { enabled: false },
      type: 'line',
      sparkline: { enabled: true }
    },
    stroke: {
      curve: 'smooth',
      dashArray: [0, 5],
      width: [2]
    },
    xaxis: {
      categories: reportMonthLabels
    },
    colors: [window.colors.solid.warning, $budgetStrokeColor2],
    series: [
      {
        name: String(reportState.currentYear),
        data: reportState.currentSeries
      },
      {
        name: String(reportState.compareYear),
        data: reportState.compareSeries
      }
    ],
    tooltip: {
      enabled: true,
      shared: true,
      intersect: false,
      x: {
        formatter: function (value, context) {
          var monthIndex = context && typeof context.dataPointIndex === 'number' ? context.dataPointIndex : -1;
          return monthIndex >= 0 && monthIndex < reportMonthLabels.length ? reportMonthLabels[monthIndex] : value;
        }
      },
      y: {
        formatter: function (value) {
          return formatCountNumber(value) + ' naloga';
        }
      }
    }
  };
  budgetChart = new ApexCharts($budgetChart, budgetChartOptions);
  budgetChart.render();

  $('#dashboard-report-year-menu').on('click', '.dropdown-item[data-year]', function (event) {
    event.preventDefault();

    var selectedYear = Number($(this).data('year'));

    if (!Number.isFinite(selectedYear) || selectedYear >= reportState.currentYear || selectedYear < 2022) {
      return;
    }

    if (selectedYear === reportState.compareYear) {
      return;
    }

    loadReportData(selectedYear);
  });

  loadReportData(reportState.compareYear);

  //------------ Browser State Charts ------------
  //----------------------------------------------

  // State Primary Chart
  browserStatePrimaryChartOptions = {
    chart: {
      height: 30,
      width: 30,
      type: 'radialBar'
    },
    grid: {
      show: false,
      padding: {
        left: -15,
        right: -15,
        top: -12,
        bottom: -15
      }
    },
    colors: [window.colors.solid.primary],
    series: [54.4],
    plotOptions: {
      radialBar: {
        hollow: {
          size: '22%'
        },
        track: {
          background: $trackBgColor
        },
        dataLabels: {
          showOn: 'always',
          name: {
            show: false
          },
          value: {
            show: false
          }
        }
      }
    },
    stroke: {
      lineCap: 'round'
    }
  };
  browserStatePrimaryChart = new ApexCharts($browserStateChartPrimary, browserStatePrimaryChartOptions);
  browserStatePrimaryChart.render();

  // State Warning Chart
  browserStateWarningChartOptions = {
    chart: {
      height: 30,
      width: 30,
      type: 'radialBar'
    },
    grid: {
      show: false,
      padding: {
        left: -15,
        right: -15,
        top: -12,
        bottom: -15
      }
    },
    colors: [window.colors.solid.warning],
    series: [6.1],
    plotOptions: {
      radialBar: {
        hollow: {
          size: '22%'
        },
        track: {
          background: $trackBgColor
        },
        dataLabels: {
          showOn: 'always',
          name: {
            show: false
          },
          value: {
            show: false
          }
        }
      }
    },
    stroke: {
      lineCap: 'round'
    }
  };
  browserStateWarningChart = new ApexCharts($browserStateChartWarning, browserStateWarningChartOptions);
  browserStateWarningChart.render();

  // State Secondary Chart 1
  browserStateSecondaryChartOptions = {
    chart: {
      height: 30,
      width: 30,
      type: 'radialBar'
    },
    grid: {
      show: false,
      padding: {
        left: -15,
        right: -15,
        top: -12,
        bottom: -15
      }
    },
    colors: [window.colors.solid.secondary],
    series: [14.6],
    plotOptions: {
      radialBar: {
        hollow: {
          size: '22%'
        },
        track: {
          background: $trackBgColor
        },
        dataLabels: {
          showOn: 'always',
          name: {
            show: false
          },
          value: {
            show: false
          }
        }
      }
    },
    stroke: {
      lineCap: 'round'
    }
  };
  browserStateSecondaryChart = new ApexCharts($browserStateChartSecondary, browserStateSecondaryChartOptions);
  browserStateSecondaryChart.render();

  // State Info Chart
  browserStateInfoChartOptions = {
    chart: {
      height: 30,
      width: 30,
      type: 'radialBar'
    },
    grid: {
      show: false,
      padding: {
        left: -15,
        right: -15,
        top: -12,
        bottom: -15
      }
    },
    colors: [window.colors.solid.info],
    series: [4.2],
    plotOptions: {
      radialBar: {
        hollow: {
          size: '22%'
        },
        track: {
          background: $trackBgColor
        },
        dataLabels: {
          showOn: 'always',
          name: {
            show: false
          },
          value: {
            show: false
          }
        }
      }
    },
    stroke: {
      lineCap: 'round'
    }
  };
  browserStateInfoChart = new ApexCharts($browserStateChartInfo, browserStateInfoChartOptions);
  browserStateInfoChart.render();

  // State Danger Chart
  browserStateDangerChartOptions = {
    chart: {
      height: 30,
      width: 30,
      type: 'radialBar'
    },
    grid: {
      show: false,
      padding: {
        left: -15,
        right: -15,
        top: -12,
        bottom: -15
      }
    },
    colors: [window.colors.solid.danger],
    series: [8.4],
    plotOptions: {
      radialBar: {
        hollow: {
          size: '22%'
        },
        track: {
          background: $trackBgColor
        },
        dataLabels: {
          showOn: 'always',
          name: {
            show: false
          },
          value: {
            show: false
          }
        }
      }
    },
    stroke: {
      lineCap: 'round'
    }
  };
  browserStateDangerChart = new ApexCharts($browserStateChartDanger, browserStateDangerChartOptions);
  browserStateDangerChart.render();

  //------------ Goal Overview Chart ------------
  //---------------------------------------------
  goalOverviewChartOptions = {
    chart: {
      height: 245,
      type: 'radialBar',
      sparkline: {
        enabled: true
      },
      dropShadow: {
        enabled: true,
        blur: 3,
        left: 1,
        top: 1,
        opacity: 0.1
      }
    },
    colors: [$goalStrokeColor2],
    plotOptions: {
      radialBar: {
        offsetY: -10,
        startAngle: -150,
        endAngle: 150,
        hollow: {
          size: '77%'
        },
        track: {
          background: $strokeColor,
          strokeWidth: '50%'
        },
        dataLabels: {
          name: {
            show: false
          },
          value: {
            color: $textHeadingColor,
            fontSize: '2.86rem',
            fontWeight: '600'
          }
        }
      }
    },
    fill: {
      type: 'gradient',
      gradient: {
        shade: 'dark',
        type: 'horizontal',
        shadeIntensity: 0.5,
        gradientToColors: [window.colors.solid.success],
        inverseColors: true,
        opacityFrom: 1,
        opacityTo: 1,
        stops: [0, 100]
      }
    },
    series: [83],
    stroke: {
      lineCap: 'round'
    },
    grid: {
      padding: {
        bottom: 30
      }
    }
  };
  goalOverviewChart = new ApexCharts($goalOverviewChart, goalOverviewChartOptions);
  goalOverviewChart.render();
});
