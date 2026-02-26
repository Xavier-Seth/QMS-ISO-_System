<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const currentPath = computed(() => page.url)

// dropdown states
const openCreateDocuments = ref(false)
const openManual = ref(false)

// exact match
const isActive = (href) => currentPath.value === href

// helpful for dropdown sections (any route starting with...)
const isStartsWith = (prefix) => currentPath.value.startsWith(prefix)
</script>

<template>
  <aside class="sidebar">

    <!-- Logo Section -->
    <div class="logo-section">
      <img :src="`/images/LNU_logo.png`" alt="LNU Logo" class="logo-img" />
      <div class="brand-text">
        <span>Quality Management</span>
        <span>System</span>
      </div>
    </div>

    <div class="divider"></div>

    <nav class="nav">

      <!-- Dashboard -->
      <Link
        href="/dashboard"
        class="nav-item"
        :class="{ active: isActive('/dashboard') }"
      >
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <rect x="3" y="3" width="7" height="7" rx="1.5"/>
          <rect x="14" y="3" width="7" height="7" rx="1.5"/>
          <rect x="3" y="14" width="7" height="7" rx="1.5"/>
          <rect x="14" y="14" width="7" height="7" rx="1.5"/>
        </svg>
        <span>Dashboard</span>
      </Link>

      <!-- Create Documents Dropdown -->
      <div class="dropdown-wrapper">
        <button
          @click="openCreateDocuments = !openCreateDocuments"
          class="nav-item dropdown-trigger"
          :class="{ active: openCreateDocuments || isStartsWith('/dcr') || isStartsWith('/ofi') }"
          type="button"
        >
          <div class="nav-item-left">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="12" y1="18" x2="12" y2="12"/>
              <line x1="9" y1="15" x2="15" y2="15"/>
            </svg>
            <span>Create Documents</span>
          </div>

          <svg class="chevron" :class="{ rotated: openCreateDocuments }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
        </button>

        <div v-if="openCreateDocuments" class="dropdown-menu">
          <Link
            href="/dcr"
            class="dropdown-item"
            :class="{ 'dropdown-item-active': isActive('/dcr') }"
          >
            Create DCR Forms
          </Link>

          <Link
            href="/ofi-form"
            class="dropdown-item"
            :class="{ 'dropdown-item-active': isActive('/ofi-form') }"
          >
            Create OFI Forms
          </Link>
        </div>
      </div>

      <!-- Documents -->
      <Link
        href="/documents"
        class="nav-item"
        :class="{ active: isActive('/documents') }"
      >
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
        <span>Documents</span>
      </Link>

      <!-- ✅ Manual Dropdown (NEW) -->
      <div class="dropdown-wrapper">
        <button
          @click="openManual = !openManual"
          class="nav-item dropdown-trigger"
          :class="{ active: openManual || isStartsWith('/manual') }"
          type="button"
        >
          <div class="nav-item-left">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
              <line x1="8" y1="7" x2="18" y2="7"/>
              <line x1="8" y1="11" x2="18" y2="11"/>
            </svg>
            <span>Manual</span>
          </div>

          <svg class="chevron" :class="{ rotated: openManual }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
        </button>

        <div v-if="openManual" class="dropdown-menu">
          <!-- change these hrefs to your real routes -->
          <Link href="/manual/asm" class="dropdown-item" :class="{ 'dropdown-item-active': isActive('/manual/asm') }">ASM</Link>
          <Link href="/manual/qsm" class="dropdown-item" :class="{ 'dropdown-item-active': isActive('/manual/qsm') }">QSM</Link>
          <Link href="/manual/hrm" class="dropdown-item" :class="{ 'dropdown-item-active': isActive('/manual/hrm') }">HRM</Link>
          <Link href="/manual/riem" class="dropdown-item" :class="{ 'dropdown-item-active': isActive('/manual/riem') }">RIEM</Link>
          <Link href="/manual/rem" class="dropdown-item" :class="{ 'dropdown-item-active': isActive('/manual/rem') }">REM</Link>
        </div>
      </div>

      <!-- ✅ Upload (NEW) -->
      <Link
        href="/upload"
        class="nav-item"
        :class="{ active: isActive('/upload') }"
      >
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="17 8 12 3 7 8"/>
          <line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <span>Upload</span>
      </Link>

      <!-- Inbox -->
      <Link href="/inbox" class="nav-item" :class="{ active: isActive('/inbox') }">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/>
          <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
        </svg>
        <span>Inbox</span>
      </Link>

      <!-- Users -->
      <Link href="/users" class="nav-item" :class="{ active: isActive('/users') }">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
        <span>Users</span>
      </Link>

      <!-- Logs -->
      <Link href="/logs" class="nav-item" :class="{ active: isActive('/logs') }">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
        <span>Logs</span>
      </Link>

      <!-- Settings -->
      <Link href="/settings" class="nav-item" :class="{ active: isActive('/settings') }">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
        <span>Settings</span>
      </Link>

    </nav>

    <!-- Logout -->
    <div class="logout-section">
      <Link href="/logout" method="post" as="button" class="logout-btn">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        <span>Log out</span>
      </Link>
    </div>

  </aside>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap');

