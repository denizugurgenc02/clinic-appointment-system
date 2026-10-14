<?php
namespace App\Patient;

use App\Core\View;

class PatientController 
{
    public function index(): void 
    {
        // Normalde bu veri veritabanından (Repository aracılığıyla) gelir
        $veri = [
            'kullaniciAdi' => 'Ahmet Yılmaz'
        ];

        // View motorunu çağırıyoruz: 
        // views/patient/home.php dosyasını render et ve views/layouts/main.php içine yerleştir
        View::render('patient/home', $veri, 'layouts/main');
    }
}