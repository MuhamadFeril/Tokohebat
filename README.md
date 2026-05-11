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

**Perbaikan:**
```php
// ❌ SEBELUM (Tidak aman)
$user = User::create([
    'name' => $data['name'],
    'email' => $data['email'],
    'password' => $data['password'],  // Langsung, tanpa hash!
]);

// ✅ SESUDAH (Aman)
use Illuminate\Support\Facades\Hash;

$user = User::create([
    'name' => $data['name'],
    'email' => $data['email'],
    'password' => Hash::make($data['password']),  // Di-hash dengan bcrypt
]);
```

**Cara Test Bug Plaintext Password:**

1. **Register User Dulu**
   - Endpoint: `POST /api/salauth/register`
   - Body:
     ```json
     {
         "name": "Feril",
         "email": "feril@mail.com",
         "password": "12345678"
     }
     ```

2. **Buka Database**
   - Gunakan tools seperti phpMyAdmin, TablePlus, HeidiSQL, atau DBeaver
   - Buka tabel `users`

3. **Lihat Kolom Password**
   - Jika vulnerable: `12345678` (atau password asli lainnya)
   - Jika aman: `$2y$12$asdasdasd...` (hash bcrypt)

**Kenapa Bahaya:**
- Jika database bocor, semua password user langsung terbaca
- Hacker bisa login ke akun user
- Risiko credential stuffing ke akun lain (Gmail, Instagram, dll.)

**Penyebab Bug:**
- Repository salah: `'password' => $data['password']` (langsung masuk database)

**Versi Aman:**
- Gunakan: `'password' => Hash::make($data['password'])`

**Penjelasan untuk Presentasi/README:**
Saya melakukan testing dengan register user menggunakan endpoint vulnerable. Setelah user berhasil register, saya membuka tabel users di database. Password ternyata tersimpan dalam bentuk plaintext dan bisa dibaca langsung tanpa decrypt. Hal ini termasuk vulnerability Sensitive Data Exposure atau Plaintext Password Storage.

**Contoh Kode Salah dan Benar Sesuai File:**

**❌ SALAH (Vulnerable) - app/Repositories/SalauthRepository.php:**
```php
// ❌ BUG: Password tersimpan plaintext
public function register(array $data)
{
    return User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        // BUG: Langsung simpan tanpa hash!
        'password' => $data['password'],
        'role' => 'user'
    ]);
}

// ❌ BUG: Login tanpa validasi password
public function login(array $credentials)
{
    $user = User::where('email', $credentials['email'])->first();
    
    if (!$user) {
        return null;
    }
    
    // BUG: Password diabaikan, langsung return token!
    return $user->createToken('auth_token')->plainTextToken;
}
```

**✅ BENAR (Secure) - app/Repositories/AuthRepository.php:**
```php
// ✅ AMAN: Password di-hash dengan bcrypt
public function register(array $data)
{
    $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        // ✅ Hash password dengan Hash::make()
        'password' => Hash::make($data['password']),
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;
    return $token;
}

// ✅ AMAN: Validasi email DAN password
public function login(array $credentials)
{
    $user = $this->findByEmail($credentials['email']);

    // ✅ Cek password dengan Hash::check()
    if ($user && isset($credentials['password']) && Hash::check($credentials['password'], $user->password)) {
        return $user->createToken('auth_token')->plainTextToken;
    }

    return null;
}
```

**✅ BENAR (Secure) - database/seeders/AdminSeeder.php:**
```php
// ✅ AMAN: Password admin juga di-hash
public function run(): void
{
    User::create([
        'name' => 'Admin',
        'email' => 'admin@gmail.com',
        // ✅ Hash password admin
        'password' => Hash::make('password'),
        'role' => 'admin'
    ]);
}
```

---

### 2. **User Bisa Login ke Akun Orang Lain** ❌
**Masalah:** Sistem login tidak validasi dengan benar, memungkinkan user mencoba-coba password orang lain atau akses akun tidak sah.

**Lokasi:** `app/Repositories/AuthRepository.php`

**Dampak Keamanan:**
- Account takeover — hacker bisa masuk akun orang lain
- Data pribadi pelanggan bisa dicuri
- Kerugian finansial jika ada transaksi

