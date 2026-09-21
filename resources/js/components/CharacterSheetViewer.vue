<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    ChevronLeft,
    ChevronRight,
    Download,
    LoaderCircle,
    Maximize2,
    Save,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import type * as PdfJsLibrary from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import type * as PdfViewerLibrary from 'pdfjs-dist/web/pdf_viewer.mjs';
import 'pdfjs-dist/web/pdf_viewer.css';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    sourceUrl: string;
    saveUrl: string;
    downloadUrl: string;
    characterName: string;
    canEdit: boolean;
    updatedAt: string | null;
}>();

const emit = defineEmits<{
    dirtyChange: [dirty: boolean];
}>();

const containerElement = ref<HTMLDivElement | null>(null);
const viewerElement = ref<HTMLDivElement | null>(null);
const loading = ref(true);
const saving = ref(false);
const dirty = ref(false);
const errorMessage = ref<string | null>(null);
const currentPage = ref(1);
const pageCount = ref(0);
const savedMessage = ref<string | null>(null);

const saveForm = useForm<{ sheet: File | null }>({ sheet: null });

let loadingTask: PdfJsLibrary.PDFDocumentLoadingTask | null = null;
let pdfDocument: PdfJsLibrary.PDFDocumentProxy | null = null;
let pdfViewer: PdfViewerLibrary.PDFViewer | null = null;
let pdfJs: typeof PdfJsLibrary | null = null;
let viewerLibrary: typeof PdfViewerLibrary | null = null;
let resizeObserver: ResizeObserver | null = null;
let fittedToWidth = true;

function setDirty(value: boolean) {
    if (!props.canEdit || dirty.value === value) {
        return;
    }

    dirty.value = value;
    emit('dirtyChange', value);
    savedMessage.value = null;
}

function handleFormChange(event: Event) {
    if (
        event.target instanceof HTMLInputElement ||
        event.target instanceof HTMLTextAreaElement ||
        event.target instanceof HTMLSelectElement
    ) {
        setDirty(true);
    }
}

async function loadDocument() {
    const container = containerElement.value;
    const viewer = viewerElement.value;

    if (!container || !viewer || !pdfJs || !viewerLibrary) {
        return;
    }

    loading.value = true;
    errorMessage.value = null;
    pageCount.value = 0;
    currentPage.value = 1;
    setDirty(false);

    try {
        await loadingTask?.destroy();
        viewer.replaceChildren();

        const eventBus = new viewerLibrary.EventBus();
        const linkService = new viewerLibrary.PDFLinkService({ eventBus });
        pdfViewer = new viewerLibrary.PDFViewer({
            container,
            viewer,
            eventBus,
            linkService,
            annotationMode: props.canEdit
                ? pdfJs.AnnotationMode.ENABLE_FORMS
                : pdfJs.AnnotationMode.ENABLE,
            textLayerMode: 0,
            removePageBorders: false,
            enablePermissions: false,
            supportsPinchToZoom: true,
        });
        linkService.setViewer(pdfViewer);

        eventBus.on('pagesinit', () => {
            if (pdfViewer) {
                pdfViewer.currentScaleValue = 'page-width';
            }

            loading.value = false;
        });
        eventBus.on(
            'pagechanging',
            ({ pageNumber }: { pageNumber: number }) => {
                currentPage.value = pageNumber;
            },
        );

        loadingTask = pdfJs.getDocument({
            url: props.sourceUrl,
            withCredentials: true,
            enableXfa: false,
        });
        pdfDocument = await loadingTask.promise;
        pageCount.value = pdfDocument.numPages;
        pdfViewer.setDocument(pdfDocument);
        linkService.setDocument(pdfDocument);
    } catch (error) {
        loading.value = false;
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Impossibile aprire la scheda del personaggio.';
    }
}

async function saveSheet() {
    if (!pdfDocument || !props.canEdit || saving.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = null;

    try {
        const data = await pdfDocument.saveDocument();
        saveForm.sheet = new File([data], 'scheda-personaggio.pdf', {
            type: 'application/pdf',
        });
        saveForm.post(props.saveUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setDirty(false);
                savedMessage.value = 'Scheda salvata';
                saveForm.reset();
            },
            onError: () => {
                errorMessage.value =
                    saveForm.errors.sheet || 'Non è stato possibile salvare.';
            },
            onFinish: () => {
                saving.value = false;
            },
        });
    } catch (error) {
        saving.value = false;
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Non è stato possibile generare il PDF aggiornato.';
    }
}

