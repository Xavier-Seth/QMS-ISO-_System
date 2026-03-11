<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
  username: '',
  password: '',
  remember: false,
});

const showPassword = ref(false);

const submit = () => {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
};
</script>

<template>
  <div class="login-wrapper">
    <header class="header">
      <img :src="'/images/QMS_Logo.png'" alt="Logo" class="logo" />
      <h2 class="brand-name">Quality Management System</h2>
    </header>

    <main class="main-content">
      <div class="form-container">
        <h1 class="sign-in-title">Sign In</h1>

        <form @submit.prevent="submit" class="login-form">
          <div class="input-group">
            <input
              v-model="form.username"
              type="text"
              placeholder="Username:"
              class="form-input"
              :class="{ 'input-error': form.errors.username }"
            />
            <div v-if="form.errors.username" class="error-msg">
              {{ form.errors.username }}
            </div>
          </div>

          <div class="input-group">
            <div class="password-wrapper">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Password:"
                class="form-input password-input"
                :class="{ 'input-error': form.errors.password }"
              />

              <button
                type="button"
                class="toggle-password"
                @click="showPassword = !showPassword"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
              >
                <svg
                  v-if="!showPassword"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.8"
                  stroke="currentColor"
                  class="eye-icon"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 4.5 12 4.5
                    c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .638
                    C20.577 16.49 16.64 19.5 12 19.5
                    c-4.638 0-8.573-3.007-9.964-7.178z"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                </svg>

                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.8"
                  stroke="currentColor"
                  class="eye-icon"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 3l18 18"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M10.584 10.587A2.25 2.25 0 0013.5 13.5"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9.88 5.09A9.953 9.953 0 0112 4.5
                    c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .638
                    a11.983 11.983 0 01-3.12 4.568M6.228 6.228
                    A11.965 11.965 0 002.037 11.68a1.012 1.012 0 000 .644
                    C3.423 16.493 7.36 19.5 12 19.5
                    a9.95 9.95 0 005.205-1.462"
                  />
                </svg>
              </button>
            </div>

            <div v-if="form.errors.password" class="error-msg">
              {{ form.errors.password }}
            </div>
          </div>

          <div class="form-row">
            <label class="remember-label">
              <input v-model="form.remember" type="checkbox" class="remember-checkbox" />
              <span>Remember me</span>
            </label>
            <a href="#" class="forgot-link">Forgot Password?</a>
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="login-btn"
          >
            {{ form.processing ? 'LOGGING IN...' : 'LOGIN' }}
          </button>
        </form>
      </div>
    </main>

    <footer class="footer">
      <div class="footer-gold"></div>
      <div class="footer-dark"></div>
    </footer>
  </div>
</template>

<style scoped>
.login-wrapper {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: #F3F4F6;
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

/* ── Header ── */
.header {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 20px 32px;
}

.logo {
  width: 52px;
  height: 52px;
  object-fit: contain;
}

.brand-name {
  font-size: 16px;
  font-weight: 500;
  color: #1a1f2b;
  letter-spacing: -0.01em;
  margin: 0;
}

/* ── Main ── */
.main-content {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 24px 80px;
}

.form-container {
  width: 100%;
  max-width: 310px;
}

.sign-in-title {
  font-size: 20px;
  font-weight: 500;
  font-family: 'Inter', sans-serif;
  color: #1a1f2b;
  text-align: center;
  margin: 0 0 20px;
  letter-spacing: -0.01em;
}

/* ── Form ── */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.input-group {
  display: flex;
  flex-direction: column;
}

.form-input {
  width: 100%;
  background: #DCDCDC;
  border: none;
  border-radius: 10px;
  padding: 12px 18px;
  font-size: 14px;
  color: #374151;
  font-family: 'Inter', sans-serif;
  outline: none;
  box-shadow: inset 0 1px 3px rgba(0,0,0,0.08);
  transition: box-shadow 0.2s, ring 0.2s;
  box-sizing: border-box;
}

.form-input::placeholder {
  color: #9CA3AF;
}

.form-input:focus {
  box-shadow: inset 0 1px 3px rgba(0,0,0,0.08), 0 0 0 2px rgba(201,168,76,0.35);
}

.input-error {
  box-shadow: inset 0 1px 3px rgba(0,0,0,0.08), 0 0 0 1px #ef4444;
}

.error-msg {
  color: #ef4444;
  font-size: 11px;
  margin-top: 4px;
  margin-left: 4px;
}

/* ── Password toggle ── */
.password-wrapper {
  position: relative;
}

.password-input {
  padding-right: 46px;
}

.toggle-password {
  position: absolute;
  top: 50%;
  right: 14px;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  cursor: pointer;
  transition: color 0.15s ease;
}

.toggle-password:hover {
  color: #1a1f2b;
}

.eye-icon {
  width: 18px;
  height: 18px;
}

/* ── Remember / Forgot ── */
.form-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 2px;
  margin-top: 2px;
}

.remember-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #6b7280;
  cursor: pointer;
  user-select: none;
}

.remember-checkbox {
  width: 14px;
  height: 14px;
  border-radius: 3px;
  border: 1px solid #d1d5db;
  accent-color: #1a1f2b;
  cursor: pointer;
}

.forgot-link {
  font-size: 12px;
  color: #6b7280;
  text-decoration: none;
  transition: color 0.15s;
}

.forgot-link:hover {
  color: #1a1f2b;
  text-decoration: underline;
}

/* ── Login Button ── */
.login-btn {
  width: 100%;
  background: #1A1F2B;
  color: #ffffff;
  font-weight: 700;
  font-size: 12px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  padding: 16px;
  border: none;
  border-radius: 10px;
  margin-top: 20px;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,0.18);
  transition: background 0.2s, transform 0.1s, opacity 0.2s;
}

.login-btn:hover {
  background: #111521;
}

.login-btn:active {
  transform: scale(0.985);
}

.login-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ── Footer ── */
.footer {
  width: 100%;
  flex-shrink: 0;
}

.footer-gold {
  height: 6px;
  background: #C9A84C;
  width: 100%;
}

.footer-dark {
  height: 50px;
  background: #151b2b;
  width: 100%;
}
</style>