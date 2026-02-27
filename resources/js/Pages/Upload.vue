<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const form = useForm({
  document_type_id: '',
  file: null,
})

function submit() {
  form.post('/documents/' + form.document_type_id + '/upload', {
    forceFormData: true,
  })
}
</script>

<template>
  <AdminLayout>
    <div class="page">
      <div class="card">
        <h1 class="title">Upload Document</h1>

        <!-- Document Type ID -->
        <!-- For now simple input.
             Later you can replace with dropdown from backend -->
        <div class="form-group">
          <label>Document Type ID</label>
          <input
            type="number"
            v-model="form.document_type_id"
            placeholder="Enter Document Type ID"
          />
        </div>

        <!-- File Upload -->
        <div class="form-group">
          <label>Select File</label>
          <input
            type="file"
            @change="e => form.file = e.target.files[0]"
          />
        </div>

        <!-- Errors -->
        <div v-if="form.errors.file" class="error">
          {{ form.errors.file }}
        </div>

        <button
          @click="submit"
          :disabled="form.processing"
          class="upload-btn"
        >
          {{ form.processing ? 'Uploading...' : 'Upload' }}
        </button>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.page {
  padding: 40px;
}

.card {
  max-width: 500px;
  background: white;
  padding: 30px;
  border-radius: 14px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.title {
  font-size: 22px;
  font-weight: 600;
  margin-bottom: 25px;
}

.form-group {
  display: flex;
  flex-direction: column;
  margin-bottom: 20px;
}

label {
  font-size: 14px;
  margin-bottom: 6px;
  color: #475569;
}

input {
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

input:focus {
  outline: none;
  border-color: #6366f1;
}

.upload-btn {
  width: 100%;
  padding: 12px;
  border-radius: 8px;
  border: none;
  background: #6366f1;
  color: white;
  font-weight: 500;
  cursor: pointer;
  transition: 0.2s;
}

.upload-btn:hover {
  background: #4f46e5;
}

.error {
  color: #ef4444;
  font-size: 13px;
  margin-bottom: 10px;
}
</style>