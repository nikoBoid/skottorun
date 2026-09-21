<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useConnectionStatus, useEcho } from '@laravel/echo-vue';
import {
    Archive,
    ArrowLeft,
    Backpack,
    BookOpenText,
    Check,
    ChevronRight,
    ChevronDown,
    ChevronUp,
    Crown,
    FileText,
    Gem,
    History,
    Image as ImageIcon,
    Library,
    LockKeyhole,
    Minus,
    Moon,
    Pencil,
    Plus,
    ScrollText,
    Send,
    Shield,
    Sparkles,
    Trash2,
    UnlockKeyhole,
    Upload,
    Utensils,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CharacterSheetViewer from '@/components/CharacterSheetViewer.vue';

type UserSummary = { id: number; name: string; username: string };
type Character = {
    id: number;
    user_id: number;
    name: string;
    ancestry: string | null;
    class_name: string | null;
    level: number;
    user?: UserSummary;
};
type InventoryItem = {
    id: number;
    name: string;
    description: string | null;
    quantity: number;
    unit: string;
    updated_by?: UserSummary | null;
};
type RelicRevelation = {
    id: number;
    kind: 'skill' | 'lore';
    title: string;
    content: string | null;
    is_unlocked: boolean;
};
type Relic = {
    id: number;
    character_id: number | null;
    name: string;
    description: string | null;
    image_url: string | null;
    character?: Pick<Character, 'id' | 'name'> | null;
    revelations: RelicRevelation[];
};
type JournalPage = {
    id: number;
    title: string;
    content: string;
    occurred_on: string | null;
    created_at: string;
    author?: UserSummary | null;
};
type JournalTopic = {
    id: number;
    title: string;
    pages_count: number;
    pages: JournalPage[];
    creator?: UserSummary | null;
};
type GalleryImage = {
    id: number;
    image_url: string;
};
type CharacterSheet = {
    character_id: number;
    character_name: string;
    url: string;
    download_url: string;
    save_url: string;
    can_edit: boolean;
    updated_at: string | null;
};
type Activity = {
    id: number;
    description: string;
    created_at: string;
    user?: UserSummary | null;
};

const props = defineProps<{
    campaign: {
        id: number;
        name: string;
        slug: string;
        synopsis: string | null;
    };
    viewer: { role: 'dm' | 'player' };
    players: Character[];
    selectedPlayer: Character | null;
    inventory: InventoryItem[];
    relics: Relic[];
    journalTopics: JournalTopic[];
    galleryImages: GalleryImage[];
    rationCount: number;
    characterSheet: CharacterSheet | null;
    activity: Activity[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tavolo di gioco', href: '/dashboard' }] },
});

const activeTab = ref<
    'inventory' | 'relics' | 'journal' | 'gallery' | 'rations' | 'sheet'
>('inventory');
const characterSheetDirty = ref(false);
const isDm = computed(() => props.viewer.role === 'dm');
const connectionStatus = useConnectionStatus();

useEcho<{ campaignId: number; area: string }>(
    `campaign.${props.campaign.id}`,
    'CampaignChanged',
    () => {
        router.reload({
            only: [
                'inventory',
                'relics',
                'journalTopics',
                'galleryImages',
                'rationCount',
                'characterSheet',
                'activity',
            ],
        });
    },
);

const inventoryForm = useForm({
    name: '',
    description: '',
    quantity: 1,
    unit: 'pz',
});
const relicForm = useForm({
    character_id: null as number | null,
    name: '',
    description: '',
    image: null as File | null,
    revelations: [
        {
            kind: 'skill' as 'skill' | 'lore',
            title: '',
            content: '',
        },
    ],
});
const journalTopicForm = useForm({
    title: '',
});
const journalTopicEditForm = useForm({
    title: '',
});
const journalPageForm = useForm({
    title: '',
    content: '',
    occurred_on: new Date().toISOString().slice(0, 10),
});
const journalPageEditForm = useForm({
    title: '',
    content: '',
    occurred_on: '',
});
const galleryForm = useForm({
    image: null as File | null,
});
const rationForm = useForm({
    quantity: props.rationCount,
});
const rationRestForm = useForm({ rations: null as string | null });

function addInventoryItem() {
    inventoryForm.post('/inventory', {
        preserveScroll: true,
        onSuccess: () => inventoryForm.reset(),
    });
}

function changeQuantity(item: InventoryItem, delta: number) {
    router.patch(
        `/inventory/${item.id}`,
        { quantity: Math.max(0, item.quantity + delta) },
        { preserveScroll: true },
    );
}

function addRelic() {
    relicForm.post('/relics', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            relicForm.reset();
            relicForm.revelations = [{ kind: 'skill', title: '', content: '' }];
        },
    });
}

function setRelicImage(event: Event) {
    relicForm.image = (event.target as HTMLInputElement).files?.item(0) ?? null;
}

function addRevelation(kind: 'skill' | 'lore' = 'skill') {
    relicForm.revelations.push({ kind, title: '', content: '' });
}

function removeRevelation(index: number) {
    relicForm.revelations.splice(index, 1);
}

function assignRelic(relic: Relic, value: string) {
    router.patch(
        `/relics/${relic.id}`,
        { character_id: value ? Number(value) : null },
        { preserveScroll: true },
    );
}

function setRevelationUnlocked(
    revelation: RelicRevelation,
    isUnlocked: boolean,
) {
    router.patch(
        `/relic-revelations/${revelation.id}`,
        { is_unlocked: isUnlocked },
        { preserveScroll: true },
    );
}

const selectedTopicId = ref<number | null>(props.journalTopics[0]?.id ?? null);
const selectedPageId = ref<number | null>(null);
const editingTopicId = ref<number | null>(null);
const editingPageId = ref<number | null>(null);
const selectedJournalTopic = computed(
    () =>
        props.journalTopics.find(
            (topic) => topic.id === selectedTopicId.value,
        ) ?? null,
);
const selectedJournalPage = computed(() => {
    const topic = selectedJournalTopic.value;

    if (!topic) {
        return null;
    }

    if (topic.pages.length === 1) {
        return topic.pages[0];
    }

    return topic.pages.find((page) => page.id === selectedPageId.value) ?? null;
});

watch(
    () => props.journalTopics,
    (topics) => {
        if (!topics.some((topic) => topic.id === selectedTopicId.value)) {
            selectedTopicId.value = topics[0]?.id ?? null;
            selectedPageId.value = null;
        }

        if (!topics.some((topic) => topic.id === editingTopicId.value)) {
            cancelJournalTopicEdit();
        }

        if (
            !topics.some((topic) =>
                topic.pages.some((page) => page.id === editingPageId.value),
            )
        ) {
            cancelJournalPageEdit();
        }
    },
);

function selectJournalTopic(topicId: number) {
    if (editingTopicId.value !== topicId) {
        cancelJournalTopicEdit();
    }

    cancelJournalPageEdit();
    selectedTopicId.value = topicId;
    selectedPageId.value = null;
}

function addJournalTopic() {
    journalTopicForm.post('/journal/topics', {
        preserveScroll: true,
        onSuccess: () => journalTopicForm.reset(),
    });
}

