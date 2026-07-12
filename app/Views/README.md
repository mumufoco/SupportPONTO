# Views/Templates - Sistema de Registro de Ponto Eletrônico

This directory contains all the HTML views for the Electronic Timesheet System, built with Bootstrap 5 and designed for Brazilian Portuguese users.

## Directory Structure

```
Views/
├── layouts/
│   ├── main.php              # Main layout with navigation (authenticated users)
│   └── auth.php              # Authentication layout (minimal, centered)
├── auth/
│   ├── login.php             # Login page (multi-method: email/password, QR code, facial, unique code)
│   ├── forgot_password.php   # Password recovery request
│   └── reset_password.php    # Password reset with strength indicator
├── dashboard/
│   └── index.php             # Dashboard por perfil (admin/gestor/funcionario/rh/dpo)
├── punch/
│   ├── index.php             # Artefato legado/transicional de ponto (não é a view current do fluxo autenticado)
│   └── success.php           # Artefato utilitário/legado sem rota current direta
├── timesheet/
│   ├── index.php             # Monthly timesheet with daily records
│   ├── punch.php             # Fluxo current de registro de ponto autenticado
│   ├── punch_kiosk.php       # Terminal/kiosk de ponto
│   ├── quick_access.php      # Registro rápido / quick access
│   └── day.php               # Detailed single-day view
├── profile/
│   ├── index.php             # Employee profile display
│   └── biometric.php         # Biometric enrollment & LGPD consent
├── notifications/
│   └── index.php             # Notification list with filters (all/unread/read)
└── errors/
    └── html/
        ├── error_404.php     # Page not found
        ├── error_403.php     # Access denied
        └── error_500.php     # Internal server error
```

## Layouts

### Main Layout (`layouts/main.php`)
- **Purpose**: Base template for authenticated users
- **Features**:
  - Responsive navigation with role-based menu items
  - Flash message display (success, error, warning, info)
  - Notification badge in navbar
  - User dropdown menu
  - Footer with LGPD/MTE compliance notice
  - Loading spinner overlay
  - Bootstrap 5 + Font Awesome 6
  - Google Fonts (Inter)
  - Auto-hiding alerts (5 seconds)

### Auth Layout (`layouts/auth.php`)
- **Purpose**: Minimal layout for authentication pages
- **Features**:
  - Centered card design
  - Gradient background
  - No navigation bar
  - LGPD compliance footer

## Authentication Views

### Login (`auth/login.php`)
- **Route**: `/auth/login`
- **Methods**:
  1. Email/CPF + Password
  2. QR Code scanning
  3. Facial recognition (camera-based)
  4. Unique code
- **Features**:
  - Password visibility toggle
  - Remember me checkbox
  - Modals for alternative methods
  - QR code timer (5 minutes)
  - Camera integration for facial recognition

### Forgot Password (`auth/forgot_password.php`)
- **Route**: `/auth/forgot-password`
- **Features**:
  - Email input for recovery
  - Link back to login
  - LGPD notice

### Reset Password (`auth/reset_password.php`)
- **Route**: `/auth/reset-password/{token}` (POST em `/auth/reset-password`)
- **Features**:
  - Password strength indicator (real-time)
  - Password confirmation
  - Requirements display (8+ chars, uppercase, lowercase, numbers, symbols)
  - Password visibility toggles

## Dashboard

### Dashboard (`dashboard/index.php`)
- **Route**: `/dashboard`
- **Role Adaptation**:
  - **Employee**: Personal stats, quick punch, notifications
  - **Manager**: + Team overview, pending justifications
  - **Admin**: + System-wide statistics
- **Features**:
  - Real-time clock (updates every second)
  - Welcome message (time-based: morning/afternoon/evening)
  - Quick stats cards:
    - Hours balance (month)
    - Today's punches
    - Days worked
    - Unread notifications
  - Quick punch buttons (4 methods)
  - Today's punch list
  - Recent notifications
  - Monthly summary
  - Quick actions menu

## Time Punch

### Punch Interface (`timesheet/punch.php`)
- **Route**: canônica `GET /timesheet/punch` (alias protegido `GET /punch`)
- **Methods**:
  1. **Unique Code**: Text input (auto-uppercase)
  2. **QR Code**: Camera scanner with video preview
  3. **Facial Recognition**: Camera capture with face overlay
  4. **Fingerprint**: Biometric reader integration
- **Features**:
  - Real-time clock display
  - Punch type selection (entrada, início intervalo, fim intervalo, saída)
  - Geolocation capture (if supported)
  - Method selection cards (visual, interactive)
  - Camera/video integration
  - AJAX submission

### Receipt / Comprovante (`timesheet/receipt/{id}`)
- **Route**: `GET /timesheet/receipt/{id}`
- **Observação**: `punch/success.php` permanece apenas como artefato utilitário/legado, sem rota current direta.
- **Features**:
  - Success animation
  - Printable receipt
  - Punch details (employee, date, time, type, method)
  - NSR and hash display
  - Geolocation (if captured)
  - MTE 671/2021 compliance notice
  - Print/download button
  - Fluxo current protegido por autenticação

## Timesheet

