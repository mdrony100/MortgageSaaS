<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import * as pdfjs from 'pdfjs-dist'
import type { PDFPageProxy, PageViewport } from 'pdfjs-dist'
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
import { ChevronLeft, ChevronRight, Download, ZoomIn, ZoomOut } from 'lucide-vue-next'
import StatusBadge from './StatusBadge.vue'
import { integrityCode, smoothScroll } from '../customerLanguage'

pdfjs.GlobalWorkerOptions.workerSrc = workerUrl

const props = defineProps<{
  version: any
  page?: number
  region?: { x: number; y: number; width: number; height: number; normalized?: boolean }
  focus?: number
}>()

const root = ref<HTMLElement>()
const scroller = ref<HTMLElement>()
const canvas = ref<HTMLCanvasElement>()
const textLayerEl = ref<HTMLDivElement>()
const pageEl = ref<HTMLElement>()
const error = ref('')
const busy = ref(false)
const current = ref(props.page && props.page > 0 ? props.page : 1)
const total = ref(0)
const pageWidth = ref(612)
const pageHeight = ref(792)
const fitMode = ref<'custom' | 'width' | 'page'>('width')
const userScale = ref(1.15)
const appliedScale = ref(1.15)
const pageDraft = ref(String(current.value))
const thumbCanvases = new Map<number, HTMLCanvasElement>()
const thumbBinders = new Map<number, (el: any) => void>()

let doc: pdfjs.PDFDocumentProxy | undefined
let task: pdfjs.PDFDocumentLoadingTask | undefined
let renderTask: pdfjs.RenderTask | undefined
let textLayer: InstanceType<typeof pdfjs.TextLayer> | undefined
let paintChain: Promise<void> = Promise.resolve()
let generation = 0
let thumbToken = 0
let resizeObserver: ResizeObserver | undefined
let resizeTimer = 0

const integrity = computed(() => integrityCode(props.version))
const downloadHref = computed(() => '/api/document-versions/' + props.version.id + '/content?download=1')
const isPdf = computed(() => props.version?.mime === 'application/pdf')
const zoomPercent = computed(() => Math.round(appliedScale.value * 100))
const showMark = computed(() => !!props.region && (!props.page || props.page === current.value) && pageWidth.value > 0)
const markStyle = computed(() => {
  const region = props.region
  if (!region) return undefined
  const nx = region.normalized ? 1 : pageWidth.value
  const ny = region.normalized ? 1 : pageHeight.value
  return {
    left: (region.x / nx) * 100 + '%',
    top: (region.y / ny) * 100 + '%',
    width: (region.width / nx) * 100 + '%',
    height: (region.height / ny) * 100 + '%',
  }
})

function clamp(scale: number) {
  return Math.min(4, Math.max(0.5, Math.round(scale * 100) / 100))
}

function bindThumb(pageNo: number) {
  let binder = thumbBinders.get(pageNo)
  if (!binder) {
    binder = (el: any) => {
      if (el instanceof HTMLCanvasElement) thumbCanvases.set(pageNo, el)
      else if (!el) thumbCanvases.delete(pageNo)
    }
    thumbBinders.set(pageNo, binder)
  }
  return binder
}

function scrollToMark() {
  const scrollerEl = scroller.value
  if (!scrollerEl) return
  const mark = scrollerEl.querySelector('.source-highlight') as HTMLElement | null
  const behavior = smoothScroll()
  if (!mark) return
  const page = mark.offsetParent as HTMLElement | null
  const top = (page?.offsetTop || 0) + mark.offsetTop - scrollerEl.clientHeight / 3
  scrollerEl.scrollTo({ top: Math.max(0, top), behavior })
}

function resolveScale(baseWidth: number, baseHeight: number) {
  if (fitMode.value === 'custom') return clamp(userScale.value)
  const box = scroller.value
  const availW = Math.max(160, (box?.clientWidth || 640) - 48)
  const availH = Math.max(200, (box?.clientHeight || 680) - 48)
  const widthScale = availW / baseWidth
  const heightScale = availH / baseHeight
  return clamp(fitMode.value === 'width' ? widthScale : Math.min(widthScale, heightScale))
}

function scheduleRender() {
  paintChain = paintChain.then(() => render()).catch(() => {})
}

async function paintText(page: PDFPageProxy, viewport: PageViewport, gen: number) {
  const container = textLayerEl.value
  if (!container || typeof pdfjs.TextLayer !== 'function' || gen !== generation) return
  textLayer?.cancel()
  container.replaceChildren()
  try {
    const layer = new pdfjs.TextLayer({
      textContentSource: page.streamTextContent({ includeMarkedContent: false }),
      container,
      viewport,
    })
    textLayer = layer
    await layer.render()
  } catch (e: any) {
    if (e?.name !== 'RenderingCancelledException' && gen === generation) textLayer = undefined
  }
}

