<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed } from 'vue'

/**
 * These props come from DashboardController via Inertia:
 * - stats: array of 4 cards
 * - recentActivity: latest uploads mapped as activity rows
 * - pendingDocs: latest DCR/OFI uploads mapped as table rows
 * - authUser: logged-in user info for header/profile
 */
const props = defineProps({
  stats: { type: Array, default: () => [] },
  recentActivity: { type: Array, default: () => [] },
  pendingDocs: { type: Array, default: () => [] },
  authUser: {
    type: Object,
    default: () => ({ name: 'User', role: 'User' }),
  },
})

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 18) return 'Good afternoon'
  return 'Good evening'
})
</script>

<template>
  <AdminLayout>
    <div class="dashboard">
      <!-- Header -->
      <div class="dash-header">
        <div class="header-left">
          <p class="welcome-sub">{{ greeting }} 👋</p>
          <h1 class="welcome-title">{{ props.authUser?.name ?? 'User' }}</h1>
          <p class="welcome-role">
            {{ props.authUser?.role ?? 'User' }} · Quality Management System
          </p>
        </div>

        <!-- Right: icons + profile -->
        <div class="header-right">
          <!-- Mail icon -->
          <button class="icon-btn" title="Messages" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path
                d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
              />
              <polyline points="22,6 12,13 2,6" />
            </svg>
          </button>

          <!-- Bell icon with red dot -->
          <button class="icon-btn has-notif" title="Notifications" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <span class="notif-dot"></span>
          </button>

          <!-- Vertical divider -->
          <div class="header-divider"></div>

          <!-- Profile card -->
          <div class="profile-card">
            <img :src="`/images/LNU_logo.png`" alt="LNU Logo" class="profile-avatar" />
            <div class="profile-info">
              <span class="profile-name">{{ props.authUser?.name ?? 'User' }}</span>
              <span class="profile-role">{{ props.authUser?.role ?? 'User' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="stats-grid">
        <div
          v-for="stat in props.stats"
          :key="stat.label"
          class="stat-card"
          :style="{ '--accent': stat.color, '--accent-bg': stat.bg }"
        >
          <div class="stat-icon-wrap">
            <svg
              v-if="stat.icon === 'docs'"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
            >
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
            </svg>

            <svg
              v-if="stat.icon === 'dcr'"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
            >
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="12" y1="18" x2="12" y2="12" />
              <line x1="9" y1="15" x2="15" y2="15" />
            </svg>

            <svg
              v-if="stat.icon === 'ofi'"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
            >
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>

            <svg
              v-if="stat.icon === 'users'"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
            >
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </div>

          <div class="stat-body">
            <span class="stat-label">{{ stat.label }}</span>
            <span class="stat-value">{{ stat.value }}</span>
            <span class="stat-change" :class="stat.trend">
              <svg
                v-if="stat.trend === 'up'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                class="trend-icon"
              >
                <polyline points="18 15 12 9 6 15" />
              </svg>
              <svg
                v-if="stat.trend === 'down'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                class="trend-icon"
              >
                <polyline points="6 9 12 15 18 9" />
              </svg>
              {{ stat.change }}
            </span>
          </div>
        </div>
      </div>

      <!-- Two Column Layout -->
      <div class="two-col">
        <!-- Recent Activity -->
        <div class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Recent Activity</h2>
            <span class="panel-badge">Live</span>
          </div>

          <ul class="activity-list">
            <li
              v-for="(act, idx) in props.recentActivity"
              :key="`${act.doc}-${idx}`"
              class="activity-item"
            >
              <div class="act-dot" :class="act.type"></div>

              <div class="act-body">
                <p class="act-text">
                  <strong>{{ act.user }}</strong> {{ act.action }}
                </p>
                <p class="act-doc">{{ act.doc }}</p>
              </div>

              <span class="act-time">{{ act.time }}</span>
            </li>

            <li v-if="props.recentActivity.length === 0" class="empty-row">
              No recent activity yet.
            </li>
          </ul>
        </div>

        <!-- Pending Documents -->
        <div class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Pending Documents</h2>
            <!-- keep as placeholder for now; later point to filtered Documents page -->
            <a href="#" class="panel-link">View all</a>
          </div>

          <div class="table-wrap">
            <table class="doc-table">
              <thead>
                <tr>
                  <th>Document ID</th>
                  <th>Type</th>
                  <th>Department</th>
                  <th>Status</th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="doc in props.pendingDocs" :key="doc.id">
                  <td class="doc-id">{{ doc.id }}</td>
                  <td>
                    <span class="type-badge" :class="doc.type.toLowerCase()">
                      {{ doc.type }}
                    </span>
                  </td>
                  <td class="doc-dept">{{ doc.dept }}</td>
                  <td>
                    <span class="status-badge">{{ doc.status }}</span>
                  </td>
                </tr>

                <tr v-if="props.pendingDocs.length === 0">
                  <td colspan="4" class="empty-cell">No pending documents yet.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&family=Sora:wght@400;600;700&display=swap');

.dashboard {
  padding: 36px 40px;
  font-family: 'DM Sans', sans-serif;
  background: #f1f5f9;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 28px;
}

/* ── Header ── */
.dash-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.welcome-sub {
  font-size: 14px;
  color: #64748b;
  margin: 0 0 4px;
}

.welcome-title {
  font-family: 'Sora', sans-serif;
  font-size: 28px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 4px;
  letter-spacing: -0.5px;
}

.welcome-role {
  font-size: 13px;
  color: #94a3b8;
  margin: 0;
}

/* ── Right cluster ── */
.header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Icon buttons */
.icon-btn {
  position: relative;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #fff;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s, box-shadow 0.15s;
  flex-shrink: 0;
  padding: 0;
}

.icon-btn:hover {
  background: #f8fafc;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.icon-btn svg {
  width: 17px;
  height: 17px;
  stroke: #64748b;
  transition: stroke 0.15s;
}

.icon-btn:hover svg {
  stroke: #1e293b;
}

/* Red notification dot on bell */
.notif-dot {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #ef4444;
  border: 1.5px solid #fff;
}

/* Thin vertical divider */
.header-divider {
  width: 1px;
  height: 30px;
  background: #e2e8f0;
  margin: 0 4px;
  flex-shrink: 0;
}

/* Profile card */
.profile-card {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 8px 14px 8px 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  cursor: pointer;
  transition: box-shadow 0.15s;
}

.profile-card:hover {
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
}

.profile-avatar {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  object-fit: contain;
  background: #0d1424;
  padding: 3px;
  flex-shrink: 0;
}

.profile-name {
  display: block;
  font-size: 13.5px;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
}

.profile-role {
  display: block;
  font-size: 11.5px;
  color: #94a3b8;
}

/* ── Stats ── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
}

.stat-card {
  background: #fff;
  border-radius: 16px;
  padding: 22px 22px 18px;
  display: flex;
  align-items: flex-start;
  gap: 16px;
  border: 1px solid #e8edf3;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s, box-shadow 0.2s;
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--accent);
  opacity: 0.7;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
}

.stat-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--accent-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon-wrap svg {
  width: 20px;
  height: 20px;
  stroke: var(--accent);
}

.stat-body {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
}

.stat-label {
  font-size: 12px;
  color: #94a3b8;
  font-weight: 500;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.stat-value {
  font-family: 'Sora', sans-serif;
  font-size: 30px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1;
  margin: 4px 0;
}

.stat-change {
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 3px;
  color: #94a3b8;
}

.stat-change.up {
  color: #10b981;
}
.stat-change.down {
  color: #f59e0b;
}

.trend-icon {
  width: 13px;
  height: 13px;
}

/* ── Two Column ── */
.two-col {
  display: grid;
  grid-template-columns: 1fr 1.3fr;
  gap: 18px;
}

/* ── Panel ── */
.panel {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e8edf3;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f1f5f9;
}

.panel-title {
  font-family: 'Sora', sans-serif;
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.panel-badge {
  font-size: 11px;
  font-weight: 600;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 3px 9px;
  border-radius: 20px;
  letter-spacing: 0.04em;
  display: flex;
  align-items: center;
  gap: 5px;
}

.panel-badge::before {
  content: '';
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
  animation: pulse 1.8s infinite;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.3;
  }
}

.panel-link {
  font-size: 13px;
  color: #6366f1;
  text-decoration: none;
  font-weight: 500;
}
.panel-link:hover {
  text-decoration: underline;
}

/* ── Activity List ── */
.activity-list {
  list-style: none;
  margin: 0;
  padding: 8px 0;
}

.activity-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 13px 24px;
  border-bottom: 1px solid #f8fafc;
  transition: background 0.15s;
}

.activity-item:last-child {
  border-bottom: none;
}
.activity-item:hover {
  background: #f8fafc;
}

.act-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  margin-top: 5px;
  flex-shrink: 0;
}