**Perbaikan:**
```php
// ✅ SESUDAH (Validasi ketat)
public function login(array $credentials)
{
    $user = $this->findByEmail($credentials['email']);
    
    // Cek email DAN password dengan Hash::check()
    if ($user && Hash::check($credentials['password'], $user->password)) {
        $token = $user->createToken('auth_token')->plainTextToken;
        return $token;
    }
    
    return null;  // Gagal jika ada salah satu tidak cocok
}
```

**Best Practice:**
- Gunakan `Hash::check()` untuk verifikasi password
- Gunakan Laravel Sanctum untuk token API
- Jangan pernah simpan plain password di database

---

### 3. **User Biasa Bisa Akses Halaman Admin** ❌
**Masalah:** Tidak ada pemisahan role (user vs admin), sehingga siapa saja bisa akses fitur admin.

**Lokasi:** Middleware belum diterapkan, tidak ada sistem role

**Dampak Keamanan:**
- User biasa bisa hapus data, ubah harga produk, lihat laporan penjualan
- Integritas data terganggu
- Potensi sabotase dari user jahat

**Perbaikan:**

1. **Tambah kolom `role` di database:**
```php
// database/migrations/0001_01_01_000000_create_users_table.php
$table->string('role')->default('user');  // 'user' atau 'admin'
```

2. **Buat AdminMiddleware untuk proteksi route:**
```php
// app/Http/Middleware/AdminMiddleware.php
namespace App\Http\Middleware;

class AdminMiddleware
{
    public function handle($request, $next)
    {
        if (auth()->user()?->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return $next($request);
    }
}
```

3. **Terapkan ke route admin:**
```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('admin')->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
        Route::post('/admin/products', [ProductController::class, 'store']);
        // ... route admin lainnya
    });
});
```

---

## ✅ Perbaikan yang Sudah Dilakukan

### 1. **Implementasi Hash Password**
- Repository login/register menggunakan `Hash::make()` untuk menyimpan password
- Validasi login menggunakan `Hash::check()`
- Semua password baru akan ter-hash dengan aman

### 2. **Tambah Support API Token dengan Sanctum**
- Model `User` sudah implement `HasApiTokens`
- User bisa mendapat token unik setelah login
- Token otomatis revoke saat logout

```php
// app/Models/User.php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
}
```

### 3. **Sistem Role (user/admin)**
- Kolom `role` ditambah ke tabel users
- AdminSeeder membuat akun admin default
- Middleware `admin` proteksi route admin

### 4. **Validasi Request Ketat**
- Password harus 8+ karakter
- Password harus dikonfirmasi saat register
- Email harus unik dan format valid

```php
// app/Http/Requests/RegisterRequest.php
'password' => 'required|string|min:8|confirmed',
'password_confirmation' => 'required|string|min:8',
```

---

## 📋 Checklist Keamanan TokoHebat

- [x] Password di-hash dengan bcrypt
- [x] Login validasi dengan Hash::check()
- [x] Sistem role (user/admin)
- [x] Middleware admin untuk proteksi route
- [x] API token dengan Sanctum
- [x] Validasi request ketat (email unik, password 8+ char)
- [ ] HTTPS/SSL (setup di production)
- [ ] Rate limiting untuk login attempts
- [ ] 2FA (two-factor authentication) — opsional future
- [ ] Audit log untuk aktivitas admin
- [ ] CORS security headers

---

## 🔐 Langkah Selanjutnya

1. **Setup HTTPS** di production server
2. **Enable rate limiting** untuk mencegah brute force:
   ```php
   Route::post('/login', [AuthController::class, 'login'])->throttle('6,1');
   ```
3. **Monitor database** untuk akses yang mencurigakan
4. **Update password semua user lama** jika ada yang plain text
5. **Dokumentasi API** untuk dev team berikutnya agar tidak repeat mistake

---

## 📞 Catatan
> "Yoga bilang semuanya sudah aman. Tapi kayaknya definisi aman kita berbeda..." 
> 
> — Definisi aman yang benar: Patuhi standar OWASP, encrypt password, validasi akses, dokumentasi jelas.

---

**Status:** ✅ Sebagian besar sudah diperbaiki  
**Last Updated:** 8 May 2026  
**Next Review:** 15 May 2026