async function render() {
  if (!doc || !canvas.value || !isPdf.value) return
  const pageNo = current.value
  const gen = generation
  try {
    busy.value = true
    const page = await doc.getPage(pageNo)
    if (!canvas.value || pageNo !== current.value || gen !== generation) return
    const base = page.getViewport({ scale: 1 })
    pageWidth.value = base.width
    pageHeight.value = base.height
    const scale = resolveScale(base.width, base.height)
    appliedScale.value = scale
    const dpr = Math.min(window.devicePixelRatio || 1, 2)
    const pixelViewport = page.getViewport({ scale: scale * dpr })
    const cssW = Math.max(1, Math.floor(base.width * scale))
    const cssH = Math.max(1, Math.floor(base.height * scale))
    if (pageEl.value) {
      pageEl.value.style.width = cssW + 'px'
      pageEl.value.style.height = cssH + 'px'
      pageEl.value.style.setProperty('--total-scale-factor', String(scale))
      pageEl.value.style.setProperty('--scale-round-x', '1px')
      pageEl.value.style.setProperty('--scale-round-y', '1px')
    }
    canvas.value.width = Math.ceil(pixelViewport.width)
    canvas.value.height = Math.ceil(pixelViewport.height)
    canvas.value.style.width = cssW + 'px'
    canvas.value.style.height = cssH + 'px'
    renderTask?.cancel()
    renderTask = page.render({ canvas: canvas.value, viewport: pixelViewport })
    await renderTask.promise
    if (pageNo !== current.value || gen !== generation) return
    await paintText(page, page.getViewport({ scale }), gen)
    pageDraft.value = String(pageNo)
    await nextTick()
    scrollToMark()
  } catch (e: any) {
    if (e?.name !== 'RenderingCancelledException' && gen === generation) error.value = 'Preview unavailable. Try the authenticated download.'
  } finally {
    if (gen === generation) busy.value = false
  }
}

async function paintThumb(token: number, pageNo: number) {
  if (!doc || token !== thumbToken) return
  while (busy.value && token === thumbToken) await new Promise((resolve) => window.setTimeout(resolve, 40))
  if (!doc || token !== thumbToken || pageNo === current.value && busy.value) return
  const thumb = thumbCanvases.get(pageNo)
  if (!thumb) return
  try {
    const page = await doc.getPage(pageNo)
    if (token !== thumbToken) return
    const base = page.getViewport({ scale: 1 })
    const viewport = page.getViewport({ scale: 92 / base.width })
    thumb.width = Math.ceil(viewport.width)
    thumb.height = Math.ceil(viewport.height)
    await page.render({ canvas: thumb, viewport }).promise
  } catch {
    /* A thumbnail is optional. The main page still renders. */
  }
}

async function paintThumbs(token: number) {
  await nextTick()
  if (!doc || token !== thumbToken) return
  const pages = Array.from({ length: doc.numPages }, (_, index) => index + 1)
  for (const pageNo of pages.filter((pageNo) => pageNo !== current.value)) await paintThumb(token, pageNo)
  await paintThumb(token, current.value)
}

async function load() {
  error.value = ''
  const gen = ++generation
  const thumbs = ++thumbToken
  renderTask?.cancel()
  textLayer?.cancel()
  await task?.destroy().catch(() => {})
  doc = undefined
  total.value = 0
  thumbCanvases.clear()
  const requested = props.page && props.page > 0 ? props.page : 1
  if (current.value !== requested) current.value = requested
  pageDraft.value = String(current.value)
  if (!isPdf.value) return
  try {
    task = pdfjs.getDocument({ url: '/api/document-versions/' + props.version.id + '/content', withCredentials: true, enableXfa: false })
    const loaded = await task.promise
    if (gen !== generation) {
      await loaded.cleanup()
      return
    }
    doc = loaded
    total.value = doc.numPages
    if (current.value > total.value) current.value = total.value || 1
    scheduleRender()
    void paintChain.then(() => {
      if (gen === generation) return paintThumbs(thumbs)
    })
  } catch {
    if (gen === generation) error.value = 'Access denied or document could not be previewed.'
  }
}

function go(pageNo: number) {
  if (!total.value) return
  const next = Math.min(total.value, Math.max(1, Math.round(pageNo)))
  if (next === current.value) return
  current.value = next
}

function commitPage(event: Event) {
  const n = Number((event.target as HTMLInputElement).value)
  if (!Number.isFinite(n)) {
    pageDraft.value = String(current.value)
    return
  }
  go(n)
  pageDraft.value = String(current.value)
}

function bump(factor: number) {
  const base = fitMode.value === 'custom' ? userScale.value : appliedScale.value
  userScale.value = clamp(base * factor)
  fitMode.value = 'custom'
}

function commitZoom(event: Event) {
  const n = Number((event.target as HTMLInputElement).value)
  if (!Number.isFinite(n)) return
  userScale.value = clamp(n / 100)
  fitMode.value = 'custom'
}

