@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">เกิดข้อผิดพลาด</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-triangle fa-4x text-danger mb-3"></i>
                        <h4>{{ $message ?? 'เกิดข้อผิดพลาดในระบบ' }}</h4>
                    </div>
                    
                    <div class="text-center">
                        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary mr-2">
                            <i class="fas fa-arrow-left mr-1"></i> ย้อนกลับ
                        </a>
                        <a href="{{ route('home') }}" class="btn btn-primary">
                            <i class="fas fa-home mr-1"></i> กลับสู่หน้าหลัก
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
