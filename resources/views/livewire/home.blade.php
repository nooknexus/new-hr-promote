<div class="dashboard space-y-6">
    <!-- Dashboard Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
    </div>

    <!-- Content Row -->
    <div class="row mb-4"> <!-- Added margin-bottom (mb-4) for spacing between rows -->
        <!-- Area Chart -->
        <div class="col-xl-12 col-lg-14">
            <div class="card shadow">
                <!-- Card Header -->
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">ข้อมูลเงินเดือนล่าสุด</h6>
                </div>
                <!-- Card Body -->
                <div class="card-body">
                    <div class="dashboard-metrics"> <!-- Added row and justify-content-center -->
                        <!-- เงินเดือนปัจจุบัน Card -->
                        <div class="col-xl-2 col-md-5 mb-4 mx-2"> <!-- Reduced width by changing from col-xl-3 to col-xl-2 and added margin (mx-2) -->
                            <div class="card border-left-primary shadow py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                เงินเดือนปัจจุบัน</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">฿{{ number_format($currentSalary ,0) }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- เงินเพิ่มพิเศษ Card -->
                        <div class="col-xl-2 col-md-5 mb-4 mx-2"> <!-- Reduced width and added margin -->
                            <div class="card border-left-success shadow py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                เงินเพิ่มพิเศษ</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">฿{{ number_format($specialAllowance, 0) }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- เงินที่หัก Card -->
                        <div class="col-xl-2 col-md-5 mb-4 mx-2"> <!-- Reduced width and added margin -->
                            <div class="card border-left-info shadow py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                เงินที่หัก
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">฿{{ number_format($totalDeductions, 0) }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- รายรับสุทธิ Card -->
                        <div class="col-xl-2 col-md-5 mb-4 mx-2"> <!-- Reduced width and added margin -->
                            <div class="card border-left-warning shadow py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                รายรับสุทธิ</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">฿{{ number_format($netIncome, 0) }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-coins fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Row for Tax Information -->
    <div class="row mb-4">
        <div class="col-xl-12 col-lg-14">
            <div class="card shadow">
                <!-- Card Header -->
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">ข้อมูลภาษีปีที่ผ่านมา</h6>
                </div>
                <!-- Card Body -->
                <div class="card-body">
                    <div class="dashboard-metrics dashboard-metrics--tax">
                        <!-- เงินได้ทั้งสิ้น Card -->
                        <div class="col-xl-2 col-md-5 mb-4 mx-2">
                            <div class="card border-left-primary shadow py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                เงินได้ทั้งสิ้น</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">฿{{ number_format((float)($totalIncome ?? 0), 0) }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ภาษีที่ถูกหัก Card -->
                        <div class="col-xl-2 col-md-5 mb-4 mx-2">
                            <div class="card border-left-danger shadow py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                ภาษีที่ถูกหัก</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">฿{{ number_format((float)($taxWithheld ?? 0), 0) }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $leaveSummary = $currentFiscalLeaveSummary ?? [
            'fiscal_year' => null,
            'total_days' => 0.0,
            'totals' => ['ลาพักผ่อน' => 0.0, 'ลาป่วย' => 0.0, 'ลากิจ' => 0.0],
        ];
        $formatLeaveDays = static fn (float $days): string => rtrim(rtrim(number_format($days, 2), '0'), '.');
    @endphp
    <section class="card shadow" data-dashboard-current-leave>
        <div class="card-header py-3">
            <h2 class="m-0 text-sm font-bold text-brand-600">ประวัติวันลาปี {{ $leaveSummary['fiscal_year'] ?? 'ปัจจุบัน' }}</h2>
        </div>
        <div class="card-body grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['ลาพักผ่อน', 'fa-umbrella-beach', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
                ['ลาป่วย', 'fa-notes-medical', 'border-sky-200 bg-sky-50 text-sky-800'],
                ['ลากิจ', 'fa-briefcase', 'border-violet-200 bg-violet-50 text-violet-800'],
            ] as [$type, $icon, $style])
                <div class="rounded-xl border p-4 {{ $style }}">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-semibold">{{ $type }}</p>
                        <i class="fas {{ $icon }} text-lg opacity-70" aria-hidden="true"></i>
                    </div>
                    <p class="mt-4 text-2xl font-bold tabular-nums">{{ $formatLeaveDays((float) ($leaveSummary['totals'][$type] ?? 0)) }} <span class="text-sm font-medium">วัน</span></p>
                </div>
            @endforeach
            <div class="rounded-xl border border-brand-200 bg-brand-50 p-4 text-brand-800">
                <div class="flex items-start justify-between gap-3"><p class="text-sm font-semibold">รวมวันลา</p><i class="fas fa-calendar-check text-lg opacity-70" aria-hidden="true"></i></div>
                <p class="mt-4 text-2xl font-bold tabular-nums">{{ $formatLeaveDays((float) $leaveSummary['total_days']) }} <span class="text-sm font-medium">วัน</span></p>
            </div>
        </div>
    </section>
    
    <!-- Content Row for Charts -->
    <div class="dashboard-charts">
        <!-- Area Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4"  style="height: 450px;">
                <!-- Card Header -->
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">ประวัติรายได้-หัก ย้อนหลัง</h6>
                </div>
                <!-- Card Body -->
                <div class="card-body">
                    <div class="chart-area" style="height: 350px; position: relative;">
                        @if(isset($salaryHistory) && count($salaryHistory) > 0)
                        <canvas id="salaryHistoryChart"></canvas>
                        <script>
                            // Initialize chart function
                            function initSalaryChart() {
                                if (!document.getElementById('salaryHistoryChart')) {
                                    return; // Exit if canvas doesn't exist
                                }

                                // Check if chart already exists and destroy it
                                if (window.salaryChart) {
                                    window.salaryChart.destroy();
                                }

                                const ctx = document.getElementById('salaryHistoryChart').getContext('2d');
                                const salaryData = @json($salaryHistory);

                                // Prepare data for chart
                                const labels = salaryData.map(item => {
                                    const thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                                    // Use yearNO directly without adding 543
                                    return thaiMonths[parseInt(item.monthNO) - 1] + ' ' + parseInt(item.yearNO);
                                });

                                const incomeAmounts = salaryData.map(item => item.summaryIncomeAmount);
                                const expenseAmounts = salaryData.map(item => item.summaryOutcomeAmount);
                                const netAmounts = salaryData.map(item => item.summaryTotalAmount);

                                // Create chart
                                window.salaryChart = new Chart(ctx, {
                                    type: 'line',
                                    data: {
                                        labels: labels,
                                        datasets: [{
                                                label: 'รายรับ (บาท)',
                                                data: incomeAmounts,
                                                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                                                borderColor: 'rgba(78, 115, 223, 1)',
                                                pointRadius: 3,
                                                pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                                                pointBorderColor: 'rgba(78, 115, 223, 1)',
                                                pointHoverRadius: 5,
                                                pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                                                pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                                                pointHitRadius: 10,
                                                pointBorderWidth: 2,
                                                lineTension: 0.3
                                            },
                                            {
                                                label: 'รายจ่าย (บาท)',
                                                data: expenseAmounts,
                                                backgroundColor: 'rgba(255, 99, 132, 0.05)',
                                                borderColor: 'rgba(255, 99, 132, 1)',
                                                pointRadius: 3,
                                                pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                                                pointBorderColor: 'rgba(255, 99, 132, 1)',
                                                pointHoverRadius: 5,
                                                pointHoverBackgroundColor: 'rgba(255, 99, 132, 1)',
                                                pointHoverBorderColor: 'rgba(255, 99, 132, 1)',
                                                pointHitRadius: 10,
                                                pointBorderWidth: 2,
                                                lineTension: 0.3
                                            },
                                            {
                                                label: 'รับสุทธิ (บาท)',
                                                data: netAmounts,
                                                backgroundColor: 'rgba(28, 200, 138, 0.05)',
                                                borderColor: 'rgba(28, 200, 138, 1)',
                                                pointRadius: 3,
                                                pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                                                pointBorderColor: 'rgba(28, 200, 138, 1)',
                                                pointHoverRadius: 5,
                                                pointHoverBackgroundColor: 'rgba(28, 200, 138, 1)',
                                                pointHoverBorderColor: 'rgba(28, 200, 138, 1)',
                                                pointHitRadius: 10,
                                                pointBorderWidth: 2,
                                                lineTension: 0.3
                                            }
                                        ]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            x: {
                                                ticks: {
                                                    maxTicksLimit: 12
                                                }
                                            },
                                            y: {
                                                ticks: {
                                                    callback: function(value) {
                                                        return '฿' + value.toLocaleString();
                                                    }
                                                }
                                            }
                                        },
                                        plugins: {
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        const label = context.dataset.label || '';
                                                        const value = context.parsed.y;
                                                        return label + ': ฿' + value.toLocaleString();
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                            }

                            // Initialize on page load
                            document.addEventListener('DOMContentLoaded', initSalaryChart);

                            // Initialize on Livewire updates
                            document.addEventListener('livewire:initialized', initSalaryChart);

                            // Re-initialize after navigation
                            document.addEventListener('livewire:navigated', initSalaryChart);
                        </script>
                        @else
                        <div class="text-center py-5">
                            <i class="fas fa-chart-line fa-3x mb-3 text-gray-300"></i>
                            <p>ไม่พบข้อมูลประวัติเงินเดือนย้อนหลัง</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Pie Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4"  style="height: 450px;">
                <!-- Card Header -->
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">สัดส่วนรายรับ-รายจ่าย</h6>
                </div>
                <!-- Card Body -->
                <div class="card-body">
                    <div class="chart-pie" style="height: 350px; position: relative;">
                        @if(isset($incomeExpenseData))
                        <canvas id="incomeExpensePieChart"></canvas>
                        <script>
                            // Initialize pie chart function
                            function initPieChart() {
                                if (!document.getElementById('incomeExpensePieChart')) {
                                    return; // Exit if canvas doesn't exist
                                }

                                // Check if chart already exists and destroy it
                                if (window.pieChart) {
                                    window.pieChart.destroy();
                                }

                                const ctx = document.getElementById('incomeExpensePieChart').getContext('2d');
                                const pieData = {
                                    labels: ['รายรับ', 'รายจ่าย'],
                                    datasets: [{
                                        data: [
                                            {{ $incomeExpenseData->summaryIncomeAmount }}, 
                                            {{ $incomeExpenseData->summaryOutcomeAmount }}
                                        ],
                                        backgroundColor: [
                                            'rgba(54, 162, 235, 0.8)',
                                            'rgba(255, 99, 132, 0.8)'
                                        ],
                                        borderColor: [
                                            'rgba(54, 162, 235, 1)',
                                            'rgba(255, 99, 132, 1)'
                                        ],
                                        borderWidth: 1
                                    }]
                                };

                                window.pieChart = new Chart(ctx, {
                                    type: 'pie',
                                    data: pieData,
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        const label = context.label;
                                                        const value = context.raw;
                                                        return label + ': ฿' + value.toLocaleString();
                                                    }
                                                }
                                            },
                                            legend: {
                                                position: 'bottom'
                                            }
                                        }
                                    }
                                });
                            }

                            // Initialize on page load
                            document.addEventListener('DOMContentLoaded', initPieChart);

                            // Initialize on Livewire updates
                            document.addEventListener('livewire:initialized', initPieChart);

                            // Re-initialize after navigation
                            document.addEventListener('livewire:navigated', initPieChart);
                        </script>
                        @else
                        <div class="text-center py-5">
                            <i class="fas fa-chart-pie fa-3x mb-3 text-gray-300"></i>
                            <p>ไม่พบข้อมูลสัดส่วนรายรับ-รายจ่าย</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Salary Promotion History Chart -->
    <div class="dashboard-promotion-chart">
        <div class="dashboard-promotion-panel">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">ประวัติร้อยละการเลื่อนขั้นเงินเดือน</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 250px; position: relative;">
                        @if(isset($promotionHistory) && count($promotionHistory) > 0)
                        <canvas id="promotionHistoryChart"></canvas>
                        <script>
                            // Initialize promotion history chart
                            function initPromotionChart() {
                                if (!document.getElementById('promotionHistoryChart')) {
                                    return; // Exit if canvas doesn't exist
                                }

                                // Check if chart already exists and destroy it
                                if (window.promotionChart) {
                                    window.promotionChart.destroy();
                                }

                                const ctx = document.getElementById('promotionHistoryChart').getContext('2d');
                                const promotionData = @json($promotionHistory);

                                // Prepare data for chart
                                const labels = promotionData.map(item => {
                                    // For promotion rounds, common format is "ครั้งที่ 1/2566" (for first round of 2023)
                                    return 'รอบ ' + item.round + '/' + item.year;
                                });

                                const percentages = promotionData.map(item => item.percentage);

                                // Create chart - changed to bar chart with only percentage data
                                window.promotionChart = new Chart(ctx, {
                                    type: 'bar',
                                    data: {
                                        labels: labels,
                                        datasets: [
                                            {
                                                label: 'ร้อยละการเลื่อนขั้นเงินเดือน (%)',
                                                data: percentages,
                                                backgroundColor: [
                                                    'rgba(78, 115, 223, 0.8)',
                                                    'rgba(28, 200, 138, 0.8)',
                                                    'rgba(246, 194, 62, 0.8)',
                                                    'rgba(54, 185, 204, 0.8)',
                                                    'rgba(231, 74, 59, 0.8)',
                                                    'rgba(133, 135, 150, 0.8)'
                                                ],
                                                borderColor: [
                                                    'rgba(78, 115, 223, 1)',
                                                    'rgba(28, 200, 138, 1)',
                                                    'rgba(246, 194, 62, 1)',
                                                    'rgba(54, 185, 204, 1)',
                                                    'rgba(231, 74, 59, 1)',
                                                    'rgba(133, 135, 150, 1)'
                                                ],
                                                borderWidth: 1
                                            }
                                        ]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                title: {
                                                    display: true,
                                                    text: 'ร้อยละ (%)'
                                                },
                                                ticks: {
                                                    callback: function(value) {
                                                        return value + '%';
                                                    }
                                                }
                                            },
                                            x: {
                                                grid: {
                                                    display: false
                                                }
                                            }
                                        },
                                        plugins: {
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        return 'ร้อยละ: ' + context.parsed.y + '%';
                                                    }
                                                }
                                            },
                                            legend: {
                                                display: false
                                            }
                                        }
                                    }
                                });
                            }

                            // Initialize on page load
                            document.addEventListener('DOMContentLoaded', initPromotionChart);

                            // Initialize on Livewire updates
                            document.addEventListener('livewire:initialized', initPromotionChart);

                            // Re-initialize after navigation
                            document.addEventListener('livewire:navigated', initPromotionChart);
                        </script>
                        @else
                        <div class="text-center py-5">
                            <i class="fas fa-chart-bar fa-3x mb-3 text-gray-300"></i>
                            <p>ไม่พบข้อมูลการเลื่อนขั้นเงินเดือน</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
