<a href="https://www.instagram.com/amvsoft.tech/" target="_blank">
  <img src="https://cdn.dribbble.com/userupload/29087371/file/original-66f8e5db372b7b1d9b62f2c7d0931143.gif" />
</a>
<p align="center">
    <a href="https://laravel.com"><img alt="Laravel v12.x" src="https://img.shields.io/badge/Laravel-v12.x-FF2D20?style=for-the-badge&logo=laravel"></a>
    <a href="https://livewire.laravel.com"><img alt="Vue v3.x" src="https://img.shields.io/badge/vue-v3.x-42b883?style=for-the-badge"></a>
    <a href="https://inertiajs.com"><img alt="Inertia v2.x" src="https://img.shields.io/badge/inertia-v2.x-FB70A9?style=for-the-badge"></a>
    <a href="https://tailwindcss.com"><img alt="Tailwind v4.x" src="https://img.shields.io/badge/Tailwind-v4.x-3e3e66?style=for-the-badge"></a>
    <a href="https://www.typescriptlang.org"><img alt="Typescript  v5.x" src="https://img.shields.io/badge/typescript-v5.x-172554?style=for-the-badge"></a>
    <a href="https://php.net"><img alt="PHP 8.3" src="https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php"></a>
</p>

## 🛠️ Instalación
1️⃣ Clonar el repositorio
```
git clone https://github.com/phpeitor/vue-laravel.git
cd vue-laravel
```
2️⃣ Instalar dependencias PHP
```
composer install
```
3️⃣ Instalar dependencias Frontend
```
npm install
```
4️⃣ Configurar entorno
```
cp .env.example .env
php artisan key:generate
```
5️⃣ Migrar base de datos
```
php artisan migrate --seed
```

## 🚀 Puesta en Marcha
Backend
```
php artisan serve
```
Frontend (Vite)
```
npm run dev
```
WebSockets (Laravel Reverb)
```
php artisan reverb:start --debug
```
Workers de cola
```
php artisan queue:work
```
📂 Estructura del Proyecto
```
├── app
├── database
├── routes
├── resources
│   ├── js
│   │   ├── Pages
│   │   ├── Components
│   │   └── Layouts
├── public
└── storage
```
[![Video](https://img.youtube.com/vi/WNNn22SycQY/0.jpg)](https://www.youtube.com/watch?v=WNNn22SycQY)  
[Ver demo](https://www.youtube.com/watch?v=WNNn22SycQY)

> [!NOTE]
> Si este proyecto te sirve... ¡Dale una estrella ⭐ y compártelo con tu equipo!