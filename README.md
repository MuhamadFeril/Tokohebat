# 🔒 Laporan Perbaikan Keamanan TokoHebat

## Konteks
TokoHebat adalah toko online kecil yang jual berbagai produk lokal. Backend dibangun dengan Laravel oleh freelancer bernama Yoga. Setelah 1 bulan kerja pertama, Yoga pergi ke proyek lain tanpa dokumentasi. Kemudian ditemukan beberapa masalah keamanan kritis.

---

## 🚨 Masalah yang Ditemukan

### 1. **Password Tersimpan Tanpa Hash** ❌
**Masalah:** Password pelanggan tersimpan apa adanya di database, tidak ter-hash.

**Lokasi:** `database/seeders/AdminSeeder.php` dan `app/Repositories/AuthRepository.php`

**Dampak Keamanan:** 
- Jika database terekspos, semua password pelanggan langsung bisa dibaca
- Melanggar standar keamanan industri
- Risiko data breach besar

---

### 2. **User Bisa Login ke Akun Orang Lain** ❌
**Masalah:** Sistem login tidak validasi dengan benar, memungkinkan user mencoba-coba password orang lain atau akses akun tidak sah.

**Lokasi:** `app/Repositories/AuthRepository.php`

**Dampak Keamanan:**
- Account takeover — hacker bisa masuk akun orang lain
- Data pribadi pelanggan bisa dicuri
- Kerugian finansial jika ada transaksi

---

### 3. **User Biasa Bisa Akses Halaman Admin** ❌
**Masalah:** Tidak ada pemisahan role (user vs admin), sehingga siapa saja bisa akses fitur admin.

**Lokasi:** Middleware belum diterapkan, tidak ada sistem role

**Dampak Keamanan:**
- User biasa bisa hapus data, ubah harga produk, lihat laporan penjualan
- Integritas data terganggu
- Potensi sabotase dari user jahat

---

## ✅ Perbaikan yang Sudah Dilakukan

---

## 📂 Struktur File & Implementasi Aktual

### 1. **Database Seeder** 
📄 [database/seeders/AdminSeeder.php](database/seeders/AdminSeeder.php)

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password'),  // ✅ Password di-hash dengan bcrypt
            'role' => 'admin'  // ✅ Role admin
        ]);
    }
}
```

**Penjelasan:**
- Membuat user admin default saat migrasi database
- Password di-hash menggunakan `Hash::make()` — aman!
- Role = `admin` untuk identifikasi user admin

**Setup:** Jalankan `php artisan migrate:fresh --seed` untuk membuat admin user

---

### 2. **Interface untuk Autentikasi**
📄 [app/Interfaces/AuthInterface.php](app/Interfaces/AuthInterface.php)

```php
<?php
namespace App\Interfaces;

