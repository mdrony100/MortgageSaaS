<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../stores/auth'
import { api } from '../api'

const auth = useAuth()
const router = useRouter()
const borrowers = ref<any[]>([])
const error = ref('')
const busy = ref(false)
const form = ref({ name: '', reference: '', borrower_id: '', module: 'bank', is_demo: false, new_borrower: '' })
const editable = ['owner', 'admin', 'underwriter', 'processor'].includes(auth.membership?.role || '')

onMounted(async () => {
  if (!editable) return
  try { borrowers.value = await api('/api/organizations/' + auth.orgId + '/borrowers') }
  catch (e: any) { error.value = e.message }
})

function cancel() {
  if (window.history.state?.back) router.back()
  else router.push('/files')
}

async function create() {
  busy.value = true
  error.value = ''
  try {
    let id = Number(form.value.borrower_id)
    if (form.value.new_borrower) id = (await api('/api/organizations/' + auth.orgId + '/borrowers', 'POST', { name: form.value.new_borrower })).id
    const file = await api('/api/organizations/' + auth.orgId + '/files', 'POST', { ...form.value, borrower_id: id })
    router.push('/files/' + file.id)
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
<section>
  <RouterLink to="/files" class="back-link">Back to loan files</RouterLink>
  <div class="page-heading">
    <div>
      <span class="eyebrow">Loan files</span>
      <h1>Create a loan file</h1>
      <p class="muted">After you create it, upload the bank statements or the appraisal. The same fields and checks apply as before.</p>
    </div>
  </div>
  <section v-if="!editable" class="card" role="alert">
    <h2>You cannot create loan files</h2>
    <p>Your role can open files that are shared with you. Ask an owner or admin to create one.</p>
    <RouterLink class="button" to="/files">Back to loan files</RouterLink>
  </section>
  <form v-else class="card form-stack create-file" @submit.prevent="create">
    <label>Loan file name<input v-model="form.name" required maxlength="150" placeholder="For example, Sample Street refinance"/></label>
    <label>Your reference number (optional)<input v-model="form.reference" maxlength="80"/></label>
    <label>Borrower already in this company
      <select v-model="form.borrower_id">
        <option value="">Choose a borrower</option>
        <option v-for="borrower in borrowers" :key="borrower.id" :value="borrower.id">{{ borrower.name }}</option>
      </select>
    </label>
    <label>Or type a new borrower name<input v-model="form.new_borrower" placeholder="Fictional borrower name" maxlength="120"/></label>
    <label>What are you reviewing?
      <select v-model="form.module">
        <option value="bank">Bank statements</option>
        <option value="appraisal">Appraisal</option>
        <option value="dscr">Rental income</option>
      </select>
    </label>
    <label class="check"><input type="checkbox" v-model="form.is_demo"/>This is a practice file with fictional documents</label>
    <p class="micro">Do not upload a real borrower’s documents in a practice file.</p>
    <p v-if="error" class="alert error" role="alert">{{ error }}</p>
    <div class="actions">
      <button type="button" @click="cancel">Cancel</button>
      <button class="primary" :disabled="busy">Create file</button>
    </div>
  </form>
</section>
</template>