### Monthly View (`timesheet/index.php`)
- **Route**: `/timesheet?month=YYYY-MM`
- **Features**:
  - Month selector (date input)
  - Summary cards (total hours, balance, days worked, late arrivals)
  - Daily records table:
    - Date, day of week
    - All 4 punch times (entrada, início intervalo, fim intervalo, saída)
    - Total hours, balance
    - Late arrival indicators
    - Action buttons (view details via `history` filtrado por dia, justify)
  - Color coding:
    - Normal days: default
    - Weekends: gray
    - Holidays: blue
    - Incomplete: yellow border
    - Missing: red border
  - Excel/PDF export buttons
  - Legend
  - Pagination (if needed)

### Day Detail (`timesheet/day.php`)
- **Status**: view utilitária disponível para composição futura; o fluxo atual de detalhe diário usa `/timesheet/history` com `start_date=end_date`.

## Profile

### Profile View (`profile/index.php`)
- **Route**: `/profile`
- **Sections**:
  1. **Avatar & Quick Stats**
     - Initials avatar (gradient background)
     - Role badge
     - Edit/password/biometric buttons
     - Quick stats (days worked, late arrivals, balance)

  2. **Personal Information**
     - Full name, CPF (formatted)
     - Email, phone (formatted)
     - Unique code, admission date

  3. **Work Information**
     - Department, position
     - Daily/weekly hours

  4. **Work Schedule**
     - Visual cards for 4 times (entrada, início intervalo, fim intervalo, saída)
     - Icons for each time

  5. **Biometric Status**
     - Facial recognition status
     - Fingerprint status
     - Call-to-action if not enrolled

### Biometric (`profile/biometric.php`)
- **Route**: `/profile/biometric`
- **Features**:

  **If no consent (LGPD)**:
  - Full LGPD consent form
  - 5 consent points
  - Checkbox agreement
  - Timestamp and IP display

  **If consent granted**:
  - **Facial Recognition Card**:
    - If enrolled: status, test button, delete button
    - If not enrolled: camera integration, capture button, enrollment tips

  - **Fingerprint Card**:
    - If enrolled: status, delete button
    - If not enrolled: waiting for reader, enrollment tips

  - **Revoke Consent Section**:
    - Explanation of consequences
    - Revoke button (with confirmation)

## Notifications

### Notifications List (`notifications/index.php`)
- **Route**: `/notifications?filter=all|unread|read`
- **Features**:
  - Filter tabs (all, unread, read) with counts
  - Mark all as read button
  - Notification cards:
    - Icon (type-based: success, warning, danger, info)
    - Title (bold if unread)
    - "New" badge if unread
    - Message
    - Link button (if applicable)
    - Time ago
    - Read timestamp (if read)
    - Dropdown menu (mark as read, delete)
  - Empty state (no notifications)
  - Pagination

## Error Pages

### 404 Not Found (`errors/html/error_404.php`)
- **Features**:
  - Search icon
  - 404 code (floating animation)
  - Gradient background (blue-purple)
  - Back to home button
  - Back to previous page link

### 403 Forbidden (`errors/html/error_403.php`)
- **Features**:
  - Ban icon
  - 403 code (shake animation)
  - Gradient background (pink-red)
  - Permission denied message
  - Back to home button
  - Back to previous page link

### 500 Internal Server Error (`errors/html/error_500.php`)
- **Features**:
  - Warning icon (pulsing animation)
  - 500 code (glitch animation)
  - Gradient background (blue-purple)
  - Server error message
  - Back to home button
  - Retry button
  - Reference code (timestamp + unique ID)

## Common Features

All views include:
- **Responsive Design**: Mobile-first, works on all screen sizes
- **Accessibility**: ARIA labels, semantic HTML
- **Portuguese Language**: All text in Brazilian Portuguese
- **Brazilian Formatting**:
  - CPF: 000.000.000-00
  - Phone: (00) 00000-0000
  - Date: dd/mm/yyyy
  - Time: HH:MM
  - Currency: R$ 0.000,00
- **Flash Messages**: Success, error, warning, info
- **Breadcrumbs**: Navigation trail
- **Font Awesome Icons**: Visual enhancements
- **Bootstrap 5**: Modern, responsive framework
- **Print Support**: Specific styles for printing
- **Form Validation**: Client-side validation

## JavaScript Features

- Real-time clock updates
- Password strength meter
- Camera/video integration (getUserMedia API)
- Geolocation capture (Geolocation API)
- AJAX form submissions
- Auto-hide alerts
- Confirm dialogs for deletions
- Loading spinner
- Form auto-submit (e.g., month selector)

## CSS Customizations

- Gradient backgrounds
- Custom colors (primary, success, danger, etc.)
- Hover effects (transform, shadow)
- Animations (float, shake, glitch, pulse)
- Custom badges
- Timeline visualization
- Progress bars
- Custom cards (punch methods, work schedule)

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- ES6+ JavaScript features
- CSS3 animations and transforms
- Camera/video API (getUserMedia)
- Geolocation API

## Security Considerations

- CSRF tokens in all forms
- XSS protection (esc() function)
- Password visibility toggles
- LGPD consent for biometric data
- Secure data transmission (HTTPS recommended)

## Future Enhancements

- WebSocket for real-time notifications
- QR code generation/scanning library integration
- Chart.js for statistics visualization
- Export to Excel/PDF functionality
- Internationalization (i18n) support
- Dark mode toggle
- Progressive Web App (PWA) features

## Total Files: 15 views
## Total Lines: ~4,000+ lines of HTML/PHP/JavaScript/CSS