/* ─────────────────────────────────────────────────────
   SIDEBAR
   position: fixed  →  never scrolls with the page
   height: 100vh    →  always full screen height
   flex-column      →  logo + nav (scrollable) + logout (pinned)
───────────────────────────────────────────────────── */
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  width: 280px;
  height: 100vh;
  background: linear-gradient(180deg, #0d1424 0%, #111827 50%, #0f1e2e 100%);
  color: #e2e8f0;
  display: flex;
  flex-direction: column;
  font-family: 'DM Sans', sans-serif;
  border-right: 1px solid rgba(255,255,255,0.04);
  z-index: 100;
  /* No overflow on sidebar itself */
  overflow: hidden;
}

/* ── Logo (fixed, never scrolls) ── */
.logo-section {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 28px 24px 24px;
}

.logo-img {
  width: 52px;
  height: 52px;
  object-fit: contain;
  flex-shrink: 0;
  filter: drop-shadow(0 2px 8px rgba(0,0,0,0.4));
}

.brand-text {
  display: flex;
  flex-direction: column;
  font-size: 13.5px;
  font-weight: 500;
  line-height: 1.4;
  color: #cbd5e1;
  letter-spacing: 0.01em;
}

/* ── Divider ── */
.divider {
  flex-shrink: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(148,163,184,0.15), transparent);
  margin: 0 20px 20px;
}

/* ─────────────────────────────────────────────────────
   NAV
   flex: 1 + overflow-y: auto  →  this section scrolls
   if items overflow (e.g. dropdown open), but ONLY
   this section — logo and logout stay fixed.
───────────────────────────────────────────────────── */
.nav {
  flex: 1;
  min-height: 0;           /* required for overflow to work in flex */
  overflow-y: auto;
  overflow-x: hidden;
  padding: 0 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;

  /* Hide scrollbar visually but keep it functional */
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.nav::-webkit-scrollbar { display: none; }

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 14px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 400;
  color: #94a3b8;
  text-decoration: none;
  transition: background 0.18s, color 0.18s;
  cursor: pointer;
  border: none;
  background: transparent;
  width: 100%;
  text-align: left;
  letter-spacing: 0.01em;
  flex-shrink: 0;
}

.nav-item:hover {
  background: rgba(148, 163, 184, 0.08);
  color: #e2e8f0;
}

.nav-item.active {
  background: rgba(99, 130, 255, 0.15);
  color: #ffffff;
  font-weight: 500;
}

.nav-item.active .nav-icon {
  stroke: #818cf8;
}

.nav-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  stroke: #64748b;
  transition: stroke 0.18s;
}

.nav-item:hover .nav-icon {
  stroke: #94a3b8;
}

/* ── Dropdown ── */
.dropdown-wrapper {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex-shrink: 0;
}

.dropdown-trigger {
  justify-content: space-between;
}

.nav-item-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.chevron {
  width: 15px;
  height: 15px;
  stroke: #64748b;
  transition: transform 0.25s ease;
  flex-shrink: 0;
}

.chevron.rotated {
  transform: rotate(180deg);
}

.dropdown-menu {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 2px 0 4px 0;
}

.dropdown-item {
  display: block;
  padding: 9px 14px 9px 44px;
  font-size: 13px;
  color: #64748b;
  text-decoration: none;
  border-radius: 8px;
  transition: color 0.15s, background 0.15s;
  letter-spacing: 0.01em;
  font-family: 'DM Sans', sans-serif;
}

.dropdown-item:hover {
  color: #cbd5e1;
  background: rgba(148, 163, 184, 0.06);
}

.dropdown-item-active {
  color: #a5b4fc;
  background: rgba(99, 130, 255, 0.08);
}

/* ─────────────────────────────────────────────────────
   LOGOUT — always pinned to the bottom of the sidebar.
   flex-shrink: 0 prevents it from being compressed.
   The border-top gives a visual separator.
───────────────────────────────────────────────────── */
.logout-section {
  flex-shrink: 0;
  padding: 16px 12px;
  border-top: 1px solid rgba(148,163,184,0.08);
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 11px 14px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 400;
  color: #64748b;
  background: transparent;
  border: none;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.18s, color 0.18s;
  letter-spacing: 0.01em;
  font-family: 'DM Sans', sans-serif;
}

.logout-btn:hover {
  background: rgba(239, 68, 68, 0.12);
  color: #f87171;
}

.logout-btn:hover .nav-icon {
  stroke: #f87171;
}

.logout-btn .nav-icon {
  stroke: #64748b;
  transition: stroke 0.18s;
}
</style>