<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'Provider Connect' }}</title>
    @vite(['resources/assets/vendor/fontawesome-free/css/all.min.css', 'resources/assets/css/sb-admin-2.min.css', 'resources/assets/vendor/jquery/jquery.min.js', 'resources/assets/vendor/bootstrap/js/bootstrap.bundle.min.js', 'resources/assets/vendor/jquery-easing/jquery.easing.min.js', 'resources/assets/js/sb-admin-2.min.js'])
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .bg-gradient-custom {
            background-color: #00897B;
            background-image: linear-gradient(180deg, #00897B 10%, #00695C 100%);
            background-size: cover;
        }

        /* Enhanced sidebar styles */
        .sidebar .nav-item .nav-link {
            padding: 1rem 1.2rem;
        }

        .sidebar .nav-item .nav-link span {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .sidebar .nav-item .nav-link i {
            font-size: 1.1rem;
            margin-right: 0.5rem;
        }

        .sidebar-brand-text {
            font-size: 1.5rem !important;
            font-weight: 700;
        }

        .sidebar-dark .nav-item.active .nav-link {
            font-weight: 700;
            border-left: 4px solid #fff;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-custom sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ route('home') }}">
                <div class="sidebar-brand-icon">
                    <img src="{{ asset('img/logoMOPH.png') }}" alt="Logo" style="width: 55px; height: 55px;">
                </div>
                <div class="sidebar-brand-text mx-3">MyPPH</div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Items -->
            <li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('home') }}" wire:navigate>
                    <i class="fas fa-fw fa-chart-area"></i>
                    <span>หน้าแรก</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('profile.index') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('profile.index') }}" wire:navigate>
                    <i class="fas fa-user fa-fw"></i>
                    <span>ข้อมูลส่วนตัว</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('profile.payslip') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('profile.payslip') }}" wire:navigate>
                    <i class="fas fa-file-invoice-dollar fa-fw"></i>
                    <span>PaySlip</span>
                </a>
            </li>
            
            <!-- Tax Certificate menu item -->
            <li class="nav-item {{ request()->routeIs('tax.certificate') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('tax.certificate') }}" wire:navigate>
                    <i class="fas fa-receipt fa-fw"></i>
                    <span>ใบรับรองการหักภาษี</span>
                </a>
            </li>
            
            <!-- New menu item for Salary Promotion -->
            <li class="nav-item {{ request()->routeIs('profile.salary-promotion') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('profile.salary-promotion') }}" wire:navigate>
                    <i class="fas fa-arrow-up fa-fw"></i>
                    <span>เลื่อนขั้นเงินเดือน</span>
                </a>
            </li>
            
            <li class="nav-item {{ request()->routeIs('training.info') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('training.info') }}" wire:navigate>
                    <i class="fas fa-graduation-cap fa-fw"></i>
                    <span>ข้อมูลการอบรม/ดูงาน</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('about') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('about') }}" wire:navigate>
                    <i class="fas fa-fw fa-info-circle"></i>
                    <span>เกี่ยวกับ</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Sidebar Toggler (Sidebar) -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Search -->


                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <!-- User Information and Logout -->
                        <li class="nav-item d-flex align-items-center">
                            <span class="mr-3 text-gray-600">{{ auth()->user()->name }}</span>
                            <livewire:logout />
                        </li>
                    </ul>

                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    {{ $slot }}

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; สำนักงานสาธารณสุขจังหวัดพิษณุโลก 2025</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Add login success notification script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check for login success in session
            @if(session('login_success'))
            Swal.fire({
                icon: 'success',
                title: 'เข้าสู่ระบบสำเร็จ',
                text: '{{ session('
                login_success ') }}',
                timer: 2000,
                showConfirmButton: false
            });
            @endif
        });

        // For Livewire login success events
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('login-success', (message) => {
                Swal.fire({
                    icon: 'success',
                    title: 'เข้าสู่ระบบสำเร็จ',
                    text: message || 'ยินดีต้อนรับเข้าสู่ระบบ',
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        });
    </script>
</body>

</html>