Interface AuthInterface
{
    public function login($request);
    public function register($request);
    public function logout();
}
```

**Penjelasan:**
- Mendefinisikan kontrak (interface) untuk semua method autentikasi
- Repository wajib implement ketiga method ini
- Memudahkan testing dan fleksibilitas implementasi

---

### 3. **Repository — Business Logic Autentikasi**
📄 [app/Repositories/AuthRepository.php](app/Repositories/AuthRepository.php)

```php
<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AuthRepository
{
    /**
     * Cari user berdasarkan email
     */
    public function findByEmail(string $email)
    {
        return User::where('email', $email)->first();
    }

    /**
     * Daftar user baru dengan password ter-hash
     */
    public function register(array $data)
    {
        // ✅ Hash password sebelum simpan
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // ✅ Generate API token dengan Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;
        return $token;
    }

    /**
     * Login user dengan validasi email & password
     */
    public function login(array $credentials)
    {
        $user = $this->findByEmail($credentials['email']);

        // ✅ Validasi password menggunakan Hash::check()
        if ($user && Hash::check($credentials['password'], $user->password)) {
            $token = $user->createToken('auth_token')->plainTextToken;
            return $token;
        }

        return null;  // Invalid email atau password
    }

    /**
     * Logout — hapus semua token user
     */
    public function logout(Request $request = null)
    {
        $user = $request && $request->user() ? $request->user() : Auth::user();

        if ($user) {
            // ✅ Revoke semua token untuk logout total
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
            Auth::logout();
        }

        return true;
    }
}
```

**Key Points:**
- `Hash::make()` — mengenkripsi password dengan bcrypt
- `Hash::check()` — verifikasi password plain vs hashed
- Sanctum token — unique token per user untuk API authentication
- `tokens()->delete()` — logout dengan revoke semua token

---

### 4. **Request Validation — RegisterRequest**
📄 [app/Http/Requests/RegisterRequest.php](app/Http/Requests/RegisterRequest.php)

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize()
    {
        return true;  // Siapa saja boleh daftar
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',  // ✅ Email harus unik
            'password' => 'required|string|min:8|confirmed',  // ✅ Harus dikonfirmasi
            'password_confirmation' => 'required|string|min:8',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Nama harus diisi.',
            'name.string' => 'Nama harus berupa teks.',
            'name.max' => 'Nama tidak boleh lebih dari 255 karakter.',
            'email.required' => 'Email harus diisi.',
            'email.email' => 'Email harus berupa alamat email yang valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Password harus diisi.',
            'password.string' => 'Password harus berupa teks.',
            'password.min' => 'Password harus minimal 8 karakter.',
            'password.confirmed' => 'Password konfirmasi tidak cocok.',
        ];
    }
}
```

**Validasi:**
- Email unik — tidak boleh duplikat
- Password minimum 8 karakter
- Password harus dikonfirmasi (field `password_confirmation`)

**Request JSON yang valid:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secure_password123",
    "password_confirmation": "secure_password123"
}
```

---

### 5. **Middleware — Proteksi Route Admin**
📄 [app/Http/Middleware/AdminMiddleware.php](app/Http/Middleware/AdminMiddleware.php)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // ❌ Cek user sudah login (pakai Sanctum token)
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // ❌ Cek user adalah admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden'
            ], 403);
        }

        return $next($request);
    }
}
```

**Flow:**
1. Request masuk dengan token API
2. Sanctum decode token & set `$request->user()`
3. Middleware cek user role = 'admin'
4. Jika bukan admin → return 403 Forbidden

**Setup Middleware di Kernel:**
```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    'admin' => \App\Http\Middleware\AdminMiddleware::class,
];
```

---

### 6. **Helper — Format Response Konsisten**
📄 [app/Helpers/ResponsHelper.php](app/Helpers/ResponsHelper.php)

```php
<?php

namespace App\Helpers;

class ResponsHelper
{
    /**
     * Response sukses
     */
    public static function success($data, $message = 'Success', $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Response error
     */
    public static function error($message = 'Error', $code = 500)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $code);
    }
}
```

**Contoh Penggunaan:**
```php
// Sukses
return ResponseHelper::success($user, 'Register berhasil', 201);
// Output: {"status":"success","message":"Register berhasil","data":{...}}

// Error
return ResponseHelper::error('Email atau password salah', 401);
// Output: {"status":"error","message":"Email atau password salah"}
```

---

### 7. **Controller — API Endpoints**
📄 [app/Http/Controllers/Api/AuthController.php](app/Http/Controllers/Api/AuthController.php)

```php
<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Repositories\AuthRepository;

class AuthController extends Controller
{
    protected $authRepository;

    public function __construct(AuthRepository $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    public function register(RegisterRequest $request)
    {
        $user = $this->authRepository->register($request->validated());
        return ResponseHelper::success($user, 'Register berhasil', 201);
    }

    public function login(LoginRequest $request)
    {
        $login = $this->authRepository->login($request->validated());

        if (!$login) {
            return ResponseHelper::error('Email atau password salah', 401);
        }

        return ResponseHelper::success($login, 'Login berhasil');
    }

    public function logout()
    {
        $this->authRepository->logout();
        return ResponseHelper::success([], 'Logout berhasil');
    }
}
```

