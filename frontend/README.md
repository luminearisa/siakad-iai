# SIAKAD Frontend — Web Application

Sistem Informasi Akademik (SIAKAD) — Single Page Application (SPA) berbasis **Vue 3**, **Vite**, **TypeScript**, dan **Tailwind CSS**.

---

## 1. Overview & Architecture

Frontend SIAKAD dibangun dengan pendekatan **Component-Driven + Domain-Oriented API Architecture**:

```text
Page Component (View)
      ↓
Composables & Stores (Pinia)
      ↓
Domain API Services (e.g. studentService, classService)
      ↓
Centralized API Client (Axios + Sanctum Auth + Global Interceptors)
      ↓
Laravel 13 REST API (/api/v1)
```

---

## 2. Tech Stack

- **Framework**: Vue 3 (Composition API `<script setup>`)
- **Build Tool**: Vite 6
- **Language**: TypeScript 5.7+
- **Styling**: Tailwind CSS 3.4+
- **State Management**: Pinia 2.3+
- **Routing**: Vue Router 4.5+
- **Icons**: Lucide Vue Next
- **HTTP Client**: Axios 1.7+

---

## 3. Project Structure

```text
frontend/
├── public/                  # Static assets & favicon
├── src/
│   ├── assets/              # Global styles (main.css)
│   ├── components/
│   │   ├── ui/              # 21 Primitive UI components (Button, Input, Modal, Badge, etc.)
│   │   ├── form/            # Form primitives (FormField, FormLabel, FormError, FormActions)
│   │   ├── data-display/    # DataTable, Pagination, PageHeader, PageContainer, PageSection
│   │   ├── navigation/      # Sidebar, Header, UserMenu
│   │   └── feedback/        # ToastContainer, ConfirmModal
│   ├── layouts/             # AppLayout, AuthLayout, BlankLayout
│   ├── pages/               # Auth, Dashboard, Domain Shells (Academic, Students, Classes, etc.)
│   ├── router/              # Vue Router with navigation guards (requiresAuth, permissions, roles)
│   ├── stores/              # Pinia stores (auth, app, navigation)
│   ├── composables/         # useAuth, usePermissions, useToast, usePagination, useFilters, useApi
│   ├── services/
│   │   ├── api/             # Centralized API client & domain API services
│   │   └── storage/         # Token storage
│   ├── types/               # TypeScript interfaces matching backend models & responses
│   ├── utils/               # Formatters, debounce, error extractors
│   ├── constants/           # Navigation config, roles, permissions
│   ├── App.vue              # Root component
│   └── main.ts              # Entry point
├── .env                     # Environment config
├── package.json
├── tsconfig.json
├── vite.config.ts
└── tailwind.config.js
```

---

## 4. Development & Build

### Install Dependencies
```bash
npm install
```

### Run Dev Server
```bash
npm run dev
```

### Production Build
```bash
npm run build
```

---

## 5. Authentication & Permissions

- **Sanctum Authentication**: API Bearer token disimpan dalam local storage dan disisipkan otomatis ke setiap request melalui Axios interceptor.
- **Role-Aware UI**: Composable `usePermissions()` menyediakan helper `can()`, `hasRole()`, `hasAnyPermission()`, dan `hasAnyRole()`.
- **Navigation Guards**: Route diproteksi otomatis oleh client-side router guard dengan pengecekan autentikasi dan hak akses permission.