function onKey(event: KeyboardEvent) {
  if (!isPdf.value) return
  const target = event.target as HTMLElement | null
  if (target?.closest('input, textarea, select, [contenteditable="true"]')) return
  if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
    event.preventDefault()
    go(current.value - 1)
  } else if (event.key === 'ArrowRight' || event.key === 'PageDown') {
    event.preventDefault()
    go(current.value + 1)
  } else if (event.key === 'Home') {
    event.preventDefault()
    go(1)
  } else if (event.key === 'End') {
    event.preventDefault()
    go(total.value)
  } else if (event.key === '+' || event.key === '=') {
    event.preventDefault()
    bump(1.15)
  } else if (event.key === '-' || event.key === '_') {
    event.preventDefault()
    bump(1 / 1.15)
  } else if (event.key === '0') {
    event.preventDefault()
    fitMode.value = 'page'
  }
}

function onResize() {
  if (fitMode.value === 'custom') return
  window.clearTimeout(resizeTimer)
  resizeTimer = window.setTimeout(() => scheduleRender(), 80)
}

onMounted(() => {
  if (typeof ResizeObserver !== 'undefined' && scroller.value) {
    resizeObserver = new ResizeObserver(() => onResize())
    resizeObserver.observe(scroller.value)
  }
  void load()
})

watch(() => props.version?.id, () => void load())
watch(current, () => scheduleRender())
watch([userScale, fitMode], () => scheduleRender())
watch(() => [props.page, props.focus], async () => {
  if (props.page && props.page > 0 && props.page !== current.value) current.value = props.page
  else scheduleRender()
  await nextTick()
  scroller.value?.focus()
})

onBeforeUnmount(() => {
  generation++
  thumbToken++
  window.clearTimeout(resizeTimer)
  resizeObserver?.disconnect()
  renderTask?.cancel()
  textLayer?.cancel()
  task?.destroy().catch(() => {})
})
</script>
<template>
<section ref="root" class="doc-reader" aria-label="Document reader" @keydown="onKey">
  <div class="doc-reader-header">
    <div class="doc-reader-title">
      <strong class="doc-reader-name" :title="version.original_name">{{ version.original_name }}</strong>
      <a class="button doc-reader-download" :href="downloadHref">
        <Download :size="16" aria-hidden="true" />
        Download original
      </a>
    </div>
    <StatusBadge class="doc-reader-integrity" kind="integrity" :code="integrity" />
  </div>
  <p v-if="error" role="alert" class="alert error">{{ error }}</p>
  <p v-if="busy" class="sr-only" aria-live="polite">Rendering document…</p>
  <div v-if="isPdf" class="doc-reader-body">
    <nav class="doc-thumbs" aria-label="Page thumbnails">
      <button
        v-for="n in total"
        :key="n"
        type="button"
        class="thumb"
        :class="{ active: n === current }"
        :aria-current="n === current ? 'page' : undefined"
        :aria-label="'Go to page ' + n"
        @click="go(n)"
      >
        <canvas :ref="bindThumb(n)" aria-hidden="true" />
        <span>{{ n }}</span>
      </button>
    </nav>
    <div class="doc-stage">
      <div class="doc-toolbar" role="toolbar" aria-label="Zoom and pages">
        <div class="doc-zoom">
          <button type="button" aria-label="Zoom out" :disabled="zoomPercent <= 50" @click="bump(1 / 1.15)"><ZoomOut :size="16" aria-hidden="true" /></button>
          <label class="inline-field">
            <span class="sr-only">Zoom percent</span>
            <input class="zoom-input" type="number" min="50" max="400" step="5" :value="zoomPercent" aria-label="Zoom percent" @change="commitZoom" />
            <span aria-hidden="true">%</span>
          </label>
          <button type="button" aria-label="Zoom in" :disabled="zoomPercent >= 400" @click="bump(1.15)"><ZoomIn :size="16" aria-hidden="true" /></button>
          <button type="button" :aria-pressed="fitMode === 'width'" @click="fitMode = 'width'">Fit width</button>
          <button type="button" :aria-pressed="fitMode === 'page'" @click="fitMode = 'page'">Fit page</button>
        </div>
        <div class="doc-pager">
          <button type="button" aria-label="Previous page" :disabled="current <= 1" @click="go(current - 1)"><ChevronLeft :size="16" aria-hidden="true" />Previous</button>
          <label class="inline-field">
            <span>Page</span>
            <input class="page-input" type="number" min="1" :max="total || 1" :value="pageDraft" aria-label="Page number" @change="commitPage" />
            <span>of {{ total || '…' }}</span>
          </label>
          <button type="button" aria-label="Next page" :disabled="!total || current >= total" @click="go(current + 1)">Next<ChevronRight :size="16" aria-hidden="true" /></button>
        </div>
      </div>
      <p class="sr-only" aria-live="polite">Page {{ current }} of {{ total || 1 }}</p>
      <div ref="scroller" class="canvas-scroll" tabindex="0" aria-label="Document page">
        <div ref="pageEl" class="pdf-page">
          <canvas ref="canvas" :aria-label="'PDF page ' + current + ' of ' + (total || 1)" />
          <div ref="textLayerEl" class="textLayer" />
          <div v-if="showMark" class="source-highlight" aria-label="Issue location on this page" :style="markStyle"></div>
        </div>
      </div>
    </div>
  </div>
  <img v-else class="source-image" :src="'/api/document-versions/' + version.id + '/content'" alt="Uploaded source document" />
</section>
</template>