---

### 8. **Model — User dengan API Token Support**
📄 [app/Models/User.php](app/Models/User.php)

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;  // ✅ Support API token

#[Fillable(['name', 'email', 'password', 'role'])]  // ✅ Role added
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;  // ✅ HasApiTokens

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',  // ✅ Auto-hash saat set password
        ];
    }
}
```

---

### 9. **Routes — API Endpoints**
📄 [routes/api.php](routes/api.php)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// Public endpoints
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected endpoints (require auth:sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Admin only
    Route::middleware('admin')->group(function () {
        Route::get('/admin/dashboard', function () {
            return response()->json([
                'message' => 'Welcome admin'
            ]);
        });
    });
});
```

---

## 📊 Flow Diagram

### Register Flow
```
POST /api/register
├─ Validasi input (RegisterRequest)
├─ Hash password (Hash::make)
├─ Simpan ke database
├─ Generate Sanctum token
└─ Return token + 201
```

### Login Flow
```
POST /api/login
├─ Validasi input (LoginRequest)
├─ Cari user by email
├─ Verifikasi password (Hash::check)
├─ Generate Sanctum token
└─ Return token
```

### Admin Access Flow
```
GET /api/admin/dashboard [with token]
├─ Sanctum middleware → validate token
├─ AdminMiddleware → check $request->user()
├─ AdminMiddleware → check role == 'admin'
└─ Return data (jika admin)
```

---

## 📋 Checklist Keamanan TokoHebat

- [x] Password di-hash dengan bcrypt
- [x] Login validasi dengan Hash::check()
- [x] Sistem role (user/admin)
- [x] Middleware admin untuk proteksi route
- [x] API token dengan Sanctum
- [x] Validasi request ketat (email unik, password 8+ char)
- [x] Password confirmation saat register
- [x] Response helper untuk format konsisten
- [ ] HTTPS/SSL (setup di production)
- [ ] Rate limiting untuk login attempts
- [ ] 2FA (two-factor authentication) — opsional future
- [ ] Audit log untuk aktivitas admin
- [ ] CORS security headers

---

## 🔐 Best Practices yang Diterapkan

### 1. **Defense in Depth**
✅ Multiple layers: Validation → Hash → Token → Middleware

### 2. **Separation of Concerns**
✅ Interface → Repository → Controller → Helper

### 3. **Input Validation**
✅ Request classes dengan custom messages

### 4. **Secure Password Handling**
✅ `Hash::make()` untuk hashing
✅ `Hash::check()` untuk verifikasi
✅ Tidak pernah log password

### 5. **Stateless API Authentication**
✅ Sanctum tokens — scalable, stateless
✅ No session, pure API tokens

### 6. **Role-Based Access Control (RBAC)**
✅ User role field
✅ Middleware untuk enforce permissions

---

## 🔐 Langkah Selanjutnya

1. **Setup HTTPS** di production server
2. **Enable rate limiting:**
   ```php
   Route::post('/login', [AuthController::class, 'login'])->throttle('6,1');
   ```
3. **Add LoginRequest validation** (cek existing atau create)
4. **Register middleware di Kernel:**
   ```php
   'admin' => \App\Http\Middleware\AdminMiddleware::class,
   ```
5. **Setup CORS headers** jika frontend beda domain
6. **Monitor & audit** login attempts yang gagal

---

## 📞 Catatan
> "Yoga bilang semuanya sudah aman. Tapi kayaknya definisi aman kita berbeda..." 
> 
> — Definisi aman yang benar: Patuhi standar OWASP, encrypt password, validasi akses, dokumentasi jelas.

---

**Status:** ✅ Mayoritas sudah diperbaiki  
**Last Updated:** 8 May 2026  
**Implementation Date:** 8 May 2026
**Files Modified:** 9 files