function editJournalTopic(topic: JournalTopic) {
    editingTopicId.value = topic.id;
    journalTopicEditForm.title = topic.title;
    journalTopicEditForm.clearErrors();
}

function cancelJournalTopicEdit() {
    editingTopicId.value = null;
    journalTopicEditForm.reset();
    journalTopicEditForm.clearErrors();
}

function updateJournalTopic() {
    if (!editingTopicId.value) {
        return;
    }

    journalTopicEditForm.patch(`/journal/topics/${editingTopicId.value}`, {
        preserveScroll: true,
        onSuccess: cancelJournalTopicEdit,
    });
}

function addJournalPage() {
    if (!selectedJournalTopic.value) {
        return;
    }

    journalPageForm.post(
        `/journal/topics/${selectedJournalTopic.value.id}/pages`,
        {
            preserveScroll: true,
            onSuccess: () => journalPageForm.reset('title', 'content'),
        },
    );
}

function openJournalPage(pageId: number) {
    if (editingPageId.value !== pageId) {
        cancelJournalPageEdit();
    }

    selectedPageId.value = pageId;
}

function closeJournalPage() {
    cancelJournalPageEdit();
    selectedPageId.value = null;
}

function editJournalPage(page: JournalPage) {
    editingPageId.value = page.id;
    journalPageEditForm.title = page.title;
    journalPageEditForm.content = page.content;
    journalPageEditForm.occurred_on = page.occurred_on?.slice(0, 10) ?? '';
    journalPageEditForm.clearErrors();
}

function cancelJournalPageEdit() {
    editingPageId.value = null;
    journalPageEditForm.reset();
    journalPageEditForm.clearErrors();
}

function updateJournalPage() {
    if (!editingPageId.value) {
        return;
    }

    journalPageEditForm.patch(`/journal/pages/${editingPageId.value}`, {
        preserveScroll: true,
        onSuccess: cancelJournalPageEdit,
    });
}

function removeJournalTopic(topic: JournalTopic) {
    if (
        window.confirm(
            `Rimuovere l’argomento «${topic.title}» e tutte le sue pagine?`,
        )
    ) {
        router.delete(`/journal/topics/${topic.id}`, {
            preserveScroll: true,
        });
    }
}

function removeJournalPage(page: JournalPage) {
    if (window.confirm(`Rimuovere la pagina «${page.title}»?`)) {
        router.delete(`/journal/pages/${page.id}`, {
            preserveScroll: true,
            onSuccess: closeJournalPage,
        });
    }
}

function setGalleryImage(event: Event) {
    galleryForm.image =
        (event.target as HTMLInputElement).files?.item(0) ?? null;
}

function addGalleryImage() {
    galleryForm.post('/gallery', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => galleryForm.reset(),
    });
}

function removeGalleryImage(galleryImage: GalleryImage) {
    if (window.confirm('Rimuovere questa immagine dalla galleria?')) {
        router.delete(`/gallery/${galleryImage.id}`, { preserveScroll: true });
    }
}

function updateRations() {
    rationForm.patch('/rations', { preserveScroll: true });
}

function changeRations(delta: number) {
    rationForm.quantity = Math.max(0, props.rationCount + delta);
    updateRations();
}

function restCompany() {
    rationRestForm.post('/rations/rest', { preserveScroll: true });
}

function selectTab(tabId: (typeof tabs)[number]['id']) {
    if (
        activeTab.value === 'sheet' &&
        characterSheetDirty.value &&
        tabId !== 'sheet' &&
        !window.confirm('Uscire senza salvare le modifiche alla scheda?')
    ) {
        return;
    }

    activeTab.value = tabId;
}

function remove(path: string, label: string) {
    if (window.confirm(`Rimuovere ${label}?`)) {
        router.delete(path, { preserveScroll: true });
    }
}

