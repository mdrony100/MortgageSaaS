<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import * as pdfjs from 'pdfjs-dist'
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
import { smoothScroll } from '../customerLanguage'
pdfjs.GlobalWorkerOptions.workerSrc = workerUrl
const props = defineProps<{ version: any; page?: number; region?: { x: number; y: number; width: number; height: number; normalized?: boolean }; focus?: number }>()
const canvas = ref<HTMLCanvasElement>()
const error = ref('')
const busy = ref(false)
const current = ref(props.page || 1)
const total = ref(1)
const pageWidth = ref(612)
const pageHeight = ref(792)
let doc: pdfjs.PDFDocumentProxy | undefined
let task: pdfjs.PDFDocumentLoadingTask | undefined
let renderTask: pdfjs.RenderTask | undefined
let paintChain: Promise<void> = Promise.resolve()

function scrollToMark() {
  const scroller = canvas.value?.closest('.canvas-scroll') as HTMLElement | null
  if (!scroller) return
  const mark = scroller.querySelector('.source-highlight') as HTMLElement | null
  const behavior = smoothScroll()
  if (!mark) {
    scroller.scrollTo({ top: 0, behavior })
    return
  }
  const page = mark.offsetParent as HTMLElement | null
  const top = (page?.offsetTop || 0) + mark.offsetTop - scroller.clientHeight / 3
  scroller.scrollTo({ top: Math.max(0, top), behavior })
}

function scheduleRender() {
  paintChain = paintChain.then(() => render()).catch(() => {})
}

async function render() {
  if (!doc || !canvas.value) return
  const pageNo = current.value
  try {
    busy.value = true
    const page = await doc.getPage(pageNo)
    if (!canvas.value || pageNo !== current.value) return
    const vp = page.getViewport({ scale: 1.15 })
    pageWidth.value = vp.width / 1.15
    pageHeight.value = vp.height / 1.15
    if (canvas.value.width !== vp.width) canvas.value.width = vp.width
    if (canvas.value.height !== vp.height) canvas.value.height = vp.height
    renderTask = page.render({ canvas: canvas.value, viewport: vp })
    await renderTask.promise
    await nextTick()
    scrollToMark()
  } catch (e: any) {
    if (e?.name !== 'RenderingCancelledException') error.value = 'Preview unavailable. Try the authenticated download.'
  } finally {
    busy.value = false
  }
}

async function load() {
  error.value = ''
  renderTask?.cancel()
  await task?.destroy()
  doc = undefined
  current.value = props.page || 1
  if (props.version.mime !== 'application/pdf') return
  try {
    task = pdfjs.getDocument({ url: '/api/document-versions/' + props.version.id + '/content', withCredentials: true, enableXfa: false })
    doc = await task.promise
    total.value = doc.numPages
    scheduleRender()
  } catch {
    error.value = 'Access denied or document could not be previewed.'
  }
}

onMounted(load)
watch(() => props.version.id, load)
watch(current, () => scheduleRender())
watch(() => [props.page, props.focus], () => {
  if (props.page && props.page !== current.value) current.value = props.page
  else scheduleRender()
})
onBeforeUnmount(() => { renderTask?.cancel(); task?.destroy().catch(() => {}) })
</script>
<template>
<section class="viewer">
  <div class="viewer-toolbar"><strong>{{ version.original_name }}</strong><a :href="'/api/document-versions/' + version.id + '/content?download=1'">Download original</a></div>
  <p class="micro warning">Original bytes · {{ version.latest_scan_status || version.scan_status }}</p>
  <p v-if="error" role="alert">{{ error }}</p>
  <p v-if="busy" aria-live="polite">Rendering document…</p>
  <div class="canvas-scroll" v-if="version.mime === 'application/pdf'">
    <div class="pdf-page">
      <canvas ref="canvas" aria-label="PDF source page" />
      <div v-if="region" class="source-highlight" aria-label="Issue location on this page" :style="{ left: (region.x / (region.normalized ? 1 : pageWidth) * 100) + '%', top: (region.y / (region.normalized ? 1 : pageHeight) * 100) + '%', width: (region.width / (region.normalized ? 1 : pageWidth) * 100) + '%', height: (region.height / (region.normalized ? 1 : pageHeight) * 100) + '%' }"></div>
    </div>
  </div>
  <img v-else :src="'/api/document-versions/' + version.id + '/content'" alt="Uploaded source document" class="source-image" />
  <div v-if="version.mime === 'application/pdf'" class="viewer-toolbar">
    <button :disabled="current <= 1 || busy" @click="current--">Previous</button>
    <span>Page {{ current }} of {{ total }}</span>
    <button :disabled="current >= total || busy" @click="current++">Next</button>
  </div>
</section>
</template>