function changePage(delta: number) {
    if (!pdfViewer) {
        return;
    }

    pdfViewer.currentPageNumber = Math.min(
        pageCount.value,
        Math.max(1, currentPage.value + delta),
    );
}

function zoom(delta: number) {
    if (!pdfViewer) {
        return;
    }

    fittedToWidth = false;
    pdfViewer.currentScale = Math.min(
        3,
        Math.max(0.35, pdfViewer.currentScale + delta),
    );
}

function fitWidth() {
    if (pdfViewer) {
        fittedToWidth = true;
        pdfViewer.currentScaleValue = 'page-width';
    }
}

function preventUnsavedExit(event: BeforeUnloadEvent) {
    if (!dirty.value) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
}

onMounted(async () => {
    window.addEventListener('beforeunload', preventUnsavedExit);
    pdfJs = await import('pdfjs-dist');
    (
        globalThis as typeof globalThis & {
            pdfjsLib: typeof PdfJsLibrary;
        }
    ).pdfjsLib = pdfJs;
    viewerLibrary = await import('pdfjs-dist/web/pdf_viewer.mjs');
    pdfJs.GlobalWorkerOptions.workerSrc = workerUrl;
    await nextTick();
    await loadDocument();

    if (containerElement.value) {
        resizeObserver = new ResizeObserver(() => {
            if (pdfViewer && fittedToWidth) {
                pdfViewer.currentScaleValue = 'page-width';
            }
        });
        resizeObserver.observe(containerElement.value);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', preventUnsavedExit);
    resizeObserver?.disconnect();
    void loadingTask?.destroy();
    emit('dirtyChange', false);
});

watch(
    () => props.sourceUrl,
    async () => {
        await nextTick();
        await loadDocument();
    },
);
</script>

<template>
    <div
        class="-mx-4 overflow-hidden border-y border-stone-200 bg-white shadow-sm sm:mx-0 sm:rounded-2xl sm:border dark:border-white/10 dark:bg-white/5"
    >
        <div
            class="flex flex-col gap-3 border-b border-stone-200 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
        >
            <div class="flex min-w-0 items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2
                        class="truncate font-serif text-base font-bold sm:text-lg"
                    >
                        Scheda di {{ characterName }}
                    </h2>
                    <p class="text-[11px] text-stone-500 sm:text-xs">
                        {{
                            canEdit
                                ? 'Tocca i campi, poi salva.'
                                : 'Consultazione in sola lettura.'
                        }}
                        <span class="sm:hidden"> Pizzica per ingrandire.</span>
                    </p>
                </div>
                <span
                    v-if="dirty"
                    class="inline-flex shrink-0 items-center gap-1 text-[11px] font-semibold text-amber-700 sm:hidden dark:text-amber-300"
                >
                    <AlertTriangle class="size-3.5" /> Da salvare
                </span>
                <span
                    v-else-if="savedMessage"
                    class="inline-flex shrink-0 items-center gap-1 text-[11px] font-semibold text-emerald-700 sm:hidden dark:text-emerald-300"
                >
                    <Check class="size-3.5" /> Salvata
                </span>
            </div>

            <div
                class="grid grid-cols-2 items-center gap-2 sm:flex sm:flex-wrap"
            >
                <span
                    v-if="dirty"
                    class="hidden items-center gap-1.5 text-xs font-semibold text-amber-700 sm:inline-flex dark:text-amber-300"
                >
                    <AlertTriangle class="size-3.5" /> Modifiche non salvate
                </span>
                <span
                    v-else-if="savedMessage"
                    class="hidden items-center gap-1.5 text-xs font-semibold text-emerald-700 sm:inline-flex dark:text-emerald-300"
                >
                    <Check class="size-3.5" /> {{ savedMessage }}
                </span>
                <a
                    :href="downloadUrl"
                    class="secondary-button min-h-11 justify-center"
                    :class="canEdit ? '' : 'col-span-2 sm:col-span-1'"
                >
                    <Download class="size-3.5" /> Scarica
                </a>
                <button
                    v-if="canEdit"
                    type="button"
                    class="primary-button min-h-11 justify-center"
                    :disabled="saving || loading || !dirty"
                    @click="saveSheet"
                >
                    <LoaderCircle v-if="saving" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    {{ saving ? 'Salvataggio…' : 'Salva scheda' }}
                </button>
            </div>
        </div>

        <div
            class="flex items-center justify-between gap-1 border-b border-stone-200 bg-stone-50 px-2 py-1.5 sm:gap-2 sm:px-3 sm:py-2 dark:border-white/10 dark:bg-black/20"
        >
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="tool-button"
                    aria-label="Pagina precedente"
                    :disabled="currentPage <= 1"
                    @click="changePage(-1)"
                >
                    <ChevronLeft class="size-4" />
                </button>
                <span
                    class="min-w-16 text-center text-[11px] font-semibold sm:min-w-20 sm:text-xs"
                >
                    <span class="hidden sm:inline">Pagina </span
                    >{{ currentPage }} /
                    {{ pageCount || '…' }}
                </span>
                <button
                    type="button"
                    class="tool-button"
                    aria-label="Pagina successiva"
                    :disabled="currentPage >= pageCount"
                    @click="changePage(1)"
                >
                    <ChevronRight class="size-4" />
                </button>
            </div>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="tool-button"
                    aria-label="Riduci zoom"
                    @click="zoom(-0.15)"
                >
                    <ZoomOut class="size-4" />
                </button>
                <button
                    type="button"
                    class="tool-button"
                    aria-label="Adatta alla larghezza"
                    @click="fitWidth"
                >
                    <Maximize2 class="size-4" />
                </button>
                <button
                    type="button"
                    class="tool-button"
                    aria-label="Aumenta zoom"
                    @click="zoom(0.15)"
                >
                    <ZoomIn class="size-4" />
                </button>
            </div>
        </div>

        <p
            v-if="errorMessage"
            class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/30 dark:bg-red-950/30 dark:text-red-200"
        >
            {{ errorMessage }}
        </p>

        <div
            class="relative h-[calc(100dvh-17rem)] min-h-[480px] bg-stone-300 sm:h-[72vh] sm:min-h-[560px] dark:bg-stone-900"
        >
            <div
                v-if="loading"
                class="absolute inset-0 z-20 grid place-items-center bg-stone-100/90 dark:bg-stone-950/90"
            >
                <span class="flex items-center gap-2 text-sm font-semibold">
                    <LoaderCircle class="size-5 animate-spin" /> Caricamento
                    scheda…
                </span>
            </div>
            <div
                ref="containerElement"
                class="pdf-container absolute inset-0 overflow-auto overscroll-contain"
                @input="handleFormChange"
                @change="handleFormChange"
            >
                <div ref="viewerElement" class="pdfViewer"></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference '../../css/app.css';
.primary-button {
    @apply inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50;
}
.secondary-button {
    @apply inline-flex items-center gap-1.5 rounded-lg border border-stone-200 bg-white px-3 py-2 text-xs font-bold text-stone-700 transition hover:border-emerald-700 hover:text-emerald-800 dark:border-white/10 dark:bg-white/5 dark:text-stone-200 dark:hover:text-emerald-300;
}
.tool-button {
    @apply grid size-9 place-items-center rounded-lg text-stone-600 transition hover:bg-white hover:text-emerald-800 disabled:cursor-not-allowed disabled:opacity-30 dark:text-stone-300 dark:hover:bg-white/10 dark:hover:text-emerald-300;
}

:deep(.pdfViewer) {
    padding: 8px 0 72px;
}

:deep(.pdfViewer .page) {
    margin: 0 auto 8px;
    box-shadow: 0 2px 12px rgb(0 0 0 / 0.16);
}

:deep(.annotationLayer input),
:deep(.annotationLayer textarea),
:deep(.annotationLayer select) {
    color: #111827;
}

:deep(.annotationLayer input:focus),
:deep(.annotationLayer textarea:focus),
:deep(.annotationLayer select:focus) {
    outline: 2px solid #047857;
    outline-offset: 1px;
}

.pdf-container {
    touch-action: pan-x pan-y pinch-zoom;
    -webkit-overflow-scrolling: touch;
}

@media (min-width: 640px) {
    :deep(.pdfViewer) {
        padding: 12px 0 24px;
    }

    :deep(.pdfViewer .page) {
        margin-bottom: 12px;
    }
}
</style>
