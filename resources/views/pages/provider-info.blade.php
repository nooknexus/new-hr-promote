<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider ID คืออะไร?</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        .header-img {
            max-height: 120px;
        }
        .provider-card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
            border: none;
            margin-bottom: 30px;
        }
        .card-header {
            background-color: #01797E;
            color: white;
            font-weight: bold;
        }
        .btn-back {
            background-color: #01797E;
            border-color: #01797E;
        }
        .btn-back:hover {
            background-color: #015d61;
            border-color: #015d61;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-md-12 text-center">
                <img src="{{ asset('img/logoMOPH.png') }}" alt="Logo" class="header-img mb-3">
                <h1 class="mb-4">Provider ID คืออะไร?</h1>
                <a href="{{ route('login') }}" class="btn btn-back text-white mb-4">
                    <i class="fas fa-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ
                </a>
            </div>
        </div>
        
        <div class="card provider-card">
            <div class="card-header">
                Provider ID คืออะไร?
            </div>
            <div class="card-body">
                <p>Provider ID คือระบบการพิสูจน์และยืนยันตัวตนทางดิจิทัลสำหรับบุคลากรทางการแพทย์และสาธารณสุข ที่พัฒนาโดยกระทรวงสาธารณสุข</p>
                
                <p>Provider ID เป็นระบบที่ช่วยให้บุคลากรทางการแพทย์และสาธารณสุขสามารถเข้าถึงระบบต่างๆ ของกระทรวงสาธารณสุขได้ด้วยบัญชีเดียว (Single Sign-On) โดยไม่จำเป็นต้องจำชื่อผู้ใช้และรหัสผ่านหลายชุด</p>
            </div>
        </div>
        
        <div class="card provider-card">
            <div class="card-header">
                ประโยชน์ของ Provider ID
            </div>
            <div class="card-body">
                <ul>
                    <li>เข้าถึงระบบต่างๆ ของกระทรวงสาธารณสุขด้วยบัญชีเดียว</li>
                    <li>ลดความยุ่งยากในการจดจำชื่อผู้ใช้และรหัสผ่านหลายชุด</li>
                    <li>เพิ่มความปลอดภัยในการเข้าถึงข้อมูลและระบบสารสนเทศ</li>
                    <li>ระบุตัวตนที่ชัดเจนของผู้ให้บริการสาธารณสุข</li>
                    <li>ช่วยให้การทำงานในระบบต่างๆ เป็นไปอย่างราบรื่นและมีประสิทธิภาพ</li>
                </ul>
            </div>
        </div>
        
        <div class="card provider-card">
            <div class="card-header">
                วิธีการลงทะเบียนและใช้งาน Provider ID
            </div>
            <div class="card-body">
                <h5>การลงทะเบียน</h5>
                <ol>
                    <li>เข้าไปที่เว็บไซต์ <a href="https://provider.moph.go.th" target="_blank">https://provider.moph.go.th</a></li>
                    <li>คลิกที่ "ลงทะเบียนใหม่"</li>
                    <li>กรอกข้อมูลส่วนตัวและข้อมูลการทำงานตามที่ระบบร้องขอ</li>
                    <li>ยืนยันตัวตนตามขั้นตอนที่กำหนด</li>
                    <li>รอการอนุมัติจากผู้ดูแลระบบ</li>
                </ol>
                
                <h5>การใช้งาน</h5>
                <ol>
                    <li>เมื่อมีการเข้าสู่ระบบที่รองรับ Provider ID ให้เลือกวิธีการเข้าสู่ระบบด้วย Provider ID</li>
                    <li>กรอกชื่อผู้ใช้และรหัสผ่านของ Provider ID</li>
                    <li>ยืนยันตัวตนตามขั้นตอนที่กำหนด (อาจมีการยืนยันตัวตนด้วย OTP หรือวิธีอื่น)</li>
                    <li>เมื่อยืนยันตัวตนสำเร็จ ระบบจะนำคุณเข้าสู่ระบบที่ต้องการโดยอัตโนมัติ</li>
                </ol>
            </div>
        </div>
        
        <div class="card provider-card">
            <div class="card-header">
                ข้อมูลเพิ่มเติมและการติดต่อ
            </div>
            <div class="card-body">
                <p>หากคุณมีคำถามหรือต้องการความช่วยเหลือเกี่ยวกับ Provider ID สามารถติดต่อได้ที่:</p>
                <ul>
                    <li>เว็บไซต์: <a href="https://provider.moph.go.th" target="_blank">https://provider.moph.go.th</a></li>
                    <li>อีเมล: provider@moph.go.th</li>
                    <li>โทรศัพท์: 02-XXX-XXXX</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <strong>หมายเหตุ:</strong> Provider ID เป็นส่วนหนึ่งของโครงการพัฒนาระบบสารสนเทศภายใต้ยุทธศาสตร์ Digital Health ของกระทรวงสาธารณสุข
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