.act-dot.dcr {
  background: #6366f1;
}
.act-dot.ofi {
  background: #10b981;
}
.act-dot.approve {
  background: #3b82f6;
}

.act-body {
  flex: 1;
}

.act-text {
  font-size: 13px;
  color: #334155;
  margin: 0 0 2px;
  line-height: 1.4;
}
.act-text strong {
  color: #0f172a;
  font-weight: 600;
}

.act-doc {
  font-size: 11.5px;
  color: #94a3b8;
  margin: 0;
  font-family: 'DM Mono', monospace;
}

.act-time {
  font-size: 11.5px;
  color: #94a3b8;
  white-space: nowrap;
  flex-shrink: 0;
  margin-top: 2px;
}

.empty-row {
  padding: 14px 24px;
  color: #94a3b8;
  font-size: 12.5px;
}

/* ── Table ── */
.table-wrap {
  overflow-x: auto;
}

.doc-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.doc-table thead tr {
  background: #f8fafc;
}

.doc-table th {
  padding: 11px 20px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-bottom: 1px solid #f1f5f9;
}

.doc-table td {
  padding: 13px 20px;
  color: #334155;
  border-bottom: 1px solid #f8fafc;
}
.doc-table tbody tr:last-child td {
  border-bottom: none;
}
.doc-table tbody tr:hover td {
  background: #f8fafc;
}

.doc-id {
  font-family: 'DM Mono', monospace;
  font-size: 12.5px;
  color: #0f172a;
  font-weight: 500;
}
.doc-dept {
  color: #64748b;
}

.type-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 6px;
  letter-spacing: 0.04em;
}
.type-badge.dcr {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}
.type-badge.ofi {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-badge {
  font-size: 11.5px;
  color: #f59e0b;
  background: rgba(245, 158, 11, 0.1);
  padding: 3px 10px;
  border-radius: 20px;
  font-weight: 500;
}

.empty-cell {
  padding: 18px 20px;
  color: #94a3b8;
  font-size: 12.5px;
}

/* ── Responsive ── */
@media (max-width: 1100px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .two-col {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 560px) {
  .dashboard {
    padding: 22px 18px;
  }
  .stats-grid {
    grid-template-columns: 1fr;
  }
  .dash-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 14px;
  }
  .header-right {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>