function formatDate(value: string | null) {
    if (!value) {
        return 'Data ignota';
    }

    return new Intl.DateTimeFormat('it-IT', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

watch(
    () => props.rationCount,
    (rationCount) => {
        rationForm.quantity = rationCount;
    },
);

const tabs = [
    { id: 'inventory' as const, label: 'Zaino condiviso', icon: Backpack },
    { id: 'relics' as const, label: 'Reliquie', icon: Gem },
    { id: 'journal' as const, label: 'Diario', icon: BookOpenText },
    { id: 'gallery' as const, label: 'Galleria', icon: ImageIcon },
    { id: 'rations' as const, label: 'Razioni', icon: Utensils },
    { id: 'sheet' as const, label: 'Scheda PG', icon: FileText },
];
</script>

<template>
    <Head :title="campaign.name" />

    <div
        class="min-h-full bg-[#f6f2e8] text-stone-900 dark:bg-[#111814] dark:text-stone-100"
    >
        <section
            class="relative overflow-hidden border-b border-amber-900/10 bg-[#183b2b] px-4 py-8 text-white sm:px-8 dark:bg-[#0c2419]"
        >
            <div
                class="pointer-events-none absolute inset-0 [background-image:radial-gradient(circle_at_20%_20%,#f5d58a_0,transparent_30%),radial-gradient(circle_at_80%_0%,#90c9a4_0,transparent_28%)] opacity-20"
            ></div>
            <div
                class="relative mx-auto flex max-w-7xl flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <div
                        class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-[0.22em] text-amber-200 uppercase"
                    >
                        <Crown v-if="isDm" class="size-4" />
                        <Shield v-else class="size-4" />
                        {{
                            isDm
                                ? 'Schermo del Dungeon Master'
                                : 'Taccuino dell’avventuriero'
                        }}
                    </div>
                    <h1
                        class="font-serif text-3xl font-bold tracking-tight sm:text-4xl"
                    >
                        {{ campaign.name }}
                    </h1>
                    <p
                        v-if="campaign.synopsis"
                        class="mt-2 max-w-2xl text-sm leading-6 text-emerald-50/80"
                    >
                        {{ campaign.synopsis }}
                    </p>
                    <div
                        class="mt-3 inline-flex items-center gap-2 text-xs text-emerald-100/70"
                    >
                        <span
                            class="size-2 rounded-full"
                            :class="
                                connectionStatus === 'connected'
                                    ? 'bg-emerald-300'
                                    : connectionStatus === 'failed'
                                      ? 'bg-red-400'
                                      : 'bg-amber-300'
                            "
                        ></span>
                        {{
                            connectionStatus === 'connected'
                                ? 'Tavolo sincronizzato'
                                : connectionStatus === 'failed'
                                  ? 'Realtime non disponibile'
                                  : 'Connessione al tavolo…'
                        }}
                    </div>
                </div>
                <div
                    v-if="
                        selectedPlayer && (!isDm || activeTab !== 'inventory')
                    "
                    class="flex items-center gap-3 rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur"
                >
                    <div
                        class="grid size-11 place-items-center rounded-xl bg-amber-300 font-serif text-lg font-bold text-emerald-950"
                    >
                        {{ selectedPlayer.name.slice(0, 1) }}
                    </div>
                    <div>
                        <p class="text-xs text-emerald-100/70">
                            {{ isDm ? 'Scheda aperta' : 'Il tuo personaggio' }}
                        </p>
                        <p class="font-semibold">{{ selectedPlayer.name }}</p>
                        <p class="text-xs text-emerald-100/70">
                            {{
                                [
                                    selectedPlayer.ancestry,
                                    selectedPlayer.class_name,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || 'Avventuriero'
                            }}
                            · Liv. {{ selectedPlayer.level }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <div
            class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-8"
            :class="isDm ? 'lg:grid-cols-[260px_minmax(0,1fr)]' : ''"
        >
            <aside v-if="isDm" class="hidden space-y-4 lg:block">
                <div
                    class="rounded-2xl border border-stone-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/5"
                >
                    <div class="mb-3 flex items-center justify-between px-2">
                        <div class="flex items-center gap-2">
                            <Users
                                class="size-4 text-emerald-700 dark:text-emerald-300"
                            />
                            <h2 class="text-sm font-bold">Compagnia</h2>
                        </div>
                        <span
                            class="rounded-full bg-stone-100 px-2 py-0.5 text-xs dark:bg-white/10"
                            >{{ players.length }}</span
                        >
                    </div>
                    <div class="space-y-1">
                        <Link
                            v-for="player in players"
                            :key="player.id"
                            :href="`/dashboard?player=${player.id}`"
                            preserve-scroll
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition"
                            :class="
                                selectedPlayer?.id === player.id
                                    ? 'bg-emerald-800 text-white shadow-sm'
                                    : 'hover:bg-stone-100 dark:hover:bg-white/10'
                            "
                        >
                            <span
                                class="grid size-8 shrink-0 place-items-center rounded-lg bg-amber-200/90 text-sm font-bold text-emerald-950"
                                >{{ player.name.slice(0, 1) }}</span
                            >
                            <span class="min-w-0"
                                ><span
                                    class="block truncate text-sm font-semibold"
                                    >{{ player.name }}</span
                                ><span
                                    class="block truncate text-xs opacity-70"
                                    >{{
                                        player.class_name || player.user?.name
                                    }}</span
                                ></span
                            >
                        </Link>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5"
                >
                    <div class="mb-3 flex items-center gap-2 text-sm font-bold">
                        <History class="size-4 text-amber-700" /> Ultime mosse
                    </div>
                    <div v-if="activity.length" class="space-y-3">
                        <div
                            v-for="event in activity"
                            :key="event.id"
                            class="border-l-2 border-amber-300 pl-3"
                        >
                            <p class="text-xs leading-5">
                                <strong>{{
                                    event.user?.name || 'Sistema'
                                }}</strong>
                                {{ event.description }}
                            </p>
                            <p class="mt-0.5 text-[11px] text-stone-500">
                                {{ formatDate(event.created_at) }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="text-xs text-stone-500">
                        La storia deve ancora cominciare.
                    </p>
                </div>
            </aside>

            <main class="min-w-0">
                <div
                    class="mb-5 grid grid-cols-6 gap-1 rounded-2xl border border-stone-200 bg-white p-1.5 shadow-sm sm:gap-2 dark:border-white/10 dark:bg-white/5"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        :aria-label="tab.label"
                        :title="tab.label"
                        class="flex items-center justify-center gap-2 rounded-xl px-2 py-3 text-xs font-bold transition sm:text-sm"
                        :class="
                            activeTab === tab.id
                                ? 'bg-emerald-800 text-white shadow-sm'
                                : 'text-stone-500 hover:bg-stone-100 dark:hover:bg-white/10'
                        "
                        @click="selectTab(tab.id)"
                    >
                        <component :is="tab.icon" class="size-4" /><span
                            class="hidden sm:inline"
                            >{{ tab.label }}</span
                        >
                    </button>
                </div>

                <section v-if="activeTab === 'inventory'" class="space-y-5">
                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-emerald-800/15 bg-emerald-950 px-5 py-4 text-white shadow-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-xl bg-amber-300 text-emerald-950"
                            >
                                <Users class="size-5" />
                            </span>
                            <div>
                                <h2 class="font-serif text-lg font-bold">
                                    Zaino della compagnia
                                </h2>
                                <p
                                    class="mt-0.5 max-w-2xl text-sm leading-5 text-emerald-100/75"
                                >
                                    È un unico inventario comune: ogni player
                                    vede gli stessi oggetti e può usarli,
                                    aggiungerli o aggiornare le quantità.
                                </p>
                            </div>
                        </div>
                        <span
                            class="shrink-0 rounded-full border border-emerald-200/20 bg-white/10 px-3 py-1 text-xs font-semibold text-emerald-100"
                        >
                            {{
                                isDm
                                    ? `Condiviso con ${players.length} player`
                                    : 'Condiviso con tutta la compagnia'
                            }}
                        </span>
                    </div>
                    <div class="hidden gap-4 sm:grid sm:grid-cols-3">
                        <div class="stat-card">
                            <Backpack class="size-5 text-emerald-700" />
                            <div>
                                <strong>{{ inventory.length }}</strong
                                ><span>tipi di oggetto</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <Sparkles class="size-5 text-amber-600" />
                            <div>
                                <strong>{{
                                    inventory.reduce(
                                        (sum, item) => sum + item.quantity,
                                        0,
                                    )
                                }}</strong
                                ><span>pezzi totali</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <Users class="size-5 text-sky-700" />
                            <div>
                                <strong>Comune</strong
                                ><span>visibile a tutti</span>
                            </div>
                        </div>
                    </div>
                    <form
                        class="panel grid gap-3 md:grid-cols-[1.2fr_2fr_90px_auto]"
                        @submit.prevent="addInventoryItem"
                    >
                        <label class="field"
                            ><span>Oggetto</span
                            ><input
                                v-model="inventoryForm.name"
                                placeholder="Pozione di cura"
                                required
                        /></label>
                        <label class="field"
                            ><span>Descrizione</span
                            ><input
                                v-model="inventoryForm.description"
                                placeholder="Dettagli utili al tavolo"
                        /></label>
                        <label class="field"
                            ><span>Quantità</span
                            ><input
                                v-model.number="inventoryForm.quantity"
                                type="number"
                                min="0"
                                max="9999"
                                required
                        /></label>
                        <button
                            class="primary-button self-end"
                            :disabled="inventoryForm.processing"
                        >
                            <Plus class="size-4" /> Aggiungi
                        </button>
                        <p
                            v-if="Object.keys(inventoryForm.errors).length"
                            class="text-sm text-red-700 md:col-span-4"
                        >
                            {{ Object.values(inventoryForm.errors)[0] }}
                        </p>
                    </form>
                    <div class="panel overflow-hidden p-0">
                        <div
                            v-if="inventory.length"
                            class="divide-y divide-stone-200 dark:divide-white/10"
                        >
                            <article
                                v-for="item in inventory"
                                :key="item.id"
                                class="flex flex-col gap-3 p-4 transition hover:bg-amber-50/40 sm:flex-row sm:items-center dark:hover:bg-white/[0.03]"
                            >
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold">
                                        {{ item.name }}
                                    </h3>
                                    <p
                                        v-if="item.description"
                                        class="mt-0.5 text-sm text-stone-500"
                                    >
                                        {{ item.description }}
                                    </p>
                                    <p class="mt-1 text-[11px] text-stone-400">
                                        Ultima modifica:
                                        {{
                                            item.updated_by?.name ||
                                            'sconosciuta'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3 sm:justify-end"
                                >
                                    <div
                                        class="flex items-center rounded-xl border border-stone-200 bg-stone-50 p-1 dark:border-white/10 dark:bg-black/20"
                                    >
                                        <button
                                            type="button"
                                            class="quantity-button"
                                            aria-label="Riduci quantità"
                                            @click="changeQuantity(item, -1)"
                                        >
                                            <ChevronDown class="size-4" />
                                        </button>
                                        <span
                                            class="min-w-10 text-center text-sm font-bold"
                                            >{{ item.quantity }}</span
                                        >
                                        <button
                                            type="button"
                                            class="quantity-button"
                                            aria-label="Aumenta quantità"
                                            @click="changeQuantity(item, 1)"
                                        >
                                            <ChevronUp class="size-4" />
                                        </button>
                                    </div>
                                    <span class="text-xs text-stone-400">{{
                                        item.unit
                                    }}</span>
                                    <button
                                        type="button"
                                        class="icon-danger"
                                        aria-label="Rimuovi oggetto"
                                        @click="
                                            remove(
                                                `/inventory/${item.id}`,
                                                item.name,
                                            )
                                        "
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </article>
                        </div>
                        <div v-else class="empty-state">
                            <Backpack class="size-8" />
                            <p>Lo zaino è vuoto.</p>
                            <span
                                >Forse è il momento di saccheggiare un
                                dungeon.</span
                            >
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 sm:hidden">
                        <div class="mobile-stat-card">
                            <Backpack class="size-4 text-emerald-700" />
                            <strong>{{ inventory.length }}</strong>
                            <span>Tipi</span>
                        </div>
                        <div class="mobile-stat-card">
                            <Sparkles class="size-4 text-amber-600" />
                            <strong>{{
                                inventory.reduce(
                                    (sum, item) => sum + item.quantity,
                                    0,
                                )
                            }}</strong>
                            <span>Pezzi</span>
                        </div>
                        <div class="mobile-stat-card">
                            <Users class="size-4 text-sky-700" />
                            <strong>Comune</strong>
                            <span>Per tutti</span>
                        </div>
                    </div>
                </section>

                <section v-else-if="activeTab === 'relics'" class="space-y-5">
                    <form
                        v-if="false"
                        class="panel space-y-5"
                        @submit.prevent="addRelic"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
                        >
                            <div>
                                <p
                                    class="mb-1 text-xs font-bold tracking-wider text-amber-700 uppercase"
                                >
                                    Forgia del Dungeon Master
                                </p>
                                <h2 class="font-serif text-xl font-bold">
                                    Prepara una reliquia
                                </h2>
                                <p class="mt-1 text-sm text-stone-500">
                                    Puoi conservarla nell’archivio oppure
                                    affidarla subito a un personaggio.
                                </p>
                            </div>
                            <div
                                class="flex items-center gap-2 rounded-xl bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-900 dark:bg-violet-950/30 dark:text-violet-100"
                            >
                                <LockKeyhole class="size-4" /> Visibile solo al
                                DM finché non viene consegnata
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="field"
                                ><span>Nome</span
                                ><input
                                    v-model="relicForm.name"
                                    placeholder="Bussola di Aster"
                                    required
                            /></label>
                            <label class="field"
                                ><span>Destinazione iniziale</span
                                ><select v-model="relicForm.character_id">
                                    <option :value="null">
                                        Archivio del DM
                                    </option>
                                    <option
                                        v-for="player in players"
                                        :key="player.id"
                                        :value="player.id"
                                    >
                                        Consegna a {{ player.name }}
                                    </option>
                                </select></label
                            >
                            <label class="field md:col-span-2"
                                ><span>Descrizione base</span
                                ><textarea
                                    v-model="relicForm.description"
                                    rows="3"
                                    placeholder="Aspetto, storia conosciuta e proprietà immediatamente visibili..."
                                ></textarea>
                            </label>
                            <label
                                class="group flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-stone-300 bg-stone-50 px-4 py-3 transition hover:border-emerald-700 dark:border-white/15 dark:bg-black/20"
                            >
                                <span
                                    class="grid size-9 place-items-center rounded-lg bg-white text-emerald-800 shadow-sm dark:bg-white/10 dark:text-emerald-300"
                                >
                                    <ImageIcon class="size-4" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-xs font-bold"
                                        >Immagine della reliquia</span
                                    >
                                    <span
                                        class="block truncate text-xs text-stone-500"
                                        >{{
                                            relicForm.image?.name ||
                                            'JPG, PNG o WebP · massimo 5 MB'
                                        }}</span
                                    >
                                </span>
                                <input
                                    class="sr-only"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    @change="setRelicImage"
                                />
                            </label>
                        </div>

                        <div
                            class="rounded-2xl border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-black/20"
                        >
                            <div
                                class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <h3 class="text-sm font-bold">
                                        Segreti da sbloccare
                                    </h3>
                                    <p class="text-xs text-stone-500">
                                        Prepara ora abilità e parti di storia;
                                        partiranno tutte sigillate.
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="secondary-button"
                                        @click="addRevelation('skill')"
                                    >
                                        <Plus class="size-3.5" /> Abilità
                                    </button>
                                    <button
                                        type="button"
                                        class="secondary-button"
                                        @click="addRevelation('lore')"
                                    >
                                        <Plus class="size-3.5" /> Descrizione
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(
                                        revelation, index
                                    ) in relicForm.revelations"
                                    :key="index"
                                    class="grid gap-3 rounded-xl border border-stone-200 bg-white p-3 md:grid-cols-[130px_1fr_auto] dark:border-white/10 dark:bg-white/5"
                                >
                                    <label class="field"
                                        ><span>Tipo</span
                                        ><select v-model="revelation.kind">
                                            <option value="skill">
                                                Abilità
                                            </option>
                                            <option value="lore">
                                                Descrizione
                                            </option>
                                        </select></label
                                    >
                                    <label class="field"
                                        ><span>Titolo</span
                                        ><input
                                            v-model="revelation.title"
                                            placeholder="Voce dell’antico custode"
                                            required
                                    /></label>
                                    <button
                                        type="button"
                                        class="icon-danger self-end"
                                        aria-label="Rimuovi segreto"
                                        @click="removeRevelation(index)"
                                    >
                                        <X class="size-4" />
                                    </button>
                                    <label class="field md:col-span-3"
                                        ><span>Contenuto completo</span
                                        ><textarea
                                            v-model="revelation.content"
                                            rows="2"
                                            placeholder="Effetto, regole o nuova parte della storia..."
                                            required
                                        ></textarea>
                                    </label>
                                </div>
                                <p
                                    v-if="!relicForm.revelations.length"
                                    class="py-3 text-center text-xs text-stone-500"
                                >
                                    Nessun segreto: la reliquia sarà già
                                    completamente rivelata.
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="Object.keys(relicForm.errors).length"
                            class="text-sm text-red-700"
                        >
                            {{ Object.values(relicForm.errors)[0] }}
                        </p>
                        <button
                            class="primary-button w-fit"
                            :disabled="relicForm.processing"
                        >
                            <Gem class="size-4" /> Crea reliquia
                        </button>
                    </form>

                    <div
                        v-if="false"
                        class="flex items-center gap-3 rounded-2xl border border-violet-300/40 bg-violet-50 px-4 py-3 text-sm text-violet-950 dark:bg-violet-950/30 dark:text-violet-100"
                    >
                        <Archive class="size-5 shrink-0" />
                        <p>
                            Le reliquie marcate <strong>Archivio DM</strong>
                            sono già pronte, ma nessun player può ancora
                            vederle.
                        </p>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <article
                            v-for="relic in relics"
                            :key="relic.id"
                            class="relative overflow-hidden rounded-2xl border border-amber-700/20 bg-gradient-to-br from-white to-amber-50 shadow-sm dark:from-white/10 dark:to-amber-950/20"
                        >
                            <div
                                v-if="relic.image_url"
                                class="aspect-[16/8] overflow-hidden bg-stone-900"
                            >
                                <img
                                    :src="relic.image_url"
                                    :alt="relic.name"
                                    class="size-full object-cover"
                                />
                            </div>
                            <div
                                v-else
                                class="grid aspect-[16/5] place-items-center bg-[radial-gradient(circle_at_center,#fde68a_0,transparent_65%)] text-amber-900 dark:bg-[radial-gradient(circle_at_center,#78350f_0,transparent_70%)] dark:text-amber-200"
                            >
                                <Gem class="size-9" />
                            </div>

                            <div class="p-5">
                                <div
                                    class="mb-3 flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <span
                                            class="mb-2 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold"
                                            :class="
                                                relic.character_id
                                                    ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
                                                    : 'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-200'
                                            "
                                        >
                                            <Send
                                                v-if="relic.character_id"
                                                class="size-3"
                                            />
                                            <Archive v-else class="size-3" />
                                            {{
                                                relic.character?.name ||
                                                'Archivio DM'
                                            }}
                                        </span>
                                        <h3
                                            class="font-serif text-xl font-bold"
                                        >
                                            {{ relic.name }}
                                        </h3>
                                    </div>
                                    <button
                                        v-if="false"
                                        type="button"
                                        class="icon-danger"
                                        @click="
                                            remove(
                                                `/relics/${relic.id}`,
                                                relic.name,
                                            )
                                        "
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>

                                <p
                                    class="text-sm leading-6 text-stone-600 dark:text-stone-300"
                                >
                                    {{
                                        relic.description ||
                                        'La sua natura non è ancora stata annotata.'
                                    }}
                                </p>

                                <label v-if="false" class="field mt-4"
                                    ><span>Consegna della reliquia</span
                                    ><select
                                        :value="relic.character_id ?? ''"
                                        @change="
                                            assignRelic(
                                                relic,
                                                (
                                                    $event.target as HTMLSelectElement
                                                ).value,
                                            )
                                        "
                                    >
                                        <option value="">
                                            Conserva nell’archivio DM
                                        </option>
                                        <option
                                            v-for="player in players"
                                            :key="player.id"
                                            :value="player.id"
                                        >
                                            Affida a {{ player.name }}
                                        </option>
                                    </select></label
                                >

                                <div
                                    v-if="relic.revelations.length"
                                    class="mt-5 space-y-3"
                                >
                                    <p
                                        class="text-xs font-bold tracking-wider text-stone-400 uppercase"
                                    >
                                        Rivelazioni
                                    </p>
                                    <div
                                        v-for="revelation in relic.revelations"
                                        :key="revelation.id"
                                        class="relative overflow-hidden rounded-xl border p-3"
                                        :class="
                                            revelation.is_unlocked
                                                ? 'border-emerald-300/50 bg-emerald-50 dark:bg-emerald-950/25'
                                                : 'border-violet-300/40 bg-violet-50 dark:bg-violet-950/25'
                                        "
                                    >
                                        <div
                                            class="flex items-start justify-between gap-3"
                                        >
                                            <div class="min-w-0">
                                                <span
                                                    class="mb-1 flex items-center gap-1 text-[10px] font-bold tracking-wider uppercase"
                                                >
                                                    <UnlockKeyhole
                                                        v-if="
                                                            revelation.is_unlocked
                                                        "
                                                        class="size-3"
                                                    />
                                                    <LockKeyhole
                                                        v-else
                                                        class="size-3"
                                                    />
                                                    {{
                                                        revelation.kind ===
                                                        'skill'
                                                            ? 'Abilità'
                                                            : 'Descrizione'
                                                    }}
                                                    ·
                                                    {{
                                                        revelation.is_unlocked
                                                            ? 'Sbloccata'
                                                            : 'Sigillata'
                                                    }}
                                                </span>
                                                <h4 class="text-sm font-bold">
                                                    {{ revelation.title }}
                                                </h4>
                                            </div>
                                            <button
                                                v-if="false"
                                                type="button"
                                                class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-bold transition"
                                                :class="
                                                    revelation.is_unlocked
                                                        ? 'bg-stone-200 text-stone-700 hover:bg-stone-300 dark:bg-white/10 dark:text-stone-200'
                                                        : 'bg-violet-800 text-white hover:bg-violet-700'
                                                "
                                                @click="
                                                    setRevelationUnlocked(
                                                        revelation,
                                                        !revelation.is_unlocked,
                                                    )
                                                "
                                            >
                                                {{
                                                    revelation.is_unlocked
                                                        ? 'Sigilla'
                                                        : 'Sblocca'
                                                }}
                                            </button>
                                        </div>

                                        <p
                                            v-if="revelation.content"
                                            class="mt-2 text-sm leading-6 whitespace-pre-wrap text-stone-600 dark:text-stone-300"
                                        >
                                            {{ revelation.content }}
                                        </p>
                                        <div
                                            v-else
                                            class="relative mt-2 overflow-hidden rounded-lg bg-violet-950 px-3 py-3 text-violet-100"
                                        >
                                            <div
                                                class="text-sm leading-5 opacity-15 blur-[3px] select-none"
                                            >
                                                Il potere della reliquia resta
                                                celato dietro antichi sigilli.
                                            </div>
                                            <div
                                                class="absolute inset-0 flex items-center justify-center gap-2 text-xs font-bold"
                                            >
                                                <LockKeyhole class="size-4" />
                                                Il DM non ha ancora sbloccato
                                                questo segreto
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div v-if="!relics.length" class="panel empty-state">
                        <Gem class="size-8" />
                        <p>
                            {{
                                isDm
                                    ? 'Nessuna reliquia affidata a questo personaggio.'
                                    : 'Nessuna reliquia affidata.'
                            }}
                        </p>
                        <span
                            >Le leggende attendono ancora di essere
                            trovate.</span
                        >
                    </div>
                </section>

                <section v-else-if="activeTab === 'journal'" class="space-y-5">
                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-sky-800/15 bg-sky-950 px-5 py-4 text-white shadow-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-xl bg-amber-300 text-sky-950"
                            >
                                <Library class="size-5" />
                            </span>
                            <div>
                                <h2 class="font-serif text-lg font-bold">
                                    Diario della compagnia
                                </h2>
                                <p
                                    class="mt-0.5 max-w-2xl text-sm leading-5 text-sky-100/75"
                                >
                                    Argomenti e pagine sono condivisi: ogni
                                    player può leggere e continuare il racconto.
                                </p>
                            </div>
                        </div>
                        <span
                            class="shrink-0 rounded-full border border-sky-200/20 bg-white/10 px-3 py-1 text-xs font-semibold text-sky-100"
                        >
                            {{ journalTopics.length }} argomenti
                        </span>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-[290px_minmax(0,1fr)]">
                        <aside class="panel h-fit p-3 lg:sticky lg:top-6">
                            <div
                                class="mb-3 flex items-center justify-between px-2"
                            >
                                <div class="flex items-center gap-2">
                                    <BookOpenText
                                        class="size-4 text-sky-700 dark:text-sky-300"
                                    />
                                    <h3 class="text-sm font-bold">Argomenti</h3>
                                </div>
                                <span
                                    class="rounded-full bg-stone-100 px-2 py-0.5 text-xs dark:bg-white/10"
                                    >{{ journalTopics.length }}</span
                                >
                            </div>

                            <div v-if="journalTopics.length" class="space-y-1">
                                <button
                                    v-for="topic in journalTopics"
                                    :key="topic.id"
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition"
                                    :class="
                                        selectedTopicId === topic.id
                                            ? 'bg-sky-900 text-white shadow-sm'
                                            : 'hover:bg-stone-100 dark:hover:bg-white/10'
                                    "
                                    @click="selectJournalTopic(topic.id)"
                                >
                                    <span
                                        class="grid size-8 shrink-0 place-items-center rounded-lg bg-amber-200 text-xs font-bold text-sky-950"
                                        >{{ topic.pages.length }}</span
                                    >
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block truncate text-sm font-semibold"
                                            >{{ topic.title }}</span
                                        >
                                        <span
                                            class="block text-[11px] opacity-65"
                                            >{{
                                                topic.pages.length === 1
                                                    ? '1 pagina'
                                                    : `${topic.pages.length} pagine`
                                            }}</span
                                        >
                                    </span>
                                    <ChevronRight class="size-4 opacity-50" />
                                </button>
                            </div>
                            <p
                                v-else
                                class="px-2 py-4 text-center text-xs text-stone-500"
                            >
                                Nessun argomento aperto.
                            </p>

                            <form
                                class="mt-3 border-t border-stone-200 px-1 pt-3 dark:border-white/10"
                                @submit.prevent="addJournalTopic"
                            >
                                <label class="field"
                                    ><span>Nuovo argomento</span
                                    ><input
                                        v-model="journalTopicForm.title"
                                        placeholder="Celestiali"
                                        required
                                /></label>
                                <p
                                    v-if="journalTopicForm.errors.title"
                                    class="mt-1 text-xs text-red-700"
                                >
                                    {{ journalTopicForm.errors.title }}
                                </p>
                                <button
                                    class="primary-button mt-2 w-full justify-center"
                                    :disabled="journalTopicForm.processing"
                                >
                                    <Plus class="size-4" /> Apri argomento
                                </button>
                            </form>
                        </aside>

                        <div class="min-w-0 space-y-5">
                            <div v-if="selectedJournalTopic" class="panel">
                                <div
                                    class="mb-5 flex items-start justify-between gap-4 border-b border-stone-200 pb-4 dark:border-white/10"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="mb-1 text-xs font-bold tracking-wider text-sky-700 uppercase dark:text-sky-300"
                                        >
                                            Argomento condiviso
                                        </p>
                                        <form
                                            v-if="
                                                editingTopicId ===
                                                selectedJournalTopic.id
                                            "
                                            class="mt-1 flex max-w-xl flex-col gap-2 sm:flex-row sm:items-start"
                                            @submit.prevent="updateJournalTopic"
                                        >
                                            <label class="field min-w-0 flex-1">
                                                <span class="sr-only"
                                                    >Titolo argomento</span
                                                >
                                                <input
                                                    v-model="
                                                        journalTopicEditForm.title
                                                    "
                                                    maxlength="160"
                                                    autofocus
                                                    required
                                                />
                                                <span
                                                    v-if="
                                                        journalTopicEditForm
                                                            .errors.title
                                                    "
                                                    class="text-xs text-red-700 dark:text-red-300"
                                                >
                                                    {{
                                                        journalTopicEditForm
                                                            .errors.title
                                                    }}
                                                </span>
                                            </label>
                                            <div class="flex gap-2">
                                                <button
                                                    type="submit"
                                                    class="secondary-button"
                                                    :disabled="
                                                        journalTopicEditForm.processing
                                                    "
                                                >
                                                    <Check class="size-3.5" />
                                                    Salva
                                                </button>
                                                <button
                                                    type="button"
                                                    class="secondary-button"
                                                    @click="
                                                        cancelJournalTopicEdit
                                                    "
                                                >
                                                    <X class="size-3.5" />
                                                    Annulla
                                                </button>
                                            </div>
                                        </form>
                                        <h2
                                            v-else
                                            class="font-serif text-2xl font-bold"
                                        >
                                            {{ selectedJournalTopic.title }}
                                        </h2>
                                        <p class="mt-1 text-xs text-stone-500">
                                            Aperto da
                                            {{
                                                selectedJournalTopic.creator
                                                    ?.name || 'autore ignoto'
                                            }}
                                            ·
                                            {{
                                                selectedJournalTopic.pages
                                                    .length === 1
                                                    ? '1 pagina'
                                                    : `${selectedJournalTopic.pages.length} pagine`
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 gap-1">
                                        <button
                                            type="button"
                                            class="secondary-button px-2.5"
                                            aria-label="Modifica argomento"
                                            title="Modifica argomento"
                                            @click="
                                                editJournalTopic(
                                                    selectedJournalTopic,
                                                )
                                            "
                                        >
                                            <Pencil class="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            class="icon-danger"
                                            aria-label="Rimuovi argomento"
                                            @click="
                                                removeJournalTopic(
                                                    selectedJournalTopic,
                                                )
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </div>

                                <article v-if="selectedJournalPage">
                                    <button
                                        v-if="
                                            selectedJournalTopic.pages.length >
                                            1
                                        "
                                        type="button"
                                        class="secondary-button mb-4"
                                        @click="closeJournalPage"
                                    >
                                        <ArrowLeft class="size-3.5" /> Torna
                                        all’indice
                                    </button>
                                    <form
                                        v-if="
                                            editingPageId ===
                                            selectedJournalPage.id
                                        "
                                        class="space-y-4"
                                        @submit.prevent="updateJournalPage"
                                    >
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <label class="field">
                                                <span>Titolo pagina</span>
                                                <input
                                                    v-model="
                                                        journalPageEditForm.title
                                                    "
                                                    maxlength="160"
                                                    autofocus
                                                    required
                                                />
                                            </label>
                                            <label class="field">
                                                <span>Data nel mondo</span>
                                                <input
                                                    v-model="
                                                        journalPageEditForm.occurred_on
                                                    "
                                                    type="date"
                                                />
                                            </label>
                                            <label class="field sm:col-span-2">
                                                <span>Contenuto</span>
                                                <textarea
                                                    v-model="
                                                        journalPageEditForm.content
                                                    "
                                                    rows="10"
                                                    maxlength="20000"
                                                    required
                                                ></textarea>
                                            </label>
                                        </div>
                                        <p
                                            v-if="
                                                Object.keys(
                                                    journalPageEditForm.errors,
                                                ).length
                                            "
                                            class="text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                Object.values(
                                                    journalPageEditForm.errors,
                                                )[0]
                                            }}
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="submit"
                                                class="primary-button"
                                                :disabled="
                                                    journalPageEditForm.processing
                                                "
                                            >
                                                <Check class="size-4" /> Salva
                                                modifiche
                                            </button>
                                            <button
                                                type="button"
                                                class="secondary-button"
                                                @click="cancelJournalPageEdit"
                                            >
                                                <X class="size-4" /> Annulla
                                            </button>
                                        </div>
                                    </form>
                                    <template v-else>
                                        <div
                                            class="flex items-start justify-between gap-4"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="mb-1 text-xs font-semibold tracking-wider text-amber-700 uppercase"
                                                >
                                                    {{
                                                        formatDate(
                                                            selectedJournalPage.occurred_on ||
                                                                selectedJournalPage.created_at,
                                                        )
                                                    }}
                                                </p>
                                                <h3
                                                    class="font-serif text-xl font-bold"
                                                >
                                                    {{
                                                        selectedJournalPage.title
                                                    }}
                                                </h3>
                                            </div>
                                            <div class="flex shrink-0 gap-1">
                                                <button
                                                    type="button"
                                                    class="secondary-button px-2.5"
                                                    aria-label="Modifica pagina"
                                                    title="Modifica pagina"
                                                    @click="
                                                        editJournalPage(
                                                            selectedJournalPage,
                                                        )
                                                    "
                                                >
                                                    <Pencil class="size-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="icon-danger"
                                                    aria-label="Rimuovi pagina"
                                                    @click="
                                                        removeJournalPage(
                                                            selectedJournalPage,
                                                        )
                                                    "
                                                >
                                                    <Trash2 class="size-4" />
                                                </button>
                                            </div>
                                        </div>
                                        <p
                                            class="mt-4 text-sm leading-7 whitespace-pre-wrap text-stone-600 dark:text-stone-300"
                                        >
                                            {{ selectedJournalPage.content }}
                                        </p>
                                        <div
                                            class="mt-5 flex items-center gap-2 border-t border-stone-200 pt-3 text-xs text-stone-400 dark:border-white/10"
                                        >
                                            <ScrollText class="size-3.5" />
                                            Scritta da
                                            {{
                                                selectedJournalPage.author
                                                    ?.name || 'autore ignoto'
                                            }}
                                        </div>
                                    </template>
                                </article>

                                <div
                                    v-else-if="
                                        selectedJournalTopic.pages.length > 1
                                    "
                                >
                                    <div class="mb-4">
                                        <h3
                                            class="font-serif text-lg font-bold"
                                        >
                                            Indice delle pagine
                                        </h3>
                                        <p class="text-sm text-stone-500">
                                            Scegli una pagina da consultare.
                                        </p>
                                    </div>
                                    <div
                                        class="divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200 dark:divide-white/10 dark:border-white/10"
                                    >
                                        <button
                                            v-for="(
                                                page, index
                                            ) in selectedJournalTopic.pages"
                                            :key="page.id"
                                            type="button"
                                            class="flex w-full items-center gap-4 bg-white px-4 py-3 text-left transition hover:bg-amber-50 dark:bg-white/5 dark:hover:bg-white/10"
                                            @click="openJournalPage(page.id)"
                                        >
                                            <span
                                                class="grid size-8 shrink-0 place-items-center rounded-lg bg-stone-100 text-xs font-bold text-stone-500 dark:bg-white/10"
                                                >{{ index + 1 }}</span
                                            >
                                            <span class="min-w-0 flex-1">
                                                <span
                                                    class="block truncate text-sm font-semibold"
                                                    >{{ page.title }}</span
                                                >
                                                <span
                                                    class="block text-xs text-stone-500"
                                                    >{{
                                                        formatDate(
                                                            page.occurred_on ||
                                                                page.created_at,
                                                        )
                                                    }}
                                                    ·
                                                    {{
                                                        page.author?.name ||
                                                        'autore ignoto'
                                                    }}</span
                                                >
                                            </span>
                                            <ChevronRight
                                                class="size-4 text-stone-400"
                                            />
                                        </button>
                                    </div>
                                </div>

                                <div v-else class="empty-state py-8">
                                    <BookOpenText class="size-8" />
                                    <p>Questo argomento è ancora vuoto.</p>
                                    <span
                                        >Scrivi qui sotto la sua prima
                                        pagina.</span
                                    >
                                </div>
                            </div>

                            <form
                                v-if="selectedJournalTopic"
                                class="panel"
                                @submit.prevent="addJournalPage"
                            >
                                <h3 class="font-serif text-xl font-bold">
                                    Nuova pagina
                                </h3>
                                <p class="mb-4 text-sm text-stone-500">
                                    Dentro “{{ selectedJournalTopic.title }}”
                                </p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="field"
                                        ><span>Titolo pagina</span
                                        ><input
                                            v-model="journalPageForm.title"
                                            placeholder="Gerarchie del cielo"
                                            required
                                    /></label>
                                    <label class="field"
                                        ><span>Data nel mondo</span
                                        ><input
                                            v-model="
                                                journalPageForm.occurred_on
                                            "
                                            type="date"
                                    /></label>
                                    <label class="field sm:col-span-2"
                                        ><span>Contenuto</span
                                        ><textarea
                                            v-model="journalPageForm.content"
                                            rows="7"
                                            placeholder="Conoscenze, incontri, supposizioni della compagnia..."
                                            required
                                        ></textarea>
                                    </label>
                                </div>
                                <p
                                    v-if="
                                        Object.keys(journalPageForm.errors)
                                            .length
                                    "
                                    class="mt-2 text-sm text-red-700"
                                >
                                    {{
                                        Object.values(journalPageForm.errors)[0]
                                    }}
                                </p>
                                <button
                                    class="primary-button mt-3"
                                    :disabled="journalPageForm.processing"
                                >
                                    <Plus class="size-4" /> Aggiungi pagina
                                </button>
                            </form>

                            <div
                                v-if="!journalTopics.length"
                                class="panel empty-state"
                            >
                                <Library class="size-8" />
                                <p>Il diario comune è ancora vuoto.</p>
                                <span
                                    >Apri il primo argomento dalla colonna a
                                    sinistra.</span
                                >
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-else-if="activeTab === 'gallery'"
                    class="mx-auto max-w-3xl space-y-5"
                >
                    <form class="panel" @submit.prevent="addGalleryImage">
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <h2 class="font-serif text-xl font-bold">
                                    Galleria della compagnia
                                </h2>
                                <p class="mt-1 text-sm text-stone-500">
                                    Carica un’immagine da condividere con tutto
                                    il tavolo.
                                </p>
                            </div>
                            <label
                                class="secondary-button cursor-pointer justify-center"
                            >
                                <ImageIcon class="size-4" />
                                {{
                                    galleryForm.image?.name || 'Scegli immagine'
                                }}
                                <input
                                    class="sr-only"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    required
                                    @change="setGalleryImage"
                                />
                            </label>
                        </div>
                        <p
                            v-if="galleryForm.errors.image"
                            class="mt-3 text-sm text-red-700 dark:text-red-300"
                        >
                            {{ galleryForm.errors.image }}
                        </p>
                        <button
                            class="primary-button mt-4"
                            :disabled="
                                galleryForm.processing || !galleryForm.image
                            "
                        >
                            <Upload class="size-4" /> Carica nella galleria
                        </button>
                    </form>

                    <div v-if="galleryImages.length" class="space-y-5">
                        <figure
                            v-for="galleryImage in galleryImages"
                            :key="galleryImage.id"
                            class="group relative overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5"
                        >
                            <img
                                :src="galleryImage.image_url"
                                alt=""
                                class="block h-auto w-full"
                                loading="lazy"
                            />
                            <button
                                type="button"
                                class="absolute top-3 right-3 grid size-10 place-items-center rounded-xl bg-black/60 text-white opacity-100 shadow-sm backdrop-blur transition hover:bg-red-700 sm:opacity-0 sm:group-hover:opacity-100"
                                aria-label="Rimuovi immagine"
                                @click="removeGalleryImage(galleryImage)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </figure>
                    </div>
                    <div v-else class="panel empty-state">
                        <ImageIcon class="size-8" />
                        <p>La galleria è ancora vuota.</p>
                        <span>La prima immagine può caricarla chiunque.</span>
                    </div>
                </section>

                <section
                    v-else-if="activeTab === 'rations'"
                    class="mx-auto max-w-2xl space-y-5"
                >
                    <div class="panel text-center">
                        <span
                            class="mx-auto grid size-14 place-items-center rounded-2xl bg-amber-100 text-amber-800 dark:bg-amber-300/10 dark:text-amber-300"
                        >
                            <Utensils class="size-7" />
                        </span>
                        <p
                            class="mt-5 text-xs font-bold tracking-[0.2em] text-stone-500 uppercase"
                        >
                            Razioni disponibili
                        </p>
                        <strong
                            class="mt-2 block font-serif text-7xl leading-none text-emerald-900 dark:text-emerald-200"
                        >
                            {{ rationCount }}
                        </strong>

                        <div
                            class="mt-6 flex items-center justify-center gap-3"
                        >
                            <button
                                type="button"
                                class="secondary-button size-11 justify-center p-0"
                                aria-label="Togli una razione"
                                :disabled="
                                    rationForm.processing || rationCount === 0
                                "
                                @click="changeRations(-1)"
                            >
                                <Minus class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="secondary-button size-11 justify-center p-0"
                                aria-label="Aggiungi una razione"
                                :disabled="rationForm.processing"
                                @click="changeRations(1)"
                            >
                                <Plus class="size-4" />
                            </button>
                        </div>

                        <form
                            class="mx-auto mt-5 flex max-w-xs gap-2"
                            @submit.prevent="updateRations"
                        >
                            <label class="field min-w-0 flex-1 text-left">
                                <span>Imposta quantità</span>
                                <input
                                    v-model.number="rationForm.quantity"
                                    type="number"
                                    min="0"
                                    max="99999"
                                    required
                                />
                            </label>
                            <button
                                class="secondary-button mt-5"
                                :disabled="rationForm.processing"
                            >
                                <Check class="size-4" /> Salva
                            </button>
                        </form>
                        <p
                            v-if="rationForm.errors.quantity"
                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                        >
                            {{ rationForm.errors.quantity }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-emerald-800/15 bg-emerald-950 p-5 text-white shadow-sm"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="grid size-11 shrink-0 place-items-center rounded-xl bg-amber-300 text-emerald-950"
                                >
                                    <Moon class="size-5" />
                                </span>
                                <div>
                                    <h2 class="font-serif text-lg font-bold">
                                        Riposo della compagnia
                                    </h2>
                                    <p class="mt-1 text-sm text-emerald-100/75">
                                        Consuma 4 razioni: una per ciascun
                                        personaggio.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-amber-300 px-4 py-2.5 text-sm font-bold text-emerald-950 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="
                                    rationRestForm.processing || rationCount < 4
                                "
                                @click="restCompany"
                            >
                                <Moon class="size-4" /> Dormi · −4
                            </button>
                        </div>
                        <p
                            v-if="rationRestForm.errors['rations']"
                            class="mt-3 text-sm text-red-200"
                        >
                            {{ rationRestForm.errors['rations'] }}
                        </p>
                        <p
                            v-else-if="rationCount < 4"
                            class="mt-3 text-sm text-amber-200"
                        >
                            Non ci sono abbastanza razioni per tutti.
                        </p>
                    </div>
                </section>

                <section v-else-if="activeTab === 'sheet'">
                    <CharacterSheetViewer
                        v-if="characterSheet"
                        :source-url="characterSheet.url"
                        :save-url="characterSheet.save_url"
                        :download-url="characterSheet.download_url"
                        :character-name="characterSheet.character_name"
                        :can-edit="characterSheet.can_edit"
                        :updated-at="characterSheet.updated_at"
                        @dirty-change="characterSheetDirty = $event"
                    />
                    <div v-else class="panel empty-state">
                        <FileText class="size-8" />
                        <p>Nessun personaggio selezionato.</p>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<style scoped>
@reference '../../css/app.css';
.panel {
    @apply rounded-2xl border border-stone-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5;
}
.field {
    @apply flex min-w-0 flex-col gap-1.5 text-xs font-semibold text-stone-600 dark:text-stone-300;
}
.field input,
.field textarea,
.field select {
    @apply w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm font-normal text-stone-900 transition outline-none placeholder:text-stone-400 focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/10 dark:border-white/10 dark:bg-black/20 dark:text-stone-100;
}
.primary-button {
    @apply inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50;
}
.secondary-button {
    @apply inline-flex items-center gap-1.5 rounded-lg border border-stone-200 bg-white px-3 py-2 text-xs font-bold text-stone-700 transition hover:border-emerald-700 hover:text-emerald-800 dark:border-white/10 dark:bg-white/5 dark:text-stone-200 dark:hover:text-emerald-300;
}
.quantity-button {
    @apply grid size-8 place-items-center rounded-lg text-stone-500 transition hover:bg-white hover:text-emerald-800 dark:hover:bg-white/10 dark:hover:text-emerald-300;
}
.icon-danger {
    @apply grid size-9 shrink-0 place-items-center rounded-lg text-stone-400 transition hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30 dark:hover:text-red-300;
}
.stat-card {
    @apply flex items-center gap-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5;
}
.stat-card strong {
    @apply block text-lg leading-none;
}
.stat-card span {
    @apply mt-1 block text-xs text-stone-500;
}
.mobile-stat-card {
    @apply flex min-w-0 flex-col items-center justify-center rounded-xl border border-stone-200 bg-white px-2 py-3 text-center shadow-sm dark:border-white/10 dark:bg-white/5;
}
.mobile-stat-card strong {
    @apply mt-1 max-w-full truncate text-sm leading-none;
}
.mobile-stat-card span {
    @apply mt-1 text-[10px] text-stone-500;
}
.empty-state {
    @apply flex flex-col items-center justify-center py-12 text-center text-stone-400;
}
.empty-state p {
    @apply mt-3 font-semibold text-stone-600 dark:text-stone-300;
}
.empty-state span {
    @apply mt-1 text-xs;
}
</style>
